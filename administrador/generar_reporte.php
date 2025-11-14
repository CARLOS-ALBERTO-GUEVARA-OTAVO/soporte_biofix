<?php
// Iniciar el buffer de salida para prevenir cualquier salida prematura que corrompa el archivo Excel.
ob_start();

session_start();

// 1. Seguridad: Verificar si el usuario es administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 'administrador') {
    header('Location: ../login/login.php');
    exit;
}

// 2. Incluir dependencias: Conexión a BD y autoload de Composer para PhpSpreadsheet
require '../bd/db.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;

// Temporarily disable error reporting to prevent warnings/notices from corrupting the Excel file
$old_error_reporting = error_reporting();
error_reporting(0); // Disable all error reporting

// 3. Crear una nueva instancia de Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Datos Tickets'); // Renombrar la hoja principal

// 4. Definir y estilizar los encabezados de la tabla en la hoja de datos
$dataHeaders = [
    'A1' => 'Radicado',
    'B1' => 'Fecha Creación',
    'C1' => 'Área',
    'D1' => 'Tipo Soporte',
    'E1' => 'Usuario',
    'F1' => 'Estado',
    'G1' => 'Mensaje Usuario',
    'H1' => 'Respuesta Admin',
    'I1' => 'Fecha Respuesta'
];

foreach ($dataHeaders as $cell => $value) {
    $sheet->setCellValue($cell, $value);
    $sheet->getStyle($cell)->getFont()->setBold(true);
}

// 5. Obtener todos los datos de los tickets de la base de datos
$stmt = $pdo->query(
    "SELECT 
        t.id_ticket, t.numero_radicado, t.fecha_creacion, a.nombre_area, 
        t.tipo_soporte, u.nombre as usuario_nombre, t.estado, t.descripcion_problema
     FROM tickets t
     JOIN usuarios u ON t.id_usuario = u.id_usuario
     JOIN areas a ON u.id_area = a.id_area
     ORDER BY t.fecha_creacion DESC"
);
$tickets = $stmt->fetchAll();

$row = 2; // Empezar a escribir en la fila 2
foreach ($tickets as $ticket) {
    // Para cada ticket, buscar la última respuesta del administrador
    // CORRECCIÓN: Se busca por id_rol numérico (1) y se obtiene también la fecha.
    $stmt_respuesta = $pdo->prepare(
        "SELECT h.mensaje, h.fecha_respuesta FROM historial_respuestas h
         JOIN usuarios u ON h.id_usuario = u.id_usuario
         WHERE h.id_ticket = ? AND u.id_rol = 1
         ORDER BY h.fecha_respuesta DESC LIMIT 1"
    );
    $stmt_respuesta->execute([$ticket['id_ticket']]);
    $respuesta = $stmt_respuesta->fetch(); // Usamos fetch() para obtener ambas columnas

    // 6. Escribir los datos en las celdas correspondientes
    $sheet->setCellValue('A' . $row, $ticket['numero_radicado']);
    $sheet->setCellValue('B' . $row, $ticket['fecha_creacion']);
    $sheet->setCellValue('C' . $row, $ticket['nombre_area']);
    $sheet->setCellValue('D' . $row, $ticket['tipo_soporte']);
    $sheet->setCellValue('E' . $row, $ticket['usuario_nombre']);
    $sheet->setCellValue('F' . $row, ucfirst($ticket['estado']));
    $sheet->setCellValue('G' . $row, $ticket['descripcion_problema']);
    $sheet->setCellValue('H' . $row, $respuesta ? $respuesta['mensaje'] : 'Sin respuesta');
    $sheet->setCellValue('I' . $row, $respuesta ? $respuesta['fecha_respuesta'] : '-');

    $row++;
}

// 7. Ajustar el ancho de las columnas automáticamente para que el contenido sea legible
foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Re-enable error reporting after Excel generation (before sending headers)
error_reporting($old_error_reporting);

// 8. Preparar el escritor y los encabezados HTTP para forzar la descarga del archivo
$writer = new Xlsx($spreadsheet);
$filename = 'reporte_tickets_' . date('Y-m-d_H-i-s') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Clear any accidental output that might have been buffered before saving the Excel file
ob_end_clean();

// 9. Enviar el archivo al navegador
$writer->save('php://output');
exit;