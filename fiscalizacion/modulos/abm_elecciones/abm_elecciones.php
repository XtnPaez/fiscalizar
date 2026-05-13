<?php
// fiscalizacion/modulos/abm_elecciones/abm_elecciones.php
// Modulo de administracion de elecciones, dias y mesas.
// Acceso: solo superadmin.
//
// Estructura de tres pestanas:
//   Pestana 1 — Elecciones: crear, activar, desactivar, migrar votos
//   Pestana 2 — Dias: crear dias para una eleccion, habilitar/deshabilitar
//   Pestana 3 — Mesas: crear mesas para un dia, liberar mesa caida, cambiar password
//
// Jerarquia: eleccion -> dia -> mesa
// La habilitacion opera a nivel dia: habilitar un dia habilita todas sus mesas.
// El cierre de una eleccion requiere que todos sus dias esten deshabilitados.

verificar_superadmin_fiscal();

// ============================================================
// PROCESAMIENTO DE ACCIONES POST
// Toda accion que modifica datos viene por POST y redirige
// despues de ejecutar para evitar reenvio del formulario.
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // --- ELECCIONES ---

    // Crear nueva eleccion
    if ($accion === 'crear_eleccion') {
        $nombre = trim($_POST['nombre'] ?? '');
        $tipo   = $_POST['tipo'] ?? '';
        $anio   = intval($_POST['anio'] ?? 0);

        $tipos_validos = ['cd', 'cp', 'rt', 'cs'];

        if ($nombre !== '' && in_array($tipo, $tipos_validos) && $anio >= 2024) {
            $stmt = $pdo->prepare("
                INSERT INTO elecciones (nombre, tipo, anio, estado)
                VALUES (?, ?, ?, 'programada')
            ");
            $stmt->execute([$nombre, $tipo, $anio]);
        }
        header('Location: index.php?mod=abm_elecciones&pestana=elecciones&ok=eleccion_creada');
        exit;
    }

    // Activar eleccion: solo si esta programada
    // Solo puede haber una eleccion activa por tipo simultaneamente
    if ($accion === 'activar_eleccion') {
        $id_eleccion = intval($_POST['id_eleccion'] ?? 0);

        // Verificar que no haya otra eleccion activa del mismo tipo
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM elecciones e
            JOIN elecciones e2 ON e2.id = ?
            WHERE e.tipo = e2.tipo AND e.estado = 'activa'
        ");
        $stmt->execute([$id_eleccion]);
        $hay_activa = $stmt->fetchColumn();

        if (!$hay_activa) {
            $pdo->prepare("
                UPDATE elecciones SET estado = 'activa' WHERE id = ? AND estado = 'programada'
            ")->execute([$id_eleccion]);
            header('Location: index.php?mod=abm_elecciones&pestana=elecciones&ok=eleccion_activada');
        } else {
            header('Location: index.php?mod=abm_elecciones&pestana=elecciones&error=ya_hay_activa');
        }
        exit;
    }

    // Desactivar eleccion: vuelve a programada
    // Solo si todos sus dias estan deshabilitados
    if ($accion === 'desactivar_eleccion') {
        $id_eleccion = intval($_POST['id_eleccion'] ?? 0);

        // Verificar que no haya dias habilitados en esta eleccion
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM dias_eleccion
            WHERE id_eleccion = ? AND habilitado = 1
        ");
        $stmt->execute([$id_eleccion]);
        $dias_habilitados = $stmt->fetchColumn();

        if (!$dias_habilitados) {
            $pdo->prepare("
                UPDATE elecciones SET estado = 'programada' WHERE id = ? AND estado = 'activa'
            ")->execute([$id_eleccion]);
            header('Location: index.php?mod=abm_elecciones&pestana=elecciones&ok=eleccion_desactivada');
        } else {
            header('Location: index.php?mod=abm_elecciones&pestana=elecciones&error=hay_dias_habilitados');
        }
        exit;
    }

    // Cerrar eleccion y migrar votos a participacion_electoral
    // Precondicion: todos los dias deshabilitados
    // La migracion inserta en participacion_electoral y trunca votos_dia
    if ($accion === 'cerrar_eleccion') {
        $id_eleccion = intval($_POST['id_eleccion'] ?? 0);

        // Verificar que no haya dias habilitados
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM dias_eleccion
            WHERE id_eleccion = ? AND habilitado = 1
        ");
        $stmt->execute([$id_eleccion]);
        $dias_habilitados = $stmt->fetchColumn();

        if ($dias_habilitados) {
            header('Location: index.php?mod=abm_elecciones&pestana=elecciones&error=hay_dias_habilitados');
            exit;
        }

        // Obtener el tipo de la eleccion para filtrar mesas
        $stmt = $pdo->prepare("SELECT tipo FROM elecciones WHERE id = ?");
        $stmt->execute([$id_eleccion]);
        $tipo_eleccion = $stmt->fetchColumn();

        if (!$tipo_eleccion) {
            header('Location: index.php?mod=abm_elecciones&pestana=elecciones&error=eleccion_no_encontrada');
            exit;
        }

        // Migrar votos_dia -> participacion_electoral
        // Solo los votos de mesas que pertenecen a esta eleccion
        // Se usa INSERT IGNORE para no duplicar si ya existe el par dni/eleccion
        $pdo->prepare("
            INSERT IGNORE INTO participacion_electoral (dni, id_eleccion, fecha_registro)
            SELECT vd.dni, ?, CURDATE()
            FROM votos_dia vd
            JOIN mesas m         ON vd.id_mesa = m.id
            JOIN dias_eleccion d ON m.id_dia = d.id
            WHERE d.id_eleccion = ?
        ")->execute([$id_eleccion, $id_eleccion]);

        // Eliminar los votos migrados de votos_dia
        // Solo los de esta eleccion, no los de otras elecciones activas
        $pdo->prepare("
            DELETE vd FROM votos_dia vd
            JOIN mesas m         ON vd.id_mesa = m.id
            JOIN dias_eleccion d ON m.id_dia = d.id
            WHERE d.id_eleccion = ?
        ")->execute([$id_eleccion]);

        // Marcar la eleccion como cerrada
        $pdo->prepare("
            UPDATE elecciones SET estado = 'cerrada' WHERE id = ?
        ")->execute([$id_eleccion]);

        header('Location: index.php?mod=abm_elecciones&pestana=elecciones&ok=eleccion_cerrada');
        exit;
    }

    // --- DIAS ---

    // Crear nuevo dia para una eleccion
    if ($accion === 'crear_dia') {
        $id_eleccion = intval($_POST['id_eleccion'] ?? 0);
        $nombre      = trim($_POST['nombre_dia'] ?? '');

        if ($id_eleccion > 0 && $nombre !== '') {
            $pdo->prepare("
                INSERT INTO dias_eleccion (id_eleccion, nombre, habilitado)
                VALUES (?, ?, 0)
            ")->execute([$id_eleccion, $nombre]);
        }
        header('Location: index.php?mod=abm_elecciones&pestana=dias&id_eleccion=' . $id_eleccion . '&ok=dia_creado');
        exit;
    }

    // Habilitar dia: todas las mesas del dia aparecen en el login
    if ($accion === 'habilitar_dia') {
        $id_dia      = intval($_POST['id_dia'] ?? 0);
        $id_eleccion = intval($_POST['id_eleccion'] ?? 0);

        $pdo->prepare("
            UPDATE dias_eleccion SET habilitado = 1 WHERE id = ?
        ")->execute([$id_dia]);

        header('Location: index.php?mod=abm_elecciones&pestana=dias&id_eleccion=' . $id_eleccion . '&ok=dia_habilitado');
        exit;
    }

    // Deshabilitar dia: las mesas del dia dejan de aparecer en el login
    // Tambien libera todas las mesas del dia (en_uso = 0)
    if ($accion === 'deshabilitar_dia') {
        $id_dia      = intval($_POST['id_dia'] ?? 0);
        $id_eleccion = intval($_POST['id_eleccion'] ?? 0);

        $pdo->prepare("
            UPDATE dias_eleccion SET habilitado = 0 WHERE id = ?
        ")->execute([$id_dia]);

        // Liberar todas las mesas del dia al deshabilitar
        $pdo->prepare("
            UPDATE mesas SET en_uso = 0 WHERE id_dia = ?
        ")->execute([$id_dia]);

        header('Location: index.php?mod=abm_elecciones&pestana=dias&id_eleccion=' . $id_eleccion . '&ok=dia_deshabilitado');
        exit;
    }

    // --- MESAS ---

    // Crear nueva mesa para un dia
    if ($accion === 'crear_mesa') {
        $id_dia      = intval($_POST['id_dia'] ?? 0);
        $id_eleccion = intval($_POST['id_eleccion'] ?? 0);
        $nombre      = trim($_POST['nombre_mesa'] ?? '');
        $password    = trim($_POST['password_mesa'] ?? '');

        if ($id_dia > 0 && $nombre !== '' && $password !== '') {
            // Obtener el tipo de la eleccion para asignarlo a la mesa
            $stmt = $pdo->prepare("
                SELECT e.tipo FROM elecciones e
                JOIN dias_eleccion d ON d.id_eleccion = e.id
                WHERE d.id = ?
            ");
            $stmt->execute([$id_dia]);
            $tipo = $stmt->fetchColumn();

            if ($tipo) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare("
                    INSERT INTO mesas (nombre, tipo, id_dia, password, habilitada, en_uso, activa)
                    VALUES (?, ?, ?, ?, 0, 0, 1)
                ")->execute([$nombre, $tipo, $id_dia, $hash]);
            }
        }
        header('Location: index.php?mod=abm_elecciones&pestana=mesas&id_dia=' . $id_dia . '&id_eleccion=' . $id_eleccion . '&ok=mesa_creada');
        exit;
    }

    // Liberar mesa caida: pone en_uso = 0 para que el fiscal pueda reloguearse
    if ($accion === 'liberar_mesa') {
        $id_mesa     = intval($_POST['id_mesa'] ?? 0);
        $id_dia      = intval($_POST['id_dia'] ?? 0);
        $id_eleccion = intval($_POST['id_eleccion'] ?? 0);

        $pdo->prepare("
            UPDATE mesas SET en_uso = 0 WHERE id = ?
        ")->execute([$id_mesa]);

        header('Location: index.php?mod=abm_elecciones&pestana=mesas&id_dia=' . $id_dia . '&id_eleccion=' . $id_eleccion . '&ok=mesa_liberada');
        exit;
    }

    // Editar nombre de una mesa
    if ($accion === 'editar_nombre_mesa') {
        $id_mesa     = intval($_POST['id_mesa']      ?? 0);
        $id_dia      = intval($_POST['id_dia']       ?? 0);
        $id_eleccion = intval($_POST['id_eleccion']  ?? 0);
        $nuevo_nombre = trim($_POST['nuevo_nombre']  ?? '');

        if ($id_mesa > 0 && $nuevo_nombre !== '') {
            $pdo->prepare("
                UPDATE mesas SET nombre = ? WHERE id = ?
            ")->execute([$nuevo_nombre, $id_mesa]);
        }
        header('Location: index.php?mod=abm_elecciones&pestana=mesas&id_dia=' . $id_dia . '&id_eleccion=' . $id_eleccion . '&ok=nombre_actualizado');
        exit;
    }

    // Cambiar password de una mesa
    if ($accion === 'cambiar_password') {
        $id_mesa     = intval($_POST['id_mesa'] ?? 0);
        $id_dia      = intval($_POST['id_dia'] ?? 0);
        $id_eleccion = intval($_POST['id_eleccion'] ?? 0);
        $password    = trim($_POST['nuevo_password'] ?? '');

        if ($id_mesa > 0 && $password !== '') {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("
                UPDATE mesas SET password = ? WHERE id = ?
            ")->execute([$hash, $id_mesa]);
        }
        header('Location: index.php?mod=abm_elecciones&pestana=mesas&id_dia=' . $id_dia . '&id_eleccion=' . $id_eleccion . '&ok=password_cambiado');
        exit;
    }
}

// ============================================================
// CARGA DE DATOS PARA LA VISTA
// Segun la pestana activa se cargan los datos correspondientes.
// ============================================================

// Pestana activa — default: elecciones
$pestana     = $_GET['pestana'] ?? 'elecciones';
$id_eleccion = intval($_GET['id_eleccion'] ?? 0);
$id_dia      = intval($_GET['id_dia'] ?? 0);

// Mensajes de feedback
$ok    = $_GET['ok'] ?? '';
$error = $_GET['error'] ?? '';

$mensajes_ok = [
    'eleccion_creada'      => 'Eleccion creada correctamente.',
    'eleccion_activada'    => 'Eleccion activada. Los listados ya la muestran.',
    'eleccion_desactivada' => 'Eleccion desactivada.',
    'eleccion_cerrada'     => 'Eleccion cerrada. Los votos fueron migrados a participacion_electoral.',
    'dia_creado'           => 'Dia creado correctamente.',
    'dia_habilitado'       => 'Dia habilitado. Los fiscales ya pueden ver sus mesas.',
    'dia_deshabilitado'    => 'Dia deshabilitado. Las mesas quedaron liberadas.',
    'mesa_creada'          => 'Mesa creada correctamente.',
    'mesa_liberada'        => 'Mesa liberada. El fiscal puede volver a loguearse.',
    'nombre_actualizado'   => 'Nombre de la mesa actualizado.',
    'password_cambiado'    => 'Password de la mesa actualizado.',
];

$mensajes_error = [
    'ya_hay_activa'            => 'Ya hay una eleccion activa de ese tipo. Desactivala antes de activar esta.',
    'hay_dias_habilitados'     => 'Hay dias habilitados en esta eleccion. Deshabilitatelos antes de continuar.',
    'eleccion_no_encontrada'   => 'No se encontro la eleccion.',
];

// --- Pestana 1: todas las elecciones ---
$elecciones = $pdo->query("
    SELECT id, nombre, tipo, anio, estado
    FROM elecciones
    ORDER BY anio DESC, tipo ASC
")->fetchAll();

// --- Pestana 2: dias de la eleccion seleccionada ---
$dias = [];
$eleccion_activa = null;

if ($id_eleccion > 0) {
    $stmt = $pdo->prepare("
        SELECT id, nombre, tipo, anio, estado
        FROM elecciones WHERE id = ?
    ");
    $stmt->execute([$id_eleccion]);
    $eleccion_activa = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT d.id, d.nombre, d.habilitado,
               COUNT(m.id) AS total_mesas,
               SUM(m.en_uso) AS mesas_en_uso
        FROM dias_eleccion d
        LEFT JOIN mesas m ON m.id_dia = d.id
        WHERE d.id_eleccion = ?
        GROUP BY d.id, d.nombre, d.habilitado
        ORDER BY d.id ASC
    ");
    $stmt->execute([$id_eleccion]);
    $dias = $stmt->fetchAll();
}

// --- Pestana 3: mesas del dia seleccionado ---
$mesas = [];
$dia_activo = null;

if ($id_dia > 0) {
    $stmt = $pdo->prepare("
        SELECT d.id, d.nombre, d.habilitado, d.id_eleccion
        FROM dias_eleccion d WHERE d.id = ?
    ");
    $stmt->execute([$id_dia]);
    $dia_activo = $stmt->fetch();

    // Si no tenemos id_eleccion en GET, lo tomamos del dia
    if (!$id_eleccion && $dia_activo) {
        $id_eleccion = $dia_activo['id_eleccion'];
    }

    $stmt = $pdo->prepare("
        SELECT id, nombre, tipo, en_uso, activa
        FROM mesas
        WHERE id_dia = ?
        ORDER BY nombre ASC
    ");
    $stmt->execute([$id_dia]);
    $mesas = $stmt->fetchAll();
}

// --- Combos para crear dia: elecciones no cerradas ---
$elecciones_disponibles = $pdo->query("
    SELECT id, nombre, tipo, anio
    FROM elecciones
    WHERE estado IN ('programada', 'activa')
    ORDER BY anio DESC, tipo ASC
")->fetchAll();

// --- Verificacion para boton cerrar eleccion ---
// Para cada eleccion activa, verificar si tiene dias habilitados
$dias_habilitados_por_eleccion = [];
foreach ($elecciones as $e) {
    if ($e['estado'] === 'activa') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM dias_eleccion
            WHERE id_eleccion = ? AND habilitado = 1
        ");
        $stmt->execute([$e['id']]);
        $dias_habilitados_por_eleccion[$e['id']] = $stmt->fetchColumn();
    }
}

require_once 'includes/navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="modulo-titulo">Administracion de Elecciones</div>
    <!-- Boton Actualizar: util en mobile donde F5 no es accesible.
         Recarga la URL actual preservando pestana y seleccion activa. -->
    <button type="button" class="btn btn-sm btn-outline-secondary"
            onclick="window.location.reload()">
        ↻ Actualizar
    </button>
</div>

<?php if ($ok && isset($mensajes_ok[$ok])): ?>
    <div class="alert alert-success py-2 mb-3" style="font-size:0.85rem;">
        <?php echo htmlspecialchars($mensajes_ok[$ok], ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if ($error && isset($mensajes_error[$error])): ?>
    <div class="alert alert-danger py-2 mb-3" style="font-size:0.85rem;">
        <?php echo htmlspecialchars($mensajes_error[$error], ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<!-- Pestanas de navegacion -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?php echo $pestana === 'elecciones' ? 'active' : ''; ?>"
           href="index.php?mod=abm_elecciones&pestana=elecciones">
            Elecciones
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $pestana === 'dias' ? 'active' : ''; ?>"
           href="index.php?mod=abm_elecciones&pestana=dias<?php echo $id_eleccion ? '&id_eleccion=' . $id_eleccion : ''; ?>">
            Dias
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $pestana === 'mesas' ? 'active' : ''; ?>"
           href="index.php?mod=abm_elecciones&pestana=mesas<?php echo $id_dia ? '&id_dia=' . $id_dia . '&id_eleccion=' . $id_eleccion : ''; ?>">
            Mesas
        </a>
    </li>
</ul>


<?php if ($pestana === 'elecciones'): ?>
<!-- ============================================================ -->
<!-- PESTANA 1 — ELECCIONES                                       -->
<!-- ============================================================ -->

<!-- Formulario: crear nueva eleccion -->
<div class="mb-4">
    <div class="modulo-subtitulo mb-2">Nueva eleccion</div>
    <form method="POST" action="index.php?mod=abm_elecciones" class="row g-2 align-items-end">
        <input type="hidden" name="accion" value="crear_eleccion">

        <div class="col-md-4">
            <label class="form-label form-label-sm">Nombre</label>
            <input type="text" name="nombre" class="form-control form-control-sm"
                   placeholder="Ej: Eleccion CD 2027" required maxlength="80">
        </div>

        <div class="col-md-2">
            <label class="form-label form-label-sm">Tipo</label>
            <select name="tipo" class="form-select form-select-sm" required>
                <option value="">— elegir —</option>
                <option value="cd">CD</option>
                <option value="cp">CP</option>
                <option value="rt">RT</option>
                <option value="cs">CS</option>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label form-label-sm">Año</label>
            <input type="number" name="anio" class="form-control form-control-sm"
                   min="2024" max="2050"
                   value="<?php echo date('Y'); ?>" required>
        </div>

        <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-primary w-100">Crear</button>
        </div>
    </form>
</div>

<!-- Listado de todas las elecciones -->
<div class="modulo-subtitulo mb-2">Todas las elecciones</div>
<div class="table-responsive">
    <table class="table table-hover table-bordered align-middle" style="font-size:0.85rem;">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Año</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($elecciones as $e): ?>
            <tr>
                <td><?php echo htmlspecialchars($e['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <span class="badge" style="background-color:#1a1a2e;color:#fff;">
                        <?php echo strtoupper($e['tipo']); ?>
                    </span>
                </td>
                <td><?php echo $e['anio']; ?></td>
                <td>
                    <?php if ($e['estado'] === 'activa'): ?>
                        <span class="badge bg-success">ACTIVA</span>
                    <?php elseif ($e['estado'] === 'programada'): ?>
                        <span class="badge bg-warning text-dark">PROGRAMADA</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">CERRADA</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="d-flex gap-1 flex-wrap">

                        <?php if ($e['estado'] === 'programada'): ?>
                        <!-- Activar -->
                        <form method="POST" action="index.php?mod=abm_elecciones"
                              onsubmit="return confirm('Activar <?php echo htmlspecialchars($e['nombre'], ENT_QUOTES, 'UTF-8'); ?>?');">
                            <input type="hidden" name="accion" value="activar_eleccion">
                            <input type="hidden" name="id_eleccion" value="<?php echo $e['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-success">Activar</button>
                        </form>
                        <?php endif; ?>

                        <?php if ($e['estado'] === 'activa'): ?>
                        <!-- Desactivar: solo si no hay dias habilitados -->
                        <form method="POST" action="index.php?mod=abm_elecciones"
                              onsubmit="return confirm('Desactivar <?php echo htmlspecialchars($e['nombre'], ENT_QUOTES, 'UTF-8'); ?>? Volvera a estado programada.');">
                            <input type="hidden" name="accion" value="desactivar_eleccion">
                            <input type="hidden" name="id_eleccion" value="<?php echo $e['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-warning"
                                <?php echo ($dias_habilitados_por_eleccion[$e['id']] ?? 0) ? 'disabled title="Hay dias habilitados"' : ''; ?>>
                                Desactivar
                            </button>
                        </form>

                        <!-- Cerrar y migrar votos: solo si no hay dias habilitados -->
                        <form method="POST" action="index.php?mod=abm_elecciones"
                              onsubmit="return confirm('CERRAR la eleccion y migrar los votos a participacion_electoral. Esta accion no se puede deshacer. Continuar?');">
                            <input type="hidden" name="accion" value="cerrar_eleccion">
                            <input type="hidden" name="id_eleccion" value="<?php echo $e['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger"
                                <?php echo ($dias_habilitados_por_eleccion[$e['id']] ?? 0) ? 'disabled title="Hay dias habilitados"' : ''; ?>>
                                Cerrar y migrar
                            </button>
                        </form>
                        <?php endif; ?>

                        <!-- Ver dias: siempre disponible para programada y activa -->
                        <?php if ($e['estado'] !== 'cerrada'): ?>
                        <a href="index.php?mod=abm_elecciones&pestana=dias&id_eleccion=<?php echo $e['id']; ?>"
                           class="btn btn-sm btn-outline-secondary">
                            Dias
                        </a>
                        <?php endif; ?>

                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>


<?php elseif ($pestana === 'dias'): ?>
<!-- ============================================================ -->
<!-- PESTANA 2 — DIAS                                             -->
<!-- ============================================================ -->

<!-- Selector de eleccion -->
<div class="mb-3">
    <form method="GET" action="index.php" class="row g-2 align-items-end">
        <input type="hidden" name="mod" value="abm_elecciones">
        <input type="hidden" name="pestana" value="dias">
        <div class="col-md-4">
            <label class="form-label form-label-sm">Eleccion</label>
            <select name="id_eleccion" class="form-select form-select-sm"
                    onchange="this.form.submit()">
                <option value="">— elegir eleccion —</option>
                <?php foreach ($elecciones_disponibles as $ed): ?>
                <option value="<?php echo $ed['id']; ?>"
                    <?php echo $id_eleccion === $ed['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($ed['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                    (<?php echo strtoupper($ed['tipo']); ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if ($id_eleccion && $eleccion_activa): ?>

<!-- Formulario: crear nuevo dia -->
<div class="mb-4">
    <div class="modulo-subtitulo mb-2">Nuevo dia</div>
    <form method="POST" action="index.php?mod=abm_elecciones" class="row g-2 align-items-end">
        <input type="hidden" name="accion" value="crear_dia">
        <input type="hidden" name="id_eleccion" value="<?php echo $id_eleccion; ?>">
        <div class="col-md-3">
            <label class="form-label form-label-sm">Nombre del dia</label>
            <input type="text" name="nombre_dia" class="form-control form-control-sm"
                   placeholder="Ej: Lunes, Martes" required maxlength="30">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-primary w-100">Crear dia</button>
        </div>
    </form>
</div>

<!-- Listado de dias de la eleccion -->
<div class="modulo-subtitulo mb-2">
    Dias de <?php echo htmlspecialchars($eleccion_activa['nombre'], ENT_QUOTES, 'UTF-8'); ?>
</div>

<?php if (empty($dias)): ?>
    <p class="text-secondary" style="font-size:0.85rem;">No hay dias creados para esta eleccion.</p>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-hover table-bordered align-middle" style="font-size:0.85rem;">
        <thead>
            <tr>
                <th>Dia</th>
                <th>Mesas</th>
                <th>En uso</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dias as $d): ?>
            <tr>
                <td><?php echo htmlspecialchars($d['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo $d['total_mesas']; ?></td>
                <td><?php echo $d['mesas_en_uso']; ?></td>
                <td>
                    <?php if ($d['habilitado']): ?>
                        <span class="badge bg-success">HABILITADO</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">DESHABILITADO</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="d-flex gap-1 flex-wrap">

                        <?php if (!$d['habilitado']): ?>
                        <!-- Habilitar dia -->
                        <form method="POST" action="index.php?mod=abm_elecciones"
                              onsubmit="return confirm('Habilitar el dia <?php echo htmlspecialchars($d['nombre'], ENT_QUOTES, 'UTF-8'); ?>? Los fiscales podran ver sus mesas.');">
                            <input type="hidden" name="accion" value="habilitar_dia">
                            <input type="hidden" name="id_dia" value="<?php echo $d['id']; ?>">
                            <input type="hidden" name="id_eleccion" value="<?php echo $id_eleccion; ?>">
                            <button type="submit" class="btn btn-sm btn-success">Habilitar</button>
                        </form>
                        <?php else: ?>
                        <!-- Deshabilitar dia -->
                        <form method="POST" action="index.php?mod=abm_elecciones"
                              onsubmit="return confirm('Deshabilitar el dia <?php echo htmlspecialchars($d['nombre'], ENT_QUOTES, 'UTF-8'); ?>? Las mesas quedaran liberadas.');">
                            <input type="hidden" name="accion" value="deshabilitar_dia">
                            <input type="hidden" name="id_dia" value="<?php echo $d['id']; ?>">
                            <input type="hidden" name="id_eleccion" value="<?php echo $id_eleccion; ?>">
                            <button type="submit" class="btn btn-sm btn-warning">Deshabilitar</button>
                        </form>
                        <?php endif; ?>

                        <!-- Ver mesas del dia -->
                        <a href="index.php?mod=abm_elecciones&pestana=mesas&id_dia=<?php echo $d['id']; ?>&id_eleccion=<?php echo $id_eleccion; ?>"
                           class="btn btn-sm btn-outline-secondary">
                            Mesas
                        </a>

                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php endif; // fin if id_eleccion ?>


<?php elseif ($pestana === 'mesas'): ?>
<!-- ============================================================ -->
<!-- PESTANA 3 — MESAS                                            -->
<!-- ============================================================ -->

<!-- Cuando se llega a la pestana sin id_dia (desde el navbar),
     se muestran dos selectores encadenados: eleccion -> dia.
     Cuando se llega con id_dia (desde el boton Mesas de la pestana Dias),
     se muestra directamente el contenido de las mesas. -->

<?php if (!$id_dia): ?>

<!-- Selector de eleccion -->
<form method="GET" action="index.php" class="mb-3">
    <input type="hidden" name="mod" value="abm_elecciones">
    <input type="hidden" name="pestana" value="mesas">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label form-label-sm">Eleccion</label>
            <select name="id_eleccion" class="form-select form-select-sm"
                    onchange="this.form.submit()">
                <option value="">— elegir eleccion —</option>
                <?php foreach ($elecciones_disponibles as $ed): ?>
                <option value="<?php echo $ed['id']; ?>"
                    <?php echo $id_eleccion === intval($ed['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($ed['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                    (<?php echo strtoupper($ed['tipo']); ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</form>

<?php if ($id_eleccion && $eleccion_activa): ?>

<!-- Selector de dia — aparece una vez elegida la eleccion -->
<form method="GET" action="index.php" class="mb-3">
    <input type="hidden" name="mod" value="abm_elecciones">
    <input type="hidden" name="pestana" value="mesas">
    <input type="hidden" name="id_eleccion" value="<?php echo $id_eleccion; ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label form-label-sm">Dia</label>
            <select name="id_dia" class="form-select form-select-sm"
                    onchange="this.form.submit()">
                <option value="">— elegir dia —</option>
                <?php foreach ($dias as $d): ?>
                <option value="<?php echo $d['id']; ?>">
                    <?php echo htmlspecialchars($d['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($d['habilitado']): ?>
                        <span>(HABILITADO)</span>
                    <?php endif; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</form>

<?php endif; // fin if id_eleccion sin id_dia ?>

<?php else: ?>
<!-- Llegamos con id_dia desde el boton Mesas de la pestana Dias -->

<div class="mb-3">
    <?php if ($id_eleccion && $eleccion_activa): ?>
    <div class="mb-2">
        <a href="index.php?mod=abm_elecciones&pestana=dias&id_eleccion=<?php echo $id_eleccion; ?>"
           class="btn btn-sm btn-outline-secondary">
            &larr; Volver a dias de <?php echo htmlspecialchars($eleccion_activa['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
        </a>
    </div>
    <?php endif; ?>

    <?php if ($dia_activo): ?>
    <div style="font-size:0.9rem;" class="mb-3">
        <strong>Dia:</strong>
        <?php echo htmlspecialchars($dia_activo['nombre'], ENT_QUOTES, 'UTF-8'); ?>
        &nbsp;
        <?php if ($dia_activo['habilitado']): ?>
            <span class="badge bg-success">HABILITADO</span>
        <?php else: ?>
            <span class="badge bg-secondary">DESHABILITADO</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php endif; // fin if !id_dia ?>

<?php if ($id_dia && $dia_activo): ?>

<!-- Formulario: crear nueva mesa -->
<div class="mb-4">
    <div class="modulo-subtitulo mb-2">Nueva mesa</div>
    <form method="POST" action="index.php?mod=abm_elecciones" class="row g-2 align-items-end">
        <input type="hidden" name="accion" value="crear_mesa">
        <input type="hidden" name="id_dia" value="<?php echo $id_dia; ?>">
        <input type="hidden" name="id_eleccion" value="<?php echo $id_eleccion; ?>">

        <div class="col-md-3">
            <label class="form-label form-label-sm">Nombre de la mesa</label>
            <input type="text" name="nombre_mesa" class="form-control form-control-sm"
                   placeholder="Ej: CD - LU - M1" required maxlength="60">
        </div>

        <div class="col-md-3">
            <label class="form-label form-label-sm">Password</label>
            <input type="text" name="password_mesa" class="form-control form-control-sm"
                   placeholder="Password del fiscal" required maxlength="60">
        </div>

        <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-primary w-100">Crear mesa</button>
        </div>
    </form>
</div>

<!-- Listado de mesas del dia -->
<div class="modulo-subtitulo mb-2">Mesas del dia</div>

<?php if (empty($mesas)): ?>
    <p class="text-secondary" style="font-size:0.85rem;">No hay mesas creadas para este dia.</p>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-hover table-bordered align-middle" style="font-size:0.85rem;">
        <thead>
            <tr>
                <th>Mesa</th>
                <th>Tipo</th>
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
                </td>
                <td>
                    <?php if ($m['en_uso']): ?>
                        <span class="badge bg-success">EN USO</span>
                    <?php else: ?>
                        <span class="text-secondary">Libre</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="d-flex gap-1 flex-wrap">

                        <?php if ($m['en_uso']): ?>
                        <!-- Liberar mesa caida -->
                        <form method="POST" action="index.php?mod=abm_elecciones"
                              onsubmit="return confirm('Liberar la mesa <?php echo htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8'); ?>? El fiscal activo perdera su sesion.');">
                            <input type="hidden" name="accion" value="liberar_mesa">
                            <input type="hidden" name="id_mesa" value="<?php echo $m['id']; ?>">
                            <input type="hidden" name="id_dia" value="<?php echo $id_dia; ?>">
                            <input type="hidden" name="id_eleccion" value="<?php echo $id_eleccion; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Liberar</button>
                        </form>
                        <?php endif; ?>

                        <!-- Editar nombre: modal inline -->
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                data-bs-toggle="modal"
                                data-bs-target="#modalNombre<?php echo $m['id']; ?>">
                            Nombre
                        </button>

                        <!-- Cambiar password: modal inline -->
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                data-bs-toggle="modal"
                                data-bs-target="#modalPassword<?php echo $m['id']; ?>">
                            Password
                        </button>

                    </div>
                </td>
            </tr>

            <!-- Modal editar nombre de la mesa -->
            <div class="modal fade" id="modalNombre<?php echo $m['id']; ?>"
                 tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h6 class="modal-title">
                                Editar nombre — <?php echo htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            </h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" action="index.php?mod=abm_elecciones">
                            <input type="hidden" name="accion" value="editar_nombre_mesa">
                            <input type="hidden" name="id_mesa" value="<?php echo $m['id']; ?>">
                            <input type="hidden" name="id_dia" value="<?php echo $id_dia; ?>">
                            <input type="hidden" name="id_eleccion" value="<?php echo $id_eleccion; ?>">
                            <div class="modal-body">
                                <input type="text" name="nuevo_nombre"
                                       class="form-control form-control-sm"
                                       value="<?php echo htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                                       required maxlength="60">
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modal cambio de password para esta mesa -->
            <div class="modal fade" id="modalPassword<?php echo $m['id']; ?>"
                 tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h6 class="modal-title">
                                Cambiar password — <?php echo htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            </h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" action="index.php?mod=abm_elecciones">
                            <input type="hidden" name="accion" value="cambiar_password">
                            <input type="hidden" name="id_mesa" value="<?php echo $m['id']; ?>">
                            <input type="hidden" name="id_dia" value="<?php echo $id_dia; ?>">
                            <input type="hidden" name="id_eleccion" value="<?php echo $id_eleccion; ?>">
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
<?php endif; ?>

<?php endif; // fin if id_dia ?>

<?php endif; // fin switch pestana ?>

<?php require_once 'includes/footer.php'; ?>
