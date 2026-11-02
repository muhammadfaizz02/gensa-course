<?php
include '../../config/database.php';
session_start();

if (isset($_GET['id'])) {
    $cert_id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Get certificate data
    $query = "
        SELECT s.*, u.full_name as student_name, u.email as student_email
        FROM sertifikat s
        JOIN users u ON s.pelajar_id = u.id
        WHERE s.id = $cert_id
    ";
    $certificate = mysqli_fetch_assoc(mysqli_query($conn, $query));
    
    if ($certificate) {
        // In real application, generate PDF using TCPDF or Dompdf
        // For demonstration, we'll create a simple text file
        
        $content = "
            CERTIFICATE OF COMPLETION
            ==========================
            
            Gensa English & Arabic Center
            
            This is to certify that
            
            {$certificate['student_name']}
            
            has successfully completed the
            
            " . strtoupper($certificate['jenis_sertifikat']) . " Course
            Level: {$certificate['level']}
            
            Certificate No: {$certificate['nomor_sertifikat']}
            Issue Date: " . date('F d, Y', strtotime($certificate['tanggal_terbit'])) . "
            " . ($certificate['tanggal_expired'] ? "Valid until: " . date('F d, Y', strtotime($certificate['tanggal_expired'])) : "") . "
            
            ---
            
            Signed,
            Gensa Course Administration
        ";
        
        // Save to file
        $filename = "certificate_{$certificate['nomor_sertifikat']}.txt";
        $filepath = "../uploads/certificates/" . $filename;
        
        file_put_contents($filepath, $content);
        
        // Update database with new file path
        $update_query = "UPDATE sertifikat SET file_path = '$filepath' WHERE id = $cert_id";
        mysqli_query($conn, $update_query);
        
        echo json_encode(['success' => true, 'filepath' => $filepath]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Certificate not found']);
    }
}
?>