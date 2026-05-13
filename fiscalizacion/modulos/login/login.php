<?php
// fiscalizacion/modulos/login/login.php
// Modulo de login del sistema Fiscalizar — Fiscalizacion.
// Pantalla unica con dos secciones visualmente separadas:
//   Superior — Fiscales: combo de mesas + password
//   Inferior — Admin y Superadmin: usuario + password
// Acceso: publico.
//
// Cambios respecto a la version anterior:
//   - La query de mesas ya no usa m.id_eleccion (columna eliminada).
//   - La habilitacion de mesas viene de dias_eleccion.habilitado (no mesas.habilitada).
//   - El combo muestra mesas cuyo dia esta habilitado y cuya eleccion esta activa.
//   - La sesion del fiscal incluye id_eleccion obtenido via dias_eleccion.

// Si ya hay sesion activa redirigir segun rol
if (isset($_SESSION['rol'])) {
    if ($_SESSION['rol'] === 'fiscal') {
        header('Location: index.php?mod=fiscal');
    } else {
        header('Location: index.php?mod=dashboard');
    }
    exit;
}

$error_fiscal = '';
$error_admin  = '';

// --- Procesar login de fiscal ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_fiscal'])) {

    $id_mesa  = intval($_POST['id_mesa'] ?? 0);
    $password = $_POST['password_fiscal'] ?? '';

    if ($id_mesa > 0 && $password !== '') {

        // Buscar la mesa con su dia y eleccion.
        // No filtramos por en_uso = 0: un fiscal puede reconectarse a su mesa
        // aunque en_uso = 1 (por ejemplo si cerro el browser sin hacer logout).
        // El password correcto es la autorización suficiente.
        $stmt = $pdo->prepare("
            SELECT m.id, m.nombre, m.tipo, m.password, m.activa, m.en_uso,
                   d.id AS id_dia,
                   e.id AS id_eleccion
            FROM mesas m
            JOIN dias_eleccion d ON m.id_dia = d.id
            JOIN elecciones e    ON d.id_eleccion = e.id
            WHERE m.id = ?
              AND d.habilitado = 1
              AND e.estado = 'activa'
        ");
        $stmt->execute([$id_mesa]);
        $mesa = $stmt->fetch();

        if ($mesa && password_verify($password, $mesa['password'])) {

            if (!$mesa['activa']) {
                $error_fiscal = 'La mesa no esta activa para recibir votos.';
            } else {
                // Marcar mesa en uso (puede ya estar en 1 si el fiscal se reconecta)
                $pdo->prepare("UPDATE mesas SET en_uso = 1 WHERE id = ?")
                    ->execute([$mesa['id']]);

                // Iniciar sesion del fiscal
                $_SESSION['rol']         = 'fiscal';
                $_SESSION['id_mesa']     = $mesa['id'];
                $_SESSION['nombre_mesa'] = $mesa['nombre'];
                $_SESSION['tipo_mesa']   = $mesa['tipo'];
                $_SESSION['id_eleccion'] = $mesa['id_eleccion'];

                header('Location: index.php?mod=fiscal');
                exit;
            }

        } else {
            $error_fiscal = 'Mesa o password incorrectos.';
        }

    } else {
        $error_fiscal = 'Elegir una mesa e ingresar el password.';
    }
}

// --- Procesar login de admin/superadmin ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_admin'])) {

    $usuario  = trim($_POST['usuario'] ?? '');
    $password = $_POST['password_admin'] ?? '';

    if ($usuario !== '' && $password !== '') {

        // Buscar usuario activo en usuarios_fiscal
        $stmt = $pdo->prepare("
            SELECT id, usuario, password, nivel
            FROM usuarios_fiscal
            WHERE usuario = ? AND activo = 1
        ");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {

            // Iniciar sesion del admin/superadmin
            $_SESSION['rol']     = $user['nivel'];
            $_SESSION['usuario'] = $user['usuario'];
            $_SESSION['id_user'] = $user['id'];

            header('Location: index.php?mod=dashboard');
            exit;

        } else {
            $error_admin = 'Usuario o password incorrectos.';
        }

    } else {
        $error_admin = 'Ingresar usuario y password.';
    }
}

