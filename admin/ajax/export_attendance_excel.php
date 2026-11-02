<?php
include '../../config/database.php';
include '../../includes/auth_check.php';

require_once '../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php';
require_once '../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Writer/Xlsx.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Create new Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set document properties
$spreadsheet->getProperties()
    ->setCreator("Gensa Course")
    ->setTitle("Teacher Attendance Report")
    ->setSubject("Attendance Data")
    ->setDescription("Report of teacher attendance");

// Add header
$sheet->setCellValue('A1', 'TEACHER ATTENDANCE REPORT');
$sheet->mergeCells('A1:L1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

// Date range
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$sheet->setCellValue('A2', 'Period: ' . date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date)));
$sheet->mergeCells('A2:L2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal('center');

// Column headers
$headers = [
    'No', 'Teacher Name', 'Date', 'Day', 'Start Time', 'End Time', 
    'Duration (hours)', 'Location', 'Proof Photo', 'Status', 
    'Approved By', 'Approval Date'
];

$col = 'A';
$row = 4;
foreach ($headers as $header) {
    $sheet->setCellValue($col . $row, $header);
    $sheet->getStyle($col . $row)->getFont()->setBold(true);
    $col++;
}

// Get attendance data
$where_conditions = ["k.status = 'approved'"];
if ($start_date) $where_conditions[] = "k.tanggal >= '$start_date'";
if ($end_date) $where_conditions[] = "k.tanggal <= '$end_date'";
$where_clause = implode(' AND ', $where_conditions);

$query = "
    SELECT k.*, u.full_name as guru_nama, a.full_name as approved_by_name
    FROM kehadiran_guru k
    JOIN users u ON k.guru_id = u.id
    LEFT JOIN users a ON k.approved_by = a.id
    WHERE $where_clause
    ORDER BY k.tanggal DESC, k.jam_mulai DESC
";

$result = mysqli_query($conn, $query);
$row = 5;
$no = 1;

while ($data = mysqli_fetch_assoc($result)) {
    $duration = (strtotime($data['jam_selesai']) - strtotime($data['jam_mulai'])) / 3600;
    
    $sheet->setCellValue('A' . $row, $no);
    $sheet->setCellValue('B' . $row, $data['guru_nama']);
    $sheet->setCellValue('C' . $row, date('d/m/Y', strtotime($data['tanggal'])));
    $sheet->setCellValue('D' . $row, date('l', strtotime($data['tanggal'])));
    $sheet->setCellValue('E' . $row, date('H:i', strtotime($data['jam_mulai'])));
    $sheet->setCellValue('F' . $row, date('H:i', strtotime($data['jam_selesai'])));
    $sheet->setCellValue('G' . $row, number_format($duration, 2));
    $sheet->setCellValue('H' . $row, $data['lokasi']);
    $sheet->setCellValue('I' . $row, $data['foto_bukti'] ? 'Yes' : 'No');
    $sheet->setCellValue('J' . $row, ucfirst($data['status']));
    $sheet->setCellValue('K' . $row, $data['approved_by_name']);
    $sheet->setCellValue('L' . $row, date('d/m/Y H:i', strtotime($data['approved_at'])));
    
    $row++;
    $no++;
}

// Auto size columns
foreach (range('A', 'L') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

// Add summary
$summaryRow = $row + 2;
$sheet->setCellValue('A' . $summaryRow, 'SUMMARY');
$sheet->getStyle('A' . $summaryRow)->getFont()->setBold(true)->setSize(14);

// Calculate totals
$stats_query = "
    SELECT 
        COUNT(*) as total_sessions,
        SUM(TIME_TO_SEC(TIMEDIFF(jam_selesai, jam_mulai))) / 3600 as total_hours,
        COUNT(DISTINCT guru_id) as total_teachers
    FROM kehadiran_guru
    WHERE $where_clause
";
$stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_query));

$sheet->setCellValue('A' . ($summaryRow + 1), 'Total Sessions:');
$sheet->setCellValue('B' . ($summaryRow + 1), $stats['total_sessions']);
$sheet->setCellValue('A' . ($summaryRow + 2), 'Total Hours:');
$sheet->setCellValue('B' . ($summaryRow + 2), number_format($stats['total_hours'], 2));
$sheet->setCellValue('A' . ($summaryRow + 3), 'Total Teachers:');
$sheet->setCellValue('B' . ($summaryRow + 3), $stats['total_teachers']);

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="attendance_report_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
?>