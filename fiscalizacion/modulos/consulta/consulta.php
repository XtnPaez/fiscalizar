<?php
// fiscalizacion/modulos/consulta/consulta.php
// Modulo de consulta del padron para usuarios con nivel 'mira'.
// Acceso: solo nivel mira.
//
// Cada usuario mira tiene un tipo de padron asignado (cd/cp/rt/cs).
// Solo puede ver el padron de su tipo en la eleccion activa correspondiente.
//
// Funcionalidades:
//   - Buscador por DNI, apellido o nombre
//   - Filtro VOTO: SI / NO / Todos
//   - Listado con columnas: DNI | APELLIDO | NOMBRE | VOTO
//   - Descarga Excel con el filtro aplicado

verificar_mira_fiscal();

require_once 'includes/excel.php';

$tipo = $_SESSION['tipo_mira'] ?? '';

// Validacion defensiva — el tipo debe ser uno de los cuatro validos
if (!in_array($tipo, ['cd', 'cp', 'rt', 'cs', 'cc'])) {
    header('Location: index.php?mod=logout');
    exit;
}

// Vista y nombre de eleccion segun tipo
$vista = match($tipo) {
    'cd' => 'vista_fiscal_cd',
    'cp' => 'vista_fiscal_cp',
    'rt' => 'vista_fiscal_rt',
    'cs' => 'vista_fiscal_cs',
    'cc' => 'vista_fiscal_cc',
    default => 'vista_fiscal_cd',
};

