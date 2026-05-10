<?php
// modulos/filtros/filtros.php
// Modulo de filtros del sistema Fiscalizar — Consulta Padron.
// Acceso: todos los niveles autenticados.
// Padron es obligatorio. Sin padron no hay resultado.
// Auxiliar CP se fuerza a NO cuando padron = CD via hidden input,
// porque los campos disabled no se envian en el form (fix mayo 2026).
// Logica de combinacion:
//   CD  + Auxiliar CP = NO → solo vista_padron_cd
//   CD  + Auxiliar CP = SI → vista_padron_cd + vista_padron_cp WHERE auxiliar = 1
//   CP  + Auxiliar CP = NO → vista_padron_cp WHERE auxiliar = 0
//   CP  + Auxiliar CP = SI → vista_padron_cp completa
// Todas las columnas de votos siempre presentes en el resultado.
// Carrera se inhibe en JS cuando padron = CP.
// COLLATE utf8mb4_unicode_ci en columnas de texto para evitar conflicto en UNION.
// Parametros posicionales (?) para evitar HY093 en array_merge.
// sede_laboral reemplazada por sede y municipio (mayo 2026).

verificar_sesion();

require_once 'includes/excel.php';

// --- Cargar opciones de los combos ---

$stmt_carreras   = $pdo->query("SELECT id, descripcion, sigla FROM carreras ORDER BY descripcion ASC");
$carreras        = $stmt_carreras->fetchAll();

$stmt_referentes = $pdo->query("SELECT id, apellido, nombre FROM referentes WHERE activo = 1 ORDER BY apellido ASC, nombre ASC");
$referentes      = $stmt_referentes->fetchAll();

$stmt_partidos   = $pdo->query("SELECT id, nombre FROM partidos WHERE activo = 1 ORDER BY nombre ASC");
$partidos        = $stmt_partidos->fetchAll();

$stmt_trabajos   = $pdo->query("SELECT id, nombre FROM trabajos WHERE activo = 1 ORDER BY nombre ASC");
$trabajos        = $stmt_trabajos->fetchAll();

$stmt_sedes      = $pdo->query("SELECT id, nombre FROM sedes WHERE activo = 1 ORDER BY nombre ASC");
$sedes           = $stmt_sedes->fetchAll();

$stmt_elecciones = $pdo->query("SELECT id, nombre, tipo FROM elecciones ORDER BY anio ASC, tipo ASC");
$elecciones      = $stmt_elecciones->fetchAll();

// --- Leer parametros ---
$padron      = $_GET['padron']      ?? '';
$auxiliar_cp = $_GET['auxiliar_cp'] ?? '';
$referente   = $_GET['referente']   ?? '';
$partido     = $_GET['partido']     ?? '';
$trabajo     = $_GET['trabajo']     ?? '';
$sede        = $_GET['sede']        ?? '';
$carrera     = $_GET['carrera']     ?? '';
$eleccion    = $_GET['eleccion']    ?? '';
$voto        = $_GET['voto']        ?? '';
$accion      = $_GET['accion']      ?? '';
$pagina      = max(1, intval($_GET['pagina'] ?? 1));
$por_pagina  = 50;

// Cuando padron = CD, auxiliar_cp llega como NO desde el hidden input.
// Cuando padron = CP, auxiliar_cp llega desde el combo visible.
// El filtro se considera aplicado cuando ambos valores estan presentes.
$filtro_aplicado = ($padron !== '' && $auxiliar_cp !== '');

$aviso = '';
$intento_filtrar = isset($_GET['padron']) || isset($_GET['auxiliar_cp']);
if ($intento_filtrar && !$filtro_aplicado) {
    if ($padron === '') {
        $aviso = 'Elegir un padrón para ver resultados.';
    } elseif ($auxiliar_cp === '') {
        $aviso = 'Elegir una opción de Auxiliares CP para ver resultados.';
    }
}

$resultados      = [];
$total_registros = 0;
$total_paginas   = 0;

