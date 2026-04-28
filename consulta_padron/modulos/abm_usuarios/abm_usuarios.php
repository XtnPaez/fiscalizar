<?php
// modulos/abm_usuarios/abm_usuarios.php
// ABM de usuarios del sistema Fiscalizar — Consulta Padron.
// Acceso: solo superadmin.
// Listado con opciones editar nivel, activar y desactivar.
// Formulario para crear nuevo usuario.
// El superadmin no puede desactivarse a si mismo.
// Passwords con hash bcrypt.

verificar_superadmin();

$mensaje = '';
$error   = '';

// --- Procesar acciones POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar') {

        $usuario  = trim($_POST['usuario']  ?? '');
        $password = trim($_POST['password'] ?? '');
        $nivel    = $_POST['nivel'] ?? 'consulta';

        if ($usuario === '' || $password === '') {
            $error = 'Usuario y contraseña son obligatorios.';
        } elseif (!in_array($nivel, ['consulta', 'admin', 'superadmin'])) {
            $error = 'Nivel de acceso invalido.';
        } else {
            // Verificar que el usuario no exista ya
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = :usuario");
            $stmt->execute([':usuario' => $usuario]);
            if ($stmt->fetch()) {
                $error = 'El nombre de usuario ya existe.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    INSERT INTO usuarios (usuario, password, nivel, activo)
                    VALUES (:usuario, :password, :nivel, 1)
                ");
                $stmt->execute([
                    ':usuario'  => $usuario,
                    ':password' => $hash,
                    ':nivel'    => $nivel,
                ]);
                $mensaje = 'Usuario creado correctamente.';
            }
        }

    } elseif ($accion === 'editar') {

        $id    = intval($_POST['id']    ?? 0);
        $nivel = $_POST['nivel'] ?? '';

        if ($id <= 0 || !in_array($nivel, ['consulta', 'admin', 'superadmin'])) {
            $error = 'Datos incompletos para editar.';
        } else {
            $stmt = $pdo->prepare("UPDATE usuarios SET nivel = :nivel WHERE id = :id");
            $stmt->execute([':nivel' => $nivel, ':id' => $id]);
            $mensaje = 'Nivel actualizado correctamente.';
        }

    } elseif ($accion === 'cambiar_password') {

        $id          = intval($_POST['id']           ?? 0);
        $password    = trim($_POST['password']       ?? '');
        $confirmar   = trim($_POST['confirmar']      ?? '');

        if ($id <= 0 || $password === '') {
            $error = 'Datos incompletos.';
        } elseif ($password !== $confirmar) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE usuarios SET password = :password WHERE id = :id");
            $stmt->execute([':password' => $hash, ':id' => $id]);
            $mensaje = 'Contraseña actualizada correctamente.';
        }

    } elseif ($accion === 'desactivar') {

        $id = intval($_POST['id'] ?? 0);

        // El superadmin no puede desactivarse a si mismo
        if ($id === $_SESSION['id_usuario']) {
            $error = 'No podes desactivar tu propio usuario.';
        } elseif ($id > 0) {
            $stmt = $pdo->prepare("UPDATE usuarios SET activo = 0 WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $mensaje = 'Usuario desactivado.';
        }

    } elseif ($accion === 'activar') {

        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE usuarios SET activo = 1 WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $mensaje = 'Usuario activado.';
        }
    }
}

