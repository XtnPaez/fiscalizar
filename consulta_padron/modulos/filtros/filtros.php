<?php
// modulos/filtros/filtros.php
// Modulo de filtros del sistema Fiscalizar — Consulta Padron.
// Acceso: todos los niveles autenticados.
// Combos: padron, carrera, referente, partido, trabajo, auxiliar, voto en eleccion.
// Resultado: perfil completo con todos los campos de las vistas CD y CP.
// Tabla unificada con todas las columnas — NULL cuando no aplica.

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

$stmt_elecciones = $pdo->query("SELECT id, nombre, tipo FROM elecciones ORDER BY anio ASC, tipo ASC");
$elecciones      = $stmt_elecciones->fetchAll();

// --- Leer parametros ---
$padron    = $_GET['padron']    ?? '';
$carrera   = $_GET['carrera']   ?? '';
$referente = $_GET['referente'] ?? '';
$partido   = $_GET['partido']   ?? '';
$trabajo   = $_GET['trabajo']   ?? '';
$auxiliar  = $_GET['auxiliar']  ?? '';
$eleccion  = $_GET['eleccion']  ?? '';
$voto      = $_GET['voto']      ?? '';
$accion    = $_GET['accion']    ?? '';
$pagina    = max(1, intval($_GET['pagina'] ?? 1));
$por_pagina = 50;

$filtro_aplicado = ($padron !== '' || $carrera !== '' || $referente !== '' ||
                    $partido !== '' || $trabajo !== '' || $auxiliar !== '' ||
                    $eleccion !== '');

$resultados      = [];
$total_registros = 0;
$total_paginas   = 0;

// --- Construir resultado unificado ---
// Se construyen dos SELECT (uno por vista) con todas las columnas
// Las columnas que no aplican a una vista se ponen como NULL
// Se unen con UNION y se filtran con WHERE en subquery