if ($filtro_aplicado) {

    // Tipo de eleccion para filtro de voto
    $tipo_eleccion = null;
    if ($eleccion !== '') {
        $stmt_tipo = $pdo->prepare("SELECT tipo FROM elecciones WHERE id = ?");
        $stmt_tipo->execute([$eleccion]);
        $tipo_eleccion = $stmt_tipo->fetchColumn();
    }

    // -----------------------------------------------------------
    // Funcion auxiliar: construye condiciones y parametros
    // para una vista dada (cd o cp).
    // Usa parametros posicionales (?) para permitir array_merge
    // sin conflicto de claves duplicadas (HY093).
    // -----------------------------------------------------------
    function construir_where($referente, $partido, $trabajo, $sede, $carrera, $tipo_eleccion, $eleccion, $voto, $vista) {
        $conds  = [];
        $params = [];

        // Referente
        if ($referente === '_con_referentes') {
            $conds[] = "dni IN (
                SELECT rg.dni FROM referentes_graduado rg
                JOIN referentes r ON rg.referente_1 = r.id
                WHERE r.apellido != 'SIN REFERENTE'
            )";
        } elseif ($referente !== '') {
            $conds[]  = "dni IN (
                SELECT dni FROM referentes_graduado
                WHERE referente_1 = ? OR referente_2 = ? OR referente_3 = ?
            )";
            $params[] = $referente;
            $params[] = $referente;
            $params[] = $referente;
        }

        // Partido
        if ($partido !== '') {
            $conds[]  = "dni IN (SELECT dni FROM persona_partido WHERE id_partido = ?)";
            $params[] = $partido;
        }

        // Trabajo
        if ($trabajo !== '') {
            $conds[]  = "dni IN (SELECT dni FROM persona_trabajo WHERE id_trabajo = ?)";
            $params[] = $trabajo;
        }

        // Sede
        if ($sede !== '') {
            $conds[]  = "dni IN (SELECT dni FROM persona_sede WHERE id_sede = ?)";
            $params[] = $sede;
        }

        // Carrera — solo para vista CD
        if ($vista === 'cd' && $carrera !== '') {
            $conds[]  = "carrera = ?";
            $params[] = $carrera;
        }

        // Voto — solo si la eleccion es del mismo tipo que la vista
        if ($eleccion !== '' && $voto !== '') {
            if (($vista === 'cd' && $tipo_eleccion === 'cd') ||
                ($vista === 'cp' && $tipo_eleccion === 'cp')) {
                $op      = $voto === 'SI' ? 'IN' : 'NOT IN';
                $conds[] = "dni $op (SELECT dni FROM participacion_electoral WHERE id_eleccion = ?)";
                $params[] = $eleccion;
            }
        }

        $where = count($conds) > 0 ? 'WHERE ' . implode(' AND ', $conds) : '';
        return [$where, $params];
    }

    // SELECT desde vista_padron_cd
    [$where_cd, $params_cd] = construir_where(
        $referente, $partido, $trabajo, $sede, $carrera,
        $tipo_eleccion, $eleccion, $voto, 'cd'
    );

    $select_cd = "
        SELECT
            dni,
            apellido     COLLATE utf8mb4_unicode_ci AS apellido,
            nombre       COLLATE utf8mb4_unicode_ci AS nombre,
            carrera      COLLATE utf8mb4_unicode_ci AS carrera,
            'CD'         AS padron,
            0            AS auxiliar,
            referente_1  COLLATE utf8mb4_unicode_ci AS referente_1,
            referente_2  COLLATE utf8mb4_unicode_ci AS referente_2,
            referente_3  COLLATE utf8mb4_unicode_ci AS referente_3,
            partido      COLLATE utf8mb4_unicode_ci AS partido,
            trabajo      COLLATE utf8mb4_unicode_ci AS trabajo,
            sede         COLLATE utf8mb4_unicode_ci AS sede,
            municipio    COLLATE utf8mb4_unicode_ci AS municipio,
            voto_cd_2021, voto_cd_2024,
            'NO' AS voto_cp_2017,
            'NO' AS voto_cp_2019,
            'NO' AS voto_cp_2021,
            'NO' AS voto_cp_2024
        FROM vista_padron_cd
        $where_cd
    ";

    // SELECT desde vista_padron_cp
    [$where_cp_base, $params_cp] = construir_where(
        $referente, $partido, $trabajo, $sede, '',
        $tipo_eleccion, $eleccion, $voto, 'cp'
    );

    if ($auxiliar_cp === 'SI' && $padron === 'CD') {
        $where_cp = $where_cp_base !== ''
            ? $where_cp_base . ' AND auxiliar = 1'
            : 'WHERE auxiliar = 1';
    } elseif ($auxiliar_cp === 'NO' && $padron === 'CP') {
        $where_cp = $where_cp_base !== ''
            ? $where_cp_base . ' AND auxiliar = 0'
            : 'WHERE auxiliar = 0';
    } else {
        $where_cp = $where_cp_base;
    }

    $select_cp = "
        SELECT
            dni,
            apellido     COLLATE utf8mb4_unicode_ci AS apellido,
            nombre       COLLATE utf8mb4_unicode_ci AS nombre,
            NULL         AS carrera,
            'CP'         AS padron,
            auxiliar,
            referente_1  COLLATE utf8mb4_unicode_ci AS referente_1,
            referente_2  COLLATE utf8mb4_unicode_ci AS referente_2,
            referente_3  COLLATE utf8mb4_unicode_ci AS referente_3,
            partido      COLLATE utf8mb4_unicode_ci AS partido,
            trabajo      COLLATE utf8mb4_unicode_ci AS trabajo,
            sede         COLLATE utf8mb4_unicode_ci AS sede,
            municipio    COLLATE utf8mb4_unicode_ci AS municipio,
            'NO' AS voto_cd_2021,
            'NO' AS voto_cd_2024,
            voto_cp_2017, voto_cp_2019, voto_cp_2021, voto_cp_2024
        FROM vista_padron_cp
        $where_cp
    ";

    // Combinar segun padron y auxiliar_cp
    if ($padron === 'CD' && $auxiliar_cp === 'NO') {
        $sql_union  = $select_cd;
        $params_all = $params_cd;

    } elseif ($padron === 'CD' && $auxiliar_cp === 'SI') {
        $sql_union  = "($select_cd) UNION ALL ($select_cp)";
        $params_all = array_merge($params_cd, $params_cp);

    } elseif ($padron === 'CP' && $auxiliar_cp === 'NO') {
        $sql_union  = $select_cp;
        $params_all = $params_cp;

    } else {
        // CP + auxiliar_cp = SI: padron CP completo sin filtro de auxiliar
        $select_cp_todos = "
            SELECT
                dni,
                apellido     COLLATE utf8mb4_unicode_ci AS apellido,
                nombre       COLLATE utf8mb4_unicode_ci AS nombre,
                NULL         AS carrera,
                'CP'         AS padron,
                auxiliar,
                referente_1  COLLATE utf8mb4_unicode_ci AS referente_1,
                referente_2  COLLATE utf8mb4_unicode_ci AS referente_2,
                referente_3  COLLATE utf8mb4_unicode_ci AS referente_3,
                partido      COLLATE utf8mb4_unicode_ci AS partido,
                trabajo      COLLATE utf8mb4_unicode_ci AS trabajo,
                sede         COLLATE utf8mb4_unicode_ci AS sede,
                municipio    COLLATE utf8mb4_unicode_ci AS municipio,
                'NO' AS voto_cd_2021,
                'NO' AS voto_cd_2024,
                voto_cp_2017, voto_cp_2019, voto_cp_2021, voto_cp_2024
            FROM vista_padron_cp
            $where_cp_base
        ";
        $sql_union  = $select_cp_todos;
        $params_all = $params_cp;
    }

    $sql_ordenado = "SELECT * FROM ($sql_union) AS resultado ORDER BY apellido ASC, nombre ASC";

    // Exportar si se pidio
    if ($accion === 'descargar') {
        $stmt = $pdo->prepare($sql_ordenado);
        $stmt->execute($params_all);
        exportar_excel($stmt->fetchAll(), 'filtros-' . date('YmdHis'));
        exit;
    }

    // Contar total
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ($sql_ordenado) AS t");
    $stmt->execute($params_all);
    $total_registros = (int) $stmt->fetchColumn();
    $total_paginas   = (int) ceil($total_registros / $por_pagina);
    $pagina          = min($pagina, max(1, $total_paginas));
    $offset          = ($pagina - 1) * $por_pagina;

    // Traer pagina actual
    $sql_paginado = $sql_ordenado . " LIMIT ? OFFSET ?";
    $params_pag   = array_merge($params_all, [$por_pagina, $offset]);
    $stmt = $pdo->prepare($sql_paginado);
    $stmt->execute($params_pag);
    $resultados = $stmt->fetchAll();
}

