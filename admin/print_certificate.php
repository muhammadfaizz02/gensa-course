<?php
include '../includes/auth_check.php';
include '../config/database.php';

if (!isset($_GET['id'])) {
    header('Location: certificates.php');
    exit();
}

$cert_id = mysqli_real_escape_string($conn, $_GET['id']);

$query = "
    SELECT s.*, u.full_name as student_name, p.kelas, p.subkelas
    FROM sertifikat s
    JOIN users u ON s.pelajar_id = u.id
    JOIN pelajar_profiles p ON u.id = p.user_id
    WHERE s.id = $cert_id
";
$certificate = mysqli_fetch_assoc(mysqli_query($conn, $query));

if (!$certificate) {
    header('Location: certificates.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Certificate - Gensa Course</title>
    <style>
    @page {
        size: A4 landscape;
        margin: 0;
    }
    
    @media print {
        body {
            margin: 0;
            padding: 0;
        }
        .no-print {
            display: none !important;
        }
    }
    
    body {
        font-family: 'Times New Roman', Times, serif;
        margin: 0;
        padding: 0;
        background-color: #fff;
    }
    
    .certificate-container {
        width: 29.7cm;
        height: 21cm;
        position: relative;
        background: linear-gradient(to bottom, #fef9e7 0%, #fff8e1 100%);
        border: 20px solid #fbb117;
        box-sizing: border-box;
    }
    
    .certificate-border {
        position: absolute;
        top: 50px;
        left: 50px;
        right: 50px;
        bottom: 50px;
        border: 2px solid #d4a017;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 40px;
    }
    
    .header {
        margin-bottom: 40px;
    }
    
    .title {
        font-size: 48px;
        font-weight: bold;
        color: #b8860b;
        letter-spacing: 4px;
        margin-bottom: 10px;
        text-transform: uppercase;
    }
    
    .subtitle {
        font-size: 20px;
        color: #d4a017;
        margin-bottom: 30px;
        font-style: italic;
    }
    
    .certificate-text {
        font-size: 24px;
        color: #333;
        line-height: 1.6;
        margin: 30px 0;
        max-width: 80%;
    }
    
    .student-name {
        font-size: 36px;
        font-weight: bold;
        color: #000;
        margin: 20px 0;
        text-decoration: underline;
        text-decoration-color: #fbb117;
        text-decoration-thickness: 3px;
    }
    
    .course-details {
        font-size: 22px;
        color: #444;
        margin: 20px 0;
    }
    
    .level {
        font-size: 28px;
        font-weight: bold;
        color: #b8860b;
        margin: 15px 0;
    }
    
    .signature-area {
        margin-top: 50px;
        display: flex;
        justify-content: space-between;
        width: 80%;
    }
    
    .signature {
        text-align: center;
    }
    
    .signature-line {
        width: 200px;
        border-top: 2px solid #333;
        margin: 40px auto 10px;
    }
    
    .signature-name {
        font-size: 18px;
        font-weight: bold;
    }
    
    .signature-title {
        font-size: 14px;
        color: #666;
    }
    
    .certificate-number {
        position: absolute;
        bottom: 20px;
        right: 20px;
        font-size: 14px;
        color: #666;
    }
    
    .logo {
        position: absolute;
        top: 40px;
        left: 40px;
        width: 100px;
        height: 100px;
        background-color: #fbb117;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 48px;
        font-weight: bold;
    }
    
    .footer {
        position: absolute;
        bottom: 20px;
        width: 100%;
        text-align: center;
        font-size: 12px;
        color: #888;
    }
    
    .print-controls {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1000;
        background: white;
        padding: 10px;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .print-btn {
        background: #fbb117;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
    }
    
    .print-btn:hover {
        background: #e8a014;
    }
    </style>
</head>
<body>
    <!-- Print Controls -->
    <div class="print-controls no-print">
        <button class="print-btn" onclick="window.print()">
            <i class="fas fa-print"></i> Print Certificate
        </button>
        <button class="print-btn" onclick="window.close()" style="background: #666; margin-left: 10px;">
            <i class="fas fa-times"></i> Close
        </button>
    </div>
    
    <!-- Certificate -->
    <div class="certificate-container">
        <div class="logo">G</div>
        
        <div class="certificate-border">
            <div class="header">
                <div class="title">Certificate of Completion</div>
                <div class="subtitle">Gensa English & Arabic Center</div>
            </div>
            
            <div class="certificate-text">
                This is to certify that
            </div>
            
            <div class="student-name">
                <?php echo $certificate['student_name']; ?>
            </div>
            
            <div class="certificate-text">
                has successfully completed the course in
            </div>
            
            <div class="level">
                <?php echo strtoupper($certificate['jenis_sertifikat']); ?> - <?php echo $certificate['level']; ?> Level
            </div>
            
            <div class="course-details">
                Class: <?php echo $certificate['kelas']; ?> - <?php echo $certificate['subkelas']; ?>
            </div>
            
            <div class="signature-area">
                <div class="signature">
                    <div class="signature-line"></div>
                    <div class="signature-name">Director</div>
                    <div class="signature-title">Gensa Course</div>
                </div>
                
                <div class="signature">
                    <div class="signature-line"></div>
                    <div class="signature-name">Head Teacher</div>
                    <div class="signature-title"><?php echo ucfirst($certificate['jenis_sertifikat']); ?> Department</div>
                </div>
            </div>
        </div>
        
        <div class="certificate-number">
            Certificate No: <?php echo $certificate['nomor_sertifikat']; ?>
        </div>
        
        <div class="footer">
            Issued on <?php echo date('F d, Y', strtotime($certificate['tanggal_terbit'])); ?>
            <?php if ($certificate['tanggal_expired']): ?>
                | Valid until <?php echo date('F d, Y', strtotime($certificate['tanggal_expired'])); ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    // Auto-print after 1 second
    setTimeout(() => {
        window.print();
    }, 1000);
    
    // Close window after print
    window.onafterprint = function() {
        setTimeout(() => {
            window.close();
        }, 1000);
    };
    </script>
</body>
</html>