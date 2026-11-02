<?php
include 'includes/auth_check.php';
include '../config/database.php'; // TAMBAHKAN INI!

// Get student data
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$username = $_SESSION['username'];

// Get student profile
$profile_query = "SELECT * FROM pelajar_profiles WHERE user_id = $user_id";
$profile_result = mysqli_query($conn, $profile_query);
$student_profile = mysqli_fetch_assoc($profile_result);

// Get student certificates
$certificates_query = "
    SELECT s.*, u.full_name as issued_by
    FROM sertifikat s
    LEFT JOIN users u ON s.created_by = u.id
    WHERE s.pelajar_id = $user_id
    ORDER BY s.tanggal_terbit DESC
";
$certificates = mysqli_query($conn, $certificates_query);

// Get certificate statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_certificates,
        COUNT(CASE WHEN jenis_sertifikat = 'arab' THEN 1 END) as arab_certs,
        COUNT(CASE WHEN jenis_sertifikat = 'inggris' THEN 1 END) as english_certs,
        COUNT(CASE WHEN jenis_sertifikat = 'keduanya' THEN 1 END) as both_certs,
        COUNT(CASE WHEN tanggal_expired IS NULL OR tanggal_expired > CURDATE() THEN 1 END) as valid_certs
    FROM sertifikat 
    WHERE pelajar_id = $user_id
";
$stats_result = mysqli_query($conn, $stats_query);
$cert_stats = mysqli_fetch_assoc($stats_result);

// Get upcoming expiries
$expiring_query = "
    SELECT * FROM sertifikat 
    WHERE pelajar_id = $user_id 
    AND tanggal_expired IS NOT NULL
    AND tanggal_expired BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY tanggal_expired
";
$expiring_certs = mysqli_query($conn, $expiring_query);

// Get counts for sidebar badges
$pending_assignments = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as count FROM jadwal j
     JOIN jadwal_siswa js ON j.id = js.jadwal_id
     WHERE js.pelajar_id = $user_id 
     AND j.tanggal = CURDATE() 
     AND j.status = 'scheduled'"
))['count'];

