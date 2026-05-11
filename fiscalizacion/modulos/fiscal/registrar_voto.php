<?php
// fiscalizacion/modulos/fiscal/registrar_voto.php
// Endpoint para registrar el voto de una persona.
// Recibe POST: dni, tipo_voto
// Devuelve JSON: exito o error
// Acceso: solo fiscales autenticados con sesion activa.

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

$dni        = intval($_POST['dni']       ?? 0);
$tipo_voto  = trim($_POST['tipo_voto']   ?? '');
$id_mesa    = $_SESSION['id_mesa'];
$id_eleccion = $_SESSION['id_eleccion'];

// Validaciones basicas
if ($dni <= 0) {
    echo json_encode(['error' => 'DNI invalido']);
    exit;
}

if (!in_array($tipo_voto, ['regular', 'observado'])) {
    echo json_encode(['error' => 'Tipo de voto invalido']);
    exit;
}

// Verificar que no haya votado ya
$stmt = $pdo->prepare("
    SELECT id FROM votos_dia
    WHERE dni = ? AND id_eleccion = ?
");
$stmt->execute([$dni, $id_eleccion]);
if ($stmt->fetch()) {
    echo json_encode(['error' => 'Esta persona ya voto en esta eleccion']);
    exit;
}

// Registrar el voto
try {
    $stmt = $pdo->prepare("
        INSERT INTO votos_dia (dni, id_mesa, id_eleccion, tipo_voto)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$dni, $id_mesa, $id_eleccion, $tipo_voto]);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    // El UNIQUE KEY sobre (dni, id_eleccion) puede dispararse por concurrencia
    echo json_encode(['error' => 'No se pudo registrar el voto. Intenta de nuevo.']);
}