// URL base para paginacion conservando filtros
$params_url = http_build_query(array_filter([
    'mod'         => 'filtros',
    'padron'      => $padron,
    'auxiliar_cp' => $auxiliar_cp,
    'referente'   => $referente,
    'partido'     => $partido,
    'trabajo'     => $trabajo,
    'sede'        => $sede,
    'carrera'     => $carrera,
    'eleccion'    => $eleccion,
    'voto'        => $voto,
]));

require_once 'includes/navbar.php';
?>

<div class="modulo-titulo">Filtros</div>

<form method="GET" action="index.php" class="mb-4">
    <input type="hidden" name="mod" value="filtros">

    <!--
        Hidden input para auxiliar_cp cuando padron = CD.
        Los campos select disabled no se envian en el form.
        Cuando padron = CD, el JS pone este hidden en NO y deshabilita el combo visible.
        Cuando padron = CP, el JS vacia este hidden y habilita el combo visible.
        Asi el PHP siempre recibe auxiliar_cp sin importar el padron elegido.
    -->
    <input type="hidden" name="auxiliar_cp" id="hidden-auxiliar" value="">

    <div class="row g-2 mb-2">

        <!-- Padron — obligatorio -->
        <div class="col-md-2">
            <label class="form-label" style="font-size:0.8rem;">Padrón <span style="color:#e53e3e;">*</span></label>
            <select name="padron" id="combo-padron" class="form-select form-select-sm">
                <option value="" disabled <?php echo $padron === '' ? 'selected' : ''; ?>>Elegir</option>
                <option value="CD" <?php echo $padron === 'CD' ? 'selected' : ''; ?>>CD</option>
                <option value="CP" <?php echo $padron === 'CP' ? 'selected' : ''; ?>>CP</option>
            </select>
        </div>

        <!-- Auxiliares CP — obligatorio cuando padron = CP, automatico cuando padron = CD -->
        <div class="col-md-2">
            <label class="form-label" style="font-size:0.8rem;">Auxiliares CP <span style="color:#e53e3e;">*</span></label>
            <select name="auxiliar_cp" id="combo-auxiliar" class="form-select form-select-sm"
                <?php echo ($padron === '' || $padron === 'CD') ? 'disabled' : ''; ?>>
                <option value="" disabled <?php echo $auxiliar_cp === '' ? 'selected' : ''; ?>>Elegir</option>
                <option value="SI" <?php echo $auxiliar_cp === 'SI' ? 'selected' : ''; ?>>SI</option>
                <option value="NO" <?php echo $auxiliar_cp === 'NO' ? 'selected' : ''; ?>>NO</option>
            </select>
        </div>

        <!-- Referente -->
        <div class="col-md-4">
            <label class="form-label" style="font-size:0.8rem;">Referente</label>
            <select name="referente" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="_con_referentes" <?php echo $referente === '_con_referentes' ? 'selected' : ''; ?>>
                    Con referentes
                </option>
                <?php foreach ($referentes as $r): ?>
                <option value="<?php echo $r['id']; ?>"
                    <?php echo $referente == $r['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($r['apellido'] . ', ' . $r['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Partido -->
        <div class="col-md-4">
            <label class="form-label" style="font-size:0.8rem;">Partido</label>
            <select name="partido" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($partidos as $p): ?>
                <option value="<?php echo $p['id']; ?>"
                    <?php echo $partido == $p['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

    </div>

    <div class="row g-2 align-items-end">

        <!-- Trabajo -->
        <div class="col-md-2">
            <label class="form-label" style="font-size:0.8rem;">Trabajo</label>
            <select name="trabajo" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($trabajos as $t): ?>
                <option value="<?php echo $t['id']; ?>"
                    <?php echo $trabajo == $t['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($t['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Sede -->
        <div class="col-md-2">
            <label class="form-label" style="font-size:0.8rem;">Sede</label>
            <select name="sede" class="form-select form-select-sm">
                <option value="">Todas</option>
                <?php foreach ($sedes as $s): ?>
                <option value="<?php echo $s['id']; ?>"
                    <?php echo $sede == $s['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($s['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Carrera — se inhibe si padron = CP -->
        <div class="col-md-2">
            <label class="form-label" style="font-size:0.8rem;">Carrera</label>
            <select name="carrera" id="combo-carrera" class="form-select form-select-sm"
                <?php echo $padron === 'CP' ? 'disabled' : ''; ?>>
                <option value="">Todas</option>
                <?php foreach ($carreras as $c): ?>
                <option value="<?php echo htmlspecialchars($c['sigla'], ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo $carrera === $c['sigla'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['descripcion'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Eleccion -->
        <div class="col-md-3">
            <label class="form-label" style="font-size:0.8rem;">Elección</label>
            <select name="eleccion" class="form-select form-select-sm">
                <option value="">Todas</option>
                <?php foreach ($elecciones as $e): ?>
                <option value="<?php echo $e['id']; ?>"
                    <?php echo $eleccion == $e['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($e['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Voto -->
        <div class="col-md-1">
            <label class="form-label" style="font-size:0.8rem;">Voto</label>
            <select name="voto" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="SI" <?php echo $voto === 'SI' ? 'selected' : ''; ?>>Votó</option>
                <option value="NO" <?php echo $voto === 'NO' ? 'selected' : ''; ?>>No votó</option>
            </select>
        </div>

        <!-- Botones -->
        <div class="col-auto">
            <button type="submit" class="btn btn-acento btn-sm">Filtrar</button>
        </div>
        <div class="col-auto">
            <a href="index.php?mod=filtros" class="btn btn-outline-secondary btn-sm">Limpiar</a>
        </div>

    </div>
</form>

<?php if ($aviso !== ''): ?>
    <div class="alert alert-warning" style="font-size:0.9rem;">
        <?php echo htmlspecialchars($aviso, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if ($filtro_aplicado): ?>

    <?php if (empty($resultados)): ?>
        <p class="text-secondary">No se encontraron resultados con los filtros aplicados.</p>

    <?php else: ?>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-secondary" style="font-size:0.85rem;">
                <?php echo number_format($total_registros, 0, ',', '.'); ?> resultado<?php echo $total_registros !== 1 ? 's' : ''; ?>
            </span>
            <a href="index.php?<?php echo $params_url; ?>&accion=descargar"
                class="btn btn-outline-secondary btn-sm">Descargar Excel</a>
        </div>

        <!-- Scroll superior sincronizado con la tabla -->
        <div id="scroll-top" style="overflow-x:auto; overflow-y:hidden; height:18px; margin-bottom:2px;">
            <div id="scroll-top-inner" style="height:1px;"></div>
        </div>

        <!-- Tabla de resultados -->
        <div id="scroll-tabla" class="table-responsive">
            <table class="table table-hover table-bordered align-middle" id="tabla-filtros" style="font-size:0.82rem;">
                <thead>
                    <tr>
                        <th>DNI</th>
                        <th>Apellido</th>
                        <th>Nombre</th>
                        <th>Padrón</th>
                        <th>Carrera</th>
                        <th>Aux CP</th>
                        <th>Referente 1</th>
                        <th>Referente 2</th>
                        <th>Referente 3</th>
                        <th>Partido</th>
                        <th>Trabajo</th>
                        <th>Sede</th>
                        <th>Municipio</th>
                        <th>CD 2021</th>
                        <th>CD 2024</th>
                        <th>CP 2017</th>
                        <th>CP 2019</th>
                        <th>CP 2021</th>
                        <th>CP 2024</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultados as $f): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($f['dni'],      ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($f['apellido'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($f['nombre'],   ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php if ($f['padron'] === 'CD'): ?>
                                <span class="badge" style="background-color:#a6d900;color:#1a1a2e;">CD</span>
                            <?php else: ?>
                                <span class="badge" style="background-color:#1a1a2e;color:#fff;">CP</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($f['carrera']     ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($f['auxiliar'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($f['referente_1'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($f['referente_2'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($f['referente_3'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($f['partido']     ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($f['trabajo']     ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($f['sede']        ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($f['municipio']   ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <?php
                        $votos = ['voto_cd_2021','voto_cd_2024','voto_cp_2017','voto_cp_2019','voto_cp_2021','voto_cp_2024'];
                        foreach ($votos as $v):
                            $val = $f[$v] ?? null;
                        ?>
                        <td><?php
                            if ($val === null)       echo '<span class="text-secondary">—</span>';
                            elseif ($val === 'SI')   echo '<span class="badge" style="background-color:#a6d900;color:#1a1a2e;">SI</span>';
                            else                     echo '<span class="text-secondary">NO</span>';
                        ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginacion -->
        <?php if ($total_paginas > 1): ?>
        <nav class="mt-3">
            <ul class="pagination pagination-sm justify-content-center">
                <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="index.php?<?php echo $params_url; ?>&pagina=<?php echo $pagina - 1; ?>">Anterior</a>
                </li>
                <?php
                $rango_inicio = max(1, $pagina - 2);
                $rango_fin    = min($total_paginas, $pagina + 2);
                for ($p = $rango_inicio; $p <= $rango_fin; $p++):
                ?>
                <li class="page-item <?php echo $p === $pagina ? 'active' : ''; ?>">
                    <a class="page-link"
                        href="index.php?<?php echo $params_url; ?>&pagina=<?php echo $p; ?>"
                        style="<?php echo $p === $pagina ? 'background-color:#a6d900;border-color:#a6d900;color:#1a1a2e;' : ''; ?>">
                        <?php echo $p; ?>
                    </a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>">
                    <a class="page-link" href="index.php?<?php echo $params_url; ?>&pagina=<?php echo $pagina + 1; ?>">Siguiente</a>
                </li>
            </ul>
            <p class="text-center text-secondary" style="font-size:0.8rem;">
                Página <?php echo $pagina; ?> de <?php echo $total_paginas; ?>
            </p>
        </nav>
        <?php endif; ?>

    <?php endif; ?>

<?php endif; ?>

<script>
(function () {

    const comboPadron    = document.getElementById('combo-padron');
    const comboAuxiliar  = document.getElementById('combo-auxiliar');
    const comboCarrera   = document.getElementById('combo-carrera');
    const hiddenAuxiliar = document.getElementById('hidden-auxiliar');

    const scrollTop   = document.getElementById('scroll-top');
    const scrollTabla = document.getElementById('scroll-tabla');
    const inner       = document.getElementById('scroll-top-inner');

    // Sincronizar scroll superior con la tabla
    if (scrollTop && scrollTabla && inner) {
        function ajustarAncho() {
            const tabla = document.getElementById('tabla-filtros');
            if (tabla) inner.style.width = tabla.offsetWidth + 'px';
        }
        ajustarAncho();
        window.addEventListener('resize', ajustarAncho);
        scrollTop.addEventListener('scroll', function () {
            scrollTabla.scrollLeft = scrollTop.scrollLeft;
        });
        scrollTabla.addEventListener('scroll', function () {
            scrollTop.scrollLeft = scrollTabla.scrollLeft;
        });
    }

    // Actualiza estado de combos segun padron seleccionado.
    // CD: auxiliar deshabilitado (no aplica), hidden envia NO al PHP,
    //     carrera habilitada.
    // CP: auxiliar habilitado, hidden vacio (el combo visible envia el valor),
    //     carrera deshabilitada y reseteada.
    // Sin padron: todo deshabilitado, hidden vacio.
    function actualizarCombos() {
        const padron = comboPadron.value;

        if (padron === '') {
            comboAuxiliar.disabled = true;
            comboAuxiliar.value    = '';
            comboCarrera.disabled  = true;
            comboCarrera.value     = '';
            hiddenAuxiliar.value   = '';

        } else if (padron === 'CD') {
            // CD nunca tiene auxiliares CP: se deshabilita el combo y
            // el hidden garantiza que auxiliar_cp = NO llegue al PHP
            comboAuxiliar.disabled = true;
            comboAuxiliar.value    = 'NO';
            comboCarrera.disabled  = false;
            hiddenAuxiliar.value   = 'NO';

        } else if (padron === 'CP') {
            // CP: el usuario elige si incluye auxiliares o no
            comboAuxiliar.disabled = false;
            comboAuxiliar.value    = '';
            comboCarrera.disabled  = true;
            comboCarrera.value     = '';
            hiddenAuxiliar.value   = ''; // el combo visible manda el valor
        }
    }

    actualizarCombos();
    comboPadron.addEventListener('change', actualizarCombos);

})();
</script>

<?php require_once 'includes/footer.php'; ?>
