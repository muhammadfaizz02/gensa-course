<?php
include '../../config/database.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $semester_id = mysqli_real_escape_string($conn, $_POST['semester_id']);
    $nama_semester = mysqli_real_escape_string($conn, $_POST['nama_semester']);
    $tahun_ajaran = mysqli_real_escape_string($conn, $_POST['tahun_ajaran']);
    $tanggal_mulai = mysqli_real_escape_string($conn, $_POST['tanggal_mulai']);
    $tanggal_selesai = mysqli_real_escape_string($conn, $_POST['tanggal_selesai']);
    $status = isset($_POST['status']) ? 'active' : 'inactive';
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // If activating this semester, deactivate all others
        if ($status == 'active') {
            mysqli_query($conn, "UPDATE master_semester SET status = 'inactive'");
        }
        
        // Update semester
        $query = "
            UPDATE master_semester 
            SET nama_semester = '$nama_semester',
                tahun_ajaran = '$tahun_ajaran',
                tanggal_mulai = '$tanggal_mulai',
                tanggal_selesai = '$tanggal_selesai',
                status = '$status'
            WHERE id = $semester_id
        ";
        
        mysqli_query($conn, $query);
        mysqli_commit($conn);
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>