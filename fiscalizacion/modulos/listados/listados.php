<?php
// fiscalizacion/modulos/listados/listados.php
// Modulo de listados del sistema Fiscalizar — Fiscalizacion.
// Acceso: admin, superadmin.
//
// Dos secciones:
//
// Seccion 1 — Buscador de persona
//   Input unico (apellido o DNI). Busca en todos los padrones de elecciones activas.
//   Resultado: ELECCION | DNI | APELLIDO | NOMBRE | VOTO 2026
//   Descargable en Excel.
//
// Seccion 2 — Listado por eleccion
//   Combo: elegir una eleccion activa.
//   Filtros opcionales: referente, partido, trabajo.
//   Para CD: ELECCION | DNI | APELLIDO | NOMBRE | CARRERA | VOTO 2026
//   Para CP/RT/CS: ELECCION | DNI | APELLIDO | NOMBRE | AUXILIAR | VOTO 2026
//   Descargable en Excel.
//   Fuente: vistas vista_fiscal_cd / vista_fiscal_cp / vista_fiscal_rt / vista_fiscal_cs
//   Los filtros usan columnas de la vista aunque no se muestren todas en pantalla.

verificar_admin_fiscal();

require_once 'includes/excel.php';

// ============================================================
// EXPORTACION EXCEL — debe ejecutarse antes de cualquier output
// El parametro export indica que seccion exportar
// ============================================================

$export = $_GET['export'] ?? '';

// --- Export: buscador ---
if ($export === 'buscador') {
    $q = trim($_GET['q'] ?? '');
    if ($q !== '') {
        $resultado_export = buscar_en_padrones($pdo, $q);
        exportar_excel($resultado_export, 'busqueda-padron');
    }
    exit;
}

// --- Export: listado por eleccion ---
if ($export === 'listado') {
    $id_eleccion_export = intval($_GET['id_eleccion'] ?? 0);
    if ($id_eleccion_export > 0) {
        $eleccion_export = obtener_eleccion($pdo, $id_eleccion_export);
        if ($eleccion_export) {
            $filtros_export = [
                'referente' => trim($_GET['referente'] ?? ''),
                'partido'   => trim($_GET['partido']   ?? ''),
                'trabajo'   => trim($_GET['trabajo']   ?? ''),
            ];
            $resultado_export = obtener_listado($pdo, $eleccion_export, $filtros_export);
            $nombre_archivo   = 'listado-' . $eleccion_export['tipo'] . '-' . $eleccion_export['anio'];
            exportar_excel($resultado_export, $nombre_archivo);
        }
    }
    exit;
}

// ============================================================
// FUNCIONES DE CONSULTA
// ============================================================

// buscar_en_padrones()
// Busca en todos los padrones de elecciones activas por apellido o DNI.
// Devuelve una fila por padron donde figura la persona.
function buscar_en_padrones(PDO $pdo, string $q): array {
    $es_dni = ctype_digit($q);
    $param  = $es_dni ? $q : $q . '%';
    $campo  = $es_dni ? 't.dni = ?' : 'p.apellido LIKE ?';

    // Un SELECT por cada tipo de eleccion activa, unidos con UNION ALL
    // Usamos parametros posicionales (?) para evitar conflicto HY093
    $sql = "
        SELECT e.nombre AS eleccion, p.dni, p.apellido, p.nombre,
               CASE WHEN v.id IS NOT NULL THEN 'SI' ELSE 'NO' END AS voto_2026
        FROM padron_cd t
        JOIN personas p      ON t.dni = p.dni
        JOIN elecciones e    ON e.tipo = 'cd' AND e.estado = 'activa'
        LEFT JOIN dias_eleccion d ON d.id_eleccion = e.id
        LEFT JOIN mesas m    ON m.id_dia = d.id
        LEFT JOIN votos_dia v ON v.dni = p.dni AND v.id_mesa = m.id
        WHERE $campo

        UNION ALL

        SELECT e.nombre AS eleccion, p.dni, p.apellido, p.nombre,
               CASE WHEN v.id IS NOT NULL THEN 'SI' ELSE 'NO' END AS voto_2026
        FROM padron_cp t
        JOIN personas p      ON t.dni = p.dni
        JOIN elecciones e    ON e.tipo = 'cp' AND e.estado = 'activa'
        LEFT JOIN dias_eleccion d ON d.id_eleccion = e.id
        LEFT JOIN mesas m    ON m.id_dia = d.id
        LEFT JOIN votos_dia v ON v.dni = p.dni AND v.id_mesa = m.id
        WHERE $campo

        UNION ALL

        SELECT e.nombre AS eleccion, p.dni, p.apellido, p.nombre,
               CASE WHEN v.id IS NOT NULL THEN 'SI' ELSE 'NO' END AS voto_2026
        FROM padron_rt t
        JOIN personas p      ON t.dni = p.dni
        JOIN elecciones e    ON e.tipo = 'rt' AND e.estado = 'activa'
        LEFT JOIN dias_eleccion d ON d.id_eleccion = e.id
        LEFT JOIN mesas m    ON m.id_dia = d.id
        LEFT JOIN votos_dia v ON v.dni = p.dni AND v.id_mesa = m.id
        WHERE $campo

        UNION ALL

        SELECT e.nombre AS eleccion, p.dni, p.apellido, p.nombre,
               CASE WHEN v.id IS NOT NULL THEN 'SI' ELSE 'NO' END AS voto_2026
        FROM padron_cs t
        JOIN personas p      ON t.dni = p.dni
        JOIN elecciones e    ON e.tipo = 'cs' AND e.estado = 'activa'
        LEFT JOIN dias_eleccion d ON d.id_eleccion = e.id
        LEFT JOIN mesas m    ON m.id_dia = d.id
        LEFT JOIN votos_dia v ON v.dni = p.dni AND v.id_mesa = m.id
        WHERE $campo

        ORDER BY apellido, nombre, eleccion
    ";

    // Cuatro parametros: uno por cada SELECT del UNION
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$param, $param, $param, $param]);
    return $stmt->fetchAll();
}