// --- Cargar usuario a editar si se pidio ---
$editando  = null;
$id_editar = intval($_GET['editar'] ?? 0);
if ($id_editar > 0) {
    $stmt = $pdo->prepare("SELECT id, usuario, nivel, activo FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $id_editar]);
    $editando = $stmt->fetch();
}

// --- Listar todos los usuarios ---
$stmt     = $pdo->query("SELECT id, usuario, nivel, activo FROM usuarios ORDER BY usuario ASC");
$usuarios = $stmt->fetchAll();

require_once 'includes/navbar.php';
?>

<div class="modulo-titulo">Usuarios</div>

<?php if ($mensaje !== ''): ?>
    <div class="alert alert-success alerta-acceso"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger alerta-acceso"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<!-- Formulario agregar usuario -->
<?php if (!$editando): ?>
<div class="card mb-4" style="border-color:#d1d5db;">
    <div class="card-body">
        <h6 class="card-title" style="font-weight:600; color:#1a1a2e;">Agregar usuario</h6>

        <form method="POST" action="index.php?mod=abm_usuarios">
            <input type="hidden" name="accion" value="agregar">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label" style="font-size:0.8rem;">Usuario</label>
                    <input type="text" name="usuario" class="form-control form-control-sm"
                        autocomplete="off">
                </div>
                <div class="col-md-3">
                    <label class="form-label" style="font-size:0.8rem;">Contraseña</label>
                    <input type="password" name="password" class="form-control form-control-sm"
                        autocomplete="new-password">
                </div>
                <div class="col-md-2">
                    <label class="form-label" style="font-size:0.8rem;">Nivel</label>
                    <select name="nivel" class="form-select form-select-sm">
                        <option value="consulta">Consulta</option>
                        <option value="admin">Admin</option>
                        <option value="superadmin">Superadmin</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-acento btn-sm">Agregar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php else: ?>
<!-- Formulario editar nivel y cambiar password -->
<div class="card mb-4" style="border-color:#d1d5db;">
    <div class="card-body">
        <h6 class="card-title" style="font-weight:600; color:#1a1a2e;">
            Editar usuario: <?php echo htmlspecialchars($editando['usuario'], ENT_QUOTES, 'UTF-8'); ?>
        </h6>

        <!-- Cambiar nivel -->
        <form method="POST" action="index.php?mod=abm_usuarios" class="mb-3">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id" value="<?php echo $editando['id']; ?>">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label" style="font-size:0.8rem;">Nivel</label>
                    <select name="nivel" class="form-select form-select-sm">
                        <option value="consulta"   <?php echo $editando['nivel'] === 'consulta'   ? 'selected' : ''; ?>>Consulta</option>
                        <option value="admin"      <?php echo $editando['nivel'] === 'admin'      ? 'selected' : ''; ?>>Admin</option>
                        <option value="superadmin" <?php echo $editando['nivel'] === 'superadmin' ? 'selected' : ''; ?>>Superadmin</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-acento btn-sm">Guardar nivel</button>
                </div>
            </div>
        </form>

        <!-- Cambiar password -->
        <form method="POST" action="index.php?mod=abm_usuarios">
            <input type="hidden" name="accion" value="cambiar_password">
            <input type="hidden" name="id" value="<?php echo $editando['id']; ?>">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label" style="font-size:0.8rem;">Nueva contraseña</label>
                    <input type="password" name="password" class="form-control form-control-sm"
                        autocomplete="new-password">
                </div>
                <div class="col-md-3">
                    <label class="form-label" style="font-size:0.8rem;">Confirmar contraseña</label>
                    <input type="password" name="confirmar" class="form-control form-control-sm"
                        autocomplete="new-password">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-acento btn-sm">Cambiar contraseña</button>
                </div>
            </div>
        </form>

        <div class="mt-3">
            <a href="index.php?mod=abm_usuarios" class="btn btn-outline-secondary btn-sm">Cancelar</a>
        </div>

    </div>
</div>
<?php endif; ?>

<!-- Listado de usuarios -->
<div class="table-responsive">
    <table class="table table-hover table-bordered align-middle">
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Nivel</th>
                <th>Estado</th>
                <th style="width:200px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $u): ?>
            <tr class="<?php echo !$u['activo'] ? 'text-secondary' : ''; ?>">
                <td><?php echo htmlspecialchars($u['usuario'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars(ucfirst($u['nivel']), ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <span class="badge"
                        style="background-color:<?php echo $u['activo'] ? '#a6d900' : '#6c757d'; ?>;
                               color:<?php echo $u['activo'] ? '#1a1a2e' : '#fff'; ?>;">
                        <?php echo $u['activo'] ? 'Activo' : 'Inactivo'; ?>
                    </span>
                </td>
                <td>
                    <a href="index.php?mod=abm_usuarios&editar=<?php echo $u['id']; ?>"
                        class="btn btn-sm btn-acento me-1">Editar</a>

                    <form method="POST" action="index.php?mod=abm_usuarios" class="d-inline">
                        <?php if ($u['activo']): ?>
                            <input type="hidden" name="accion" value="desactivar">
                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary"
                                <?php echo $u['id'] === $_SESSION['id_usuario'] ? 'disabled title="No podes desactivar tu propio usuario"' : ''; ?>>
                                Desactivar
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="accion" value="activar">
                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                Activar
                            </button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
