<?php
include '../includes/auth_check.php';
include '../config/database.php';

if (!isset($_GET['id'])) {
    header('Location: certificates.php');
    exit();
}

$cert_id = mysqli_real_escape_string($conn, $_GET['id']);

// Get certificate details
$query = "
    SELECT s.*, u.full_name as student_name
    FROM sertifikat s
    JOIN users u ON s.pelajar_id = u.id
    WHERE s.id = $cert_id
";
$certificate = mysqli_fetch_assoc(mysqli_query($conn, $query));

if (!$certificate) {
    header('Location: certificates.php');
    exit();
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $jenis_sertifikat = mysqli_real_escape_string($conn, $_POST['jenis_sertifikat']);
    $level = mysqli_real_escape_string($conn, $_POST['level']);
    $tanggal_terbit = mysqli_real_escape_string($conn, $_POST['tanggal_terbit']);
    $tanggal_expired = mysqli_real_escape_string($conn, $_POST['tanggal_expired']);
    
    // Handle file upload
    if (isset($_FILES['certificate_file']) && $_FILES['certificate_file']['error'] == 0) {
        $target_dir = "../uploads/certificates/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        // Delete old file if exists
        if ($certificate['file_path'] && file_exists($certificate['file_path'])) {
            unlink($certificate['file_path']);
        }
        
        // Upload new file
        $file_extension = pathinfo($_FILES["certificate_file"]["name"], PATHINFO_EXTENSION);
        $filename = $certificate['nomor_sertifikat'] . '.' . $file_extension;
        $file_path = $target_dir . $filename;
        move_uploaded_file($_FILES["certificate_file"]["tmp_name"], $file_path);
        
        $file_update = ", file_path = '$file_path'";
    } else {
        $file_update = "";
    }
    
    $update_query = "
        UPDATE sertifikat 
        SET jenis_sertifikat = '$jenis_sertifikat',
            level = '$level',
            tanggal_terbit = '$tanggal_terbit',
            tanggal_expired = " . ($tanggal_expired ? "'$tanggal_expired'" : "NULL") . "
            $file_update
        WHERE id = $cert_id
    ";
    
    if (mysqli_query($conn, $update_query)) {
        $success = "Certificate updated successfully!";
        // Refresh certificate data
        $certificate = mysqli_fetch_assoc(mysqli_query($conn, $query));
    } else {
        $error = "Failed to update certificate: " . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Certificate - Admin Gensa Course</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="ml-64">
        <!-- Top Bar -->
        <div class="bg-white shadow-sm p-4 border-b">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Edit Certificate</h1>
                    <p class="text-gray-600">Update certificate information</p>
                </div>
                <div>
                    <a href="certificates.php" class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Certificates
                    </a>
                </div>
            </div>
        </div>
        
        <div class="p-6">
            <?php if (isset($success)): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span><?php echo $success; ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Edit Form -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Certificate Details</h2>
                        
                        <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
                            <!-- Certificate Info -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Certificate Number</label>
                                    <input type="text" value="<?php echo $certificate['nomor_sertifikat']; ?>" 
                                           readonly
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Student</label>
                                    <input type="text" value="<?php echo $certificate['student_name']; ?>" 
                                           readonly
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Certificate Type *</label>
                                    <select name="jenis_sertifikat" required 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select">
                                        <option value="arab" <?php echo $certificate['jenis_sertifikat'] == 'arab' ? 'selected' : ''; ?>>Arabic</option>
                                        <option value="inggris" <?php echo $certificate['jenis_sertifikat'] == 'inggris' ? 'selected' : ''; ?>>English</option>
                                        <option value="keduanya" <?php echo $certificate['jenis_sertifikat'] == 'keduanya' ? 'selected' : ''; ?>>Both (Arabic & English)</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Level *</label>
                                    <input type="text" name="level" required 
                                           value="<?php echo htmlspecialchars($certificate['level']); ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Issue Date *</label>
                                    <input type="date" name="tanggal_terbit" required 
                                           value="<?php echo $certificate['tanggal_terbit']; ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
                                    <input type="date" name="tanggal_expired" 
                                           value="<?php echo $certificate['tanggal_expired']; ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                                    <p class="text-xs text-gray-500 mt-1">Leave empty for no expiry</p>
                                </div>
                            </div>
                            
                            <!-- File Upload -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Certificate File</label>
                                <?php if ($certificate['file_path']): ?>
                                    <div class="mb-3 p-3 bg-blue-50 rounded-lg">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="font-medium">Current File:</p>
                                                <p class="text-sm text-gray-600"><?php echo basename($certificate['file_path']); ?></p>
                                            </div>
                                            <a href="certificates.php?download=<?php echo $cert_id; ?>" 
                                               class="px-3 py-1 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <input type="file" name="certificate_file" accept=".pdf,.jpg,.png"
                                       class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100">
                                <p class="text-xs text-gray-500 mt-1">Leave empty to keep current file. PDF, JPG, PNG (Max 5MB)</p>
                            </div>
                            
                            <!-- Submit Buttons -->
                            <div class="flex space-x-3 pt-4">
                                <button type="submit" 
                                        class="flex-1 py-3 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold rounded-lg shadow-md transition btn-gensa">
                                    <i class="fas fa-save mr-2"></i> Update Certificate
                                </button>
                                <a href="certificates.php" 
                                   class="flex-1 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg text-center">
                                    <i class="fas fa-times mr-2"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Certificate Preview -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Certificate Preview</h2>
                    
                    <div class="space-y-4">
                        <div class="border-2 border-yellow-300 rounded-lg p-4 bg-gradient-to-br from-yellow-50 to-yellow-100">
                            <div class="text-center mb-4">
                                <h3 class="text-lg font-bold text-yellow-800">CERTIFICATE OF COMPLETION</h3>
                                <p class="text-sm text-yellow-700">Gensa English & Arabic Center</p>
                            </div>
                            
                            <div class="text-center mb-4">
                                <p class="text-sm text-gray-600">This is to certify that</p>
                                <p class="text-xl font-bold text-gray-800"><?php echo $certificate['student_name']; ?></p>
                            </div>
                            
                            <div class="text-center mb-4">
                                <p class="text-sm text-gray-600">has successfully completed the</p>
                                <p class="text-lg font-semibold text-gray-800">
                                    <?php echo ucfirst($certificate['jenis_sertifikat']); ?> Course
                                </p>
                                <p class="text-sm text-gray-700">Level: <?php echo $certificate['level']; ?></p>
                            </div>
                            
                            <div class="flex justify-between text-sm text-gray-600">
                                <div>
                                    <p>Issued: <?php echo date('d/m/Y', strtotime($certificate['tanggal_terbit'])); ?></p>
                                </div>
                                <div>
                                    <?php if ($certificate['tanggal_expired']): ?>
                                        <p>Valid until: <?php echo date('d/m/Y', strtotime($certificate['tanggal_expired'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mt-6 text-center">
                                <p class="text-xs text-gray-500">
                                    Certificate No: <?php echo $certificate['nomor_sertifikat']; ?>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="space-y-2">
                            <button onclick="printCertificate(<?php echo $cert_id; ?>)" 
                                    class="w-full py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg">
                                <i class="fas fa-print mr-2"></i> Print Certificate
                            </button>
                            <button onclick="regenerateCertificate(<?php echo $cert_id; ?>)" 
                                    class="w-full py-2 bg-green-50 text-green-700 hover:bg-green-100 rounded-lg">
                                <i class="fas fa-redo mr-2"></i> Regenerate File
                            </button>
                            <button onclick="sendCertificateEmail(<?php echo $cert_id; ?>)" 
                                    class="w-full py-2 bg-purple-50 text-purple-700 hover:bg-purple-100 rounded-lg">
                                <i class="fas fa-paper-plane mr-2"></i> Send via Email
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function printCertificate(certId) {
        window.open(`print_certificate.php?id=${certId}`, '_blank');
    }
    
    function regenerateCertificate(certId) {
        if (confirm('Regenerate certificate file? This will create a new PDF file.')) {
            // AJAX call to regenerate
            fetch(`../ajax/regenerate_certificate.php?id=${certId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Certificate regenerated successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
        }
    }
    
    function sendCertificateEmail(certId) {
        const email = prompt('Enter student email address:', 'student@example.com');
        if (email) {
            // AJAX call to send email
            fetch(`../ajax/send_certificate_email.php?id=${certId}&email=${encodeURIComponent(email)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Certificate sent to email!');
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
        }
    }
    </script>
</body>
</html>