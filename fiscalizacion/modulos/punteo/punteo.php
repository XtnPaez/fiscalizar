<?php
// fiscalizacion/modulos/punteo/punteo.php
// Modulo de punteo del sistema Fiscalizar — Fiscalizacion.
// Acceso: admin, superadmin.
//
// Registra y muestra el punteo de nuestra lista por corte y por mesa.
// Un corte = cada vez que los fiscales entran al cuarto oscuro a reponer boletas.
// Normalmente cada 20 votantes, pero el numero real de votantes se ingresa
// en cada corte porque puede variar.
//
// Dos secciones:
//
// Seccion 1 — Carga por mesa
//   Elegir eleccion activa → elegir mesa → ver cortes + cargar/editar
//
// Seccion 2 — Vista consolidada por eleccion
//   Tabla por mesa: MESA | CORTES | VOTANTES | FALTANTES | %
//   Fila TOTAL sumando todas las mesas
//   Proyecciones: × 0.90 | × 0.85 | × input (calculado con JS sin recargar)

verificar_admin_fiscal();

// ============================================================
// PROCESAMIENTO DE ACCIONES POST
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // --- Cargar nuevo corte ---
    // El numero de corte se asigna automaticamente: MAX + 1 para esa mesa
    if ($accion === 'nuevo_corte') {
        $id_mesa   = intval($_POST['id_mesa']   ?? 0);
        $votantes  = intval($_POST['votantes']   ?? 0);
        $faltantes = intval($_POST['faltantes']  ?? 0);

        if ($id_mesa > 0 && $votantes > 0 && $faltantes >= 0) {
            // Obtener el proximo numero de corte para esta mesa
            $stmt = $pdo->prepare("
                SELECT COALESCE(MAX(numero_corte), 0) + 1
                FROM punteo
                WHERE id_mesa = ?
            ");
            $stmt->execute([$id_mesa]);
            $numero_corte = $stmt->fetchColumn();

            $pdo->prepare("
                INSERT INTO punteo (id_mesa, numero_corte, votantes, faltantes)
                VALUES (?, ?, ?, ?)
            ")->execute([$id_mesa, $numero_corte, $votantes, $faltantes]);
        }

        // Redirigir manteniendo la seleccion actual
        $id_eleccion = intval($_POST['id_eleccion'] ?? 0);
        header("Location: index.php?mod=punteo&id_eleccion=$id_eleccion&id_mesa=$id_mesa&ok=corte_cargado");
        exit;
    }

    // --- Editar corte existente ---
    if ($accion === 'editar_corte') {
        $id_punteo = intval($_POST['id_punteo']  ?? 0);
        $id_mesa   = intval($_POST['id_mesa']    ?? 0);
        $votantes  = intval($_POST['votantes']   ?? 0);
        $faltantes = intval($_POST['faltantes']  ?? 0);

        if ($id_punteo > 0 && $votantes > 0 && $faltantes >= 0) {
            $pdo->prepare("
                UPDATE punteo
                SET votantes = ?, faltantes = ?
                WHERE id = ?
            ")->execute([$votantes, $faltantes, $id_punteo]);
        }

        $id_eleccion = intval($_POST['id_eleccion'] ?? 0);
        header("Location: index.php?mod=punteo&id_eleccion=$id_eleccion&id_mesa=$id_mesa&ok=corte_editado");
        exit;
    }
}

// ============================================================
// CARGA DE DATOS
// ============================================================

$ok          = $_GET['ok']          ?? '';
$id_eleccion = intval($_GET['id_eleccion'] ?? 0);
$id_mesa     = intval($_GET['id_mesa']     ?? 0);

$mensajes_ok = [
    'corte_cargado' => 'Corte registrado correctamente.',
    'corte_editado' => 'Corte actualizado correctamente.',
];

// Elecciones activas para el combo
$elecciones_activas = $pdo->query("
    SELECT id, nombre, tipo, anio
    FROM elecciones
    WHERE estado = 'activa'
    ORDER BY tipo ASC, anio ASC
")->fetchAll();

foreach ($elecciones_activas as &$e) {
    $e['id'] = intval($e['id']);
}
unset($e);

// Eleccion seleccionada
$eleccion_sel = null;
if ($id_eleccion > 0) {
    $stmt = $pdo->prepare("
        SELECT id, nombre, tipo, anio
        FROM elecciones
        WHERE id = ? AND estado = 'activa'
    ");
    $stmt->execute([$id_eleccion]);
    $eleccion_sel = $stmt->fetch() ?: null;
}

// Mesas de la eleccion seleccionada
$mesas = [];
if ($eleccion_sel) {
    $stmt = $pdo->prepare("
        SELECT m.id, m.nombre
        FROM mesas m
        JOIN dias_eleccion d ON m.id_dia = d.id
        WHERE d.id_eleccion = ?
        ORDER BY m.nombre ASC
    ");
    $stmt->execute([$id_eleccion]);
    $mesas = $stmt->fetchAll();

    foreach ($mesas as &$m) {
        $m['id'] = intval($m['id']);
    }
    unset($m);
}

// Mesa seleccionada y sus cortes
$mesa_sel = null;
$cortes   = [];

if ($id_mesa > 0 && $eleccion_sel) {
    $stmt = $pdo->prepare("
        SELECT m.id, m.nombre
        FROM mesas m
        JOIN dias_eleccion d ON m.id_dia = d.id
        WHERE m.id = ? AND d.id_eleccion = ?
    ");
    $stmt->execute([$id_mesa, $id_eleccion]);
    $mesa_sel = $stmt->fetch() ?: null;

    if ($mesa_sel) {
        $stmt = $pdo->prepare("
            SELECT id, numero_corte, votantes, faltantes
            FROM punteo
            WHERE id_mesa = ?
            ORDER BY numero_corte ASC
        ");
        $stmt->execute([$id_mesa]);
        $cortes = $stmt->fetchAll();
    }
}

// Acumulados de la mesa seleccionada
$total_votantes_mesa  = array_sum(array_column($cortes, 'votantes'));
$total_faltantes_mesa = array_sum(array_column($cortes, 'faltantes'));
$porcentaje_mesa      = $total_votantes_mesa > 0
    ? round($total_faltantes_mesa / $total_votantes_mesa * 100, 1)
    : 0;

// Vista consolidada por eleccion: acumulado por mesa
$consolidado = [];
if ($eleccion_sel) {
    $stmt = $pdo->prepare("
        SELECT
            m.id,
            m.nombre AS mesa,
            COUNT(p.id)       AS cortes,
            SUM(p.votantes)   AS votantes,
            SUM(p.faltantes)  AS faltantes
        FROM mesas m
        JOIN dias_eleccion d ON m.id_dia = d.id
        LEFT JOIN punteo p   ON p.id_mesa = m.id
        WHERE d.id_eleccion = ?
        GROUP BY m.id, m.nombre
        ORDER BY m.nombre ASC
    ");
    $stmt->execute([$id_eleccion]);
    $consolidado = $stmt->fetchAll();
}

// Totales del consolidado
$total_cortes     = array_sum(array_column($consolidado, 'cortes'));
$total_votantes   = array_sum(array_column($consolidado, 'votantes'));
$total_faltantes  = array_sum(array_column($consolidado, 'faltantes'));
$porcentaje_total = $total_votantes > 0
    ? round($total_faltantes / $total_votantes * 100, 1)
    : 0;

require_once 'includes/navbar.php';
?>

<div class="modulo-titulo">Punteo</div>

<?php if ($ok && isset($mensajes_ok[$ok])): ?>
    <div class="alert alert-success py-2 mb-3" style="font-size:0.85rem;">
        <?php echo htmlspecialchars($mensajes_ok[$ok], ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if (empty($elecciones_activas)): ?>
    <p class="text-secondary" style="font-size:0.85rem;">
        No hay elecciones activas. Activá una desde Administración → Elecciones.
    </p>
<?php else: ?>

<!-- Selector de eleccion — form propio para no perder id_eleccion al cambiar mesa -->
<form method="GET" action="index.php" class="mb-3">
    <input type="hidden" name="mod" value="punteo">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label form-label-sm">Elección</label>
            <select name="id_eleccion" class="form-select form-select-sm"
                    onchange="this.form.submit()">
                <option value="">— elegir —</option>
                <?php foreach ($elecciones_activas as $e): ?>
                <option value="<?php echo $e['id']; ?>"
                    <?php echo $id_eleccion === $e['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($e['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</form>

<?php if ($eleccion_sel): ?>

<!-- ============================================================ -->
<!-- SECCION 1 — CARGA POR MESA                                   -->
<!-- ============================================================ -->

<div class="modulo-subtitulo mb-2">Carga por mesa</div>

<!-- Selector de mesa — form propio con id_eleccion como hidden -->
<form method="GET" action="index.php" class="mb-3">
    <input type="hidden" name="mod" value="punteo">
    <input type="hidden" name="id_eleccion" value="<?php echo $id_eleccion; ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label form-label-sm">Mesa</label>
            <select name="id_mesa" class="form-select form-select-sm"
                    onchange="this.form.submit()">
                <option value="">— elegir mesa —</option>
                <?php foreach ($mesas as $m): ?>
                <option value="<?php echo $m['id']; ?>"
                    <?php echo $id_mesa === $m['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</form>

<?php if ($mesa_sel): ?>

<!-- Cortes cargados para esta mesa -->
<?php if (!empty($cortes)): ?>
<div class="table-responsive mb-3">
    <table class="table table-bordered align-middle" style="font-size:0.85rem;">
        <thead>
            <tr>
                <th>Corte</th>
                <th>Votantes</th>
                <th>Faltantes</th>
                <th>% corte</th>
                <th>Editar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cortes as $c): ?>
            <?php
                $pct_corte = $c['votantes'] > 0
                    ? round($c['faltantes'] / $c['votantes'] * 100, 1)
                    : 0;
            ?>
            <tr>
                <td><?php echo $c['numero_corte']; ?></td>
                <td><?php echo $c['votantes']; ?></td>
                <td><?php echo $c['faltantes']; ?></td>
                <td><?php echo $pct_corte; ?>%</td>
                <td>
                    <!-- Editar inline via modal -->
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal"
                            data-bs-target="#modalEditar<?php echo $c['id']; ?>">
                        Editar
                    </button>
                </td>
            </tr>

            <!-- Modal editar corte -->
            <div class="modal fade" id="modalEditar<?php echo $c['id']; ?>"
                 tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h6 class="modal-title">Corte <?php echo $c['numero_corte']; ?></h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" action="index.php?mod=punteo">
                            <input type="hidden" name="accion" value="editar_corte">
                            <input type="hidden" name="id_punteo" value="<?php echo $c['id']; ?>">
                            <input type="hidden" name="id_mesa" value="<?php echo $id_mesa; ?>">
                            <input type="hidden" name="id_eleccion" value="<?php echo $id_eleccion; ?>">
                            <div class="modal-body">
                                <div class="mb-2">
                                    <label class="form-label form-label-sm">Votantes</label>
                                    <input type="number" name="votantes"
                                           class="form-control form-control-sm"
                                           value="<?php echo $c['votantes']; ?>"
                                           min="1" required>
                                </div>
                                <div>
                                    <label class="form-label form-label-sm">Faltantes</label>
                                    <input type="number" name="faltantes"
                                           class="form-control form-control-sm"
                                           value="<?php echo $c['faltantes']; ?>"
                                           min="0" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="fw-semibold" style="background:#f0f2f5;">
                <td>TOTAL</td>
                <td><?php echo $total_votantes_mesa; ?></td>
                <td><?php echo $total_faltantes_mesa; ?></td>
                <td><?php echo $porcentaje_mesa; ?>%</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php else: ?>
    <p class="text-secondary mb-3" style="font-size:0.85rem;">
        No hay cortes cargados para esta mesa todavía.
    </p>
<?php endif; ?>

<!-- Formulario nuevo corte -->
<div class="modulo-subtitulo mb-2">
    Nuevo corte
    <?php if (!empty($cortes)): ?>
        <span class="text-secondary fw-normal" style="font-size:0.82rem;">
            — Corte <?php echo count($cortes) + 1; ?>
        </span>
    <?php endif; ?>
</div>

<form method="POST" action="index.php?mod=punteo" class="row g-2 align-items-end mb-4">
    <input type="hidden" name="accion" value="nuevo_corte">
    <input type="hidden" name="id_mesa" value="<?php echo $id_mesa; ?>">
    <input type="hidden" name="id_eleccion" value="<?php echo $id_eleccion; ?>">

    <div class="col-md-2">
        <label class="form-label form-label-sm">Votantes</label>
        <input type="number" name="votantes" class="form-control form-control-sm"
               value="20" min="1" required>
    </div>

    <div class="col-md-2">
        <label class="form-label form-label-sm">Faltantes</label>
        <input type="number" name="faltantes" class="form-control form-control-sm"
               value="0" min="0" required>
    </div>

    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Registrar corte</button>
    </div>
</form>

<?php endif; // fin if mesa_sel ?>

<hr class="my-4">

<!-- ============================================================ -->
<!-- SECCION 2 — VISTA CONSOLIDADA POR ELECCION                   -->
<!-- ============================================================ -->

<div class="modulo-subtitulo mb-2">
    Consolidado — <?php echo htmlspecialchars($eleccion_sel['nombre'], ENT_QUOTES, 'UTF-8'); ?>
</div>

<?php if (empty($consolidado) || $total_cortes == 0): ?>
    <p class="text-secondary" style="font-size:0.85rem;">
        No hay cortes cargados para esta elección todavía.
    </p>
<?php else: ?>

<div class="table-responsive mb-3">
    <table class="table table-bordered align-middle" style="font-size:0.85rem;">
        <thead>
            <tr>
                <th>Mesa</th>
                <th>Cortes</th>
                <th>Votantes</th>
                <th>Faltantes</th>
                <th>%</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($consolidado as $fila): ?>
            <?php
                $pct = ($fila['votantes'] > 0)
                    ? round($fila['faltantes'] / $fila['votantes'] * 100, 1)
                    : 0;
            ?>
            <tr>
                <td><?php echo htmlspecialchars($fila['mesa'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo intval($fila['cortes']); ?></td>
                <td><?php echo intval($fila['votantes']); ?></td>
                <td><?php echo intval($fila['faltantes']); ?></td>
                <td><?php echo $pct; ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="fw-semibold" style="background:#f0f2f5;">
                <td>TOTAL</td>
                <td><?php echo $total_cortes; ?></td>
                <td><?php echo $total_votantes; ?></td>
                <td><?php echo $total_faltantes; ?></td>
                <td><?php echo $porcentaje_total; ?>%</td>
            </tr>
        </tfoot>
    </table>
</div>

<!-- Proyecciones sobre el total de faltantes -->
<?php if ($total_faltantes > 0): ?>
<div class="modulo-subtitulo mb-2">Proyecciones</div>

<div class="table-responsive mb-3">
    <table class="table table-bordered align-middle" style="font-size:0.85rem;max-width:500px;">
        <thead>
            <tr>
                <th>Factor</th>
                <th>Resultado estimado</th>
                <th>% estimado</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>× 0.90</td>
                <td><?php echo round($total_faltantes * 0.90, 1); ?></td>
                <td><?php echo $total_votantes > 0 ? round(($total_faltantes * 0.90) / $total_votantes * 100, 1) : 0; ?>%</td>
            </tr>
            <tr>
                <td>× 0.85</td>
                <td><?php echo round($total_faltantes * 0.85, 1); ?></td>
                <td><?php echo $total_votantes > 0 ? round(($total_faltantes * 0.85) / $total_votantes * 100, 1) : 0; ?>%</td>
            </tr>
            <tr>
                <!-- Factor variable: calculado con JS sin recargar la pagina -->
                <td>
                    ×
                    <input type="number" id="factor_custom"
                           class="form-control form-control-sm d-inline-block"
                           style="width:80px;"
                           value="0.80" min="0" max="1" step="0.01">
                </td>
                <td>
                    <span id="resultado_custom">
                        <?php echo round($total_faltantes * 0.80, 1); ?>
                    </span>
                </td>
                <td>
                    <span id="porcentaje_custom">
                        <?php echo $total_votantes > 0 ? round(($total_faltantes * 0.80) / $total_votantes * 100, 1) : 0; ?>%
                    </span>
                </td>
            </tr>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php endif; // fin if consolidado ?>

<?php endif; // fin if eleccion_sel ?>
<?php endif; // fin if elecciones_activas ?>

<?php require_once 'includes/footer.php'; ?>

<!-- JS para el factor variable — sin recargar la pagina -->
<script>
(function () {
    const input     = document.getElementById('factor_custom');
    const resultado = document.getElementById('resultado_custom');
    const total     = <?php echo $total_faltantes; ?>;

    if (!input || !resultado) return;

    const porcentaje = document.getElementById('porcentaje_custom');
    const votantes   = <?php echo $total_votantes; ?>;

    input.addEventListener('input', function () {
        const factor = parseFloat(this.value);
        if (!isNaN(factor) && factor >= 0) {
            const res = total * factor;
            resultado.textContent  = res.toFixed(1);
            porcentaje.textContent = votantes > 0
                ? (res / votantes * 100).toFixed(1) + '%'
                : '0%';
        }
    });
})();
</script>
