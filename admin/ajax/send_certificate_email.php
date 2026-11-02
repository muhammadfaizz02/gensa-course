<?php
include '../../config/database.php';

if (isset($_GET['id']) && isset($_GET['email'])) {
    $cert_id = mysqli_real_escape_string($conn, $_GET['id']);
    $email = mysqli_real_escape_string($conn, $_GET['email']);
    
    // Get certificate data
    $query = "
        SELECT s.*, u.full_name as student_name
        FROM sertifikat s
        JOIN users u ON s.pelajar_id = u.id
        WHERE s.id = $cert_id
    ";
    $certificate = mysqli_fetch_assoc(mysqli_query($conn, $query));
    
    if ($certificate) {
        // In real application, send email with attachment
        // For demonstration, just return success
        
        $subject = "Your Certificate from Gensa Course - " . $certificate['nomor_sertifikat'];
        $message = "
            Dear {$certificate['student_name']},
            
            Congratulations! Your certificate has been issued.
            
            Certificate Details:
            - Number: {$certificate['nomor_sertifikat']}
            - Type: " . ucfirst($certificate['jenis_sertifikat']) . "
            - Level: {$certificate['level']}
            - Issue Date: " . date('F d, Y', strtotime($certificate['tanggal_terbit'])) . "
            
            You can download your certificate from your student portal.
            
            Best regards,
            Gensa Course Administration
        ";
        
        // In production: mail($email, $subject, $message);
        
        // Log email sending
        error_log("Certificate email sent to: $email - Cert: {$certificate['nomor_sertifikat']}");
        
        echo json_encode(['success' => true, 'message' => 'Email sent successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Certificate not found']);
    }
}
?>