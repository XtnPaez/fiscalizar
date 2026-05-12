<?php
// fiscalizacion/modulos/observados/observados.php
// Modulo de votos observados del sistema Fiscalizar — Fiscalizacion.
// Acceso: admin, superadmin.
//
// Muestra todos los votos registrados como 'observado' en votos_dia,
// de todas las elecciones activas juntas.
// Columnas: ELECCION | MESA | DNI | APELLIDO | NOMBRE
// Descargable en Excel. Sin filtros — los observados son pocos
// y el admin necesita verlos todos de un vistazo para el escrutinio.

verificar_admin_fiscal();

require_once 'includes/excel.php';

// ============================================================
// EXPORTACION EXCEL — antes de cualquier output HTML
// ============================================================

if (isset($_GET['export'])) {
    $resultado_export = obtener_observados($pdo);
    exportar_excel($resultado_export, 'votos-observados');
    exit;
}

// ============================================================
// FUNCION DE CONSULTA
// ============================================================

// obtener_observados()
// Devuelve todos los votos observados de elecciones activas.
// Cruza votos_dia -> mesas -> dias_eleccion -> elecciones -> personas.
// Ordenado por eleccion, mesa, apellido.
function obtener_observados(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT
            e.nombre  AS eleccion,
            m.nombre  AS mesa,
            p.dni,
            p.apellido,
            p.nombre
        FROM votos_dia vd
        JOIN mesas m          ON vd.id_mesa = m.id
        JOIN dias_eleccion d  ON m.id_dia = d.id
        JOIN elecciones e     ON d.id_eleccion = e.id
        JOIN personas p       ON vd.dni = p.dni
        WHERE vd.tipo_voto = 'observado'
          AND e.estado = 'activa'
        ORDER BY e.nombre ASC, m.nombre ASC, p.apellido ASC, p.nombre ASC
    ");
    return $stmt->fetchAll();
}

// ============================================================
// CARGA DE DATOS
// ============================================================

$observados = obtener_observados($pdo);

require_once 'includes/navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="modulo-titulo">Votos Observados</div>
    <?php if (!empty($observados)): ?>
    <a href="index.php?mod=observados&export=1"
       class="btn btn-sm btn-outline-secondary">
        Descargar Excel
    </a>
    <?php endif; ?>
</div>

<p class="text-secondary mb-3" style="font-size:0.82rem;">
    Votos registrados como observados en todas las elecciones activas.
    Usar este listado durante el escrutinio para resolver cada caso.
</p>

<?php if (empty($observados)): ?>
    <p class="text-secondary" style="font-size:0.85rem;">
        No hay votos observados registrados en este momento.
    </p>
<?php else: ?>

<div class="text-secondary mb-2" style="font-size:0.82rem;">
    <?php echo count($observados); ?> voto<?php echo count($observados) !== 1 ? 's' : ''; ?> observado<?php echo count($observados) !== 1 ? 's' : ''; ?>
</div>

<div class="table-responsive">
    <table class="table table-hover table-bordered align-middle" style="font-size:0.85rem;">
        <thead>
            <tr>
                <th>Elección</th>
                <th>Mesa</th>
                <th>DNI</th>
                <th>Apellido</th>
                <th>Nombre</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($observados as $fila): ?>
            <tr>
                <td><?php echo htmlspecialchars($fila['eleccion'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($fila['mesa'],     ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($fila['dni'],      ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($fila['apellido'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($fila['nombre'],   ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