if ($filtro_aplicado) {

    // Condiciones comunes a ambas vistas
    $conds_comunes = [];
    $params        = [];

    if ($referente !== '') {
        $conds_comunes[] = "dni IN (
            SELECT dni FROM referentes_graduado
            WHERE referente_1 = :ref OR referente_2 = :ref2 OR referente_3 = :ref3
        )";
        $params[':ref']  = $referente;
        $params[':ref2'] = $referente;
        $params[':ref3'] = $referente;
    }

    if ($partido !== '') {
        $conds_comunes[] = "dni IN (SELECT dni FROM persona_partido WHERE id_partido = :partido)";
        $params[':partido'] = $partido;
    }

    if ($trabajo !== '') {
        $conds_comunes[] = "dni IN (SELECT dni FROM persona_trabajo WHERE id_trabajo = :trabajo)";
        $params[':trabajo'] = $trabajo;
    }

    // --- SELECT desde vista_padron_cd ---
    $conds_cd  = $conds_comunes;
    $params_cd = $params;

    if ($carrera !== '') {
        $conds_cd[]         = "carrera = :carrera";
        $params_cd[':carrera'] = $carrera;
    }

    if ($eleccion !== '' && $voto !== '') {
        // Determinar si la eleccion es de tipo CD o CP
        $stmt_tipo = $pdo->prepare("SELECT tipo FROM elecciones WHERE id = :id");
        $stmt_tipo->execute([':id' => $eleccion]);
        $tipo_eleccion = $stmt_tipo->fetchColumn();

        if ($tipo_eleccion === 'cd') {
            if ($voto === 'SI') {
                $conds_cd[] = "dni IN (SELECT dni FROM participacion_electoral WHERE id_eleccion = :eleccion_cd)";
            } else {
                $conds_cd[] = "dni NOT IN (SELECT dni FROM participacion_electoral WHERE id_eleccion = :eleccion_cd)";
            }
            $params_cd[':eleccion_cd'] = $eleccion;
        }
    }

    $where_cd = count($conds_cd) > 0 ? 'WHERE ' . implode(' AND ', $conds_cd) : '';

    $select_cd = "
        SELECT
            dni, apellido, nombre, carrera,
            'SI'        AS padron_cd,
            'NO'        AS padron_cp,
            NULL        AS auxiliar,
            referente_1, referente_2, referente_3,
            partido, trabajo, sede_laboral,
            voto_cd_2021, voto_cd_2024,
            NULL AS voto_cp_2017,
            NULL AS voto_cp_2019,
            NULL AS voto_cp_2021,
            NULL AS voto_cp_2024
        FROM vista_padron_cd
        $where_cd
    ";

    // --- SELECT desde vista_padron_cp ---
    $conds_cp  = $conds_comunes;
    $params_cp = $params;

    if ($auxiliar !== '') {
        $conds_cp[]           = "auxiliar = :auxiliar";
        $params_cp[':auxiliar'] = ($auxiliar === 'SI') ? 1 : 0;
    }

    if ($eleccion !== '' && $voto !== '' && isset($tipo_eleccion) && $tipo_eleccion === 'cp') {
        if ($voto === 'SI') {
            $conds_cp[] = "dni IN (SELECT dni FROM participacion_electoral WHERE id_eleccion = :eleccion_cp)";
        } else {
            $conds_cp[] = "dni NOT IN (SELECT dni FROM participacion_electoral WHERE id_eleccion = :eleccion_cp)";
        }
        $params_cp[':eleccion_cp'] = $eleccion;
    }

    $where_cp = count($conds_cp) > 0 ? 'WHERE ' . implode(' AND ', $conds_cp) : '';

    $select_cp = "
        SELECT
            dni, apellido, nombre,
            NULL        AS carrera,
            'NO'        AS padron_cd,
            'SI'        AS padron_cp,
            auxiliar,
            referente_1, referente_2, referente_3,
            partido, trabajo, sede_laboral,
            NULL AS voto_cd_2021,
            NULL AS voto_cd_2024,
            voto_cp_2017, voto_cp_2019, voto_cp_2021, voto_cp_2024
        FROM vista_padron_cp
        $where_cp
    ";

    // --- Combinar segun padron seleccionado ---
    if ($padron === 'CD') {
        $sql_union  = $select_cd;
        $params_all = $params_cd;
    } elseif ($padron === 'CP') {
        $sql_union  = $select_cp;
        $params_all = $params_cp;
        // Carrera no aplica a CP — ignorar ese filtro
    } else {
        // Ambos padrones
        // Para personas en ambos padrones se consolida en una sola fila via subquery
        $sql_union = "
            SELECT
                p.dni,
                p.apellido,
                p.nombre,
                cd.carrera,
                IF(cd.dni IS NOT NULL, 'SI', 'NO') AS padron_cd,
                IF(cp.dni IS NOT NULL, 'SI', 'NO') AS padron_cp,
                cp.auxiliar,
                COALESCE(cd.referente_1, cp.referente_1) AS referente_1,
                COALESCE(cd.referente_2, cp.referente_2) AS referente_2,
                COALESCE(cd.referente_3, cp.referente_3) AS referente_3,
                COALESCE(cd.partido,     cp.partido)     AS partido,
                COALESCE(cd.trabajo,     cp.trabajo)     AS trabajo,
                COALESCE(cd.sede_laboral,cp.sede_laboral)AS sede_laboral,
                cd.voto_cd_2021, cd.voto_cd_2024,
                cp.voto_cp_2017, cp.voto_cp_2019, cp.voto_cp_2021, cp.voto_cp_2024
            FROM personas p
            LEFT JOIN ($select_cd) cd ON p.dni = cd.dni
            LEFT JOIN ($select_cp) cp ON p.dni = cp.dni
            WHERE cd.dni IS NOT NULL OR cp.dni IS NOT NULL
        ";
        $params_all = array_merge($params_cd, $params_cp);
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
    $sql_paginado = $sql_ordenado . " LIMIT :limite OFFSET :offset";
    $stmt = $pdo->prepare($sql_paginado);
    foreach ($params_all as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,     PDO::PARAM_INT);
    $stmt->execute();
    $resultados = $stmt->fetchAll();
}

// URL base para paginacion conservando filtros
$params_url = http_build_query(array_filter([
    'mod'      => 'filtros',
    'padron'   => $padron,
    'carrera'  => $carrera,
    'referente'=> $referente,
    'partido'  => $partido,
    'trabajo'  => $trabajo,
    'auxiliar' => $auxiliar,
    'eleccion' => $eleccion,
    'voto'     => $voto,
]));

require_once 'includes/navbar.php';
?>

<div class="modulo-titulo">Filtros</div>

