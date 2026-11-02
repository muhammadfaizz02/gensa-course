<?php
include '../../includes/auth_check.php';
include '../../config/database.php';

header('Content-Type: application/json');

// Hanya admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$id     = (int) ($_GET['id'] ?? 0);
$status = $_GET['status'] ?? '';

if ($id <= 0 || !in_array($status, ['active', 'inactive'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Pastikan user adalah guru
$check = mysqli_prepare($conn, "SELECT id FROM users WHERE id = ? AND role = 'guru' LIMIT 1");
mysqli_stmt_bind_param($check, 'i', $id);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);
if (mysqli_stmt_num_rows($check) === 0) {
    mysqli_stmt_close($check);
    echo json_encode(['success' => false, 'message' => 'Teacher not found']);
    exit();
}
mysqli_stmt_close($check);

$stmt = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE id = ? AND role = 'guru'");
mysqli_stmt_bind_param($stmt, 'si', $status, $id);
if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'message' => 'Status updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update']);
}
mysqli_stmt_close($stmt);