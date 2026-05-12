<?php
// fiscalizacion/modulos/dashboard/dashboard.php
// Dashboard del sistema Fiscalizar — Fiscalizacion.
// Muestra estado de mesas activas y conteo de votos por eleccion.
// Acceso: admin, superadmin.
//
// Cambios respecto a la version anterior:
//   - mesas ya no tiene id_eleccion: la eleccion se obtiene via id_dia -> dias_eleccion -> elecciones
//   - votos_dia ya no tiene id_eleccion: el conteo se hace via id_mesa -> dias_eleccion -> elecciones
//   - La habilitacion de mesas viene de dias_eleccion.habilitado, no de mesas.habilitada
//   - Solo superadmin ve el estado de mesas. Admin solo ve el conteo de votos.

verificar_admin_fiscal();

// --- Procesar liberacion de mesa (solo superadmin) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_mesa'])) {
    verificar_superadmin_fiscal();
    $id_mesa = intval($_POST['id_mesa'] ?? 0);
    if ($id_mesa > 0) {
        $pdo->prepare("UPDATE mesas SET en_uso = 0 WHERE id = ?")
            ->execute([$id_mesa]);
    }
    header('Location: index.php?mod=dashboard');
    exit;
}

// --- Cargar estado de mesas (solo para superadmin) ---
// Solo mesas de elecciones activas, con su dia y eleccion via JOIN
$mesas = [];
if ($_SESSION['rol'] === 'superadmin') {
    $stmt_mesas = $pdo->query("
        SELECT m.id, m.nombre, m.tipo, m.en_uso,
               d.nombre AS dia, d.habilitado AS dia_habilitado,
               e.nombre AS eleccion, e.estado AS eleccion_estado
        FROM mesas m
        JOIN dias_eleccion d  ON m.id_dia = d.id
        JOIN elecciones e     ON d.id_eleccion = e.id
        WHERE e.estado = 'activa'
        ORDER BY e.tipo ASC, d.id ASC, m.nombre ASC
    ");
    $mesas = $stmt_mesas->fetchAll();
}

// --- Conteo de votos por eleccion activa ---
// Cuenta votos en votos_dia cruzando por mesa -> dia -> eleccion
// Solo elecciones con estado = 'activa'
$stmt_votos = $pdo->query("
    SELECT e.id, e.nombre AS eleccion, e.tipo,
           COUNT(v.id) AS total_votos
    FROM elecciones e
    JOIN dias_eleccion d  ON d.id_eleccion = e.id
    JOIN mesas m          ON m.id_dia = d.id
    LEFT JOIN votos_dia v ON v.id_mesa = m.id
    WHERE e.estado = 'activa'
    GROUP BY e.id, e.nombre, e.tipo
    ORDER BY e.tipo ASC, e.anio ASC
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

<!-- ============================================================ -->
<!-- ESTADO DE MESAS — solo superadmin                            -->
<!-- ============================================================ -->
<?php if ($_SESSION['rol'] === 'superadmin'): ?>

<div class="modulo-subtitulo mb-2">Estado de mesas</div>

<?php if (empty($mesas)): ?>
    <p class="text-secondary mb-4" style="font-size:0.85rem;">
        No hay mesas activas. Activá una elección desde Administración → Elecciones.
    </p>
<?php else: ?>
<div class="table-responsive mb-4">
    <table class="table table-hover table-bordered align-middle" style="font-size:0.85rem;">
        <thead>
            <tr>
                <th>Mesa</th>
                <th>Elección</th>
                <th>Día</th>
                <th>Día habilitado</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($mesas as $m): ?>
            <tr>
                <td><?php echo htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <span class="badge" style="background-color:#1a1a2e;color:#fff;">
                        <?php echo strtoupper($m['tipo']); ?>
                    </span>
                    <?php echo htmlspecialchars($m['eleccion'], ENT_QUOTES, 'UTF-8'); ?>
                </td>
                <td><?php echo htmlspecialchars($m['dia'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <?php if ($m['dia_habilitado']): ?>
                        <span class="badge bg-success">SI</span>
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
<?php endif; ?>

<?php endif; // fin bloque superadmin ?>

<!-- ============================================================ -->
<!-- CONTEO DE VOTOS — admin y superadmin                         -->
<!-- ============================================================ -->
<?php if (!empty($conteo_votos)): ?>
<div class="modulo-subtitulo mb-2">Votos registrados</div>
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
<?php elseif ($_SESSION['rol'] !== 'superadmin'): ?>
    <p class="text-secondary" style="font-size:0.85rem;">
        No hay elecciones activas con votos registrados.
    </p>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
