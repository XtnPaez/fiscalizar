<?php
// fiscalizacion/modulos/fiscal/buscar.php
// Endpoint AJAX para sugerencias en tiempo real en la pantalla del fiscal.
// Recibe: q (texto buscado), modo (sugerencias | buscar)
// Devuelve: JSON con array de resultados
// Acceso: solo fiscales autenticados con sesion activa.
//
// Cambios respecto a la version anterior:
//   - votos_dia no tiene id_eleccion. Para saber si una persona ya voto,
//     se cruza por id_mesa usando las mesas de la eleccion activa del tipo
//     correspondiente, obtenidas via dias_eleccion -> elecciones.

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

$q    = trim($_GET['q']    ?? '');
$modo = trim($_GET['modo'] ?? 'sugerencias');
$tipo = $_SESSION['tipo_mesa'];

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

// Determinar tabla segun tipo de mesa
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

// Limite segun modo
$limite = $modo === 'sugerencias' ? 3 : 50;

// Para saber si ya voto: buscamos en votos_dia cruzando por las mesas
// de la eleccion activa del tipo correspondiente.
// Usamos parametros posicionales (?) para el UNION implicito en el subquery.
$subquery_mesas = "
    SELECT m.id FROM mesas m
    JOIN dias_eleccion d ON m.id_dia = d.id
    JOIN elecciones e    ON d.id_eleccion = e.id
    WHERE e.tipo = ? AND e.estado = 'activa'
";

// Detectar si es busqueda por DNI (solo numeros) o por apellido
if (ctype_digit($q)) {
    // Busqueda por DNI
    $stmt = $pdo->prepare("
        SELECT p.dni, p.apellido, p.nombre,
            CASE WHEN v.id IS NOT NULL THEN 1 ELSE 0 END AS ya_voto
        FROM $tabla t
        JOIN personas p ON t.dni = p.dni
        LEFT JOIN votos_dia v ON p.dni = v.dni
            AND v.id_mesa IN ($subquery_mesas)
        WHERE t.dni LIKE ?
        ORDER BY p.apellido, p.nombre
        LIMIT $limite
    ");
    $stmt->execute([$tipo, $q . '%']);
} else {
    // Busqueda por apellido
    $stmt = $pdo->prepare("
        SELECT p.dni, p.apellido, p.nombre,
            CASE WHEN v.id IS NOT NULL THEN 1 ELSE 0 END AS ya_voto
        FROM $tabla t
        JOIN personas p ON t.dni = p.dni
        LEFT JOIN votos_dia v ON p.dni = v.dni
            AND v.id_mesa IN ($subquery_mesas)
        WHERE p.apellido LIKE ?
        ORDER BY p.apellido, p.nombre
        LIMIT $limite
    ");
    $stmt->execute([$tipo, $q . '%']);
}

$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($resultados);
