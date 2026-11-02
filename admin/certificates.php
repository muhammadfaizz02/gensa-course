<?php
include '../includes/auth_check.php';
include '../config/database.php';

// Handle issue certificate
if (isset($_POST['issue_certificate'])) {
    $pelajar_id = mysqli_real_escape_string($conn, $_POST['pelajar_id']);
    $jenis_sertifikat = mysqli_real_escape_string($conn, $_POST['jenis_sertifikat']);
    $level = mysqli_real_escape_string($conn, $_POST['level']);
    $tanggal_terbit = mysqli_real_escape_string($conn, $_POST['tanggal_terbit']);
    $tanggal_expired = mysqli_real_escape_string($conn, $_POST['tanggal_expired']);
    
    // Generate certificate number
    $nomor_sertifikat = 'GENSA-' . date('Ymd') . '-' . str_pad($pelajar_id, 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
    
    // Handle file upload if any
    $file_path = '';
    if (isset($_FILES['certificate_file']) && $_FILES['certificate_file']['error'] == 0) {
        $target_dir = "../uploads/certificates/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        // Validate file type
        $allowed_types = ['pdf', 'jpg', 'jpeg', 'png'];
        $file_extension = strtolower(pathinfo($_FILES["certificate_file"]["name"], PATHINFO_EXTENSION));
        
        if (in_array($file_extension, $allowed_types)) {
            // Validate file size (max 5MB)
            if ($_FILES['certificate_file']['size'] <= 5 * 1024 * 1024) {
                $filename = $nomor_sertifikat . '.' . $file_extension;
                $file_path = $target_dir . $filename;
                
                if (move_uploaded_file($_FILES["certificate_file"]["tmp_name"], $file_path)) {
                    // File uploaded successfully
                } else {
                    $error = "Failed to upload certificate file.";
                }
            } else {
                $error = "File size too large. Maximum 5MB allowed.";
            }
        } else {
            $error = "Invalid file type. Allowed: PDF, JPG, PNG.";
        }
    }
    
    if (!isset($error)) {
        // Insert certificate
        $query = "
            INSERT INTO sertifikat 
            (pelajar_id, nomor_sertifikat, jenis_sertifikat, level, tanggal_terbit, tanggal_expired, file_path, created_by)
            VALUES ('$pelajar_id', '$nomor_sertifikat', '$jenis_sertifikat', '$level', 
                    '$tanggal_terbit', " . ($tanggal_expired ? "'$tanggal_expired'" : "NULL") . ", 
                    '$file_path', '$user_id')
        ";
        
        if (mysqli_query($conn, $query)) {
            $success = "Certificate issued successfully! Certificate Number: <strong>$nomor_sertifikat</strong>";
            
            // Clear form
            $_POST = array();
        } else {
            $error = "Failed to issue certificate: " . mysqli_error($conn);
        }
    }
}

// Handle download certificate
if (isset($_GET['download'])) {
    $cert_id = mysqli_real_escape_string($conn, $_GET['download']);
    $query = "SELECT file_path, nomor_sertifikat FROM sertifikat WHERE id = $cert_id";
    $result = mysqli_query($conn, $query);
    $certificate = mysqli_fetch_assoc($result);
    
    if ($certificate && $certificate['file_path'] && file_exists($certificate['file_path'])) {
        $file_extension = pathinfo($certificate['file_path'], PATHINFO_EXTENSION);
        $filename = $certificate['nomor_sertifikat'] . '.' . $file_extension;
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($certificate['file_path']));
        readfile($certificate['file_path']);
        exit();
    } else {
        $error = "Certificate file not found.";
    }
}

// Handle delete certificate
if (isset($_GET['delete'])) {
    $cert_id = mysqli_real_escape_string($conn, $_GET['delete']);
    
    // Get file path to delete file
    $query = "SELECT file_path FROM sertifikat WHERE id = $cert_id";
    $result = mysqli_query($conn, $query);
    $certificate = mysqli_fetch_assoc($result);
    
    // Delete physical file if exists
    if ($certificate && $certificate['file_path'] && file_exists($certificate['file_path'])) {
        unlink($certificate['file_path']);
    }
    
    // Delete from database
    $delete_query = "DELETE FROM sertifikat WHERE id = $cert_id";
    
    if (mysqli_query($conn, $delete_query)) {
        header('Location: certificates.php?success=Certificate deleted successfully!');
        exit();
    } else {
        header('Location: certificates.php?error=Failed to delete certificate.');
        exit();
    }
}

// Handle regenerate certificate
if (isset($_GET['regenerate'])) {
    $cert_id = mysqli_real_escape_string($conn, $_GET['regenerate']);
    
    // Redirect to regenerate script
    header('Location: ajax/regenerate_certificate.php?id=' . $cert_id);
    exit();
}

// Get all certificates with filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query with filters
$where_conditions = ["1=1"];
if ($search) {
    $where_conditions[] = "(s.nomor_sertifikat LIKE '%$search%' OR u.full_name LIKE '%$search%' OR u.email LIKE '%$search%')";
}
if ($status_filter == 'valid') {
    $where_conditions[] = "(s.tanggal_expired IS NULL OR s.tanggal_expired > CURDATE())";
} elseif ($status_filter == 'expired') {
    $where_conditions[] = "s.tanggal_expired < CURDATE()";
}
if ($type_filter) {
    $where_conditions[] = "s.jenis_sertifikat = '$type_filter'";
}
if ($date_from) {
    $where_conditions[] = "s.tanggal_terbit >= '$date_from'";
}
if ($date_to) {
    $where_conditions[] = "s.tanggal_terbit <= '$date_to'";
}

