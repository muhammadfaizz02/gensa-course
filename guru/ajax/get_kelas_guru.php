<?php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);

include '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// VALIDASI LOGIN GURU
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    echo json_encode([]);
    exit;
}

// PARAMETER
$jenjang = $_GET['jenjang'] ?? '';
$guru_id = $_GET['guru_id'] ?? $_SESSION['user_id'];

$jenjang = mysqli_real_escape_string($conn, $jenjang);
$guru_id = intval($guru_id);

if ($jenjang == '' || $guru_id == 0) {
    echo json_encode([]);
    exit;
}

// QUERY KELAS GURU
$query = "
    SELECT DISTINCT mk.id, mk.nama_kelas, mk.jenjang, mk.urutan
    FROM jadwal j
    JOIN master_kelas mk ON j.kelas_id = mk.id
    WHERE j.guru_id = $guru_id
      AND mk.jenjang = '$jenjang'
      AND j.status = 'scheduled'
    ORDER BY mk.urutan
";

$result = mysqli_query($conn, $query);

$classes = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $classes[] = $row;
    }
}

// fallback bila tidak ada kelas terjadwal
if (!$classes) {
    $alt = mysqli_query(
        $conn,
        "SELECT id, nama_kelas, jenjang, urutan
         FROM master_kelas
         WHERE jenjang='$jenjang'
         ORDER BY urutan"
    );

    while ($r = mysqli_fetch_assoc($alt)) {
        $classes[] = $r;
    }
}

// KIRIM JSON
echo json_encode($classes);
exit;
