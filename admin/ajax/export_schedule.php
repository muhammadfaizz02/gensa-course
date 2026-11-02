<?php
include '../../config/database.php';
include '../../includes/auth_check.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="schedule_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

// CSV header
fputcsv($output, [
    'Date',
    'Day',
    'Start Time',
    'End Time',
    'Duration (hours)',
    'Teacher',
    'Class',
    'Subclass',
    'Language',
    'Lesson Type',
    'Status',
    'Students Count',
    'Description'
]);

// Get filters from URL
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d', strtotime('+7 days'));
$status = $_GET['status'] ?? '';
$kelas_id = $_GET['kelas_id'] ?? '';
$guru_id = $_GET['guru_id'] ?? '';

// Build where conditions
$where_conditions = ["1=1"];
if ($start_date) $where_conditions[] = "j.tanggal >= '$start_date'";
if ($end_date) $where_conditions[] = "j.tanggal <= '$end_date'";
if ($status) $where_conditions[] = "j.status = '$status'";
if ($kelas_id) $where_conditions[] = "j.kelas_id = '$kelas_id'";
if ($guru_id) $where_conditions[] = "j.guru_id = '$guru_id'";
$where_clause = implode(' AND ', $where_conditions);

// Get schedule data
$query = "
    SELECT j.tanggal, j.jam_mulai, j.jam_selesai,
           TIMEDIFF(j.jam_selesai, j.jam_mulai) as duration,
           u.full_name as guru_nama,
           mk.nama_kelas, ms.nama_subkelas,
           j.bahasa, j.jenis_pelajaran, j.status,
           j.deskripsi,
           COUNT(DISTINCT js.id) as student_count
    FROM jadwal j
    LEFT JOIN users u ON j.guru_id = u.id
    LEFT JOIN master_kelas mk ON j.kelas_id = mk.id
    LEFT JOIN master_subkelas ms ON j.subkelas_id = ms.id
    LEFT JOIN jadwal_siswa js ON j.id = js.jadwal_id
    WHERE $where_clause
    GROUP BY j.id
    ORDER BY j.tanggal, j.jam_mulai
";

$result = mysqli_query($conn, $query);

while ($row = mysqli_fetch_assoc($result)) {
    $duration_hours = (strtotime($row['duration']) - strtotime('00:00:00')) / 3600;
    
    fputcsv($output, [
        date('d/m/Y', strtotime($row['tanggal'])),
        date('l', strtotime($row['tanggal'])),
        date('H:i', strtotime($row['jam_mulai'])),
        date('H:i', strtotime($row['jam_selesai'])),
        number_format($duration_hours, 2),
        $row['guru_nama'],
        $row['nama_kelas'],
        $row['nama_subkelas'],
        ucfirst($row['bahasa']),
        ucfirst($row['jenis_pelajaran']),
        ucfirst($row['status']),
        $row['student_count'],
        $row['deskripsi']
    ]);
}

fclose($output);
?>