<?php
include '../../config/database.php';
include '../../includes/auth_check.php';

if (isset($_GET['id'])) {
    $class_id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Check if class has subclasses
    $check_subclasses = "SELECT COUNT(*) as count FROM master_subkelas WHERE kelas_id = $class_id";
    $result = mysqli_fetch_assoc(mysqli_query($conn, $check_subclasses));
    
    if ($result['count'] > 0) {
        // Soft delete (mark as inactive)
        $query = "UPDATE master_kelas SET status = 'inactive' WHERE id = $class_id";
    } else {
        // Hard delete if no subclasses
        $query = "DELETE FROM master_kelas WHERE id = $class_id";
    }
    
    if (mysqli_query($conn, $query)) {
        header('Location: ../manage_kelas.php?success=deleted');
        exit();
    } else {
        header('Location: ../manage_kelas.php?error=delete_failed');
        exit();
    }
}
?>