$new_scores = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as count FROM nilai_siswa 
     WHERE pelajar_id = $user_id 
     AND DATE(created_at) = CURDATE()"
))['count'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Certificates - Gensa Course</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .gensa-gradient {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
        }
        .gensa-text {
            color: #f59e0b;
        }
        .sidebar-badge {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
        }
        .certificate-card {
            transition: all 0.3s ease;
        }
        .certificate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Mobile Menu Toggle -->
    <button id="mobileMenuBtn" class="lg:hidden fixed top-4 left-4 z-50 p-2 bg-yellow-500 text-white rounded-lg shadow-lg">
        <i class="fas fa-bars text-xl"></i>
    </button>
    
    <!-- Sidebar Overlay -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden lg:hidden"></div>
    
    <!-- Sidebar -->
    <div id="sidebar" class="fixed left-0 top-0 h-full w-64 gensa-gradient text-white z-40 transform -translate-x-full lg:translate-x-0 transition-transform duration-300">
        <div class="p-6 h-full flex flex-col">
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center">
                        <i class="fas fa-user-graduate text-2xl gensa-text"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold">Student Portal</h2>
                        <p class="text-sm opacity-80">Gensa Course</p>
                    </div>
                </div>
                <button id="closeSidebar" class="lg:hidden text-white hover:text-gray-300">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <nav class="space-y-2 flex-1 overflow-y-auto">
                <a href="dashboard.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition relative">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="nilai.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition relative">
                    <i class="fas fa-graduation-cap"></i>
                    <span>My Scores</span>
                    <?php if ($new_scores > 0): ?>
                        <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full sidebar-badge"><?php echo $new_scores; ?></span>
                    <?php endif; ?>
                </a>
                <a href="jadwal.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition relative">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Schedule</span>
                    <?php if ($pending_assignments > 0): ?>
                        <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full sidebar-badge"><?php echo $pending_assignments; ?></span>
                    <?php endif; ?>
                </a>
                <a href="absensi.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Attendance</span>
                </a>
                <a href="riwayat_nilai.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
                    <i class="fas fa-history"></i>
                    <span>Score History</span>
                </a>
                <a href="sertifikat.php" class="flex items-center space-x-3 p-3 bg-white bg-opacity-20 rounded-lg relative">
                    <i class="fas fa-certificate"></i>
                    <span>Certificates</span>
                </a>
            </nav>
            
            <div class="pt-6 border-t border-white border-opacity-20">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                            <?php 
                            $initials = strtoupper(substr($full_name, 0, 1));
                            if (strpos($full_name, ' ') !== false) {
                                $initials .= strtoupper(substr($full_name, strpos($full_name, ' ') + 1, 1));
                            }
                            ?>
                            <span class="text-sm font-bold"><?php echo $initials; ?></span>
                        </div>
                        <div>
                            <p class="text-sm font-medium"><?php echo htmlspecialchars($full_name); ?></p>
                            <p class="text-xs opacity-80">Student</p>
                        </div>
                    </div>
                    <a href="../logout.php" class="p-2 hover:bg-white hover:bg-opacity-10 rounded" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Wrapper -->
    <div class="lg:ml-64 pt-14 lg:pt-0 min-h-screen min-w-0">
        <!-- Top Bar -->
        <div class="bg-white shadow-sm p-4 border-b">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">My Certificates</h1>
                    <p class="text-gray-600">View and manage your language course certificates</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                    <button onclick="printAllCertificates()" class="w-full sm:w-auto justify-center px-4 py-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg transition">
                        <i class="fas fa-print mr-2"></i> Print All
                    </button>
                    <button onclick="verifyCertificate()" class="w-full sm:w-auto justify-center px-4 py-2 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg transition">
                        <i class="fas fa-shield-alt mr-2"></i> Verify
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Content -->
        <div class="p-4 sm:p-6">
            <!-- Certificate Statistics -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-yellow-500 certificate-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Certificates</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $cert_stats['total_certificates'] ?? 0; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-certificate text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500 certificate-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Arabic Certificates</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $cert_stats['arab_certs'] ?? 0; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-language text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500 certificate-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">English Certificates</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $cert_stats['english_certs'] ?? 0; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-globe text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-purple-500 certificate-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Valid Certificates</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $cert_stats['valid_certs'] ?? 0; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-check-circle text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Certificate List -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-800">My Certificates</h2>
                            <div class="text-sm text-gray-600">
                                <?php echo mysqli_num_rows($certificates); ?> certificates
                            </div>
                        </div>
                        
                        <?php if (mysqli_num_rows($certificates) > 0): ?>
                            <div class="space-y-4">
                                <?php while ($cert = mysqli_fetch_assoc($certificates)): 
                                    $is_expired = !empty($cert['tanggal_expired']) && strtotime($cert['tanggal_expired']) < time();
                                    $type_colors = [
                                        'arab' => ['bg-green-100 text-green-800', 'fa-language', 'border-green-200'],
                                        'inggris' => ['bg-blue-100 text-blue-800', 'fa-globe', 'border-blue-200'],
                                        'keduanya' => ['bg-purple-100 text-purple-800', 'fa-certificate', 'border-purple-200']
                                    ];
                                    $type_color = $type_colors[$cert['jenis_sertifikat']] ?? $type_colors['keduanya'];
                                ?>
                                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition certificate-card <?php echo $is_expired ? 'bg-gray-50' : ''; ?>">
                                        <div class="flex flex-col sm:flex-row justify-between items-start gap-3 mb-3">
                                            <div class="flex items-start space-x-3">
                                                <div class="w-12 h-12 <?php echo $type_color[0]; ?> rounded-lg flex items-center justify-center">
                                                    <i class="fas <?php echo $type_color[1]; ?> text-lg"></i>
                                                </div>
                                                <div>
                                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($cert['nomor_sertifikat'] ?? 'No Number'); ?></p>
                                                    <p class="text-sm text-gray-600">
                                                        <?php echo ucfirst($cert['jenis_sertifikat'] ?? 'Unknown'); ?> - Level: <?php echo $cert['level'] ?? 'Unknown'; ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500">
                                                        Issued by: <?php echo htmlspecialchars($cert['issued_by'] ?? 'System'); ?> on 
                                                        <?php echo date('d M Y', strtotime($cert['tanggal_terbit'])); ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="text-left sm:text-right">
                                                <?php if ($is_expired): ?>
                                                    <span class="inline-block px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs mb-2">
                                                        Expired
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-block px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs mb-2">
                                                        Valid
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($cert['tanggal_expired'])): ?>
                                                    <p class="text-xs text-gray-600">
                                                        Expires: <?php echo date('d M Y', strtotime($cert['tanggal_expired'])); ?>
                                                    </p>
                                                <?php else: ?>
                                                    <p class="text-xs text-gray-600">No expiry</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mt-4 pt-4 border-t border-gray-200">
                                            <div class="flex flex-wrap gap-2">
                                                <?php if (!empty($cert['file_path'])): ?>
                                                    <a href="download_certificate.php?id=<?php echo $cert['id']; ?>" 
                                                       class="px-3 py-1 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded text-sm transition">
                                                        <i class="fas fa-download mr-1"></i> Download
                                                    </a>
                                                <?php endif; ?>
                                                <button onclick="viewCertificate(<?php echo $cert['id']; ?>)" 
                                                        class="px-3 py-1 bg-green-100 text-green-700 hover:bg-green-200 rounded text-sm transition">
                                                    <i class="fas fa-eye mr-1"></i> View
                                                </button>
                                                <button onclick="shareCertificate(<?php echo $cert['id']; ?>)" 
                                                        class="px-3 py-1 bg-purple-100 text-purple-700 hover:bg-purple-200 rounded text-sm transition">
                                                    <i class="fas fa-share-alt mr-1"></i> Share
                                                </button>
                                            </div>
                                            <button onclick="verifyCertificateNumber('<?php echo $cert['nomor_sertifikat']; ?>')" 
                                                    class="text-sm text-yellow-600 hover:text-yellow-700 transition">
                                                <i class="fas fa-shield-alt mr-1"></i> Verify
                                            </button>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <i class="fas fa-certificate text-5xl text-gray-300 mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-700 mb-2">No Certificates Yet</h3>
                                <p class="text-gray-500">You haven't received any certificates yet. Certificates are awarded upon course completion.</p>
                                <a href="dashboard.php" class="inline-block mt-4 text-yellow-600 hover:text-yellow-700 font-medium">
                                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Side Panel -->
                <div class="space-y-6">
                    <!-- Certificate Verification -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Verify Certificate</h2>
                        
                        <div class="space-y-3">
                            <p class="text-sm text-gray-600">Enter certificate number to verify authenticity</p>
                            <div class="relative">
                                <input type="text" id="verifyInput" 
                                       placeholder="CERT-20240101-0001-123"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                                <button onclick="verifyInput()" 
                                        class="absolute right-2 top-2 px-3 py-1 bg-yellow-500 text-white rounded hover:bg-yellow-600 transition">
                                    Verify
                                </button>
                            </div>
                            <div id="verificationResult" class="hidden p-3 rounded-lg transition">
                                <!-- Result will be shown here -->
                            </div>
                        </div>
                        
                        <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-shield-alt text-blue-500 mr-2"></i>
                                <p class="text-sm text-blue-700">All certificates are digitally verified and secure</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Upcoming Expiries -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Upcoming Expiries</h2>
                        
                        <?php if (mysqli_num_rows($expiring_certs) > 0): ?>
                            <div class="space-y-3">
                                <?php while ($expiring = mysqli_fetch_assoc($expiring_certs)): 
                                    $days_left = ceil((strtotime($expiring['tanggal_expired']) - time()) / (60 * 60 * 24));
                                ?>
                                    <div class="p-3 border border-yellow-200 rounded-lg bg-yellow-50">
                                        <div class="flex justify-between items-center mb-2">
                                            <p class="text-sm font-medium"><?php echo htmlspecialchars($expiring['nomor_sertifikat']); ?></p>
                                            <span class="text-xs px-2 py-1 bg-yellow-100 text-yellow-800 rounded">
                                                <?php echo $days_left; ?> days
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-600">
                                            <?php echo ucfirst($expiring['jenis_sertifikat']); ?> - <?php echo $expiring['level']; ?>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            Expires: <?php echo date('d M Y', strtotime($expiring['tanggal_expired'])); ?>
                                        </p>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-6">
                                <i class="fas fa-check-circle text-3xl text-green-300 mb-2"></i>
                                <p class="text-gray-500">No certificates expiring soon</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Certificate Types -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Certificate Types</h2>
                        
                        <div class="space-y-3">
                            <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded transition">
                                <div class="flex items-center space-x-2">
                                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                    <span class="text-sm">Arabic Language</span>
                                </div>
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-medium">
                                    <?php echo $cert_stats['arab_certs'] ?? 0; ?>
                                </span>
                            </div>
                            
                            <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded transition">
                                <div class="flex items-center space-x-2">
                                    <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                                    <span class="text-sm">English Language</span>
                                </div>
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-medium">
                                    <?php echo $cert_stats['english_certs'] ?? 0; ?>
                                </span>
                            </div>
                            
                            <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded transition">
                                <div class="flex items-center space-x-2">
                                    <div class="w-3 h-3 bg-purple-500 rounded-full"></div>
                                    <span class="text-sm">Both Languages</span>
                                </div>
                                <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-xs font-medium">
                                    <?php echo $cert_stats['both_certs'] ?? 0; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Quick Actions</h2>
                        
                        <div class="space-y-3">
                            <button onclick="requestNewCertificate()" 
                                    class="w-full py-2 bg-yellow-50 text-yellow-700 hover:bg-yellow-100 rounded-lg text-left px-4 transition">
                                <i class="fas fa-plus-circle mr-2"></i> Request New Certificate
                            </button>
                            <button onclick="downloadAllCertificates()" 
                                    class="w-full py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg text-left px-4 transition">
                                <i class="fas fa-download mr-2"></i> Download All
                            </button>
                            <button onclick="shareProfile()" 
                                    class="w-full py-2 bg-green-50 text-green-700 hover:bg-green-100 rounded-lg text-left px-4 transition">
                                <i class="fas fa-share-square mr-2"></i> Share Profile
                            </button>
                            <a href="dashboard.php" 
                               class="block w-full py-2 bg-purple-50 text-purple-700 hover:bg-purple-100 rounded-lg text-left px-4 transition">
                                <i class="fas fa-tachometer-alt mr-2"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Certificate Gallery -->
            <div class="mt-6 bg-white rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Certificate Gallery</h2>
                
                <?php
                // Reset pointer to beginning of certificates result
                mysqli_data_seek($certificates, 0);
                $has_files = false;
                ?>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" id="certificateGallery">
                    <?php while ($cert = mysqli_fetch_assoc($certificates)): 
                        if (!empty($cert['file_path'])) {
                            $has_files = true;
                    ?>
                        <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition certificate-card">
                            <div class="p-4">
                                <div class="aspect-video bg-gray-100 rounded flex items-center justify-center mb-3">
                                    <i class="fas fa-certificate text-4xl <?php echo $cert['jenis_sertifikat'] == 'arab' ? 'text-green-400' : ($cert['jenis_sertifikat'] == 'inggris' ? 'text-blue-400' : 'text-purple-400'); ?>"></i>
                                </div>
                                <p class="text-sm font-medium truncate"><?php echo htmlspecialchars($cert['nomor_sertifikat']); ?></p>
                                <p class="text-xs text-gray-500">
                                    <?php echo ucfirst($cert['jenis_sertifikat']); ?> - <?php echo $cert['level']; ?>
                                </p>
                                <div class="mt-2 flex justify-between">
                                    <button onclick="previewCertificate('<?php echo $cert['file_path']; ?>')" 
                                            class="text-xs text-blue-600 hover:text-blue-700 transition">
                                        <i class="fas fa-eye mr-1"></i> Preview
                                    </button>
                                    <a href="download_certificate.php?id=<?php echo $cert['id']; ?>" 
                                       class="text-xs text-green-600 hover:text-green-700 transition">
                                        <i class="fas fa-download mr-1"></i> Download
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php 
                        }
                    endwhile; 
                    
                    if (!$has_files):
                    ?>
                        <div class="col-span-3 text-center py-12">
                            <i class="fas fa-images text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No certificate files available for preview</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="mt-8 text-center text-gray-500 text-sm">
                <p>Gensa Course Certificate Portal &copy; <?php echo date('Y'); ?> - All rights reserved</p>
                <p class="mt-1">Student ID: <?php echo htmlspecialchars($username); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Certificate Preview Modal -->
    <div id="previewModal" class="fixed inset-0 bg-black bg-opacity-75 hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-800">Certificate Preview</h3>
                    <button onclick="closePreviewModal()" class="text-gray-400 hover:text-gray-600 transition">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="previewContent" class="flex items-center justify-center">
                    <div class="text-center py-12">
                        <i class="fas fa-spinner fa-spin text-3xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">Loading preview...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const closeSidebar = document.getElementById('closeSidebar');
    
    function openSidebar() {
        sidebar.classList.remove('-translate-x-full');
        sidebarOverlay.classList.remove('hidden');
    }
    
    function closeSidebarFunc() {
        sidebar.classList.add('-translate-x-full');
        sidebarOverlay.classList.add('hidden');
    }
    
    mobileMenuBtn.addEventListener('click', openSidebar);
    closeSidebar.addEventListener('click', closeSidebarFunc);
    sidebarOverlay.addEventListener('click', closeSidebarFunc);
    
    function printAllCertificates() {
        alert('Print feature coming soon! For now, please download certificates individually.');
    }
    
    function verifyCertificate() {
        const input = document.getElementById('verifyInput');
        input.focus();
    }
    
    function verifyInput() {
        const certNumber = document.getElementById('verifyInput').value.trim();
        if (!certNumber) {
            alert('Please enter a certificate number');
            return;
        }
        
        // Show loading state
        const resultDiv = document.getElementById('verificationResult');
        resultDiv.classList.remove('hidden');
        resultDiv.className = 'p-3 bg-blue-50 border border-blue-200 rounded-lg';
        resultDiv.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-spinner fa-spin text-blue-500 mr-2"></i>
                <div>
                    <p class="font-medium text-blue-800">Verifying certificate...</p>
                </div>
            </div>
        `;
        
        // Simulate verification (in real app, this would be an AJAX call)
        setTimeout(() => {
            // Mock verification result
            const isVerified = certNumber.includes('CERT');
            
            if (isVerified) {
                resultDiv.className = 'p-3 bg-green-50 border border-green-200 rounded-lg';
                resultDiv.innerHTML = `
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-2"></i>
                        <div>
                            <p class="font-medium text-green-800">Certificate Verified</p>
                            <p class="text-sm text-green-700">Issued to: <?php echo htmlspecialchars($full_name); ?></p>
                            <p class="text-sm text-green-700">Status: Valid</p>
                            <p class="text-sm text-green-700">This certificate is authentic</p>
                        </div>
                    </div>
                `;
            } else {
                resultDiv.className = 'p-3 bg-red-50 border border-red-200 rounded-lg';
                resultDiv.innerHTML = `
                    <div class="flex items-center">
                        <i class="fas fa-times-circle text-red-500 mr-2"></i>
                        <div>
                            <p class="font-medium text-red-800">Certificate Not Found</p>
                            <p class="text-sm text-red-700">The certificate number is invalid or expired</p>
                        </div>
                    </div>
                `;
            }
        }, 1500);
    }
    
    function verifyCertificateNumber(certNumber) {
        document.getElementById('verifyInput').value = certNumber;
        verifyInput();
    }
    
    function viewCertificate(certId) {
        window.open(`certificate_view.php?id=${certId}`, '_blank');
    }
    
    function previewCertificate(filePath) {
        const previewContent = document.getElementById('previewContent');
        const extension = filePath.split('.').pop().toLowerCase();
        
        if (['jpg', 'jpeg', 'png', 'gif'].includes(extension)) {
            previewContent.innerHTML = `
                <img src="${filePath}" alt="Certificate Preview" class="max-w-full max-h-[70vh] object-contain">
            `;
        } else if (extension === 'pdf') {
            previewContent.innerHTML = `
                <iframe src="${filePath}" class="w-full h-[70vh]" frameborder="0"></iframe>
            `;
        } else {
            previewContent.innerHTML = `
                <div class="text-center py-12">
                    <i class="fas fa-file text-5xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">Preview not available for this file type</p>
                    <a href="${filePath}" class="inline-block mt-2 text-yellow-600 hover:text-yellow-700">
                        <i class="fas fa-download mr-1"></i> Download File
                    </a>
                </div>
            `;
        }
        
        document.getElementById('previewModal').classList.remove('hidden');
        document.getElementById('previewModal').classList.add('flex');
    }
    
    function closePreviewModal() {
        document.getElementById('previewModal').classList.add('hidden');
        document.getElementById('previewModal').classList.remove('flex');
    }
    
    function shareCertificate(certId) {
        // In a real app, this would use Web Share API or social media sharing
        alert('Sharing feature coming soon! For now, you can download and share the certificate file.');
    }
    
    function requestNewCertificate() {
        alert('To request a new certificate, please contact your teacher or administrator.');
    }
    
    function downloadAllCertificates() {
        alert('Download all feature coming soon! For now, please download certificates individually.');
    }
    
    function shareProfile() {
        // Generate a shareable profile link
        const profileUrl = `${window.location.origin}/pelajar/profile.php?id=<?php echo $user_id; ?>`;
        alert(`Share your profile: ${profileUrl}`);
    }
    
    // Close modal when clicking outside or pressing Escape
    document.getElementById('previewModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePreviewModal();
        }
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !document.getElementById('previewModal').classList.contains('hidden')) {
            closePreviewModal();
        }
    });
    
    // Auto focus verify input when page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Highlight current page in sidebar
        const currentPage = window.location.pathname.split('/').pop();
        document.querySelectorAll('nav a').forEach(link => {
            if (link.getAttribute('href') === currentPage) {
                link.classList.add('bg-white', 'bg-opacity-20');
            }
        });
    });
    </script>
</body>
</html>