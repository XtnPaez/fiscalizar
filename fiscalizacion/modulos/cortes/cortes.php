<?php
// fiscalizacion/modulos/cortes/cortes.php
// Modulo temporal de descarga de cortes por referente.
// Uso: dia de eleccion. Se elimina del sistema una vez cerrada la eleccion.
//
// Muestra los referentes cargados en referentes_cortes con checkboxes.
// El usuario tilda uno o mas referentes y presiona Descargar.
// Se genera un ZIP con un Excel por cada referente seleccionado.
// Cada Excel tiene dos hojas:
//   Hoja CD: referidos que no votaron en CD
//   Hoja CP: referidos que no votaron en CP y que no aparecen en la hoja CD
//
// Acceso: admin, superadmin.

verificar_admin_fiscal();

// Cargar referentes de la tabla referentes_cortes
// Se hace JOIN con referentes para obtener apellido y nombre
$referentes = $pdo->query("
    SELECT r.id, r.apellido, r.nombre
    FROM referentes_cortes rc
    JOIN referentes r ON rc.id_referente = r.id
    ORDER BY r.apellido, r.nombre
")->fetchAll();

require_once 'includes/navbar.php';
?>

<div class="modulo-titulo">Cortes por referente</div>

<?php if (empty($referentes)): ?>
    <p class="text-secondary" style="font-size:0.85rem;">
        No hay referentes cargados. Insertá registros en la tabla referentes_cortes.
    </p>
<?php else: ?>

<!-- Formulario: checkboxes de referentes + boton descarga -->
<!-- El POST va a cortes_descarga.php que genera el ZIP -->
<form method="POST" action="index.php?mod=cortes_descarga">

    <div class="mb-3">
        <!-- Boton para tildar/destildar todos -->
        <button type="button" class="btn btn-sm btn-outline-secondary mb-3"
                id="btn-todos">
            Seleccionar todos
        </button>
    </div>

    <!-- Lista de referentes con checkboxes -->
    <div class="row g-2 mb-4">
        <?php foreach ($referentes as $r): ?>
        <div class="col-12 col-md-6">
            <div class="form-check">
                <input class="form-check-input check-referente"
                       type="checkbox"
                       name="ids[]"
                       value="<?php echo intval($r['id']); ?>"
                       id="ref_<?php echo intval($r['id']); ?>">
                <label class="form-check-label" for="ref_<?php echo intval($r['id']); ?>"
                       style="font-size:0.9rem;">
                    <?php echo htmlspecialchars($r['apellido'] . ', ' . $r['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                </label>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <button type="submit" class="btn btn-primary btn-sm">
        ⬇ Descargar ZIP
    </button>

</form>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>

<script>
// Boton seleccionar/deseleccionar todos
(function () {
    const btn    = document.getElementById('btn-todos');
    const checks = document.querySelectorAll('.check-referente');
    let   todos  = false;

    if (!btn) return;

    btn.addEventListener('click', function () {
        todos = !todos;
        checks.forEach(c => c.checked = todos);
        btn.textContent = todos ? 'Deseleccionar todos' : 'Seleccionar todos';
    });
})();
</script>
