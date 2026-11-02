<?php
include '../../config/database.php';
include '../../includes/auth_check.php';

$month = $_GET['month'] ?? date('Y-m');
$start_date = date('Y-m-01', strtotime($month));
$end_date = date('Y-m-t', strtotime($month));
$rate_per_hour = 50000;

// Get attendance data
$query = "
    SELECT k.guru_id, u.full_name as guru_nama, u.email as guru_email,
           SUM(TIME_TO_SEC(TIMEDIFF(k.jam_selesai, k.jam_mulai))) / 3600 as total_hours
    FROM kehadiran_guru k
    JOIN users u ON k.guru_id = u.id
    WHERE k.status = 'approved'
    AND k.tanggal BETWEEN '$start_date' AND '$end_date'
    GROUP BY k.guru_id
";

$result = mysqli_query($conn, $query);

// Create payroll records
$payroll_data = [];
$total_payment = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $payment = $row['total_hours'] * $rate_per_hour;
    $total_payment += $payment;
    
    $payroll_data[] = [
        'teacher_id' => $row['guru_id'],
        'teacher_name' => $row['guru_nama'],
        'hours' => $row['total_hours'],
        'payment' => $payment
    ];
}

// Generate CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="payroll_' . $month . '.csv"');

$output = fopen('php://output', 'w');

// CSV header
fputcsv($output, ['Teacher ID', 'Teacher Name', 'Hours Worked', 'Payment Amount', 'Status', 'Payment Date']);

foreach ($payroll_data as $item) {
    fputcsv($output, [
        $item['teacher_id'],
        $item['teacher_name'],
        number_format($item['hours'], 2),
        'Rp ' . number_format($item['payment'], 0, ',', '.'),
        'Pending',
        date('Y-m-d')
    ]);
}

// Total row
fputcsv($output, ['', '', '', '', '', '']);
fputcsv($output, ['TOTAL', '', number_format(array_sum(array_column($payroll_data, 'hours')), 2), 
                 'Rp ' . number_format($total_payment, 0, ',', '.'), '', '']);

fclose($output);
?>