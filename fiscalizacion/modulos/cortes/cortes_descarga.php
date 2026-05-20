<?php
// fiscalizacion/modulos/cortes/cortes_descarga.php
// Generador de ZIP con Excels por referente.
// Se invoca por POST desde cortes.php con un array de ids de referentes.
//
// Por cada referente lee su campo "tipo" en referentes_cortes para saber
// que padrones consultar:
//   cd_cp  -> Hoja 1: CP sin voto | Hoja 2: CD sin voto y no en CP
//   cd_cs  -> Hoja 1: CS sin voto | Hoja 2: CD sin voto y no en CS
//   cd_rt  -> Hoja 1: RT sin voto | Hoja 2: CD sin voto y no en RT
//   cd_cc  -> Hoja 1: CC sin voto | Hoja 2: CD sin voto y no en CC
//   solo_cp -> Hoja 1: CP sin voto (sin segunda hoja)
//   solo_cd -> Hoja 1: CD sin voto (sin segunda hoja)
//   solo_cs -> Hoja 1: CS sin voto (sin segunda hoja)
//   solo_rt -> Hoja 1: RT sin voto (sin segunda hoja)
//   solo_cc -> Hoja 1: CC sin voto (sin segunda hoja)
//
// El archivo Excel se nombra APELLIDO_NOMBRE.xlsx
// Todos los Excels se empaquetan en un ZIP y se descargan.
//
// Acceso: admin, superadmin.

verificar_admin_fiscal();

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// --- Validar input ---
$ids_raw = $_POST['ids'] ?? [];
$ids = array_filter(array_map('intval', $ids_raw), fn($id) => $id > 0);

if (empty($ids)) {
    header('Location: index.php?mod=cortes&error=sin_seleccion');
    exit;
}

// --- Directorio temporal ---
$tmp_dir = sys_get_temp_dir() . '/cortes_' . uniqid();
mkdir($tmp_dir, 0700, true);

// ============================================================
// FUNCION: generar_hoja()
// Escribe un array de filas en una hoja de PhpSpreadsheet.
// ============================================================
function generar_hoja($hoja, string $titulo, array $filas): void {
    $hoja->setTitle($titulo);

    $hoja->setCellValue('A1', 'DNI');
    $hoja->setCellValue('B1', 'APELLIDO');
    $hoja->setCellValue('C1', 'NOMBRE');

    $hoja->getStyle('A1:C1')->applyFromArray([
        'fill' => [
            'fillType'   => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '1a1a2e'],
        ],
        'font' => [
            'bold'  => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size'  => 10,
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_LEFT,
        ],
    ]);

    if (empty($filas)) {
        $hoja->setCellValue('A2', 'Sin pendientes');
    } else {
        $fila_num = 2;
        foreach ($filas as $fila) {
            $hoja->setCellValue('A' . $fila_num, $fila['dni']);
            $hoja->setCellValue('B' . $fila_num, $fila['apellido']);
            $hoja->setCellValue('C' . $fila_num, $fila['nombre']);
            $fila_num++;
        }
    }

    foreach (['A', 'B', 'C'] as $col) {
        $hoja->getColumnDimension($col)->setAutoSize(true);
    }
}

