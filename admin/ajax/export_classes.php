<?php
include '../../config/database.php';
include '../../includes/auth_check.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="classes_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

// CSV header
fputcsv($output, [
    'Education Level',
    'Class Name',
    'Order',
    'Sub Classes',
    'Total Students',
    'Capacity',
    'Utilization (%)',
    'Status'
]);

// Get classes data
$query = "
    SELECT mk.jenjang, mk.nama_kelas, mk.urutan, mk.status,
           GROUP_CONCAT(ms.nama_subkelas ORDER BY ms.urutan SEPARATOR ', ') as subkelas_list,
           COUNT(DISTINCT ms.id) as total_subkelas,
           COUNT(DISTINCT p.user_id) as total_students,
           SUM(ms.kapasitas) as total_capacity
    FROM master_kelas mk
    LEFT JOIN master_subkelas ms ON mk.id = ms.kelas_id
    LEFT JOIN pelajar_profiles p ON mk.nama_kelas = p.kelas AND ms.nama_subkelas = p.subkelas
    WHERE mk.status = 'active'
    GROUP BY mk.id
    ORDER BY mk.urutan
";

$result = mysqli_query($conn, $query);

while ($row = mysqli_fetch_assoc($result)) {
    $utilization = $row['total_capacity'] > 0 ? 
        ($row['total_students'] / $row['total_capacity'] * 100) : 0;
    
    fputcsv($output, [
        $row['jenjang'],
        $row['nama_kelas'],
        $row['urutan'],
        $row['subkelas_list'] ?: 'None',
        $row['total_students'],
        $row['total_capacity'] ?: 0,
        number_format($utilization, 1),
        ucfirst($row['status'])
    ]);
}

fclose($output);
?>