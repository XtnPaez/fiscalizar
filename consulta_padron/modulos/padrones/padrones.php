<?php
// modulos/padrones/padrones.php
// Modulo de padrones predefinidos del sistema Fiscalizar — Consulta Padron.
// Acceso: todos los niveles autenticados.
// Muestra una tabla de padrones disponibles con botones Ver y Descargar.
// Ver: muestra el padron paginado debajo (50 registros por pagina).
// Descargar: genera el Excel completo sin paginacion.

verificar_sesion();

require_once 'includes/excel.php';

// Definicion de padrones disponibles
// fuente_ver: query usada para mostrar en pantalla
// fuente_descargar: query usada para exportar a Excel
// Separadas para controlar columnas visibles sin modificar las tablas
$padrones_disponibles = [
    'padron_cd_oficial'  => [
        'nombre'           => 'Padron CD oficial',
        'fuente_ver'       => 'SELECT dni, apellido, nombre, sigla AS carrera FROM padron_cd',
        'fuente_descargar' => 'SELECT dni, apellido, nombre, sigla AS carrera FROM padron_cd',
        'archivo'          => 'padron-cd-oficial',
    ],
    'padron_cp_oficial'  => [
        'nombre'           => 'Padron CP oficial',
        // auxiliar se excluye: no viene en el padron oficial de la facultad
        'fuente_ver'       => 'SELECT dni, apellido, nombre FROM padron_cp',
        'fuente_descargar' => 'SELECT dni, apellido, nombre FROM padron_cp',
        'archivo'          => 'padron-cp-oficial',
    ],
    'padron_cd_completo' => [
        'nombre'           => 'Padron CD completo',
        'fuente_ver'       => 'SELECT * FROM vista_padron_cd',
        'fuente_descargar' => 'SELECT * FROM vista_padron_cd',
        'archivo'          => 'padron-cd-completo',
    ],
    'padron_cp_completo' => [
        'nombre'           => 'Padron CP completo',
        'fuente_ver'       => 'SELECT * FROM vista_padron_cp',
        'fuente_descargar' => 'SELECT * FROM vista_padron_cp',
        'archivo'          => 'padron-cp-completo',
    ],
];

// Leer parametros de la URL
$padron_key = $_GET['padron'] ?? '';
$accion     = $_GET['accion'] ?? '';
$pagina     = max(1, intval($_GET['pagina'] ?? 1));
$por_pagina = 50;

$padron_activo   = null;
$resultados      = [];
$total_registros = 0;
$total_paginas   = 0;

// Procesar si se pidio un padron valido
if ($padron_key !== '' && array_key_exists($padron_key, $padrones_disponibles)) {

    $padron_activo = $padrones_disponibles[$padron_key];

    if ($accion === 'descargar') {

        // Exportar todo sin paginacion
        $stmt = $pdo->query($padron_activo['fuente_descargar'] . ' ORDER BY apellido ASC, nombre ASC');
        exportar_excel($stmt->fetchAll(), $padron_activo['archivo']);
        exit;

    } elseif ($accion === 'ver') {

        // Contar total para paginacion
        $sql_count       = "SELECT COUNT(*) FROM (" . $padron_activo['fuente_ver'] . ") AS t";
        $total_registros = (int) $pdo->query($sql_count)->fetchColumn();
        $total_paginas   = (int) ceil($total_registros / $por_pagina);
        $pagina          = min($pagina, max(1, $total_paginas));
        $offset          = ($pagina - 1) * $por_pagina;

        // Traer solo la pagina actual
        $sql_paginado = $padron_activo['fuente_ver'] . ' ORDER BY apellido ASC, nombre ASC LIMIT :limite OFFSET :offset';
        $stmt = $pdo->prepare($sql_paginado);
        $stmt->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,     PDO::PARAM_INT);
        $stmt->execute();
        $resultados = $stmt->fetchAll();
    }
}

require_once 'includes/navbar.php';
?>

<div class="modulo-titulo">Padrones</div>

<!-- Tabla de padrones disponibles -->
<div class="table-responsive mb-4">
    <table class="table table-hover table-bordered align-middle">
        <thead>
            <tr>
                <th>Padron</th>
                <th style="width:180px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($padrones_disponibles as $key => $padron): ?>
            <tr>
                <td><?php echo htmlspecialchars($padron['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <a href="index.php?mod=padrones&padron=<?php echo $key; ?>&accion=ver"
                        class="btn btn-sm btn-acento me-1">Ver</a>
                    <a href="index.php?mod=padrones&padron=<?php echo $key; ?>&accion=descargar"
                        class="btn btn-sm btn-outline-secondary">Descargar</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($padron_activo !== null && $accion === 'ver' && !empty($resultados)): ?>

    <!-- Encabezado del padron activo -->
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <span class="modulo-titulo mb-0">
                <?php echo htmlspecialchars($padron_activo['nombre'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <span class="text-secondary ms-2" style="font-size:0.85rem;">
                <?php echo number_format($total_registros, 0, ',', '.'); ?> registros
            </span>
        </div>
        <a href="index.php?mod=padrones&padron=<?php echo $padron_key; ?>&accion=descargar"
            class="btn btn-outline-secondary btn-sm">Descargar Excel</a>
    </div>

    <!-- Tabla de resultados -->
    <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle">
            <thead>
                <tr>
                    <?php foreach (array_keys($resultados[0]) as $col): ?>
                    <th><?php echo htmlspecialchars(strtoupper(str_replace('_', ' ', $col)), ENT_QUOTES, 'UTF-8'); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resultados as $fila): ?>
                <tr>
                    <?php foreach ($fila as $valor): ?>
                    <td>
                        <?php echo htmlspecialchars($valor ?? '—', ENT_QUOTES, 'UTF-8'); ?>
                    </td>
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
                <a class="page-link"
                    href="index.php?mod=padrones&padron=<?php echo $padron_key; ?>&accion=ver&pagina=<?php echo $pagina - 1; ?>">
                    Anterior
                </a>
            </li>

            <?php
            $rango_inicio = max(1, $pagina - 2);
            $rango_fin    = min($total_paginas, $pagina + 2);
            for ($p = $rango_inicio; $p <= $rango_fin; $p++):
            ?>
            <li class="page-item <?php echo $p === $pagina ? 'active' : ''; ?>">
                <a class="page-link"
                    href="index.php?mod=padrones&padron=<?php echo $padron_key; ?>&accion=ver&pagina=<?php echo $p; ?>"
                    style="<?php echo $p === $pagina ? 'background-color:#a6d900;border-color:#a6d900;color:#1a1a2e;' : ''; ?>">
                    <?php echo $p; ?>
                </a>
            </li>
            <?php endfor; ?>

            <li class="page-item <?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>">
                <a class="page-link"
                    href="index.php?mod=padrones&padron=<?php echo $padron_key; ?>&accion=ver&pagina=<?php echo $pagina + 1; ?>">
                    Siguiente
                </a>
            </li>

        </ul>
        <p class="text-center text-secondary" style="font-size:0.8rem;">
            Pagina <?php echo $pagina; ?> de <?php echo $total_paginas; ?>
        </p>
    </nav>
    <?php endif; ?>

<?php elseif ($padron_activo !== null && $accion === 'ver' && empty($resultados)): ?>
    <p class="text-secondary">El padron no tiene registros.</p>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
