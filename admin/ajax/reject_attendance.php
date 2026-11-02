<?php
include '../../config/database.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $attendance_id = mysqli_real_escape_string($conn, $_POST['attendance_id']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    $admin_id = $_SESSION['user_id'];
    
    $query = "
        UPDATE kehadiran_guru 
        SET status = 'rejected', 
            approved_at = NOW(), 
            approved_by = $admin_id,
            catatan = CONCAT(COALESCE(catatan, ''), '\\n[REJECTED]: ', '$reason')
        WHERE id = $attendance_id
    ";
    
    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true, 'message' => 'Attendance rejected successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reject attendance: ' . mysqli_error($conn)]);
    }
}
?>