<?php
// --- WAJIB PALING ATAS TANPA SPASI / ENTER ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require '../../config/database.php';

// Pastikan user guru
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$guru_id = $_SESSION['user_id'];

$kelas     = $_GET['kelas']     ?? '';
$subkelas  = $_GET['subkelas']  ?? '';

// Log untuk debugging server (tidak tampil ke user)
error_log("Guru ID: $guru_id | Kelas: $kelas | Subkelas: $subkelas");

if ($kelas === '' || $subkelas === '') {
    echo json_encode([]);
    exit;
}

$students = [];

/*
|--------------------------------------------------------------------------
| QUERY 1 — siswa yang memang ada di jadwal guru ini
|--------------------------------------------------------------------------
*/

$sql1 = "
    SELECT DISTINCT u.id, u.full_name, p.kelas, p.subkelas
    FROM jadwal j
    JOIN jadwal_siswa js   ON j.id = js.jadwal_id
    JOIN users u           ON js.pelajar_id = u.id
    JOIN pelajar_profiles p ON u.id = p.user_id
    JOIN master_kelas mk   ON j.kelas_id = mk.id
    JOIN master_subkelas ms ON j.subkelas_id = ms.id
    WHERE j.guru_id = ?
      AND p.kelas = mk.nama_kelas
      AND p.subkelas = ms.nama_subkelas
      AND mk.nama_kelas = ?
      AND ms.nama_subkelas = ?
      AND j.status = 'scheduled'
      AND p.status = 'approved'
    ORDER BY u.full_name
";

$stmt1 = $conn->prepare($sql1);
$stmt1->bind_param("iss", $guru_id, $kelas, $subkelas);
$stmt1->execute();
$res1 = $stmt1->get_result();

while ($row = $res1->fetch_assoc()) {
    $students[] = $row;
}

error_log("Query 1 found: " . count($students));

/*
|--------------------------------------------------------------------------
| QUERY 2 — fallback kalau query pertama kosong
|--------------------------------------------------------------------------
*/
if (empty($students)) {

    $sql2 = "
        SELECT u.id, u.full_name, p.kelas, p.subkelas
        FROM users u
        JOIN pelajar_profiles p ON u.id = p.user_id
        WHERE u.role = 'pelajar'
          AND p.status = 'approved'
          AND p.kelas = ?
          AND p.subkelas = ?
        ORDER BY u.full_name
    ";

    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("ss", $kelas, $subkelas);
    $stmt2->execute();
    $res2 = $stmt2->get_result();

    while ($row = $res2->fetch_assoc()) {
        $students[] = $row;
    }

    error_log("Query 2 found: " . count($students));
}

echo json_encode($students);
?>