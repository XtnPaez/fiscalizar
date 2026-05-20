<?php
// fiscalizacion/modulos/cortes/cortes_descarga.php
// Generador de ZIP con Excels por referente.
// Se invoca por POST desde cortes.php con un array de ids de referentes.
//
// Por cada referente genera un Excel con dos hojas:
//   Hoja CD: dni, apellido, nombre de referidos que NO votaron en la eleccion CD activa
//   Hoja CP: dni, apellido, nombre de referidos que NO votaron en la eleccion CP activa
//            y que NO aparecen ya en la hoja CD (sin duplicados entre hojas)
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
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// --- Validar input ---
// ids[] viene del formulario de cortes.php
$ids_raw = $_POST['ids'] ?? [];

// Filtrar y convertir a enteros para evitar inyeccion SQL
$ids = array_filter(array_map('intval', $ids_raw), fn($id) => $id > 0);

if (empty($ids)) {
    // Si no se selecciono ningun referente volver al modulo
    header('Location: index.php?mod=cortes&error=sin_seleccion');
    exit;
}

// --- Directorio temporal para los Excels ---
// Se crea en /tmp para no ensuciar el proyecto
$tmp_dir = sys_get_temp_dir() . '/cortes_' . uniqid();
mkdir($tmp_dir, 0700, true);

// ============================================================
// FUNCION: generar_hoja()
// Escribe un array de filas en una hoja de PhpSpreadsheet.
// $hoja    : objeto Worksheet
// $titulo  : string — nombre de la hoja (CD o CP)
// $filas   : array de arrays asociativos ['dni','apellido','nombre']
// ============================================================
function generar_hoja($hoja, string $titulo, array $filas): void {

    $hoja->setTitle($titulo);

    // Encabezados
    $hoja->setCellValue('A1', 'DNI');
    $hoja->setCellValue('B1', 'APELLIDO');
    $hoja->setCellValue('C1', 'NOMBRE');

    // Estilo encabezado — fondo oscuro, texto blanco, negrita
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

    // Datos
    $fila_num = 2;
    foreach ($filas as $fila) {
        $hoja->setCellValue('A' . $fila_num, $fila['dni']);
        $hoja->setCellValue('B' . $fila_num, $fila['apellido']);
        $hoja->setCellValue('C' . $fila_num, $fila['nombre']);
        $fila_num++;
    }

    // Ancho automatico
    foreach (['A', 'B', 'C'] as $col) {
        $hoja->getColumnDimension($col)->setAutoSize(true);
    }

    // Si no hay datos mostrar mensaje
    if (empty($filas)) {
        $hoja->setCellValue('A2', 'Sin pendientes');
    }
}

// ============================================================
// QUERY: referidos que no votaron en CD
// Busca en cualquiera de las 3 posiciones de referente
// Solo personas que esten en padron_cd
// Solo eleccion activa tipo 'cd'
// ============================================================
$sql_cd = "
    SELECT DISTINCT p.dni, p.apellido, p.nombre
    FROM personas p
    JOIN referentes_graduado rg ON rg.dni = p.dni
        AND (rg.referente_1 = :id OR rg.referente_2 = :id2 OR rg.referente_3 = :id3)
    JOIN padron_cd pcd ON pcd.dni = p.dni
    WHERE NOT EXISTS (
        SELECT 1
        FROM votos_dia v
        JOIN mesas m      ON v.id_mesa = m.id
        JOIN dias_eleccion d ON m.id_dia = d.id
        JOIN elecciones e ON d.id_eleccion = e.id
        WHERE v.dni = p.dni
          AND e.tipo = 'cd'
          AND e.estado = 'activa'
    )
    ORDER BY p.apellido, p.nombre
";