$where_clause = implode(' AND ', $where_conditions);

// Get certificates
$certificates_query = "
    SELECT s.*, u.full_name as student_name, u.email as student_email,
           p.kelas, p.subkelas, p.jenjang,
           a.full_name as issued_by
    FROM sertifikat s
    JOIN users u ON s.pelajar_id = u.id
    JOIN pelajar_profiles p ON u.id = p.user_id
    LEFT JOIN users a ON s.created_by = a.id
    WHERE $where_clause
    ORDER BY s.tanggal_terbit DESC
";
$certificates = mysqli_query($conn, $certificates_query);

// Get students for dropdown
$students_query = "
    SELECT u.id, u.full_name, p.kelas, p.subkelas, p.jenjang
    FROM users u
    JOIN pelajar_profiles p ON u.id = p.user_id
    WHERE u.role = 'pelajar' AND p.status = 'approved'
    ORDER BY u.full_name
";
$students = mysqli_query($conn, $students_query);

// Statistics
$total_certs = mysqli_num_rows($certificates);
$valid_certs = mysqli_num_rows(mysqli_query($conn, 
    "SELECT id FROM sertifikat WHERE tanggal_expired IS NULL OR tanggal_expired > CURDATE()"
));
$expired_certs = mysqli_num_rows(mysqli_query($conn, 
    "SELECT id FROM sertifikat WHERE tanggal_expired < CURDATE()"
));
$arab_certs = mysqli_num_rows(mysqli_query($conn, 
    "SELECT id FROM sertifikat WHERE jenis_sertifikat = 'arab'"
));
$english_certs = mysqli_num_rows(mysqli_query($conn, 
    "SELECT id FROM sertifikat WHERE jenis_sertifikat = 'inggris'"
));
$both_certs = mysqli_num_rows(mysqli_query($conn, 
    "SELECT id FROM sertifikat WHERE jenis_sertifikat = 'keduanya'"
));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Certificates - Admin Gensa Course</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .certificate-template {
            background: linear-gradient(45deg, #fef9e7 0%, #fff8e1 100%);
            border: 3px solid #fbb117;
            border-radius: 15px;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }
        .certificate-template::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(251, 177, 23, 0.1) 0%, transparent 70%);
            z-index: 0;
        }
        .certificate-template > * {
            position: relative;
            z-index: 1;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            color: rgba(251, 177, 23, 0.1);
            font-weight: bold;
            white-space: nowrap;
            z-index: 0;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- PERUBAHAN 1: Wrapper Utama (ml-64 -> lg:ml-64 pt-14 lg:pt-0 min-h-screen min-w-0) -->
    <div class="lg:ml-64 pt-14 lg:pt-0 min-h-screen min-w-0">
        
        <!-- Top Bar -->
        <div class="bg-white shadow-sm p-4 border-b">
            <!-- PERUBAHAN 2: Header Responsive -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-certificate text-yellow-600 mr-2"></i>Manage Certificates
                    </h1>
                    <p class="text-gray-600">Issue and manage student certificates</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                    <a href="dashboard.php" class="inline-flex w-full sm:w-auto justify-center items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                    </a>
                    <button onclick="printCertificates()" 
                            class="inline-flex w-full sm:w-auto justify-center items-center px-4 py-2 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-lg">
                        <i class="fas fa-print mr-2"></i> Print All
                    </button>
                </div>
            </div>
        </div>
        
        <!-- PERUBAHAN 3: Wrapper Konten (p-6 -> p-4 sm:p-6) -->
        <div class="p-4 sm:p-6">
            <!-- Success/Error Messages -->
            <?php if (isset($_GET['success'])): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span><?php echo htmlspecialchars($_GET['success']); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span><?php echo htmlspecialchars($_GET['error']); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
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
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Certificates</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $total_certs; ?></p>
                        </div>
                        <i class="fas fa-certificate text-yellow-500 text-xl"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Valid</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $valid_certs; ?></p>
                        </div>
                        <i class="fas fa-check-circle text-green-500 text-xl"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Expired</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $expired_certs; ?></p>
                        </div>
                        <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-600">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Arabic</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $arab_certs; ?></p>
                        </div>
                        <i class="fas fa-language text-green-600 text-xl"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">English</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $english_certs; ?></p>
                        </div>
                        <i class="fas fa-globe-americas text-blue-500 text-xl"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Both</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $both_certs; ?></p>
                        </div>
                        <i class="fas fa-award text-purple-500 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Issue Certificate Form -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4">
                            <h2 class="text-xl font-bold text-gray-800">
                                <i class="fas fa-plus-circle text-yellow-600 mr-2"></i>Issue New Certificate
                            </h2>
                            <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">
                                Auto-generated Certificate Number
                            </span>
                        </div>
                        
                        <form method="POST" action="" enctype="multipart/form-data" class="space-y-4" id="certificateForm">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        <i class="fas fa-user-graduate mr-1"></i>Student *
                                    </label>
                                    <select name="pelajar_id" id="student_select" required 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select"
                                            onchange="updateStudentInfo()">
                                        <option value="">Select Student</option>
                                        <?php while ($student = mysqli_fetch_assoc($students)): ?>
                                            <option value="<?php echo $student['id']; ?>" 
                                                    data-class="<?php echo htmlspecialchars($student['kelas']); ?>"
                                                    data-subclass="<?php echo htmlspecialchars($student['subkelas']); ?>"
                                                    data-level="<?php echo htmlspecialchars($student['jenjang']); ?>">
                                                <?php echo htmlspecialchars($student['full_name']); ?> 
                                                (<?php echo htmlspecialchars($student['kelas']); ?> - <?php echo htmlspecialchars($student['subkelas']); ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        <i class="fas fa-certificate mr-1"></i>Certificate Type *
                                    </label>
                                    <select name="jenis_sertifikat" required 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select"
                                            onchange="updateCertificateType()">
                                        <option value="">Select Type</option>
                                        <option value="arab">Arabic Language</option>
                                        <option value="inggris">English Language</option>
                                        <option value="keduanya">Both Languages (Arabic & English)</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        <i class="fas fa-signal mr-1"></i>Level / Achievement *
                                    </label>
                                    <input type="text" name="level" required 
                                           id="certificate_level"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa"
                                           placeholder="e.g., Beginner, Intermediate, Advanced, Excellence Award">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        <i class="fas fa-calendar-day mr-1"></i>Issue Date *
                                    </label>
                                    <input type="date" name="tanggal_terbit" required 
                                           value="<?php echo date('Y-m-d'); ?>"
                                           max="<?php echo date('Y-m-d'); ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        <i class="fas fa-calendar-times mr-1"></i>Expiry Date (Optional)
                                    </label>
                                    <input type="date" name="tanggal_expired" 
                                           min="<?php echo date('Y-m-d'); ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                                    <p class="text-xs text-gray-500 mt-1">Leave empty for permanent certificate</p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        <i class="fas fa-file-upload mr-1"></i>Certificate File (Optional)
                                    </label>
                                    <div class="relative">
                                        <input type="file" name="certificate_file" 
                                               accept=".pdf,.jpg,.jpeg,.png"
                                               class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100">
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">PDF, JPG, PNG (Max 5MB). Leave empty for auto-generated certificate.</p>
                                </div>
                            </div>
                            
                            <!-- Student Information Preview -->
                            <div id="studentInfo" class="hidden p-4 bg-blue-50 rounded-lg border border-blue-200">
                                <h4 class="font-medium text-gray-800 mb-2 flex items-center">
                                    <i class="fas fa-info-circle text-blue-500 mr-2"></i>Student Information
                                </h4>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    <div>
                                        <p class="text-xs text-gray-600">Class Level</p>
                                        <p class="text-sm font-medium" id="infoClass">-</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-600">Sub Class</p>
                                        <p class="text-sm font-medium" id="infoSubclass">-</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-600">Education Level</p>
                                        <p class="text-sm font-medium" id="infoLevel">-</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-600">Status</p>
                                        <span class="inline-flex items-center px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">
                                            <i class="fas fa-check-circle mr-1"></i> Active
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Generated Certificate Preview -->
                            <div id="certificatePreview" class="hidden">
                                <h4 class="font-medium text-gray-800 mb-2 flex items-center">
                                    <i class="fas fa-eye text-yellow-600 mr-2"></i>Certificate Preview
                                </h4>
                                <div class="certificate-template p-4 mb-4">
                                    <div class="watermark">GENSA COURSE</div>
                                    <div class="text-center mb-4">
                                        <h3 class="text-2xl font-bold text-yellow-700 mb-2" id="previewTitle">CERTIFICATE OF ACHIEVEMENT</h3>
                                        <p class="text-gray-600" id="previewSubtitle">Gensa English & Arabic Center</p>
                                    </div>
                                    <div class="text-center my-6">
                                        <p class="text-gray-600 mb-2">This is to certify that</p>
                                        <p class="text-2xl font-bold text-gray-800" id="previewStudentName">[Student Name]</p>
                                    </div>
                                    <div class="text-center my-6">
                                        <p class="text-gray-600 mb-2">has successfully completed</p>
                                        <p class="text-xl font-semibold text-gray-800" id="previewCourse">[Course Name]</p>
                                        <p class="text-lg text-yellow-700 font-medium" id="previewLevel">[Level]</p>
                                    </div>
                                    <div class="text-center mt-8">
                                        <div class="inline-block text-center">
                                            <div class="border-t-2 border-gray-400 w-48 mx-auto mb-2"></div>
                                            <p class="text-sm text-gray-600">Director</p>
                                            <p class="font-medium">Gensa Course</p>
                                        </div>
                                    </div>
                                    <div class="text-center mt-6">
                                        <p class="text-xs text-gray-500" id="previewCertificateNumber">
                                            Certificate No: [Auto-generated]
                                        </p>
                                        <p class="text-xs text-gray-500" id="previewIssueDate">
                                            Issued: <?php echo date('d F Y'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="pt-4">
                                <button type="submit" name="issue_certificate" 
                                        class="w-full py-3 bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-white font-semibold rounded-lg shadow-md transition btn-gensa">
                                    <i class="fas fa-certificate mr-2"></i> Issue Certificate Now
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Certificate Templates -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-file-alt text-yellow-600 mr-2"></i>Certificate Templates
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="border border-gray-200 rounded-lg p-4 hover:border-yellow-300 hover:bg-yellow-50 cursor-pointer transition" 
                                 onclick="selectTemplate('arab')">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="font-semibold text-gray-800">Arabic Certificate</h3>
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">
                                        <i class="fas fa-star mr-1"></i> Standard
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">For Arabic language course completion with Islamic design elements</p>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500">
                                        <i class="far fa-calendar mr-1"></i> Last used: Today
                                    </span>
                                    <button class="text-yellow-600 hover:text-yellow-700">
                                        <i class="fas fa-eye"></i> Preview
                                    </button>
                                </div>
                            </div>
                            
                            <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 hover:bg-blue-50 cursor-pointer transition" 
                                 onclick="selectTemplate('inggris')">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="font-semibold text-gray-800">English Certificate</h3>
                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">
                                        <i class="fas fa-star mr-1"></i> Standard
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">For English language course completion with modern design</p>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500">
                                        <i class="far fa-calendar mr-1"></i> Last used: 2 days ago
                                    </span>
                                    <button class="text-yellow-600 hover:text-yellow-700">
                                        <i class="fas fa-eye"></i> Preview
                                    </button>
                                </div>
                            </div>
                            
                            <div class="border border-gray-200 rounded-lg p-4 hover:border-purple-300 hover:bg-purple-50 cursor-pointer transition" 
                                 onclick="selectTemplate('premium')">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="font-semibold text-gray-800">Premium Certificate</h3>
                                    <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-xs">
                                        <i class="fas fa-crown mr-1"></i> Premium
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">For both Arabic and English courses with premium design</p>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500">
                                        <i class="far fa-calendar mr-1"></i> Last used: 1 week ago
                                    </span>
                                    <button class="text-yellow-600 hover:text-yellow-700">
                                        <i class="fas fa-eye"></i> Preview
                                    </button>
                                </div>
                            </div>
                            
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-yellow-400 hover:bg-yellow-50 cursor-pointer transition" 
                                 onclick="uploadCustomTemplate()">
                                <div class="text-center py-4">
                                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-3"></i>
                                    <h3 class="font-semibold text-gray-800 mb-2">Custom Template</h3>
                                    <p class="text-sm text-gray-600">Upload your own certificate design template</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="mt-6 pt-6 border-t">
                            <h4 class="font-medium text-gray-800 mb-3">Quick Actions</h4>
                            <div class="grid grid-cols-2 gap-3">
                                <button onclick="bulkGenerate()" 
                                        class="py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-bolt mr-2"></i> Bulk Generate
                                </button>
                                <button onclick="downloadTemplates()" 
                                        class="py-2 bg-green-50 text-green-700 hover:bg-green-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-download mr-2"></i> Download Templates
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Certificate List & Filters -->
                <div>
                    <!-- Search & Filters -->
                    <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-filter text-yellow-600 mr-2"></i>Search & Filters
                        </h2>
                        
                        <form method="GET" action="" class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                                <div class="relative">
                                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Search by name, certificate number..."
                                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                        <option value="">All Status</option>
                                        <option value="valid" <?php echo $status_filter == 'valid' ? 'selected' : ''; ?>>Valid</option>
                                        <option value="expired" <?php echo $status_filter == 'expired' ? 'selected' : ''; ?>>Expired</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                                    <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                        <option value="">All Types</option>
                                        <option value="arab" <?php echo $type_filter == 'arab' ? 'selected' : ''; ?>>Arabic</option>
                                        <option value="inggris" <?php echo $type_filter == 'inggris' ? 'selected' : ''; ?>>English</option>
                                        <option value="keduanya" <?php echo $type_filter == 'keduanya' ? 'selected' : ''; ?>>Both</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                                    <input type="date" name="date_from" value="<?php echo $date_from; ?>" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                                    <input type="date" name="date_to" value="<?php echo $date_to; ?>" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                </div>
                            </div>
                            
                            <div class="flex space-x-2 pt-2">
                                <button type="submit" 
                                        class="flex-1 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg">
                                    <i class="fas fa-filter mr-2"></i> Apply Filters
                                </button>
                                <a href="certificates.php" 
                                   class="flex-1 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-center">
                                    <i class="fas fa-redo mr-2"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-chart-bar text-yellow-600 mr-2"></i>Quick Stats
                        </h2>
                        
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-calendar-check text-green-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">This Month</p>
                                        <p class="font-bold">
                                            <?php 
                                            $month_count = mysqli_num_rows(mysqli_query($conn, 
                                                "SELECT id FROM sertifikat WHERE MONTH(tanggal_terbit) = MONTH(CURDATE()) AND YEAR(tanggal_terbit) = YEAR(CURDATE())"
                                            ));
                                            echo $month_count;
                                            ?> issued
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-calendar-day text-blue-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Today</p>
                                        <p class="font-bold">
                                            <?php 
                                            $today_count = mysqli_num_rows(mysqli_query($conn, 
                                                "SELECT id FROM sertifikat WHERE DATE(tanggal_terbit) = CURDATE()"
                                            ));
                                            echo $today_count;
                                            ?> issued
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Expiring Soon</p>
                                        <p class="font-bold">
                                            <?php 
                                            $expiring_count = mysqli_num_rows(mysqli_query($conn, 
                                                "SELECT id FROM sertifikat WHERE tanggal_expired BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
                                            ));
                                            echo $expiring_count;
                                            ?> certificates
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Export Options -->
                        <div class="mt-6 pt-6 border-t">
                            <h4 class="font-medium text-gray-800 mb-3">Export Options</h4>
                            <div class="space-y-2">
                                <a href="ajax/export_certificates.php" 
                                   class="block p-2 bg-green-50 hover:bg-green-100 rounded-lg text-green-700">
                                    <i class="fas fa-file-excel mr-2"></i> Export to Excel
                                </a>
                                <button onclick="printReport()" 
                                        class="w-full p-2 bg-blue-50 hover:bg-blue-100 rounded-lg text-blue-700 text-left">
                                    <i class="fas fa-print mr-2"></i> Print Report
                                </button>
                                <button onclick="exportToPDF()" 
                                        class="w-full p-2 bg-red-50 hover:bg-red-100 rounded-lg text-red-700 text-left">
                                    <i class="fas fa-file-pdf mr-2"></i> Export to PDF
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activities -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-history text-yellow-600 mr-2"></i>Recent Activities
                        </h2>
                        
                        <div class="space-y-3 max-h-64 overflow-y-auto">
                            <?php
                            $recent_query = "
                                SELECT s.nomor_sertifikat, u.full_name as student_name, 
                                       s.jenis_sertifikat, s.tanggal_terbit, a.full_name as issued_by
                                FROM sertifikat s
                                JOIN users u ON s.pelajar_id = u.id
                                LEFT JOIN users a ON s.created_by = a.id
                                ORDER BY s.created_at DESC
                                LIMIT 5
                            ";
                            $recent = mysqli_query($conn, $recent_query);
                            
                            if (mysqli_num_rows($recent) > 0):
                                while ($activity = mysqli_fetch_assoc($recent)):
                            ?>
                                <div class="flex items-start space-x-3 p-2 hover:bg-gray-50 rounded">
                                    <div class="w-8 h-8 rounded-full <?php 
                                        echo $activity['jenis_sertifikat'] == 'arab' ? 'bg-green-100 text-green-600' : 
                                        ($activity['jenis_sertifikat'] == 'inggris' ? 'bg-blue-100 text-blue-600' : 'bg-purple-100 text-purple-600');
                                    ?> flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-certificate text-sm"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium">
                                            <?php echo htmlspecialchars($activity['student_name']); ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo strtoupper($activity['jenis_sertifikat']); ?> • 
                                            <?php echo date('d M', strtotime($activity['tanggal_terbit'])); ?>
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            By: <?php echo htmlspecialchars($activity['issued_by']); ?>
                                        </p>
                                    </div>
                                    <span class="text-xs font-mono text-gray-500">
                                        #<?php echo substr($activity['nomor_sertifikat'], -6); ?>
                                    </span>
                                </div>
                            <?php 
                                endwhile;
                            else:
                            ?>
                                <p class="text-gray-500 text-center py-4">No recent activities</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Certificate List -->
            <div class="mt-6 bg-white rounded-xl shadow-md p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">Issued Certificates</h2>
                        <p class="text-gray-600">Showing <?php echo $total_certs; ?> certificates</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button onclick="selectAllCertificates()" 
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg">
                            <i class="fas fa-check-square mr-2"></i> Select All
                        </button>
                        <button onclick="bulkActions()" 
                                class="px-4 py-2 bg-yellow-100 hover:bg-yellow-200 text-yellow-700 rounded-lg">
                            <i class="fas fa-cogs mr-2"></i> Bulk Actions
                        </button>
                        <button onclick="refreshList()" 
                                class="px-4 py-2 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-lg">
                            <i class="fas fa-sync-alt mr-2"></i> Refresh
                        </button>
                    </div>
                </div>
                
                <?php if ($total_certs > 0): ?>
                    <!-- PERUBAHAN 4: Tabel Responsive -->
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px]">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700 w-16">
                                        <input type="checkbox" id="selectAll" class="rounded text-yellow-600">
                                    </th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Certificate No.</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Student</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Type & Level</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Issue Date</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Expiry Date</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Status</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php 
                                mysqli_data_seek($certificates, 0);
                                while ($cert = mysqli_fetch_assoc($certificates)): 
                                    $is_expired = $cert['tanggal_expired'] && strtotime($cert['tanggal_expired']) < time();
                                    $type_colors = [
                                        'arab' => 'bg-green-100 text-green-800',
                                        'inggris' => 'bg-blue-100 text-blue-800',
                                        'keduanya' => 'bg-purple-100 text-purple-800'
                                    ];
                                    $status_colors = $is_expired ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800';
                                ?>
                                    <tr class="hover:bg-gray-50" id="cert-row-<?php echo $cert['id']; ?>">
                                        <td class="py-3 px-4">
                                            <input type="checkbox" value="<?php echo $cert['id']; ?>" 
                                                   class="certificate-checkbox rounded text-yellow-600">
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="font-mono text-sm font-medium text-gray-800">
                                                <?php echo htmlspecialchars($cert['nomor_sertifikat']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo $cert['issued_by'] ? 'By: ' . htmlspecialchars($cert['issued_by']) : 'System Generated'; ?>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="font-medium text-gray-800">
                                                <?php echo htmlspecialchars($cert['student_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($cert['kelas']); ?> - <?php echo htmlspecialchars($cert['subkelas']); ?>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="space-y-1">
                                                <span class="inline-block px-2 py-1 <?php echo $type_colors[$cert['jenis_sertifikat']]; ?> rounded text-xs font-medium">
                                                    <i class="fas fa-<?php echo $cert['jenis_sertifikat'] == 'arab' ? 'language' : ($cert['jenis_sertifikat'] == 'inggris' ? 'globe-americas' : 'award'); ?> mr-1"></i>
                                                    <?php echo ucfirst($cert['jenis_sertifikat']); ?>
                                                </span>
                                                <div class="text-sm font-medium"><?php echo htmlspecialchars($cert['level']); ?></div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="text-sm text-gray-800">
                                                <?php echo date('d M Y', strtotime($cert['tanggal_terbit'])); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo date('H:i', strtotime($cert['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <?php if ($cert['tanggal_expired']): ?>
                                                <div class="text-sm <?php echo $is_expired ? 'text-red-600 font-medium' : 'text-gray-800'; ?>">
                                                    <?php echo date('d M Y', strtotime($cert['tanggal_expired'])); ?>
                                                </div>
                                                <?php if ($is_expired): ?>
                                                    <div class="text-xs text-red-500">Expired</div>
                                                <?php else: ?>
                                                    <div class="text-xs text-gray-500">
                                                        <?php 
                                                        $days_left = ceil((strtotime($cert['tanggal_expired']) - time()) / (60 * 60 * 24));
                                                        echo $days_left > 0 ? $days_left . ' days left' : 'Expires today';
                                                        ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-sm text-gray-400 italic">No expiry</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $status_colors; ?>">
                                                <i class="fas fa-<?php echo $is_expired ? 'exclamation-triangle' : 'check-circle'; ?> mr-1"></i>
                                                <?php echo $is_expired ? 'Expired' : 'Valid'; ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex space-x-1">
                                                <?php if ($cert['file_path']): ?>
                                                    <a href="?download=<?php echo $cert['id']; ?>" 
                                                       class="p-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded"
                                                       title="Download Certificate"
                                                       onclick="return confirm('Download certificate file?')">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="?regenerate=<?php echo $cert['id']; ?>" 
                                                       class="p-2 bg-yellow-100 text-yellow-700 hover:bg-yellow-200 rounded"
                                                       title="Generate Certificate File"
                                                       onclick="return confirm('Generate certificate file?')">
                                                        <i class="fas fa-file-download"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <a href="edit_certificate.php?id=<?php echo $cert['id']; ?>" 
                                                   class="p-2 bg-green-100 text-green-700 hover:bg-green-200 rounded"
                                                   title="Edit Certificate">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <a href="print_certificate.php?id=<?php echo $cert['id']; ?>" 
                                                   target="_blank"
                                                   class="p-2 bg-purple-100 text-purple-700 hover:bg-purple-200 rounded"
                                                   title="Print Certificate">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                                
                                                <a href="?delete=<?php echo $cert['id']; ?>" 
                                                   class="p-2 bg-red-100 text-red-700 hover:bg-red-200 rounded"
                                                   title="Delete Certificate"
                                                   onclick="return confirm('Are you sure you want to delete this certificate? This action cannot be undone.')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="mt-6 flex flex-col md:flex-row justify-between items-center">
                        <div class="text-sm text-gray-600 mb-4 md:mb-0">
                            Showing <?php echo $total_certs; ?> of <?php echo $total_certs; ?> certificates
                        </div>
                        <div class="flex space-x-2">
                            <button class="px-3 py-1 bg-gray-100 text-gray-700 rounded disabled">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="px-3 py-1 bg-yellow-500 text-white rounded">1</button>
                            <button class="px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                                2
                            </button>
                            <button class="px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                                3
                            </button>
                            <button class="px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <div class="inline-block p-6 bg-yellow-50 rounded-full mb-4">
                            <i class="fas fa-certificate text-4xl text-yellow-400"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-700 mb-2">No Certificates Found</h3>
                        <p class="text-gray-500 mb-6">No certificates match your search criteria or no certificates have been issued yet.</p>
                        <button onclick="showIssueForm()" 
                                class="px-6 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg">
                            <i class="fas fa-plus mr-2"></i> Issue Your First Certificate
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Bulk Actions Modal -->
    <div id="bulkModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Bulk Actions</h3>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-600 mb-2">Selected <span id="selectedCount">0</span> certificates</p>
                        <select id="bulkAction" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <option value="">Choose Action</option>
                            <option value="download">Download Selected</option>
                            <option value="print">Print Selected</option>
                            <option value="email">Email to Students</option>
                            <option value="renew">Renew Certificates</option>
                            <option value="delete">Delete Selected</option>
                        </select>
                    </div>
                    <div class="flex space-x-3">
                        <button type="button" onclick="closeBulkModal()" 
                                class="flex-1 py-2 bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-lg">
                            Cancel
                        </button>
                        <button type="button" onclick="executeBulkAction()" 
                                class="flex-1 py-2 bg-yellow-500 text-white hover:bg-yellow-600 rounded-lg">
                            Execute
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Student information update
    function updateStudentInfo() {
        const select = document.getElementById('student_select');
        const selectedOption = select.options[select.selectedIndex];
        
        if (selectedOption && selectedOption.value) {
            // Show student info
            document.getElementById('studentInfo').classList.remove('hidden');
            document.getElementById('infoClass').textContent = selectedOption.dataset.class;
            document.getElementById('infoSubclass').textContent = selectedOption.dataset.subclass;
            document.getElementById('infoLevel').textContent = selectedOption.dataset.level;
            
            // Set default level
            document.getElementById('certificate_level').value = selectedOption.dataset.level;
            
            // Update certificate preview
            updateCertificatePreview();
        } else {
            document.getElementById('studentInfo').classList.add('hidden');
        }
    }
    
    // Update certificate type
    function updateCertificateType() {
        const typeSelect = document.querySelector('select[name="jenis_sertifikat"]');
        const levelInput = document.getElementById('certificate_level');
        
        if (typeSelect.value) {
            // Show certificate preview
            document.getElementById('certificatePreview').classList.remove('hidden');
            
            // Update preview based on type
            const typeNames = {
                'arab': 'Arabic Language',
                'inggris': 'English Language',
                'keduanya': 'Arabic & English Languages'
            };
            
            document.getElementById('previewCourse').textContent = typeNames[typeSelect.value] + ' Course';
            
            // Update preview
            updateCertificatePreview();
        } else {
            document.getElementById('certificatePreview').classList.add('hidden');
        }
    }
    
    // Update certificate preview
    function updateCertificatePreview() {
        const studentSelect = document.getElementById('student_select');
        const typeSelect = document.querySelector('select[name="jenis_sertifikat"]');
        const levelInput = document.getElementById('certificate_level');
        const issueDate = document.querySelector('input[name="tanggal_terbit"]');
        
        if (studentSelect.value && typeSelect.value) {
            const studentName = studentSelect.options[studentSelect.selectedIndex].text.split(' (')[0];
            const certificateType = typeSelect.options[typeSelect.selectedIndex].text;
            const level = levelInput.value;
            
            // Update preview elements
            document.getElementById('previewStudentName').textContent = studentName;
            document.getElementById('previewLevel').textContent = level;
            
            // Generate certificate number preview
            const certNumber = 'GENSA-' + new Date().toISOString().slice(0,10).replace(/-/g, '') + '-' + 
                              studentSelect.value.padStart(4, '0') + '-' + Math.floor(Math.random() * 1000);
            document.getElementById('previewCertificateNumber').textContent = 'Certificate No: ' + certNumber;
            
            // Update issue date
            if (issueDate.value) {
                const date = new Date(issueDate.value);
                document.getElementById('previewIssueDate').textContent = 'Issued: ' + 
                    date.toLocaleDateString('en-US', { day: 'numeric', month: 'long', year: 'numeric' });
            }
        }
    }
    
    // Template selection
    function selectTemplate(type) {
        const templates = {
            'arab': {
                title: 'ARABIC LANGUAGE CERTIFICATE',
                subtitle: 'Gensa Arabic Center - Islamic Studies Department',
                color: 'from-green-100 to-green-50',
                border: 'border-green-300'
            },
            'inggris': {
                title: 'ENGLISH LANGUAGE CERTIFICATE',
                subtitle: 'Gensa English Center - International Curriculum',
                color: 'from-blue-100 to-blue-50',
                border: 'border-blue-300'
            },
            'premium': {
                title: 'PREMIUM ACHIEVEMENT CERTIFICATE',
                subtitle: 'Gensa English & Arabic Center - Excellence Award',
                color: 'from-purple-100 to-purple-50',
                border: 'border-purple-300'
            }
        };
        
        const template = templates[type];
        if (template) {
            alert(`Selected ${type} certificate template`);
            // In production, you would apply template settings to the form
        }
    }
    
    // Upload custom template
    function uploadCustomTemplate() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.pdf,.jpg,.jpeg,.png,.psd,.ai';
        input.onchange = function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 10 * 1024 * 1024) {
                    alert('File too large. Maximum 10MB allowed.');
                    return;
                }
                
                alert(`Template "${file.name}" uploaded successfully!`);
                // In production, upload via AJAX
            }
        };
        input.click();
    }
    
    // Bulk certificate generation
    function bulkGenerate() {
        window.open('bulk_certificates.php', '_blank');
    }
    
    // Download templates
    function downloadTemplates() {
        alert('Downloading certificate templates...');
        // In production, redirect to download link
    }
    
    // Print all certificates
    function printCertificates() {
        if (confirm('Print all certificates? This may take a while.')) {
            window.open('print_all_certificates.php', '_blank');
        }
    }
    
    // Print report
    function printReport() {
        window.print();
    }
    
    // Export to PDF
    function exportToPDF() {
        const params = new URLSearchParams(window.location.search);
        window.open(`ajax/export_certificates_pdf.php?${params.toString()}`, '_blank');
    }
    
    // Select all certificates
    function selectAllCertificates() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.certificate-checkbox');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
        });
        
        updateSelectedCount();
    }
    
    // Update selected count
    function updateSelectedCount() {
        const selected = document.querySelectorAll('.certificate-checkbox:checked');
        document.getElementById('selectedCount').textContent = selected.length;
    }
    
    // Bulk actions modal
    function bulkActions() {
        const selected = document.querySelectorAll('.certificate-checkbox:checked');
        if (selected.length === 0) {
            alert('Please select at least one certificate.');
            return;
        }
        
        document.getElementById('selectedCount').textContent = selected.length;
        document.getElementById('bulkModal').classList.remove('hidden');
        document.getElementById('bulkModal').classList.add('flex');
    }
    
    // Close bulk modal
    function closeBulkModal() {
        document.getElementById('bulkModal').classList.add('hidden');
        document.getElementById('bulkModal').classList.remove('flex');
    }
    
    // Execute bulk action
    function executeBulkAction() {
        const action = document.getElementById('bulkAction').value;
        const selected = Array.from(document.querySelectorAll('.certificate-checkbox:checked'))
                             .map(cb => cb.value);
        
        if (!action) {
            alert('Please select an action.');
            return;
        }
        
        if (selected.length === 0) {
            alert('No certificates selected.');
            return;
        }
        
        switch(action) {
            case 'download':
                alert(`Downloading ${selected.length} certificates...`);
                // In production: window.open('ajax/bulk_download.php?ids=' + selected.join(','));
                break;
            case 'print':
                alert(`Printing ${selected.length} certificates...`);
                // In production: window.open('ajax/bulk_print.php?ids=' + selected.join(','));
                break;
            case 'email':
                alert(`Emailing ${selected.length} certificates to students...`);
                // In production: fetch('ajax/bulk_email.php', { method: 'POST', body: JSON.stringify({ ids: selected }) });
                break;
            case 'renew':
                if (confirm(`Renew ${selected.length} certificates for another year?`)) {
                    fetch('ajax/bulk_renew.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ids: selected })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Certificates renewed successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    });
                }
                break;
            case 'delete':
                if (confirm(`Permanently delete ${selected.length} certificates? This action cannot be undone.`)) {
                    fetch('ajax/bulk_delete.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ids: selected })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Certificates deleted successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    });
                }
                break;
        }
        
        closeBulkModal();
    }
    
    // Refresh list
    function refreshList() {
        location.reload();
    }
    
    // Show issue form
    function showIssueForm() {
        document.getElementById('student_select').focus();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Update selected count when checkboxes change
        document.querySelectorAll('.certificate-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedCount);
        });
        
        // Select all checkbox
        document.getElementById('selectAll').addEventListener('change', selectAllCertificates);
        
        // Form validation
        document.getElementById('certificateForm').addEventListener('submit', function(e) {
            const studentSelect = document.getElementById('student_select');
            const typeSelect = document.querySelector('select[name="jenis_sertifikat"]');
            const levelInput = document.getElementById('certificate_level');
            const issueDate = document.querySelector('input[name="tanggal_terbit"]');
            const expiryDate = document.querySelector('input[name="tanggal_expired"]');
            
            if (!studentSelect.value) {
                e.preventDefault();
                alert('Please select a student.');
                studentSelect.focus();
                return false;
            }
            
            if (!typeSelect.value) {
                e.preventDefault();
                alert('Please select certificate type.');
                typeSelect.focus();
                return false;
            }
            
            if (!levelInput.value.trim()) {
                e.preventDefault();
                alert('Please enter certificate level/achievement.');
                levelInput.focus();
                return false;
            }
            
            if (expiryDate.value && expiryDate.value <= issueDate.value) {
                e.preventDefault();
                alert('Expiry date must be after issue date.');
                expiryDate.focus();
                return false;
            }
            
            // File validation
            const fileInput = document.querySelector('input[name="certificate_file"]');
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
                const maxSize = 5 * 1024 * 1024; // 5MB
                
                if (!allowedTypes.includes(file.type)) {
                    e.preventDefault();
                    alert('Invalid file type. Please upload PDF, JPG, or PNG files only.');
                    return false;
                }
                
                if (file.size > maxSize) {
                    e.preventDefault();
                    alert('File too large. Maximum 5MB allowed.');
                    return false;
                }
            }
            
            return true;
        });
        
        // Auto-update preview when fields change
        const previewFields = ['student_select', 'jenis_sertifikat', 'certificate_level', 'tanggal_terbit'];
        previewFields.forEach(field => {
            const element = document.getElementById(field) || document.querySelector(`[name="${field}"]`);
            if (element) {
                element.addEventListener('change', updateCertificatePreview);
                element.addEventListener('input', updateCertificatePreview);
            }
        });
        
        // Initialize preview if student and type are selected
        const studentSelect = document.getElementById('student_select');
        const typeSelect = document.querySelector('select[name="jenis_sertifikat"]');
        
        if (studentSelect.value && typeSelect.value) {
            updateStudentInfo();
            updateCertificateType();
        }
    });
    
    // Close modal when clicking outside
    document.getElementById('bulkModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeBulkModal();
        }
    });
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + N to focus on new certificate form
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            showIssueForm();
        }
        
        // Ctrl + F to focus on search
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            document.querySelector('input[name="search"]').focus();
        }
        
        // Esc to close modal
        if (e.key === 'Escape') {
            closeBulkModal();
        }
    });
    </script>
</body>
</html>