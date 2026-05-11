<?php
// modulos/abm_referentes/abm_referentes.php
// ABM de referentes politicos.
// Acceso: admin y superadmin.
// Buscador por apellido o nombre arriba del listado.
// Formulario agregar en collapse — requiere gesto explicito para abrir.
// Botones editar y dar de baja en la misma fila.
// Boton de descarga Excel del listado completo (con ID).
// Nunca se elimina un registro fisicamente.

verificar_admin();

// --- Exportacion a Excel ---
// Se procesa antes de cualquier output HTML para no romper los headers HTTP.
// Se activa con GET accion=descargar. Ignora el filtro de busqueda: siempre exporta el listado completo.
if (($_GET['accion'] ?? '') === 'descargar') {

    require_once 'includes/excel.php';

    // Consulta sin filtro de busqueda: listado completo ordenado igual que la pantalla
    $stmt = $pdo->prepare("
        SELECT
            id,
            apellido,
            nombre,
            CASE WHEN aplica_cd = 1 THEN 'SI' ELSE 'NO' END AS aplica_cd,
            CASE WHEN aplica_cp = 1 THEN 'SI' ELSE 'NO' END AS aplica_cp,
            CASE WHEN activo    = 1 THEN 'Activo' ELSE 'Baja' END AS estado
        FROM referentes
        ORDER BY apellido ASC, nombre ASC
    ");
    $stmt->execute();
    $datos_excel = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Las columnas se construyen dinamicamente desde las claves del primer registro.
    // El nombre del archivo sigue el patron del proyecto: nombre-YYYY-MM-DD.xlsx
    exportar_excel($datos_excel, 'referentes-' . date('Y-m-d'));
    exit;
}

$mensaje      = '';
$error        = '';
$abrir_form   = false; // controla si el collapse de agregar se abre automaticamente

// --- Procesar acciones POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar') {

        $apellido  = trim($_POST['apellido']  ?? '');
        $nombre    = trim($_POST['nombre']    ?? '');
        $aplica_cd = isset($_POST['aplica_cd']) ? 1 : 0;
        $aplica_cp = isset($_POST['aplica_cp']) ? 1 : 0;

        if ($apellido === '' || $nombre === '') {
            $error      = 'Apellido y nombre son obligatorios.';
            $abrir_form = true; // reabrir el collapse si hay error
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

        $id        = intval($_POST['id']       ?? 0);
        $apellido  = trim($_POST['apellido']   ?? '');
        $nombre    = trim($_POST['nombre']     ?? '');
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
$editando  = null;
$id_editar = intval($_GET['editar'] ?? 0);
if ($id_editar > 0) {
    $stmt = $pdo->prepare("SELECT * FROM referentes WHERE id = :id");
    $stmt->execute([':id' => $id_editar]);
    $editando   = $stmt->fetch();
    $abrir_form = true; // abrir collapse al editar
}

// --- Busqueda ---
$busqueda = trim($_GET['q'] ?? '');
$params_q = [];
$where_q  = '';
if ($busqueda !== '') {
    $where_q       = "WHERE CONCAT(apellido, ' ', nombre) LIKE :q";
    $params_q[':q'] = '%' . strtoupper($busqueda) . '%';
}

// --- Listar referentes (pantalla: respeta el filtro de busqueda si existe) ---
$stmt       = $pdo->prepare("SELECT * FROM referentes $where_q ORDER BY apellido ASC, nombre ASC");
$stmt->execute($params_q);
$referentes = $stmt->fetchAll();

require_once 'includes/navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="modulo-titulo mb-0">Referentes</div>
    <div class="d-flex gap-2">
        <!-- Descarga el listado completo (sin filtro de busqueda) con ID incluido -->
        <a href="index.php?mod=abm_referentes&accion=descargar"
           class="btn btn-outline-secondary btn-sm">
            Descargar Excel
        </a>
        <button class="btn btn-acento btn-sm"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#form-agregar"
            aria-expanded="<?php echo $abrir_form ? 'true' : 'false'; ?>">
            + Agregar referente
        </button>
    </div>
</div>

<?php if ($mensaje !== ''): ?>
    <div class="alert alert-success" style="font-size:0.875rem;"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger" style="font-size:0.875rem;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<!-- Collapse: formulario agregar / editar -->
<div class="collapse <?php echo $abrir_form ? 'show' : ''; ?> mb-4" id="form-agregar">
    <div class="card" style="border-color:#d1d5db;">
        <div class="card-body">
            <h6 class="card-title" style="font-weight:600;color:#1a1a2e;">
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
</div>

<!-- Buscador -->
<form method="GET" action="index.php" class="mb-3">
    <input type="hidden" name="mod" value="abm_referentes">
    <div class="row g-2 align-items-center">
        <div class="col-md-4">
            <input type="text" name="q" class="form-control form-control-sm"
                placeholder="Buscar por apellido o nombre"
                value="<?php echo htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-secondary btn-sm">Buscar</button>
        </div>
        <?php if ($busqueda !== ''): ?>
        <div class="col-auto">
            <a href="index.php?mod=abm_referentes" class="btn btn-outline-secondary btn-sm">Limpiar</a>
        </div>
        <?php endif; ?>
    </div>
</form>

<!-- Listado -->
<div class="table-responsive">
    <table class="table table-hover table-bordered align-middle">
        <thead>
            <tr>
                <th>Apellido</th>
                <th>Nombre</th>
                <th>CD</th>
                <th>CP</th>
                <th>Estado</th>
                <th style="width:140px;"></th>
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
                    <span class="badge"
                        style="background-color:<?php echo $r['activo'] ? '#a6d900' : '#6c757d'; ?>;
                               color:<?php echo $r['activo'] ? '#1a1a2e' : '#fff'; ?>;">
                        <?php echo $r['activo'] ? 'Activo' : 'Baja'; ?>
                    </span>
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="index.php?mod=abm_referentes&editar=<?php echo $r['id']; ?>"
                            class="btn btn-sm btn-acento">Editar</a>
                        <form method="POST" action="index.php?mod=abm_referentes">
                            <input type="hidden" name="accion" value="<?php echo $r['activo'] ? 'baja' : 'alta'; ?>">
                            <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                <?php echo $r['activo'] ? 'Dar de baja' : 'Reactivar'; ?>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($referentes)): ?>
            <tr><td colspan="6" class="text-secondary">No se encontraron resultados.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