// ============================================================
// QUERY: referidos que no votaron en CP
// Excluye los DNIs que ya aparecen en la hoja CD (sin duplicados)
// Solo personas que esten en padron_cp
// Solo eleccion activa tipo 'cp'
// ============================================================
$sql_cp = "
    SELECT DISTINCT p.dni, p.apellido, p.nombre
    FROM personas p
    JOIN referentes_graduado rg ON rg.dni = p.dni
        AND (rg.referente_1 = :id OR rg.referente_2 = :id2 OR rg.referente_3 = :id3)
    JOIN padron_cp pcp ON pcp.dni = p.dni
    WHERE NOT EXISTS (
        SELECT 1
        FROM votos_dia v
        JOIN mesas m      ON v.id_mesa = m.id
        JOIN dias_eleccion d ON m.id_dia = d.id
        JOIN elecciones e ON d.id_eleccion = e.id
        WHERE v.dni = p.dni
          AND e.tipo = 'cp'
          AND e.estado = 'activa'
    )
    -- Excluir DNIs que ya estan en la hoja CD para ese referente
    AND p.dni NOT IN (
        SELECT DISTINCT p2.dni
        FROM personas p2
        JOIN referentes_graduado rg2 ON rg2.dni = p2.dni
            AND (rg2.referente_1 = :id4 OR rg2.referente_2 = :id5 OR rg2.referente_3 = :id6)
        JOIN padron_cd pcd2 ON pcd2.dni = p2.dni
        WHERE NOT EXISTS (
            SELECT 1
            FROM votos_dia v2
            JOIN mesas m2      ON v2.id_mesa = m2.id
            JOIN dias_eleccion d2 ON m2.id_dia = d2.id
            JOIN elecciones e2 ON d2.id_eleccion = e2.id
            WHERE v2.dni = p2.dni
              AND e2.tipo = 'cd'
              AND e2.estado = 'activa'
        )
    )
    ORDER BY p.apellido, p.nombre
";

// ============================================================
// GENERAR UN EXCEL POR REFERENTE
// ============================================================

// Guardar rutas de los Excels generados para el ZIP
$archivos_excel = [];

foreach ($ids as $id_referente) {

    // Obtener apellido y nombre del referente para el nombre del archivo
    $stmt = $pdo->prepare("SELECT apellido, nombre FROM referentes WHERE id = ?");
    $stmt->execute([$id_referente]);
    $ref = $stmt->fetch();

    if (!$ref) continue; // Referente no encontrado, saltear

    // Nombre del archivo: APELLIDO_NOMBRE.xlsx (sin caracteres especiales)
    $nombre_archivo = preg_replace('/[^A-Z0-9_]/', '',
        strtoupper(
            str_replace(' ', '_',
                $ref['apellido'] . '_' . $ref['nombre']
            )
        )
    ) . '.xlsx';

    $ruta_excel = $tmp_dir . '/' . $nombre_archivo;

    // --- Consultar hoja CD ---
    $stmt_cd = $pdo->prepare($sql_cd);
    $stmt_cd->execute([
        ':id'  => $id_referente,
        ':id2' => $id_referente,
        ':id3' => $id_referente,
    ]);
    $filas_cd = $stmt_cd->fetchAll(PDO::FETCH_ASSOC);

    // --- Consultar hoja CP ---
    $stmt_cp = $pdo->prepare($sql_cp);
    $stmt_cp->execute([
        ':id'  => $id_referente,
        ':id2' => $id_referente,
        ':id3' => $id_referente,
        ':id4' => $id_referente,
        ':id5' => $id_referente,
        ':id6' => $id_referente,
    ]);
    $filas_cp = $stmt_cp->fetchAll(PDO::FETCH_ASSOC);

    // --- Crear el Excel ---
    $spreadsheet = new Spreadsheet();

    // Hoja 1 — CD
    $hoja_cd = $spreadsheet->getActiveSheet();
    generar_hoja($hoja_cd, 'CD', $filas_cd);

    // Hoja 2 — CP
    $hoja_cp = $spreadsheet->createSheet();
    generar_hoja($hoja_cp, 'CP', $filas_cp);

    // Volver a la primera hoja al abrir
    $spreadsheet->setActiveSheetIndex(0);

    // Guardar el Excel en el directorio temporal
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
    // Agregar cada Excel al ZIP con solo el nombre del archivo (sin ruta)
    $zip->addFile($ruta, basename($ruta));
}

$zip->close();

// Headers para forzar descarga del ZIP
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $nombre_zip . '"');
header('Content-Length: ' . filesize($ruta_zip));
header('Cache-Control: max-age=0');

readfile($ruta_zip);

// Limpiar archivos temporales
foreach ($archivos_excel as $ruta) {
    @unlink($ruta);
}
@unlink($ruta_zip);
@rmdir($tmp_dir);

exit;
