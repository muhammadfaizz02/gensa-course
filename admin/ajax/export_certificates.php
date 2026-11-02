<?php
include '../../config/database.php';
include '../../includes/auth_check.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="certificates_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

// CSV header
fputcsv($output, [
    'Certificate Number',
    'Student Name',
    'Student Email',
    'Certificate Type',
    'Level',
    'Class',
    'Subclass',
    'Issue Date',
    'Expiry Date',
    'Status',
    'Issued By',
    'Created Date'
]);

// Get certificates data
$query = "
    SELECT s.nomor_sertifikat, u.full_name as student_name, u.email as student_email,
           s.jenis_sertifikat, s.level, p.kelas, p.subkelas,
           s.tanggal_terbit, s.tanggal_expired,
           CASE 
               WHEN s.tanggal_expired IS NULL THEN 'Valid'
               WHEN s.tanggal_expired > CURDATE() THEN 'Valid'
               ELSE 'Expired'
           END as status,
           a.full_name as issued_by, s.created_at
    FROM sertifikat s
    JOIN users u ON s.pelajar_id = u.id
    JOIN pelajar_profiles p ON u.id = p.user_id
    LEFT JOIN users a ON s.created_by = a.id
    ORDER BY s.tanggal_terbit DESC
";

$result = mysqli_query($conn, $query);

while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $row['nomor_sertifikat'],
        $row['student_name'],
        $row['student_email'],
        ucfirst($row['jenis_sertifikat']),
        $row['level'],
        $row['kelas'],
        $row['subkelas'],
        date('d/m/Y', strtotime($row['tanggal_terbit'])),
        $row['tanggal_expired'] ? date('d/m/Y', strtotime($row['tanggal_expired'])) : '',
        $row['status'],
        $row['issued_by'],
        date('d/m/Y H:i', strtotime($row['created_at']))
    ]);
}

fclose($output);
?>