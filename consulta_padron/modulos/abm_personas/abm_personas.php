<?php
// modulos/abm_personas/abm_personas.php
// ABM de personas — edicion de vinculos de cada graduado.
// Acceso: admin y superadmin.
// Flujo: buscar por apellido o DNI -> ver perfil completo -> editar vinculos.
// Se editan: referentes_graduado, persona_partido, persona_trabajo.
// No se modifican personas, padron_cd ni padron_cp.

verificar_admin();

$mensaje = '';
$error   = '';

// --- Procesar edicion de vinculos ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'editar') {

    $dni         = intval($_POST['dni']         ?? 0);
    $referente_1 = intval($_POST['referente_1'] ?? 0) ?: null;
    $referente_2 = intval($_POST['referente_2'] ?? 0) ?: null;
    $referente_3 = intval($_POST['referente_3'] ?? 0) ?: null;
    $id_partido  = intval($_POST['id_partido']  ?? 0) ?: null;
    $id_trabajo  = intval($_POST['id_trabajo']  ?? 0) ?: null;

    if ($dni <= 0) {
        $error = 'DNI invalido.';
    } else {

        // Actualizar referentes_graduado
        $stmt = $pdo->prepare("SELECT dni FROM referentes_graduado WHERE dni = :dni");
        $stmt->execute([':dni' => $dni]);
        $tiene_referentes = $stmt->fetch();

        if ($tiene_referentes) {
            $stmt = $pdo->prepare("
                UPDATE referentes_graduado
                SET referente_1 = :r1, referente_2 = :r2, referente_3 = :r3
                WHERE dni = :dni
            ");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO referentes_graduado (dni, referente_1, referente_2, referente_3)
                VALUES (:dni, :r1, :r2, :r3)
            ");
        }
        $stmt->execute([':dni' => $dni, ':r1' => $referente_1, ':r2' => $referente_2, ':r3' => $referente_3]);

        // Actualizar persona_partido
        if ($id_partido !== null) {
            $stmt = $pdo->prepare("SELECT dni FROM persona_partido WHERE dni = :dni");
            $stmt->execute([':dni' => $dni]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE persona_partido SET id_partido = :id_partido WHERE dni = :dni");
            } else {
                $stmt = $pdo->prepare("INSERT INTO persona_partido (dni, id_partido) VALUES (:dni, :id_partido)");
            }
            $stmt->execute([':dni' => $dni, ':id_partido' => $id_partido]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM persona_partido WHERE dni = :dni");
            $stmt->execute([':dni' => $dni]);
        }

        // Actualizar persona_trabajo
        if ($id_trabajo !== null) {
            $stmt = $pdo->prepare("SELECT dni FROM persona_trabajo WHERE dni = :dni");
            $stmt->execute([':dni' => $dni]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE persona_trabajo SET id_trabajo = :id_trabajo WHERE dni = :dni");
            } else {
                $stmt = $pdo->prepare("INSERT INTO persona_trabajo (dni, id_trabajo) VALUES (:dni, :id_trabajo)");
            }
            $stmt->execute([':dni' => $dni, ':id_trabajo' => $id_trabajo]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM persona_trabajo WHERE dni = :dni");
            $stmt->execute([':dni' => $dni]);
        }

        $mensaje = 'Vinculos actualizados correctamente.';
    }
}

// --- Leer parametros de navegacion ---
$busqueda   = trim($_GET['q']        ?? '');
$dni_perfil = intval($_GET['perfil'] ?? 0);
$busco      = $busqueda !== '';

$resultados = [];
$perfil     = null;
$vinculos   = [];

// --- Busqueda ---
if ($busco) {

    if (ctype_digit($busqueda)) {
        $stmt = $pdo->prepare("
            SELECT dni, apellido, nombre FROM personas WHERE dni = :dni
        ");
        $stmt->execute([':dni' => $busqueda]);
    } else {
        $stmt = $pdo->prepare("
            SELECT dni, apellido, nombre FROM personas
            WHERE apellido LIKE :apellido
            ORDER BY apellido ASC, nombre ASC
        ");
        $stmt->execute([':apellido' => strtoupper($busqueda) . '%']);
    }

    $resultados = $stmt->fetchAll();

    // Si hay un unico resultado ir directo al perfil
    if (count($resultados) === 1) {
        $dni_perfil = $resultados[0]['dni'];
        $resultados = [];
    }
}

// --- Cargar perfil y vinculos actuales ---
if ($dni_perfil > 0) {

    // Datos basicos de la persona
    $stmt = $pdo->prepare("SELECT * FROM personas WHERE dni = :dni");
    $stmt->execute([':dni' => $dni_perfil]);
    $perfil = $stmt->fetch();

    if ($perfil) {

        // En que padrones figura — dos parametros separados para evitar HY093
        $stmt = $pdo->prepare("
            SELECT 'CD' AS padron FROM padron_cd WHERE dni = :dni_cd
            UNION
            SELECT 'CP' AS padron FROM padron_cp WHERE dni = :dni_cp
        ");
        $stmt->execute([':dni_cd' => $dni_perfil, ':dni_cp' => $dni_perfil]);
        $padrones_persona = array_column($stmt->fetchAll(), 'padron');

        // Referentes actuales
        $stmt = $pdo->prepare("SELECT * FROM referentes_graduado WHERE dni = :dni");
        $stmt->execute([':dni' => $dni_perfil]);
        $vinculos['referentes'] = $stmt->fetch() ?: ['referente_1' => null, 'referente_2' => null, 'referente_3' => null];

        // Partido actual
        $stmt = $pdo->prepare("SELECT id_partido FROM persona_partido WHERE dni = :dni");
        $stmt->execute([':dni' => $dni_perfil]);
        $fila = $stmt->fetch();
        $vinculos['id_partido'] = $fila ? $fila['id_partido'] : null;

        // Trabajo actual
        $stmt = $pdo->prepare("SELECT id_trabajo FROM persona_trabajo WHERE dni = :dni");
        $stmt->execute([':dni' => $dni_perfil]);
        $fila = $stmt->fetch();
        $vinculos['id_trabajo'] = $fila ? $fila['id_trabajo'] : null;

        // Participacion electoral
        $stmt = $pdo->prepare("
            SELECT e.nombre, pe.fecha_registro
            FROM participacion_electoral pe
            JOIN elecciones e ON pe.id_eleccion = e.id
            WHERE pe.dni = :dni
            ORDER BY e.anio ASC, e.tipo ASC
        ");
        $stmt->execute([':dni' => $dni_perfil]);
        $vinculos['participacion'] = $stmt->fetchAll();
    }
}

// --- Cargar opciones para los combos del formulario de edicion ---
$stmt_referentes   = $pdo->query("SELECT id, apellido, nombre FROM referentes WHERE activo = 1 ORDER BY apellido ASC, nombre ASC");
$opciones_referentes = $stmt_referentes->fetchAll();

$stmt_partidos     = $pdo->query("SELECT id, nombre FROM partidos WHERE activo = 1 ORDER BY nombre ASC");
$opciones_partidos = $stmt_partidos->fetchAll();

$stmt_trabajos     = $pdo->query("SELECT id, nombre FROM trabajos WHERE activo = 1 ORDER BY nombre ASC");
$opciones_trabajos = $stmt_trabajos->fetchAll();

require_once 'includes/navbar.php';
?>

<div class="modulo-titulo">Personas</div>

<?php if ($mensaje !== ''): ?>
    <div class="alert alert-success alerta-acceso"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger alerta-acceso"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<!-- Paso 1: Busqueda -->
<form method="GET" action="index.php" class="mb-4">
    <input type="hidden" name="mod" value="abm_personas">
    <div class="row g-2 align-items-center">
        <div class="col-md-5">
            <input type="text" name="q" class="form-control form-control-sm"
                placeholder="Apellido o DNI"
                value="<?php echo htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?>"
                autofocus>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-acento btn-sm">Buscar</button>
        </div>
        <?php if ($busco || $dni_perfil > 0): ?>
        <div class="col-auto">
            <a href="index.php?mod=abm_personas" class="btn btn-outline-secondary btn-sm">Limpiar</a>
        </div>
        <?php endif; ?>
    </div>
</form>

<?php if ($busco && !empty($resultados)): ?>
<!-- Paso 2a: Multiples resultados -->
<div class="table-responsive">
    <table class="table table-hover table-bordered align-middle">
        <thead>
            <tr>
                <th>DNI</th>
                <th>Apellido</th>
                <th>Nombre</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($resultados as $fila): ?>
            <tr>
                <td><?php echo htmlspecialchars($fila['dni'],      ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($fila['apellido'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($fila['nombre'],   ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <a href="index.php?mod=abm_personas&perfil=<?php echo $fila['dni']; ?>"
                        class="btn btn-sm btn-acento">Seleccionar</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php elseif ($busco && empty($resultados) && $dni_perfil === 0): ?>
<p class="text-secondary">No se encontraron resultados.</p>

<?php endif; ?>

<?php if ($perfil !== null): ?>
<!-- Paso 2b / 3: Ver perfil y editar vinculos -->

<div class="modulo-titulo" style="font-size:1rem;">
    <?php echo htmlspecialchars($perfil['apellido'] . ', ' . $perfil['nombre'], ENT_QUOTES, 'UTF-8'); ?>
    <span class="text-secondary ms-2" style="font-size:0.85rem; font-weight:400;">
        DNI <?php echo htmlspecialchars($perfil['dni'], ENT_QUOTES, 'UTF-8'); ?>
    </span>
    <?php foreach ($padrones_persona as $pad): ?>
        <span class="badge ms-1" style="background-color:#a6d900; color:#1a1a2e; font-size:0.75rem;">
            Padron <?php echo $pad; ?>
        </span>
    <?php endforeach; ?>
</div>

<!-- Participacion electoral — solo lectura -->
<?php if (!empty($vinculos['participacion'])): ?>
<div class="mb-3">
    <span style="font-size:0.8rem; font-weight:500; color:#4a5568;">Elecciones en que voto:</span>
    <?php foreach ($vinculos['participacion'] as $p): ?>
        <span class="badge ms-1" style="background-color:#1a1a2e; color:#fff; font-size:0.75rem;">
            <?php echo htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8'); ?>
        </span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Formulario de edicion de vinculos -->
<form method="POST" action="index.php?mod=abm_personas">
    <input type="hidden" name="accion" value="editar">
    <input type="hidden" name="dni" value="<?php echo $perfil['dni']; ?>">

    <div class="card" style="border-color:#d1d5db;">
        <div class="card-body">
            <h6 class="card-title" style="font-weight:600; color:#1a1a2e;">Editar vinculos</h6>

            <!-- Referentes -->
            <div class="row g-2 mb-3">
                <?php foreach ([1, 2, 3] as $n): ?>
                <div class="col-12">
                    <label class="form-label" style="font-size:0.8rem;">Referente <?php echo $n; ?></label>
                    <select name="referente_<?php echo $n; ?>" class="form-select form-select-sm">
                        <option value="">Sin referente</option>
                        <?php foreach ($opciones_referentes as $r): ?>
                        <option value="<?php echo $r['id']; ?>"
                            <?php echo $vinculos['referentes']['referente_' . $n] == $r['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r['apellido'] . ', ' . $r['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Partido -->
            <div class="mb-3">
                <label class="form-label" style="font-size:0.8rem;">Partido</label>
                <select name="id_partido" class="form-select form-select-sm">
                    <option value="">Sin partido</option>
                    <?php foreach ($opciones_partidos as $p): ?>
                    <option value="<?php echo $p['id']; ?>"
                        <?php echo $vinculos['id_partido'] == $p['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Trabajo -->
            <div class="mb-3">
                <label class="form-label" style="font-size:0.8rem;">Trabajo</label>
                <select name="id_trabajo" class="form-select form-select-sm">
                    <option value="">Sin trabajo</option>
                    <?php foreach ($opciones_trabajos as $t): ?>
                    <option value="<?php echo $t['id']; ?>"
                        <?php echo $vinculos['id_trabajo'] == $t['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($t['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-acento btn-sm">Guardar cambios</button>
            <a href="index.php?mod=abm_personas" class="btn btn-outline-secondary btn-sm ms-1">Cancelar</a>

        </div>
    </div>
</form>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
