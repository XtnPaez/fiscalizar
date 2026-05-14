<?php
// fiscalizacion/modulos/abm_usuarios/abm_usuarios.php
// Modulo de administracion de usuarios fiscales.
// Acceso: solo superadmin.
//
// Permite:
//   - Ver todos los usuarios de usuarios_fiscal
//   - Crear nuevo usuario (admin o superadmin)
//   - Editar nivel (admin <-> superadmin)
//   - Activar / desactivar (baja logica)
//
// Restriccion: el superadmin logueado no puede desactivarse a si mismo.

verificar_superadmin_fiscal();

$mensaje_ok    = '';
$mensaje_error = '';

// ============================================================
// PROCESAMIENTO DE ACCIONES POST
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // --- Crear nuevo usuario ---
    if ($accion === 'crear_usuario') {
        $usuario  = trim($_POST['usuario']  ?? '');
        $password = trim($_POST['password'] ?? '');
        $nivel    = $_POST['nivel']         ?? '';

        $niveles_validos = ['admin', 'superadmin', 'mira'];
        $tipos_validos   = ['cd', 'cp', 'rt', 'cs'];

        // Para nivel mira el tipo es obligatorio
        $tipo = $_POST['tipo'] ?? '';
        if ($nivel === 'mira' && !in_array($tipo, $tipos_validos)) {
            $tipo = null;
        }

        if ($usuario === '' || $password === '' || !in_array($nivel, $niveles_validos)) {
            $mensaje_error = 'Completá todos los campos correctamente.';
        } elseif ($nivel === 'mira' && !$tipo) {
            $mensaje_error = 'Para el nivel Mira hay que elegir un tipo de padrón.';
        } else {
            // Verificar que el usuario no exista ya
            $stmt = $pdo->prepare("
                SELECT id FROM usuarios_fiscal WHERE usuario = ?
            ");
            $stmt->execute([$usuario]);

            if ($stmt->fetch()) {
                $mensaje_error = 'El nombre de usuario ya existe. Elegí otro.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare("
                    INSERT INTO usuarios_fiscal (usuario, password, nivel, tipo, activo)
                    VALUES (?, ?, ?, ?, 1)
                ")->execute([$usuario, $hash, $nivel, $tipo]);
                $mensaje_ok = 'Usuario creado correctamente.';
            }
        }
    }

    // --- Editar nivel ---
    if ($accion === 'editar_nivel') {
        $id_usuario = intval($_POST['id_usuario'] ?? 0);
        $nuevo_nivel = $_POST['nuevo_nivel'] ?? '';

        $niveles_validos = ['admin', 'superadmin', 'mira'];

        if ($id_usuario > 0 && in_array($nuevo_nivel, $niveles_validos)) {
            $pdo->prepare("
                UPDATE usuarios_fiscal SET nivel = ? WHERE id = ?
            ")->execute([$nuevo_nivel, $id_usuario]);
            $mensaje_ok = 'Nivel actualizado correctamente.';
        }
    }

    // --- Activar usuario ---
    if ($accion === 'activar') {
        $id_usuario = intval($_POST['id_usuario'] ?? 0);
        if ($id_usuario > 0) {
            $pdo->prepare("
                UPDATE usuarios_fiscal SET activo = 1 WHERE id = ?
            ")->execute([$id_usuario]);
            $mensaje_ok = 'Usuario activado.';
        }
    }

    // --- Desactivar usuario ---
    // El superadmin no puede desactivarse a si mismo
    if ($accion === 'desactivar') {
        $id_usuario = intval($_POST['id_usuario'] ?? 0);

        if ($id_usuario === intval($_SESSION['id_user'])) {
            $mensaje_error = 'No podés desactivar tu propio usuario.';
        } elseif ($id_usuario > 0) {
            $pdo->prepare("
                UPDATE usuarios_fiscal SET activo = 0 WHERE id = ?
            ")->execute([$id_usuario]);
            $mensaje_ok = 'Usuario desactivado.';
        }
    }

    // --- Cambiar password ---
    if ($accion === 'cambiar_password') {
        $id_usuario   = intval($_POST['id_usuario']   ?? 0);
        $nuevo_pass   = trim($_POST['nuevo_password'] ?? '');

        if ($id_usuario > 0 && $nuevo_pass !== '') {
            $hash = password_hash($nuevo_pass, PASSWORD_BCRYPT);
            $pdo->prepare("
                UPDATE usuarios_fiscal SET password = ? WHERE id = ?
            ")->execute([$hash, $id_usuario]);
            $mensaje_ok = 'Password actualizado correctamente.';
        } else {
            $mensaje_error = 'El password no puede estar vacío.';
        }
    }
}

// ============================================================
// CARGA DE DATOS
// ============================================================

$usuarios = $pdo->query("
    SELECT id, usuario, nivel, tipo, activo
    FROM usuarios_fiscal
    ORDER BY activo DESC, nivel DESC, usuario ASC
")->fetchAll();

require_once 'includes/navbar.php';
?>

<div class="modulo-titulo">Usuarios</div>

<?php if ($mensaje_ok !== ''): ?>
    <div class="alert alert-success py-2 mb-3" style="font-size:0.85rem;">
        <?php echo htmlspecialchars($mensaje_ok, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if ($mensaje_error !== ''): ?>
    <div class="alert alert-danger py-2 mb-3" style="font-size:0.85rem;">
        <?php echo htmlspecialchars($mensaje_error, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- FORMULARIO ALTA                                               -->
<!-- ============================================================ -->

<div class="modulo-subtitulo mb-2">Nuevo usuario</div>

<form method="POST" action="index.php?mod=abm_usuarios" class="row g-2 align-items-end mb-4">
    <input type="hidden" name="accion" value="crear_usuario">

    <div class="col-md-3">
        <label class="form-label form-label-sm">Usuario</label>
        <input type="text" name="usuario" class="form-control form-control-sm"
               placeholder="Nombre de usuario" required maxlength="60">
    </div>

    <div class="col-md-3">
        <label class="form-label form-label-sm">Password</label>
        <input type="text" name="password" class="form-control form-control-sm"
               placeholder="Password inicial" required maxlength="60">
    </div>

    <div class="col-md-2">
        <label class="form-label form-label-sm">Nivel</label>
        <select name="nivel" id="combo-nivel" class="form-select form-select-sm" required
                onchange="toggleTipo(this.value)">
            <option value="">— elegir —</option>
            <option value="admin">admin</option>
            <option value="superadmin">superadmin</option>
            <option value="mira">mira</option>
        </select>
    </div>

    <!-- Combo tipo — solo visible cuando nivel = mira -->
    <div class="col-md-2" id="div-tipo" style="display:none;">
        <label class="form-label form-label-sm">Padrón</label>
        <select name="tipo" class="form-select form-select-sm">
            <option value="">— elegir —</option>
            <option value="cd">CD</option>
            <option value="cp">CP</option>
            <option value="rt">RT</option>
            <option value="cs">CS</option>
        </select>
    </div>

    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Crear</button>
    </div>
</form>

<script>
function toggleTipo(nivel) {
    const divTipo = document.getElementById('div-tipo');
    divTipo.style.display = nivel === 'mira' ? 'block' : 'none';
}
</script>

<!-- ============================================================ -->
<!-- LISTADO DE USUARIOS                                           -->
<!-- ============================================================ -->

<div class="modulo-subtitulo mb-2">Usuarios registrados</div>

<div class="table-responsive">
    <table class="table table-hover table-bordered align-middle" style="font-size:0.85rem;">
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Nivel</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $u): ?>
            <tr>
                <td>
                    <?php echo htmlspecialchars($u['usuario'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (intval($u['id']) === intval($_SESSION['id_user'])): ?>
                        <span class="badge ms-1" style="background-color:#a6d900;color:#1a1a2e;font-size:0.7rem;">vos</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge" style="background-color:#1a1a2e;color:#fff;">
                        <?php echo htmlspecialchars($u['nivel'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <?php if ($u['nivel'] === 'mira' && $u['tipo']): ?>
                        <span class="badge ms-1" style="background-color:#4f8ef7;color:#fff;font-size:0.7rem;">
                            <?php echo strtoupper($u['tipo']); ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($u['activo']): ?>
                        <span class="badge bg-success">Activo</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="d-flex gap-1 flex-wrap">

                        <!-- Editar nivel -->
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                data-bs-toggle="modal"
                                data-bs-target="#modalNivel<?php echo $u['id']; ?>">
                            Nivel
                        </button>

                        <!-- Cambiar password -->
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                data-bs-toggle="modal"
                                data-bs-target="#modalPassword<?php echo $u['id']; ?>">
                            Password
                        </button>

                        <!-- Activar / Desactivar -->
                        <?php if ($u['activo']): ?>
                            <?php if (intval($u['id']) !== intval($_SESSION['id_user'])): ?>
                            <form method="POST" action="index.php?mod=abm_usuarios"
                                  onsubmit="return confirm('¿Desactivar a <?php echo htmlspecialchars($u['usuario'], ENT_QUOTES, 'UTF-8'); ?>?');">
                                <input type="hidden" name="accion" value="desactivar">
                                <input type="hidden" name="id_usuario" value="<?php echo $u['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-warning">Desactivar</button>
                            </form>
                            <?php else: ?>
                                <span class="text-secondary" style="font-size:0.8rem;">—</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <form method="POST" action="index.php?mod=abm_usuarios">
                                <input type="hidden" name="accion" value="activar">
                                <input type="hidden" name="id_usuario" value="<?php echo $u['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-success">Activar</button>
                            </form>
                        <?php endif; ?>

                    </div>
                </td>
            </tr>

            <!-- Modal editar nivel -->
            <div class="modal fade" id="modalNivel<?php echo $u['id']; ?>"
                 tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h6 class="modal-title">
                                Nivel — <?php echo htmlspecialchars($u['usuario'], ENT_QUOTES, 'UTF-8'); ?>
                            </h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" action="index.php?mod=abm_usuarios">
                            <input type="hidden" name="accion" value="editar_nivel">
                            <input type="hidden" name="id_usuario" value="<?php echo $u['id']; ?>">
                            <div class="modal-body">
                                <select name="nuevo_nivel" class="form-select form-select-sm">
                                    <option value="admin"
                                        <?php echo $u['nivel'] === 'admin' ? 'selected' : ''; ?>>
                                        admin
                                    </option>
                                    <option value="superadmin"
                                        <?php echo $u['nivel'] === 'superadmin' ? 'selected' : ''; ?>>
                                        superadmin
                                    </option>
                                    <option value="mira"
                                        <?php echo $u['nivel'] === 'mira' ? 'selected' : ''; ?>>
                                        mira
                                    </option>
                                </select>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modal cambiar password -->
            <div class="modal fade" id="modalPassword<?php echo $u['id']; ?>"
                 tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h6 class="modal-title">
                                Password — <?php echo htmlspecialchars($u['usuario'], ENT_QUOTES, 'UTF-8'); ?>
                            </h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" action="index.php?mod=abm_usuarios">
                            <input type="hidden" name="accion" value="cambiar_password">
                            <input type="hidden" name="id_usuario" value="<?php echo $u['id']; ?>">
                            <div class="modal-body">
                                <input type="text" name="nuevo_password"
                                       class="form-control form-control-sm"
                                       placeholder="Nuevo password" required maxlength="60">
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
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
