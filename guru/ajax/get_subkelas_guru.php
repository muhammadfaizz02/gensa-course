<?php
// =======================
//  JSON + ERROR SETTINGS
// =======================
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE); // hilangkan warning/notice agar JSON bersih

// =======================
//  SESSION
// =======================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =======================
//  DATABASE
// =======================
include '../../config/database.php';

// =======================
//  VALIDASI LOGIN GURU
// =======================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    echo json_encode([]);
    exit();
}

$guru_id = intval($_SESSION['user_id']);

// =======================
//  GET PARAMETER
// =======================
$jenjang   = mysqli_real_escape_string($conn, $_GET['jenjang'] ?? '');
$kelas_id  = intval($_GET['kelas_id'] ?? 0);

// =======================
//  VALIDASI PARAM
// =======================
if ($jenjang === '' || $kelas_id === 0) {
    echo json_encode([]);
    exit();
}

// =======================
//  QUERY SUBKELAS
// =======================
// NOTE: status filter dihapus dulu agar tidak bikin kosong
$query = "
    SELECT DISTINCT ms.id, ms.nama_subkelas
    FROM jadwal j
    JOIN master_kelas mk ON j.kelas_id = mk.id
    JOIN master_subkelas ms ON j.subkelas_id = ms.id
    WHERE j.guru_id = $guru_id
    AND mk.jenjang = '$jenjang'
    AND mk.id = $kelas_id
    ORDER BY ms.urutan
";

$result = mysqli_query($conn, $query);

$data = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
}

// =======================
//  OUTPUT JSON
// =======================
echo json_encode($data);
exit();
