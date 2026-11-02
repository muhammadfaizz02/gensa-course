<?php
include '../../config/database.php';
include '../../includes/auth_check.php';

if (isset($_GET['id'])) {
    $cert_id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Get file path to delete file
    $query = "SELECT file_path FROM sertifikat WHERE id = $cert_id";
    $result = mysqli_query($conn, $query);
    $certificate = mysqli_fetch_assoc($result);
    
    // Delete physical file if exists
    if ($certificate['file_path'] && file_exists($certificate['file_path'])) {
        unlink($certificate['file_path']);
    }
    
    // Delete from database
    $delete_query = "DELETE FROM sertifikat WHERE id = $cert_id";
    
    if (mysqli_query($conn, $delete_query)) {
        header('Location: ../certificates.php?success=deleted');
        exit();
    } else {
        header('Location: ../certificates.php?error=delete_failed');
        exit();
    }
}
?>