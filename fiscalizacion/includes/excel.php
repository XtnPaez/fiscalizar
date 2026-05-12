<?php
// includes/excel.php
// Funcion de exportacion a Excel del sistema Fiscalizar — Consulta Padron.
// Recibe un array de filas asociativas y genera un .xlsx para descarga.
// Las columnas se construyen dinamicamente desde las claves del primer registro.
// Nunca columnas hardcodeadas.
// Nombre del archivo: nombre-del-listado-YYYY-MM-DD.xlsx

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// exportar_excel()
// $resultado     : array de filas asociativas (resultado de fetchAll())
// $nombre_archivo: string sin extension ni fecha, ej: 'padron-cd-completo'
function exportar_excel(array $resultado, string $nombre_archivo): void {

    if (empty($resultado)) {
        // Si no hay datos no generar archivo
        return;
    }

    $spreadsheet = new Spreadsheet();
    $hoja        = $spreadsheet->getActiveSheet();

    // Obtener las columnas desde las claves del primer registro
    $columnas = array_keys($resultado[0]);

    // --- Encabezados ---
    $col_letra = 'A';
    foreach ($columnas as $columna) {
        $hoja->setCellValue($col_letra . '1', strtoupper(str_replace('_', ' ', $columna)));
        $col_letra++;
    }

    // Estilo del encabezado — fondo oscuro, texto blanco, negrita
    $ultima_col = chr(ord('A') + count($columnas) - 1);
    $rango_encabezado = 'A1:' . $ultima_col . '1';

    $hoja->getStyle($rango_encabezado)->applyFromArray([
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

    // --- Datos ---
    $fila_num = 2;
    foreach ($resultado as $fila) {
        $col_letra = 'A';
        foreach ($columnas as $columna) {
            $valor = $fila[$columna] ?? '';
            $hoja->setCellValue($col_letra . $fila_num, $valor);
            $col_letra++;
        }
        $fila_num++;
    }

    // Ancho automatico de columnas
    foreach (range('A', $ultima_col) as $col) {
        $hoja->getColumnDimension($col)->setAutoSize(true);
    }

    // Nombre del archivo con fecha
    $nombre_limpio = preg_replace('/[^a-z0-9\-]/', '', strtolower($nombre_archivo));
    $fecha         = date('Y-m-d');
    $nombre_final  = $nombre_limpio . '-' . $fecha . '.xlsx';

    // Headers para forzar descarga
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nombre_final . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
