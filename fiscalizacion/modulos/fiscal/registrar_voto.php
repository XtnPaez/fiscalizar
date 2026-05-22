<?php
// fiscalizacion/modulos/fiscal/registrar_voto.php
// Endpoint para registrar el voto de una persona.
// Recibe POST: dni, tipo_voto
// Devuelve JSON: exito o error
// Acceso: solo fiscales autenticados con sesion activa.
//
// Cambios respecto a la version anterior:
//   - votos_dia no tiene id_eleccion. El voto se registra solo con dni e id_mesa.
//   - Para verificar si ya voto, se cruza por las mesas de la eleccion activa
//     del tipo correspondiente via dias_eleccion -> elecciones.
//   - El UNIQUE KEY de votos_dia es sobre (dni, id_mesa) o similar — la unicidad
//     se garantiza verificando antes del INSERT.

session_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';

// Verificar sesion y rol
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'fiscal') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json');

$dni       = intval($_POST['dni']      ?? 0);
$tipo_voto = trim($_POST['tipo_voto']  ?? '');
$id_mesa   = $_SESSION['id_mesa'];
$tipo      = $_SESSION['tipo_mesa'];

// Validaciones basicas
if ($dni <= 0) {
    echo json_encode(['error' => 'DNI invalido']);
    exit;
}

if (!in_array($tipo_voto, ['regular', 'observado'])) {
    echo json_encode(['error' => 'Tipo de voto invalido']);
    exit;
}

// Verificar que la mesa sigue en uso — si el admin la libero, la sesion ya no es valida
$stmt = $pdo->prepare("SELECT en_uso FROM mesas WHERE id = ?");
$stmt->execute([$id_mesa]);
$mesa_estado = $stmt->fetchColumn();

if ($mesa_estado === false || intval($mesa_estado) === 0) {
    echo json_encode(['error' => 'mesa_liberada']);
    exit;
}

// Verificar que la persona no haya votado ya en ninguna mesa de esta eleccion
// Se cruza por todas las mesas activas del mismo tipo de eleccion
$stmt = $pdo->prepare("
    SELECT v.id FROM votos_dia v
    JOIN mesas m         ON v.id_mesa = m.id
    JOIN dias_eleccion d ON m.id_dia = d.id
    JOIN elecciones e    ON d.id_eleccion = e.id
    WHERE v.dni = ?
      AND e.tipo = ?
      AND e.estado = 'activa'
    LIMIT 1
");
$stmt->execute([$dni, $tipo]);

if ($stmt->fetch()) {
    echo json_encode(['error' => 'Esta persona ya voto en esta eleccion']);
    exit;
}

// Verificar que el DNI exista en el padron correspondiente
$tabla = match($tipo) {
    'cd' => 'padron_cd',
    'cp' => 'padron_cp',
    'rt' => 'padron_rt',
    'cs' => 'padron_cs',
    'cc' => 'padron_cc',
    default => null
};

if (!$tabla) {
    echo json_encode(['error' => 'Tipo de mesa invalido']);
    exit;
}

$stmt = $pdo->prepare("SELECT dni FROM $tabla WHERE dni = ?");
$stmt->execute([$dni]);
if (!$stmt->fetch()) {
    echo json_encode(['error' => 'El DNI no figura en el padron de esta eleccion']);
    exit;
}

// Registrar el voto
// El INSERT usa solo dni, id_mesa y tipo_voto — sin id_eleccion
try {
    $stmt = $pdo->prepare("
        INSERT INTO votos_dia (dni, id_mesa, tipo_voto)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$dni, $id_mesa, $tipo_voto]);

    // Verificar que realmente se inserto la fila
    // rowCount() = 0 significa que el INSERT no afecto ninguna fila
    // aunque no haya tirado excepcion (ej: UNIQUE KEY silencioso)
    if ($stmt->rowCount() > 0) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['error' => 'voto_no_insertado', 'dni' => $dni]);
    }

} catch (Exception $e) {
    // Puede dispararse por concurrencia si dos mesas registran el mismo DNI
    echo json_encode(['error' => 'voto_no_insertado', 'dni' => $dni]);
}