// --- Cargar mesas disponibles para el combo ---
// Mesas de dias habilitados de elecciones activas.
// Se incluyen mesas con en_uso = 1 para permitir reconexion del fiscal
// que cerro el browser sin hacer logout. El password es la autorizacion.
// Las mesas en_uso se marcan visualmente para que el fiscal sepa que
// ya hay una sesion activa y puede ser que este reconectandose.
$stmt_mesas = $pdo->query("
    SELECT m.id, m.nombre, m.tipo, m.en_uso, e.nombre AS eleccion
    FROM mesas m
    JOIN dias_eleccion d ON m.id_dia = d.id
    JOIN elecciones e    ON d.id_eleccion = e.id
    WHERE d.habilitado = 1
      AND e.estado = 'activa'
    ORDER BY m.tipo, m.nombre
");
$mesas_disponibles = $stmt_mesas->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fiscalizar — Ingreso</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/img/logo.png">

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Estilos propios -->
    <link href="assets/css/estilos.css" rel="stylesheet">

    <style>
        /* Login centrado en pantalla */
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .login-card {
            width: 100%;
            max-width: 460px;
        }
        .login-divider {
            border-top: 2px solid #e2e8f0;
            margin: 2rem 0;
            position: relative;
            text-align: center;
        }
        .login-divider span {
            position: absolute;
            top: -0.75rem;
            left: 50%;
            transform: translateX(-50%);
            background: #fff;
            padding: 0 0.75rem;
            font-size: 0.8rem;
            color: #4a5568;
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">

        <!-- Logo y titulo -->
        <div class="text-center mb-4">
            <img src="assets/img/logo.png" alt="Fiscalizar" style="height:48px;" class="mb-3">
            <h5 class="fw-semibold" style="color:#1a1a2e;">Fiscalizar</h5>
            <p class="text-secondary" style="font-size:0.85rem;">Facultad de Ciencias Sociales — UBA</p>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">

                <!-- SECCION FISCAL -->
                <p class="fw-semibold mb-3" style="font-size:0.9rem;color:#1a1a2e;">Ingreso de fiscales</p>

                <?php if ($error_fiscal !== ''): ?>
                    <div class="alert alert-danger py-2" style="font-size:0.85rem;">
                        <?php echo htmlspecialchars($error_fiscal, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="index.php?mod=login">

                    <!-- Combo de mesas -->
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;">Mesa</label>
                        <?php if (empty($mesas_disponibles)): ?>
                            <div class="text-secondary" style="font-size:0.85rem;">
                                No hay mesas habilitadas en este momento.
                            </div>
                        <?php else: ?>
                            <select name="id_mesa" class="form-select form-select-sm">
                                <option value="" disabled selected>Elegir mesa</option>
                                <?php foreach ($mesas_disponibles as $m): ?>
                                    <option value="<?php echo $m['id']; ?>">
                                        <?php echo htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                        — <?php echo htmlspecialchars($m['eleccion'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php echo $m['en_uso'] ? '(en uso — reconectar)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <!-- Password fiscal -->
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;">Password</label>
                        <input type="password" name="password_fiscal"
                            class="form-control form-control-sm"
                            autocomplete="current-password"
                            placeholder="Password de la mesa">
                    </div>

                    <button type="submit" name="login_fiscal"
                        class="btn btn-acento btn-sm w-100"
                        <?php echo empty($mesas_disponibles) ? 'disabled' : ''; ?>>
                        Ingresar como fiscal
                    </button>

                </form>

                <!-- Separador -->
                <div class="login-divider mt-4">
                    <span>Administracion</span>
                </div>

                <!-- SECCION ADMIN -->
                <p class="fw-semibold mb-3" style="font-size:0.9rem;color:#1a1a2e;">Ingreso de administradores</p>

                <?php if ($error_admin !== ''): ?>
                    <div class="alert alert-danger py-2" style="font-size:0.85rem;">
                        <?php echo htmlspecialchars($error_admin, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="index.php?mod=login">

                    <!-- Usuario -->
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;">Usuario</label>
                        <input type="text" name="usuario"
                            class="form-control form-control-sm"
                            autocomplete="username"
                            placeholder="Nombre de usuario">
                    </div>

                    <!-- Password admin -->
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;">Password</label>
                        <input type="password" name="password_admin"
                            class="form-control form-control-sm"
                            autocomplete="current-password"
                            placeholder="Password">
                    </div>

                    <button type="submit" name="login_admin"
                        class="btn btn-outline-secondary btn-sm w-100">
                        Ingresar como administrador
                    </button>

                </form>

            </div>
        </div>

    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