// ============================================================
// FUNCION: query_no_voto()
// Devuelve referidos de un referente que NO votaron en un padron/tipo dado.
// $id_referente : int
// $tipo_padron  : string — 'cd', 'cp', 'cs', 'rt', 'cc'
// $excluir_dnis : array  — DNIs a excluir (los de la hoja principal)
// ============================================================
function query_no_voto(PDO $pdo, int $id_referente, string $tipo_padron, array $excluir_dnis = []): array {

    // Tabla de padron segun tipo
    $tabla_padron = match($tipo_padron) {
        'cd' => 'padron_cd',
        'cp' => 'padron_cp',
        'cs' => 'padron_cs',
        'rt' => 'padron_rt',
        'cc' => 'padron_cc',
        default => null
    };

    if (!$tabla_padron) return [];

    // Clausula de exclusion de DNIs ya listados en la hoja principal
    $clausula_excluir = '';
    if (!empty($excluir_dnis)) {
        $placeholders    = implode(',', array_fill(0, count($excluir_dnis), '?'));
        $clausula_excluir = "AND p.dni NOT IN ($placeholders)";
    }

    $sql = "
        SELECT DISTINCT p.dni, p.apellido, p.nombre
        FROM personas p
        JOIN referentes_graduado rg ON rg.dni = p.dni
            AND (rg.referente_1 = ? OR rg.referente_2 = ? OR rg.referente_3 = ?)
        JOIN $tabla_padron pad ON pad.dni = p.dni
        WHERE NOT EXISTS (
            SELECT 1
            FROM votos_dia v
            JOIN mesas m         ON v.id_mesa = m.id
            JOIN dias_eleccion d ON m.id_dia = d.id
            JOIN elecciones e    ON d.id_eleccion = e.id
            WHERE v.dni = p.dni
              AND e.tipo = ?
              AND e.estado = 'activa'
        )
        $clausula_excluir
        ORDER BY p.apellido, p.nombre
    ";

    // Params: 3x id_referente + tipo_padron + dnis a excluir
    $params = [$id_referente, $id_referente, $id_referente, $tipo_padron];
    foreach ($excluir_dnis as $dni) {
        $params[] = $dni;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================================
// GENERAR UN EXCEL POR REFERENTE
// ============================================================
$archivos_excel = [];

foreach ($ids as $id_referente) {

    // Obtener datos del referente y su tipo de descarga
    $stmt = $pdo->prepare("
        SELECT r.apellido, r.nombre, rc.tipo
        FROM referentes r
        JOIN referentes_cortes rc ON rc.id_referente = r.id
        WHERE r.id = ?
    ");
    $stmt->execute([$id_referente]);
    $ref = $stmt->fetch();

    if (!$ref) continue;

    $tipo = $ref['tipo']; // ej: 'cd_cp', 'solo_cp', etc.

    // Nombre del archivo
    $nombre_archivo = preg_replace('/[^A-Z0-9_]/', '',
        strtoupper(str_replace(' ', '_', $ref['apellido'] . '_' . $ref['nombre']))
    ) . '.xlsx';

    $ruta_excel = $tmp_dir . '/' . $nombre_archivo;

    $spreadsheet = new Spreadsheet();

    if (str_starts_with($tipo, 'solo_')) {
        // --- Caso: un solo padron ---
        // Extraer el tipo de padron del valor: 'solo_cp' -> 'cp'
        $tipo_padron = substr($tipo, 5); // quita 'solo_'

        $filas = query_no_voto($pdo, $id_referente, $tipo_padron);

        $hoja = $spreadsheet->getActiveSheet();
        generar_hoja($hoja, strtoupper($tipo_padron), $filas);

    } else {
        // --- Caso: dos padrones ---
        // El tipo es 'cd_cp', 'cd_cs', etc.
        // El primero en el nombre es siempre CD pero lo leemos dinamicamente
        [$padron_a, $padron_b] = explode('_', $tipo); // ej: ['cd', 'cp']

        // Hoja A (primer padron) — sin exclusiones, es la principal
        // Por convencion el segundo padron es el prioritario (CP, CS, etc.)
        // Hoja 1: padron_b (CP/CS/RT/CC) — principal
        // Hoja 2: padron_a (CD) — excluye los de hoja 1
        $filas_b = query_no_voto($pdo, $id_referente, $padron_b);

        // Extraer DNIs de la hoja principal para excluirlos en la segunda
        $dnis_b = array_column($filas_b, 'dni');

        $filas_a = query_no_voto($pdo, $id_referente, $padron_a, $dnis_b);

        // Hoja 1 — padron_b (CP/CS/RT/CC)
        $hoja_b = $spreadsheet->getActiveSheet();
        generar_hoja($hoja_b, strtoupper($padron_b), $filas_b);

        // Hoja 2 — CD
        $hoja_a = $spreadsheet->createSheet();
        generar_hoja($hoja_a, strtoupper($padron_a), $filas_a);
    }

    // Volver a la primera hoja al abrir
    $spreadsheet->setActiveSheetIndex(0);

    $writer = new Xlsx($spreadsheet);
    $writer->save($ruta_excel);

    $archivos_excel[] = $ruta_excel;
}

// ============================================================
// EMPAQUETAR EN ZIP Y DESCARGAR
// ============================================================
if (empty($archivos_excel)) {
    header('Location: index.php?mod=cortes&error=sin_datos');
    exit;
}

$nombre_zip = 'cortes_' . date('Ymd_His') . '.zip';
$ruta_zip   = $tmp_dir . '/' . $nombre_zip;

$zip = new ZipArchive();
$zip->open($ruta_zip, ZipArchive::CREATE);
foreach ($archivos_excel as $ruta) {
    $zip->addFile($ruta, basename($ruta));
}
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $nombre_zip . '"');
header('Content-Length: ' . filesize($ruta_zip));
header('Cache-Control: max-age=0');

readfile($ruta_zip);

// Limpiar temporales
foreach ($archivos_excel as $ruta) {
    @unlink($ruta);
}
@unlink($ruta_zip);
@rmdir($tmp_dir);

exit;
