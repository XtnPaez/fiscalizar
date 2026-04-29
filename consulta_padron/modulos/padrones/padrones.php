<?php
// modulos/padrones/padrones.php
// Modulo de padrones predefinidos del sistema Fiscalizar — Consulta Padron.
// Acceso: todos los niveles autenticados.
// Muestra una tabla de padrones disponibles con botones Ver y Descargar.
// Ver: muestra el padron paginado con buscador interno por apellido, nombre o DNI.
// Descargar: genera el Excel completo sin paginacion.

verificar_sesion();

require_once 'includes/excel.php';

// Definicion de padrones disponibles
// fuente_base: SELECT sin ORDER ni LIMIT — se le agregan condiciones dinamicamente
$padrones_disponibles = [
    'padron_cd_oficial'  => [
        'nombre'      => 'Padron CD oficial',
        'fuente_base' => 'SELECT dni, apellido, nombre, sigla AS carrera FROM padron_cd',
        'archivo'     => 'padron-cd-oficial',
    ],
    'padron_cp_oficial'  => [
        'nombre'      => 'Padron CP oficial',
        // auxiliar se excluye: no viene en el padron oficial de la facultad
        'fuente_base' => 'SELECT dni, apellido, nombre FROM padron_cp',
        'archivo'     => 'padron-cp-oficial',
    ],
    'padron_cd_completo' => [
        'nombre'      => 'Padron CD completo',
        'fuente_base' => 'SELECT * FROM vista_padron_cd',
        'archivo'     => 'padron-cd-completo',
    ],
    'padron_cp_completo' => [
        'nombre'      => 'Padron CP completo',
        'fuente_base' => 'SELECT * FROM vista_padron_cp',
        'archivo'     => 'padron-cp-completo',
    ],
];

// Leer parametros de la URL
$padron_key = $_GET['padron']  ?? '';
$accion     = $_GET['accion']  ?? '';
$busqueda   = trim($_GET['q']  ?? '');
$pagina     = max(1, intval($_GET['pagina'] ?? 1));
$por_pagina = 50;

$padron_activo   = null;
$resultados      = [];
$total_registros = 0;
$total_paginas   = 0;
$params          = [];

if ($padron_key !== '' && array_key_exists($padron_key, $padrones_disponibles)) {

    $padron_activo = $padrones_disponibles[$padron_key];
    $fuente        = $padron_activo['fuente_base'];

    // Construir condicion de busqueda interna
    $where = '';
    if ($busqueda !== '') {
        if (ctype_digit($busqueda)) {
            $where          = 'WHERE dni = :dni';
            $params[':dni'] = $busqueda;
        } else {
            $tokens = preg_split('/\s+/', strtoupper($busqueda));
            if (count($tokens) >= 2) {
                $where              = 'WHERE apellido LIKE :apellido AND nombre LIKE :nombre';
                $params[':apellido'] = $tokens[0] . '%';
                $params[':nombre']   = $tokens[1] . '%';
            } else {
                $where              = 'WHERE apellido LIKE :apellido';
                $params[':apellido'] = strtoupper($busqueda) . '%';
            }
        }
    }

    $sql_base     = "SELECT * FROM ($fuente) AS t $where";
    $sql_ordenado = "$sql_base ORDER BY apellido ASC, nombre ASC";

    if ($accion === 'descargar') {
        // Descarga sin paginacion — respeta el buscador si esta activo
        $stmt = $pdo->prepare($sql_ordenado);
        $stmt->execute($params);
        exportar_excel($stmt->fetchAll(), $padron_activo['archivo']);
        exit;
    }

    if ($accion === 'ver') {
        // Contar total
        $stmt            = $pdo->prepare("SELECT COUNT(*) FROM ($sql_base) AS c");
        $stmt->execute($params);
        $total_registros = (int) $stmt->fetchColumn();
        $total_paginas   = (int) ceil($total_registros / $por_pagina);
        $pagina          = min($pagina, max(1, $total_paginas));
        $offset          = ($pagina - 1) * $por_pagina;

        // Traer pagina actual
        $stmt = $pdo->prepare("$sql_ordenado LIMIT :limite OFFSET :offset");
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,     PDO::PARAM_INT);
        $stmt->execute();
        $resultados = $stmt->fetchAll();
    }
}

// URL base para paginacion y busqueda conservando parametros
$params_url = http_build_query(array_filter([
    'mod'    => 'padrones',
    'padron' => $padron_key,
    'accion' => 'ver',
    'q'      => $busqueda,
]));

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

<?php if ($padron_activo !== null && $accion === 'ver'): ?>

    <!-- Encabezado del padron activo -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <span class="modulo-titulo mb-0">
                <?php echo htmlspecialchars($padron_activo['nombre'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <span class="text-secondary ms-2" style="font-size:0.85rem;">
                <?php echo number_format($total_registros, 0, ',', '.'); ?> registros
            </span>
        </div>
        <a href="index.php?mod=padrones&padron=<?php echo $padron_key; ?>&accion=descargar&q=<?php echo urlencode($busqueda); ?>"
            class="btn btn-outline-secondary btn-sm">Descargar Excel</a>
    </div>

    <!-- Buscador interno del padron -->
    <form method="GET" action="index.php" class="mb-3">
        <input type="hidden" name="mod"    value="padrones">
        <input type="hidden" name="padron" value="<?php echo $padron_key; ?>">
        <input type="hidden" name="accion" value="ver">
        <div class="row g-2 align-items-center">
            <div class="col-md-5">
                <input
                    type="text"
                    name="q"
                    class="form-control form-control-sm"
                    placeholder="Buscar por apellido, apellido nombre o DNI"
                    value="<?php echo htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?>"
                >
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-acento btn-sm">Buscar</button>
            </div>
            <?php if ($busqueda !== ''): ?>
            <div class="col-auto">
                <a href="index.php?mod=padrones&padron=<?php echo $padron_key; ?>&accion=ver"
                    class="btn btn-outline-secondary btn-sm">Limpiar</a>
            </div>
            <?php endif; ?>
        </div>
    </form>

    <?php if (empty($resultados)): ?>
        <p class="text-secondary">No se encontraron resultados.</p>

    <?php else: ?>

        <!-- Scroll superior sincronizado -->
        <div id="scroll-top" style="overflow-x:auto; overflow-y:hidden; height:18px; margin-bottom:2px;">
            <div id="scroll-top-inner" style="height:1px;"></div>
        </div>

        <!-- Tabla de resultados -->
        <div id="scroll-tabla" class="table-responsive">
            <table class="table table-hover table-bordered align-middle" id="tabla-padron">
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
                        <td><?php echo htmlspecialchars($valor ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
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

<!-- Script scroll superior sincronizado -->
<script>
(function () {
    const scrollTop   = document.getElementById('scroll-top');
    const scrollTabla = document.getElementById('scroll-tabla');
    const inner       = document.getElementById('scroll-top-inner');

    if (!scrollTop || !scrollTabla || !inner) return;

    // Ajustar el ancho del div fantasma al ancho real de la tabla
    function ajustarAncho() {
        const tabla = document.getElementById('tabla-padron');
        if (tabla) inner.style.width = tabla.offsetWidth + 'px';
    }

    ajustarAncho();
    window.addEventListener('resize', ajustarAncho);

    // Sincronizar scroll en ambas direcciones
    scrollTop.addEventListener('scroll', function () {
        scrollTabla.scrollLeft = scrollTop.scrollLeft;
    });
    scrollTabla.addEventListener('scroll', function () {
        scrollTop.scrollLeft = scrollTabla.scrollLeft;
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