// obtener_eleccion()
// Devuelve los datos de una eleccion activa por id.
function obtener_eleccion(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("
        SELECT id, nombre, tipo, anio
        FROM elecciones
        WHERE id = ? AND estado = 'activa'
    ");
    $stmt->execute([$id]);
    $result = $stmt->fetch();
    return $result ?: null;
}

// obtener_listado()
// Consulta la vista correspondiente segun el tipo de eleccion.
// Aplica filtros opcionales de referente, partido y trabajo.
// Devuelve solo las columnas relevantes para el tipo.
function obtener_listado(PDO $pdo, array $eleccion, array $filtros): array {
    $tipo = $eleccion['tipo'];

    // Vista segun tipo de eleccion
    $vista = match($tipo) {
        'cd' => 'vista_fiscal_cd',
        'cp' => 'vista_fiscal_cp',
        'rt' => 'vista_fiscal_rt',
        'cs' => 'vista_fiscal_cs',
        default => null
    };

    if (!$vista) {
        return [];
    }

    // Columnas a mostrar segun tipo
    // CD: carrera en lugar de auxiliar
    // CP/RT/CS: auxiliar, sin carrera
    if ($tipo === 'cd') {
        $columnas_select = "eleccion, dni, apellido, nombre, carrera, voto_2026";
    } else {
        $columnas_select = "eleccion, dni, apellido, nombre, auxiliar, voto_2026";
    }

    // Construir WHERE dinamico para los filtros opcionales
    // Los filtros usan columnas de la vista que no se muestran en pantalla
    $where  = [];
    $params = [];

    if ($filtros['referente'] !== '') {
        // Buscar en cualquiera de los tres referentes
        $where[]  = "(referente_1 = ? OR referente_2 = ? OR referente_3 = ?)";
        $params[] = $filtros['referente'];
        $params[] = $filtros['referente'];
        $params[] = $filtros['referente'];
    }

    if ($filtros['partido'] !== '') {
        $where[]  = "partido = ?";
        $params[] = $filtros['partido'];
    }

    if ($filtros['trabajo'] !== '') {
        $where[]  = "trabajo = ?";
        $params[] = $filtros['trabajo'];
    }

    $clausula_where = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("
        SELECT $columnas_select
        FROM $vista
        $clausula_where
        ORDER BY apellido ASC, nombre ASC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// ============================================================
// CARGA DE DATOS PARA LA VISTA
// ============================================================

// Elecciones activas para el combo
$elecciones_activas = $pdo->query("
    SELECT id, nombre, tipo, anio
    FROM elecciones
    WHERE estado = 'activa'
    ORDER BY tipo ASC, anio ASC
")->fetchAll();

// Eleccion seleccionada
$id_eleccion_sel = intval($_GET['id_eleccion'] ?? 0);
$eleccion_sel    = null;
$listado         = [];
$filtros         = [
    'referente' => trim($_GET['referente'] ?? ''),
    'partido'   => trim($_GET['partido']   ?? ''),
    'trabajo'   => trim($_GET['trabajo']   ?? ''),
];

if ($id_eleccion_sel > 0) {
    $eleccion_sel = obtener_eleccion($pdo, $id_eleccion_sel);
    if ($eleccion_sel) {
        $listado = obtener_listado($pdo, $eleccion_sel, $filtros);
    }
}

// Combos de filtros — solo referentes, partidos y trabajos activos
$referentes = $pdo->query("
    SELECT CONCAT(apellido, ' ', nombre) AS nombre_completo
    FROM referentes
    WHERE activo = 1
    ORDER BY apellido ASC, nombre ASC
")->fetchAll(PDO::FETCH_COLUMN);

$partidos = $pdo->query("
    SELECT nombre FROM partidos
    WHERE activo = 1
    ORDER BY nombre ASC
")->fetchAll(PDO::FETCH_COLUMN);

$trabajos = $pdo->query("
    SELECT nombre FROM trabajos
    WHERE activo = 1
    ORDER BY nombre ASC
")->fetchAll(PDO::FETCH_COLUMN);

// Buscador de persona
$q_busqueda      = trim($_GET['q'] ?? '');
$resultado_busca = [];
if ($q_busqueda !== '') {
    $resultado_busca = buscar_en_padrones($pdo, $q_busqueda);
}

require_once 'includes/navbar.php';
?>

<div class="modulo-titulo">Listados</div>

<!-- ============================================================ -->
<!-- SECCION 1 — BUSCADOR DE PERSONA                              -->
<!-- ============================================================ -->

<div class="modulo-subtitulo mb-2">Buscar persona</div>
<p class="text-secondary mb-3" style="font-size:0.82rem;">
    Buscá por apellido o DNI en todos los padrones de elecciones activas.
</p>

<form method="GET" action="index.php" class="row g-2 align-items-end mb-3">
    <input type="hidden" name="mod" value="listados">
    <div class="col-md-4">
        <input type="text" name="q" class="form-control form-control-sm"
               placeholder="Apellido o DNI"
               value="<?php echo htmlspecialchars($q_busqueda, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Buscar</button>
    </div>
    <?php if (!empty($resultado_busca)): ?>
    <div class="col-auto">
        <a href="index.php?mod=listados&export=buscador&q=<?php echo urlencode($q_busqueda); ?>"
           class="btn btn-sm btn-outline-secondary">
            Descargar Excel
        </a>
    </div>
    <?php endif; ?>
</form>

<?php if ($q_busqueda !== '' && empty($resultado_busca)): ?>
    <p class="text-secondary" style="font-size:0.85rem;">
        No se encontraron resultados para
        <strong><?php echo htmlspecialchars($q_busqueda, ENT_QUOTES, 'UTF-8'); ?></strong>
        en ningún padrón activo.
    </p>
<?php endif; ?>

<?php if (!empty($resultado_busca)): ?>
<div class="table-responsive mb-4">
    <table class="table table-hover table-bordered align-middle" style="font-size:0.85rem;">
        <thead>
            <tr>
                <th>Elección</th>
                <th>DNI</th>
                <th>Apellido</th>
                <th>Nombre</th>
                <th>Votó</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($resultado_busca as $fila): ?>
            <tr>
                <td><?php echo htmlspecialchars($fila['eleccion'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($fila['dni'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($fila['apellido'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($fila['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <?php if ($fila['voto_2026'] === 'SI'): ?>
                        <span class="badge bg-success">SI</span>
                    <?php else: ?>
                        <span class="text-secondary">NO</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<hr class="my-4">

<!-- ============================================================ -->
<!-- SECCION 2 — LISTADO POR ELECCION                             -->
<!-- ============================================================ -->

<div class="modulo-subtitulo mb-2">Listado por elección</div>

<?php if (empty($elecciones_activas)): ?>
    <p class="text-secondary" style="font-size:0.85rem;">
        No hay elecciones activas. Activá una desde Administración → Elecciones.
    </p>
<?php else: ?>

<!-- Formulario de seleccion y filtros -->
<form method="GET" action="index.php" class="mb-3">
    <input type="hidden" name="mod" value="listados">

    <div class="row g-2 align-items-end">

        <!-- Combo de elecciones -->
        <div class="col-md-3">
            <label class="form-label form-label-sm">Elección</label>
            <select name="id_eleccion" class="form-select form-select-sm"
                    onchange="this.form.submit()">
                <option value="">— elegir —</option>
                <?php foreach ($elecciones_activas as $e): ?>
                <option value="<?php echo $e['id']; ?>"
                    <?php echo $id_eleccion_sel === $e['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($e['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($eleccion_sel): ?>

        <!-- Filtro referente -->
        <div class="col-md-3">
            <label class="form-label form-label-sm">Referente</label>
            <select name="referente" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($referentes as $r): ?>
                <option value="<?php echo htmlspecialchars($r, ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo $filtros['referente'] === $r ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($r, ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Filtro partido -->
        <div class="col-md-3">
            <label class="form-label form-label-sm">Partido</label>
            <select name="partido" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($partidos as $p): ?>
                <option value="<?php echo htmlspecialchars($p, ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo $filtros['partido'] === $p ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($p, ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Filtro trabajo -->
        <div class="col-md-3">
            <label class="form-label form-label-sm">Trabajo</label>
            <select name="trabajo" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($trabajos as $t): ?>
                <option value="<?php echo htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo $filtros['trabajo'] === $t ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Botones -->
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
        </div>
        <div class="col-auto">
            <a href="index.php?mod=listados&id_eleccion=<?php echo $id_eleccion_sel; ?>"
               class="btn btn-sm btn-outline-secondary">
                Limpiar
            </a>
        </div>
        <div class="col-auto">
            <!-- Descarga Excel con los mismos filtros activos -->
            <a href="index.php?mod=listados&export=listado&id_eleccion=<?php echo $id_eleccion_sel; ?>&referente=<?php echo urlencode($filtros['referente']); ?>&partido=<?php echo urlencode($filtros['partido']); ?>&trabajo=<?php echo urlencode($filtros['trabajo']); ?>"
               class="btn btn-sm btn-outline-secondary">
                Descargar Excel
            </a>
        </div>

        <?php endif; ?>

    </div>
</form>

<!-- Resultado del listado -->
<?php if ($eleccion_sel && !empty($listado)): ?>

<div class="text-secondary mb-2" style="font-size:0.82rem;">
    <?php echo number_format(count($listado), 0, ',', '.'); ?> registros
    — <?php echo htmlspecialchars($eleccion_sel['nombre'], ENT_QUOTES, 'UTF-8'); ?>
    <?php if (array_filter($filtros)): ?>
        — con filtros aplicados
    <?php endif; ?>
</div>

<div class="table-responsive">
    <table class="table table-hover table-bordered align-middle" style="font-size:0.85rem;">
        <thead>
            <tr>
                <th>Elección</th>
                <th>DNI</th>
                <th>Apellido</th>
                <th>Nombre</th>
                <?php if ($eleccion_sel['tipo'] === 'cd'): ?>
                <th>Carrera</th>
                <?php else: ?>
                <th>Auxiliar</th>
                <?php endif; ?>
                <th>Votó</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($listado as $fila): ?>
            <tr>
                <td><?php echo htmlspecialchars($fila['eleccion'],  ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($fila['dni'],       ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($fila['apellido'],  ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($fila['nombre'],    ENT_QUOTES, 'UTF-8'); ?></td>
                <?php if ($eleccion_sel['tipo'] === 'cd'): ?>
                <td><?php echo htmlspecialchars($fila['carrera'],   ENT_QUOTES, 'UTF-8'); ?></td>
                <?php else: ?>
                <td>
                    <?php if ($fila['auxiliar'] === 'SI'): ?>
                        <span class="badge" style="background-color:#1a1a2e;color:#fff;">SI</span>
                    <?php else: ?>
                        <span class="text-secondary">NO</span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
                <td>
                    <?php if ($fila['voto_2026'] === 'SI'): ?>
                        <span class="badge bg-success">SI</span>
                    <?php else: ?>
                        <span class="text-secondary">NO</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php elseif ($eleccion_sel && empty($listado)): ?>
    <p class="text-secondary" style="font-size:0.85rem;">
        No hay registros para los filtros seleccionados.
    </p>
<?php endif; ?>

<?php endif; // fin if elecciones_activas ?>

<?php require_once 'includes/footer.php'; ?>
