<?php
// fiscalizacion/modulos/dashboard/dashboard.php
// Dashboard del sistema Fiscalizar — Fiscalizacion.
// Muestra estado de mesas en tiempo real y conteo de votos por eleccion.
// El admin puede resetear mesas desde aqui (liberar en_uso = 0).
// Acceso: admin, superadmin.

verificar_admin_fiscal();

// --- Procesar reset de mesa ---
// El admin puede liberar una mesa en uso desde el dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_mesa'])) {
    $id_mesa = intval($_POST['id_mesa'] ?? 0);
    if ($id_mesa > 0) {
        $pdo->prepare("UPDATE mesas SET en_uso = 0 WHERE id = ?")
            ->execute([$id_mesa]);
    }
    header('Location: index.php?mod=dashboard');
    exit;
}

// --- Cargar estado de mesas ---
$stmt_mesas = $pdo->query("
    SELECT m.id, m.nombre, m.tipo, m.habilitada, m.en_uso, m.activa,
           e.nombre AS eleccion
    FROM mesas m
    JOIN elecciones e ON m.id_eleccion = e.id
    ORDER BY m.tipo, m.nombre
");
$mesas = $stmt_mesas->fetchAll();

// --- Conteo de votos — solo elecciones con mesas habilitadas ---
// No mostramos historico, solo lo que tiene actividad hoy
$stmt_votos = $pdo->query("
    SELECT e.nombre AS eleccion, e.tipo, COUNT(v.id) AS total_votos
    FROM elecciones e
    JOIN mesas m ON e.id = m.id_eleccion AND m.habilitada = 1
    LEFT JOIN votos_dia v ON e.id = v.id_eleccion
    GROUP BY e.id, e.nombre, e.tipo
    ORDER BY e.tipo, e.anio
");
$conteo_votos = $stmt_votos->fetchAll();

require_once 'includes/navbar.php';
?>

<div class="modulo-titulo">Dashboard</div>

<?php if (isset($_GET['error']) && $_GET['error'] === 'acceso_denegado'): ?>
    <div class="alert alert-danger py-2 mb-3" style="font-size:0.85rem;">
        No tenés permiso para acceder a esa sección.
    </div>
<?php endif; ?>

<!-- Estado de mesas -->
<div class="modulo-titulo" style="font-size:0.95rem;">Estado de mesas</div>

<div class="table-responsive mb-4">
    <table class="table table-hover table-bordered align-middle" style="font-size:0.85rem;">
        <thead>
            <tr>
                <th>Mesa</th>
                <th>Elección</th>
                <th>Tipo</th>
                <th>Habilitada</th>
                <th>Estado</th>
                <th>Activa</th>
                <?php if ($_SESSION['rol'] === 'superadmin' || $_SESSION['rol'] === 'admin'): ?>
                <th>Acciones</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($mesas as $m): ?>
            <tr>
                <td><?php echo htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($m['eleccion'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <span class="badge" style="background-color:#1a1a2e;color:#fff;">
                        <?php echo strtoupper($m['tipo']); ?>
                    </span>
                </td>
                <td>
                    <?php if ($m['habilitada']): ?>
                        <span class="badge" style="background-color:#a6d900;color:#1a1a2e;">SI</span>
                    <?php else: ?>
                        <span class="text-secondary">NO</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($m['en_uso']): ?>
                        <span class="badge bg-success">EN USO</span>
                    <?php else: ?>
                        <span class="text-secondary">Libre</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($m['activa']): ?>
                        <span class="badge" style="background-color:#a6d900;color:#1a1a2e;">SI</span>
                    <?php else: ?>
                        <span class="text-secondary">NO</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($m['en_uso']): ?>
                        <form method="POST" action="index.php?mod=dashboard"
                            onsubmit="return confirm('¿Liberar la mesa <?php echo htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8'); ?>? El fiscal activo perderá su sesión.');">
                            <input type="hidden" name="reset_mesa" value="1">
                            <input type="hidden" name="id_mesa" value="<?php echo $m['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Liberar</button>
                        </form>
                    <?php else: ?>
                        <span class="text-secondary">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Conteo de votos por eleccion — solo elecciones con mesas habilitadas -->
<?php if (!empty($conteo_votos)): ?>
<div class="modulo-titulo" style="font-size:0.95rem;">Votos registrados</div>
<div class="row g-3 mb-4">
    <?php foreach ($conteo_votos as $c): ?>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div style="font-size:2rem;font-weight:700;color:#1a1a2e;">
                    <?php echo number_format($c['total_votos'], 0, ',', '.'); ?>
                </div>
                <div class="text-secondary" style="font-size:0.8rem;">
                    <?php echo htmlspecialchars($c['eleccion'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <span class="badge mt-1" style="background-color:#1a1a2e;color:#fff;font-size:0.7rem;">
                    <?php echo strtoupper($c['tipo']); ?>
                </span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
