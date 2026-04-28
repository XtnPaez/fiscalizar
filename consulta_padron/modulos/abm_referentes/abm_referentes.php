<?php
// modulos/abm_referentes/abm_referentes.php
// ABM de referentes politicos.
// Acceso: admin y superadmin.
// Listado con opciones editar y dar de baja logica.
// Formulario para agregar nuevo referente.
// Nunca se elimina un registro fisicamente.

verificar_admin();

$mensaje = '';
$error   = '';

// --- Procesar acciones POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar') {

        $apellido   = trim($_POST['apellido']   ?? '');
        $nombre     = trim($_POST['nombre']     ?? '');
        $aplica_cd  = isset($_POST['aplica_cd']) ? 1 : 0;
        $aplica_cp  = isset($_POST['aplica_cp']) ? 1 : 0;

        if ($apellido === '' || $nombre === '') {
            $error = 'Apellido y nombre son obligatorios.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO referentes (apellido, nombre, aplica_cd, aplica_cp, activo)
                VALUES (:apellido, :nombre, :aplica_cd, :aplica_cp, 1)
            ");
            $stmt->execute([
                ':apellido'  => strtoupper($apellido),
                ':nombre'    => strtoupper($nombre),
                ':aplica_cd' => $aplica_cd,
                ':aplica_cp' => $aplica_cp,
            ]);
            $mensaje = 'Referente agregado correctamente.';
        }

    } elseif ($accion === 'editar') {

        $id        = intval($_POST['id']        ?? 0);
        $apellido  = trim($_POST['apellido']    ?? '');
        $nombre    = trim($_POST['nombre']      ?? '');
        $aplica_cd = isset($_POST['aplica_cd']) ? 1 : 0;
        $aplica_cp = isset($_POST['aplica_cp']) ? 1 : 0;

        if ($id <= 0 || $apellido === '' || $nombre === '') {
            $error = 'Datos incompletos para editar.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE referentes
                SET apellido = :apellido, nombre = :nombre,
                    aplica_cd = :aplica_cd, aplica_cp = :aplica_cp
                WHERE id = :id
            ");
            $stmt->execute([
                ':apellido'  => strtoupper($apellido),
                ':nombre'    => strtoupper($nombre),
                ':aplica_cd' => $aplica_cd,
                ':aplica_cp' => $aplica_cp,
                ':id'        => $id,
            ]);
            $mensaje = 'Referente actualizado correctamente.';
        }

    } elseif ($accion === 'baja') {

        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE referentes SET activo = 0 WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $mensaje = 'Referente dado de baja.';
        }

    } elseif ($accion === 'alta') {

        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE referentes SET activo = 1 WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $mensaje = 'Referente reactivado.';
        }
    }
}

// --- Cargar referente a editar si se pidio ---
$editando = null;
$id_editar = intval($_GET['editar'] ?? 0);
if ($id_editar > 0) {
    $stmt = $pdo->prepare("SELECT * FROM referentes WHERE id = :id");
    $stmt->execute([':id' => $id_editar]);
    $editando = $stmt->fetch();
}

// --- Listar todos los referentes ---
$stmt       = $pdo->query("SELECT * FROM referentes ORDER BY apellido ASC, nombre ASC");
$referentes = $stmt->fetchAll();

require_once 'includes/navbar.php';
?>

<div class="modulo-titulo">Referentes</div>

<?php if ($mensaje !== ''): ?>
    <div class="alert alert-success alerta-acceso"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger alerta-acceso"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<!-- Formulario agregar / editar -->
<div class="card mb-4" style="border-color: #d1d5db;">
    <div class="card-body">
        <h6 class="card-title" style="font-weight:600; color:#1a1a2e;">
            <?php echo $editando ? 'Editar referente' : 'Agregar referente'; ?>
        </h6>

        <form method="POST" action="index.php?mod=abm_referentes">
            <input type="hidden" name="accion" value="<?php echo $editando ? 'editar' : 'agregar'; ?>">
            <?php if ($editando): ?>
                <input type="hidden" name="id" value="<?php echo $editando['id']; ?>">
            <?php endif; ?>

            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label" style="font-size:0.8rem;">Apellido</label>
                    <input type="text" name="apellido" class="form-control form-control-sm"
                        value="<?php echo htmlspecialchars($editando['apellido'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" style="font-size:0.8rem;">Nombre</label>
                    <input type="text" name="nombre" class="form-control form-control-sm"
                        value="<?php echo htmlspecialchars($editando['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-auto">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="aplica_cd" id="aplica_cd" value="1"
                            <?php echo (!$editando || $editando['aplica_cd']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="aplica_cd" style="font-size:0.85rem;">CD</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="aplica_cp" id="aplica_cp" value="1"
                            <?php echo (!$editando || $editando['aplica_cp']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="aplica_cp" style="font-size:0.85rem;">CP</label>
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-acento btn-sm">
                        <?php echo $editando ? 'Guardar cambios' : 'Agregar'; ?>
                    </button>
                    <?php if ($editando): ?>
                        <a href="index.php?mod=abm_referentes" class="btn btn-outline-secondary btn-sm ms-1">Cancelar</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Listado de referentes -->
<div class="table-responsive">
    <table class="table table-hover table-bordered align-middle">
        <thead>
            <tr>
                <th>Apellido</th>
                <th>Nombre</th>
                <th>CD</th>
                <th>CP</th>
                <th>Estado</th>
                <th style="width:160px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($referentes as $r): ?>
            <tr class="<?php echo !$r['activo'] ? 'text-secondary' : ''; ?>">
                <td><?php echo htmlspecialchars($r['apellido'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($r['nombre'],   ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo $r['aplica_cd'] ? 'SI' : '—'; ?></td>
                <td><?php echo $r['aplica_cp'] ? 'SI' : '—'; ?></td>
                <td>
                    <span class="badge" style="background-color:<?php echo $r['activo'] ? '#a6d900' : '#6c757d'; ?>;color:<?php echo $r['activo'] ? '#1a1a2e' : '#fff'; ?>;">
                        <?php echo $r['activo'] ? 'Activo' : 'Baja'; ?>
                    </span>
                </td>
                <td>
                    <a href="index.php?mod=abm_referentes&editar=<?php echo $r['id']; ?>"
                        class="btn btn-sm btn-acento me-1">Editar</a>

                    <form method="POST" action="index.php?mod=abm_referentes" class="d-inline">
                        <input type="hidden" name="accion" value="<?php echo $r['activo'] ? 'baja' : 'alta'; ?>">
                        <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                            <?php echo $r['activo'] ? 'Dar de baja' : 'Reactivar'; ?>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