<!-- Formulario de filtros -->
<form method="GET" action="index.php" class="mb-4">
    <input type="hidden" name="mod" value="filtros">

    <div class="row g-2 mb-2">

        <!-- Padron -->
        <div class="col-md-2">
            <label class="form-label" style="font-size:0.8rem;">Padron</label>
            <select name="padron" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="CD" <?php echo $padron === 'CD' ? 'selected' : ''; ?>>CD</option>
                <option value="CP" <?php echo $padron === 'CP' ? 'selected' : ''; ?>>CP</option>
            </select>
        </div>

        <!-- Carrera -->
        <div class="col-md-2">
            <label class="form-label" style="font-size:0.8rem;">Carrera</label>
            <select name="carrera" class="form-select form-select-sm">
                <option value="">Todas</option>
                <?php foreach ($carreras as $c): ?>
                <option value="<?php echo htmlspecialchars($c['sigla'], ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo $carrera === $c['sigla'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['descripcion'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Auxiliar -->
        <div class="col-md-2">
            <label class="form-label" style="font-size:0.8rem;">Auxiliar</label>
            <select name="auxiliar" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="SI" <?php echo $auxiliar === 'SI' ? 'selected' : ''; ?>>Si</option>
                <option value="NO" <?php echo $auxiliar === 'NO' ? 'selected' : ''; ?>>No</option>
            </select>
        </div>

        <!-- Referente -->
        <div class="col-md-3">
            <label class="form-label" style="font-size:0.8rem;">Referente</label>
            <select name="referente" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($referentes as $r): ?>
                <option value="<?php echo $r['id']; ?>"
                    <?php echo $referente == $r['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($r['apellido'] . ', ' . $r['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Partido -->
        <div class="col-md-3">
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
        <div class="col-md-3">
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

        <!-- Eleccion -->
        <div class="col-md-4">
            <label class="form-label" style="font-size:0.8rem;">Eleccion</label>
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
        <div class="col-md-2">
            <label class="form-label" style="font-size:0.8rem;">Voto</label>
            <select name="voto" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="SI" <?php echo $voto === 'SI' ? 'selected' : ''; ?>>Voto</option>
                <option value="NO" <?php echo $voto === 'NO' ? 'selected' : ''; ?>>No voto</option>
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

        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle" style="font-size:0.82rem;">
                <thead>
                    <tr>
                        <th>DNI</th>
                        <th>Apellido</th>
                        <th>Nombre</th>
                        <th>Carrera</th>
                        <th>CD</th>
                        <th>CP</th>
                        <th>Auxiliar</th>
                        <th>Referente 1</th>
                        <th>Referente 2</th>
                        <th>Referente 3</th>
                        <th>Partido</th>
                        <th>Trabajo</th>
                        <th>Sede</th>
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
                        <td><?php echo htmlspecialchars($f['carrera']      ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo $f['padron_cd'] === 'SI'
                            ? '<span class="badge" style="background-color:#a6d900;color:#1a1a2e;">SI</span>'
                            : '<span class="text-secondary">—</span>'; ?></td>
                        <td><?php echo $f['padron_cp'] === 'SI'
                            ? '<span class="badge" style="background-color:#1a1a2e;color:#fff;">SI</span>'
                            : '<span class="text-secondary">—</span>'; ?></td>
                        <td><?php echo $f['auxiliar'] === null ? '—' : ($f['auxiliar'] ? 'SI' : 'NO'); ?></td>
                        <td><?php echo htmlspecialchars($f['referente_1'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($f['referente_2'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($f['referente_3'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($f['partido']      ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($f['trabajo']      ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($f['sede_laboral'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <?php
                        // Mostrar votos con badge si es SI, guion si NO o NULL
                        $votos = ['voto_cd_2021','voto_cd_2024','voto_cp_2017','voto_cp_2019','voto_cp_2021','voto_cp_2024'];
                        foreach ($votos as $v):
                            $val = $f[$v] ?? null;
                        ?>
                        <td><?php
                            if ($val === null) echo '<span class="text-secondary">—</span>';
                            elseif ($val === 'SI') echo '<span class="badge" style="background-color:#a6d900;color:#1a1a2e;">SI</span>';
                            else echo '<span class="text-secondary">NO</span>';
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
                Pagina <?php echo $pagina; ?> de <?php echo $total_paginas; ?>
            </p>
        </nav>
        <?php endif; ?>

    <?php endif; ?>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