// Obtener nombre de la eleccion activa de este tipo
$stmt = $pdo->prepare("
    SELECT nombre FROM elecciones
    WHERE tipo = ? AND estado = 'activa'
    LIMIT 1
");
$stmt->execute([$tipo]);
$nombre_eleccion = $stmt->fetchColumn() ?: 'Sin eleccion activa';

// ============================================================
// PARAMETROS
// ============================================================

$q    = trim($_GET['q']    ?? '');
$voto = trim($_GET['voto'] ?? '');

// Validar voto
if (!in_array($voto, ['', 'SI', 'NO'])) {
    $voto = '';
}

// ============================================================
// EXPORTACION EXCEL — antes de cualquier output
// ============================================================

if (isset($_GET['export'])) {
    $resultado_export = obtener_resultado($pdo, $vista, $q, $voto);
    $nombre_archivo   = 'consulta-' . $tipo . '-' . date('Y-m-d');
    exportar_excel($resultado_export, $nombre_archivo);
    exit;
}

// ============================================================
// FUNCION DE CONSULTA
// ============================================================

// obtener_resultado()
// Consulta la vista del tipo correspondiente.
// Aplica filtros opcionales de busqueda (q) y voto (SI/NO).
// Devuelve DNI, apellido, nombre y voto_2026.
function obtener_resultado(PDO $pdo, string $vista, string $q, string $voto): array {
    $where  = [];
    $params = [];

    // Filtro de busqueda: DNI (numerico) o apellido/nombre (texto)
    if ($q !== '') {
        if (ctype_digit($q)) {
            $where[]  = "dni = ?";
            $params[] = intval($q);
        } else {
            $where[]  = "(apellido LIKE ? OR nombre LIKE ?)";
            $params[] = $q . '%';
            $params[] = $q . '%';
        }
    }

    // Filtro voto: valor controlado internamente
    // COLLATE explicito para evitar error 1267 en vista_fiscal_cc
    // que mezcla collations entre padron_cc y otras tablas
    if ($voto === 'SI') {
        $where[] = "voto_2026 COLLATE utf8mb4_unicode_ci = 'SI'";
    } elseif ($voto === 'NO') {
        $where[] = "voto_2026 COLLATE utf8mb4_unicode_ci = 'NO'";
    }

    $clausula_where = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Envolver en subquery para evitar problemas de MariaDB al filtrar
    // por columnas calculadas (CASE WHEN) usando WHERE sobre alias de vista
    $stmt = $pdo->prepare("
        SELECT dni, apellido, nombre, voto_2026
        FROM (
            SELECT dni, apellido, nombre, voto_2026
            FROM $vista
        ) AS sub
        $clausula_where
        ORDER BY apellido ASC, nombre ASC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// ============================================================
// CARGA DE DATOS
// ============================================================

$resultado = [];

// Mostrar resultado siempre que haya algun criterio activo O cuando voto = Todos
// Todos sin busqueda = padron completo con columna voto SI/NO
// Solo cuando no hay ningun criterio se muestra el mensaje de bienvenida
$hay_criterio = ($q !== '' || $voto !== '');
$mostrar_todo = ($q === '' && $voto === '');

// Ejecutar query si hay criterio o si el usuario eligio Todos explicitamente
// Para distinguir "recien entro" de "eligio Todos", usamos el parametro en la URL
$filtro_enviado = isset($_GET['voto']) || isset($_GET['q']);

if ($filtro_enviado) {
    $resultado = obtener_resultado($pdo, $vista, $q, $voto);
}

require_once 'includes/navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="modulo-titulo">
        Padrón <?php echo strtoupper($tipo); ?>
        <span class="text-secondary fw-normal" style="font-size:0.85rem;">
            — <?php echo htmlspecialchars($nombre_eleccion, ENT_QUOTES, 'UTF-8'); ?>
        </span>
    </div>
</div>

<!-- Formulario de busqueda y filtro -->
<form method="GET" action="index.php" class="row g-2 align-items-end mb-3">
    <input type="hidden" name="mod" value="consulta">

    <div class="col-md-4">
        <label class="form-label form-label-sm">Buscar</label>
        <input type="text" name="q" class="form-control form-control-sm"
               placeholder="DNI, apellido o nombre"
               value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="col-md-2">
        <label class="form-label form-label-sm">Voto</label>
        <select name="voto" class="form-select form-select-sm">
            <option value=""   <?php echo $voto === ''    ? 'selected' : ''; ?>>Todos</option>
            <option value="SI" <?php echo $voto === 'SI'  ? 'selected' : ''; ?>>SI</option>
            <option value="NO" <?php echo $voto === 'NO'  ? 'selected' : ''; ?>>NO</option>
        </select>
    </div>

    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Buscar</button>
    </div>

    <?php if ($filtro_enviado): ?>
    <div class="col-auto">
        <a href="index.php?mod=consulta" class="btn btn-sm btn-outline-secondary">
            Limpiar
        </a>
    </div>
    <?php endif; ?>

    <?php if (!empty($resultado)): ?>
    <div class="col-auto">
        <a href="index.php?mod=consulta&export=1&q=<?php echo urlencode($q); ?>&voto=<?php echo urlencode($voto); ?>"
           class="btn btn-sm btn-outline-secondary">
            Descargar Excel
        </a>
    </div>
    <?php endif; ?>

</form>

<?php if (!$filtro_enviado): ?>
    <p class="text-secondary" style="font-size:0.85rem;">
        Ingresá un criterio de búsqueda o elegí un filtro de voto para ver resultados.
    </p>

<?php elseif (empty($resultado)): ?>
    <p class="text-secondary" style="font-size:0.85rem;">
        No se encontraron resultados para los criterios ingresados.
    </p>

<?php else: ?>

<div class="text-secondary mb-2" style="font-size:0.82rem;">
    <?php echo number_format(count($resultado), 0, ',', '.'); ?> resultado<?php echo count($resultado) !== 1 ? 's' : ''; ?>
</div>

<div class="table-responsive">
    <table class="table table-hover table-bordered align-middle" style="font-size:0.85rem;">
        <thead>
            <tr>
                <th>DNI</th>
                <th>Apellido</th>
                <th>Nombre</th>
                <th>Votó</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($resultado as $fila): ?>
            <tr>
                <td><?php echo htmlspecialchars($fila['dni'],      ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($fila['apellido'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($fila['nombre'],   ENT_QUOTES, 'UTF-8'); ?></td>
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

<?php require_once 'includes/footer.php'; ?>
