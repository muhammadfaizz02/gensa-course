<?php
include 'includes/auth_check.php';
include '../config/database.php';

// Get all scores grouped by subject
$arab_scores_query = "
    SELECT * FROM nilai_siswa 
    WHERE pelajar_id = $user_id AND mata_pelajaran = 'arab'
    ORDER BY semester, jenis_nilai, created_at DESC
";
$arab_scores = mysqli_query($conn, $arab_scores_query);

$english_scores_query = "
    SELECT * FROM nilai_siswa 
    WHERE pelajar_id = $user_id AND mata_pelajaran = 'inggris'
    ORDER BY semester, jenis_nilai, created_at DESC
";
$english_scores = mysqli_query($conn, $english_scores_query);

// Calculate averages
$avg_arab_query = "
    SELECT 
        AVG(nilai) as avg_score,
        COUNT(*) as total_scores,
        MAX(nilai) as highest_score,
        MIN(nilai) as lowest_score
    FROM nilai_siswa 
    WHERE pelajar_id = $user_id AND mata_pelajaran = 'arab'
";
$arab_stats = mysqli_fetch_assoc(mysqli_query($conn, $avg_arab_query));

$avg_english_query = "
    SELECT 
        AVG(nilai) as avg_score,
        COUNT(*) as total_scores,
        MAX(nilai) as highest_score,
        MIN(nilai) as lowest_score
    FROM nilai_siswa 
    WHERE pelajar_id = $user_id AND mata_pelajaran = 'inggris'
";
$english_stats = mysqli_fetch_assoc(mysqli_query($conn, $avg_english_query));

// Get score distribution by type
$distribution_query = "
    SELECT 
        mata_pelajaran,
        jenis_nilai,
        COUNT(*) as count,
        AVG(nilai) as average
    FROM nilai_siswa 
    WHERE pelajar_id = $user_id
    GROUP BY mata_pelajaran, jenis_nilai
    ORDER BY mata_pelajaran, FIELD(jenis_nilai, 'listening', 'grammar', 'speaking', 'writing')
";
$distribution = mysqli_query($conn, $distribution_query);

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
    <title>My Scores - Gensa Course</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .gensa-gradient {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
        }
        .gensa-text {
            color: #f59e0b;
        }
        .gensa-shadow {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .sidebar-badge {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
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
                <a href="nilai.php" class="flex items-center space-x-3 p-3 bg-white bg-opacity-20 rounded-lg relative">
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
                <a href="sertifikat.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
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
                    <h1 class="text-2xl font-bold text-gray-800">My Scores</h1>
                    <p class="text-gray-600">View your scores for Arabic and English subjects</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                    <button onclick="printScores()" class="w-full sm:w-auto justify-center px-4 py-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg transition">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                    <button onclick="exportScores()" class="w-full sm:w-auto justify-center px-4 py-2 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg transition">
                        <i class="fas fa-download mr-2"></i> Export
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Content -->
        <div class="p-4 sm:p-6">
            <!-- Subject Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- Arabic Statistics -->
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-language text-green-600 text-xl"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-gray-800">Arabic Language</h2>
                                <p class="text-sm text-gray-600">Scores & Performance</p>
                            </div>
                        </div>
                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                            <?php echo $arab_stats['total_scores'] ?: 0; ?> Scores
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div class="text-center p-3 bg-gray-50 rounded-lg">
                            <p class="text-2xl font-bold text-green-600">
                                <?php echo $arab_stats['avg_score'] ? number_format($arab_stats['avg_score'], 1) : 'N/A'; ?>
                            </p>
                            <p class="text-xs text-gray-600">Average</p>
                        </div>
                        <div class="text-center p-3 bg-gray-50 rounded-lg">
                            <p class="text-2xl font-bold text-blue-600">
                                <?php echo $arab_stats['highest_score'] ?: 'N/A'; ?>
                            </p>
                            <p class="text-xs text-gray-600">Highest</p>
                        </div>
                        <div class="text-center p-3 bg-gray-50 rounded-lg">
                            <p class="text-2xl font-bold text-red-600">
                                <?php echo $arab_stats['lowest_score'] ?: 'N/A'; ?>
                            </p>
                            <p class="text-xs text-gray-600">Lowest</p>
                        </div>
                    </div>
                    
                    <!-- Score Progress -->
                    <div class="mb-4">
                        <p class="text-sm font-medium text-gray-700 mb-2">Score Distribution</p>
                        <?php
                        mysqli_data_seek($arab_scores, 0);
                        $arab_types = ['listening', 'grammar', 'speaking', 'writing'];
                        foreach ($arab_types as $type):
                            $type_scores = [];
                            while ($score = mysqli_fetch_assoc($arab_scores)) {
                                if ($score['jenis_nilai'] == $type) {
                                    $type_scores[] = $score['nilai'];
                                }
                            }
                            mysqli_data_seek($arab_scores, 0);
                            $avg = count($type_scores) > 0 ? array_sum($type_scores) / count($type_scores) : 0;
                        ?>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-600 capitalize"><?php echo $type; ?></span>
                                <div class="flex items-center space-x-2">
                                    <div class="w-32 bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo $avg; ?>%"></div>
                                    </div>
                                    <span class="text-sm font-medium w-12 text-right">
                                        <?php echo $avg > 0 ? number_format($avg, 1) : '-'; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <a href="#arab-scores" class="block w-full py-2 bg-green-50 text-green-700 hover:bg-green-100 rounded-lg text-center transition">
                        <i class="fas fa-list mr-2"></i> View All Arabic Scores
                    </a>
                </div>
                
                <!-- English Statistics -->
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-globe text-blue-600 text-xl"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-gray-800">English Language</h2>
                                <p class="text-sm text-gray-600">Scores & Performance</p>
                            </div>
                        </div>
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                            <?php echo $english_stats['total_scores'] ?: 0; ?> Scores
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div class="text-center p-3 bg-gray-50 rounded-lg">
                            <p class="text-2xl font-bold text-green-600">
                                <?php echo $english_stats['avg_score'] ? number_format($english_stats['avg_score'], 1) : 'N/A'; ?>
                            </p>
                            <p class="text-xs text-gray-600">Average</p>
                        </div>
                        <div class="text-center p-3 bg-gray-50 rounded-lg">
                            <p class="text-2xl font-bold text-blue-600">
                                <?php echo $english_stats['highest_score'] ?: 'N/A'; ?>
                            </p>
                            <p class="text-xs text-gray-600">Highest</p>
                        </div>
                        <div class="text-center p-3 bg-gray-50 rounded-lg">
                            <p class="text-2xl font-bold text-red-600">
                                <?php echo $english_stats['lowest_score'] ?: 'N/A'; ?>
                            </p>
                            <p class="text-xs text-gray-600">Lowest</p>
                        </div>
                    </div>
                    
                    <!-- Score Progress -->
                    <div class="mb-4">
                        <p class="text-sm font-medium text-gray-700 mb-2">Score Distribution</p>
                        <?php
                        mysqli_data_seek($english_scores, 0);
                        $english_types = ['listening', 'grammar', 'speaking', 'writing'];
                        foreach ($english_types as $type):
                            $type_scores = [];
                            while ($score = mysqli_fetch_assoc($english_scores)) {
                                if ($score['jenis_nilai'] == $type) {
                                    $type_scores[] = $score['nilai'];
                                }
                            }
                            mysqli_data_seek($english_scores, 0);
                            $avg = count($type_scores) > 0 ? array_sum($type_scores) / count($type_scores) : 0;
                        ?>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-600 capitalize"><?php echo $type; ?></span>
                                <div class="flex items-center space-x-2">
                                    <div class="w-32 bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo $avg; ?>%"></div>
                                    </div>
                                    <span class="text-sm font-medium w-12 text-right">
                                        <?php echo $avg > 0 ? number_format($avg, 1) : '-'; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <a href="#english-scores" class="block w-full py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg text-center transition">
                        <i class="fas fa-list mr-2"></i> View All English Scores
                    </a>
                </div>
            </div>
            
            <!-- Score Distribution Chart -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Score Distribution by Type</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php
                    $subjects_data = ['arab' => [], 'inggris' => []];
                    mysqli_data_seek($distribution, 0);
                    while ($dist = mysqli_fetch_assoc($distribution)) {
                        $subjects_data[$dist['mata_pelajaran']][$dist['jenis_nilai']] = [
                            'count' => $dist['count'],
                            'average' => $dist['average']
                        ];
                    }
                    ?>
                    
                    <!-- Arabic Distribution -->
                    <div>
                        <h3 class="font-medium text-gray-700 mb-3 flex items-center">
                            <i class="fas fa-language text-green-600 mr-2"></i> Arabic
                        </h3>
                        <div class="space-y-3">
                            <?php 
                            $arab_types = ['listening', 'grammar', 'speaking', 'writing'];
                            foreach ($arab_types as $type): 
                                $data = $subjects_data['arab'][$type] ?? ['count' => 0, 'average' => 0];
                            ?>
                                <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded transition">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                        <span class="text-sm capitalize"><?php echo $type; ?></span>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium"><?php echo number_format($data['average'], 1); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo $data['count']; ?> records</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- English Distribution -->
                    <div>
                        <h3 class="font-medium text-gray-700 mb-3 flex items-center">
                            <i class="fas fa-globe text-blue-600 mr-2"></i> English
                        </h3>
                        <div class="space-y-3">
                            <?php 
                            $english_types = ['listening', 'grammar', 'speaking', 'writing'];
                            foreach ($english_types as $type): 
                                $data = $subjects_data['inggris'][$type] ?? ['count' => 0, 'average' => 0];
                            ?>
                                <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded transition">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                                        <span class="text-sm capitalize"><?php echo $type; ?></span>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium"><?php echo number_format($data['average'], 1); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo $data['count']; ?> records</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Detailed Scores -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Arabic Scores -->
                <div id="arab-scores" class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-language text-green-600 mr-2"></i> Arabic Scores
                        </h2>
                        <div class="text-sm text-gray-600">
                            <?php echo mysqli_num_rows($arab_scores); ?> scores recorded
                        </div>
                    </div>
                    
                    <?php if (mysqli_num_rows($arab_scores) > 0): ?>
                        <!-- PERUBAHAN 4: Tabel Responsive -->
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[640px]">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Type</th>
                                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Score</th>
                                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Semester</th>
                                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Date</th>
                                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php 
                                    mysqli_data_seek($arab_scores, 0);
                                    while ($score = mysqli_fetch_assoc($arab_scores)): 
                                    ?>
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="py-3 px-4">
                                                <span class="capitalize"><?php echo $score['jenis_nilai']; ?></span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $score['nilai'] >= 75 ? 'bg-green-100 text-green-800' : ($score['nilai'] >= 60 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                                    <?php echo $score['nilai']; ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="text-sm"><?php echo $score['semester']; ?></span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="text-sm text-gray-600">
                                                    <?php echo date('d M Y', strtotime($score['created_at'])); ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <?php if ($score['nilai'] >= 75): ?>
                                                    <span class="text-xs text-green-600">
                                                        <i class="fas fa-check-circle mr-1"></i> Excellent
                                                    </span>
                                                <?php elseif ($score['nilai'] >= 60): ?>
                                                    <span class="text-xs text-yellow-600">
                                                        <i class="fas fa-exclamation-circle mr-1"></i> Good
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-xs text-red-600">
                                                        <i class="fas fa-times-circle mr-1"></i> Need Improvement
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Arabic Score Summary -->
                        <div class="mt-6 p-4 bg-green-50 rounded-lg">
                            <h3 class="font-medium text-green-800 mb-2">Arabic Performance Summary</h3>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="text-center">
                                    <p class="text-lg font-bold text-green-700"><?php echo number_format($arab_stats['avg_score'], 1); ?></p>
                                    <p class="text-xs text-green-600">Average</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-lg font-bold text-blue-700"><?php echo $arab_stats['highest_score'] ?: 'N/A'; ?></p>
                                    <p class="text-xs text-blue-600">Highest</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-lg font-bold text-yellow-700"><?php echo $arab_stats['total_scores'] ?: 0; ?></p>
                                    <p class="text-xs text-yellow-600">Total</p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="fas fa-language text-5xl text-gray-300 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-700 mb-2">No Arabic Scores Yet</h3>
                            <p class="text-gray-500">Your Arabic language scores will appear here once added by teachers</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- English Scores -->
                <div id="english-scores" class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-globe text-blue-600 mr-2"></i> English Scores
                        </h2>
                        <div class="text-sm text-gray-600">
                            <?php echo mysqli_num_rows($english_scores); ?> scores recorded
                        </div>
                    </div>
                    
                    <?php if (mysqli_num_rows($english_scores) > 0): ?>
                        <!-- PERUBAHAN 4: Tabel Responsive -->
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[640px]">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Type</th>
                                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Score</th>
                                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Semester</th>
                                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Date</th>
                                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php 
                                    mysqli_data_seek($english_scores, 0);
                                    while ($score = mysqli_fetch_assoc($english_scores)): 
                                    ?>
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="py-3 px-4">
                                                <span class="capitalize"><?php echo $score['jenis_nilai']; ?></span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $score['nilai'] >= 75 ? 'bg-green-100 text-green-800' : ($score['nilai'] >= 60 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                                    <?php echo $score['nilai']; ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="text-sm"><?php echo $score['semester']; ?></span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="text-sm text-gray-600">
                                                    <?php echo date('d M Y', strtotime($score['created_at'])); ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <?php if ($score['nilai'] >= 75): ?>
                                                    <span class="text-xs text-green-600">
                                                        <i class="fas fa-check-circle mr-1"></i> Excellent
                                                    </span>
                                                <?php elseif ($score['nilai'] >= 60): ?>
                                                    <span class="text-xs text-yellow-600">
                                                        <i class="fas fa-exclamation-circle mr-1"></i> Good
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-xs text-red-600">
                                                        <i class="fas fa-times-circle mr-1"></i> Need Improvement
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- English Score Summary -->
                        <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                            <h3 class="font-medium text-blue-800 mb-2">English Performance Summary</h3>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="text-center">
                                    <p class="text-lg font-bold text-green-700"><?php echo number_format($english_stats['avg_score'], 1); ?></p>
                                    <p class="text-xs text-green-600">Average</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-lg font-bold text-blue-700"><?php echo $english_stats['highest_score'] ?: 'N/A'; ?></p>
                                    <p class="text-xs text-blue-600">Highest</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-lg font-bold text-yellow-700"><?php echo $english_stats['total_scores'] ?: 0; ?></p>
                                    <p class="text-xs text-yellow-600">Total</p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="fas fa-globe text-5xl text-gray-300 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-700 mb-2">No English Scores Yet</h3>
                            <p class="text-gray-500">Your English language scores will appear here once added by teachers</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Overall Performance -->
            <div class="mt-6 bg-white rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Overall Performance</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php
                    // Calculate overall statistics
                    $overall_query = "
                        SELECT 
                            AVG(nilai) as overall_avg,
                            COUNT(*) as total_scores,
                            SUM(CASE WHEN nilai >= 75 THEN 1 ELSE 0 END) as excellent_count,
                            SUM(CASE WHEN nilai >= 60 AND nilai < 75 THEN 1 ELSE 0 END) as good_count,
                            SUM(CASE WHEN nilai < 60 THEN 1 ELSE 0 END) as need_improvement_count
                        FROM nilai_siswa 
                        WHERE pelajar_id = $user_id
                    ";
                    $overall_stats = mysqli_fetch_assoc(mysqli_query($conn, $overall_query));
                    ?>
                    
                    <div class="text-center p-6 bg-gradient-to-r from-yellow-50 to-orange-50 rounded-xl">
                        <p class="text-5xl font-bold text-yellow-600 mb-2">
                            <?php echo $overall_stats['overall_avg'] ? number_format($overall_stats['overall_avg'], 1) : 'N/A'; ?>
                        </p>
                        <p class="text-sm text-gray-600">Overall Average Score</p>
                        <div class="mt-3">
                            <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">
                                <?php echo $overall_stats['total_scores']; ?> total scores
                            </span>
                        </div>
                    </div>
                    
                    <div class="p-6 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl">
                        <h3 class="font-medium text-gray-700 mb-3">Performance Breakdown</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Excellent (≥75)</span>
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-sm">
                                    <?php echo $overall_stats['excellent_count']; ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Good (60-74)</span>
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-sm">
                                    <?php echo $overall_stats['good_count']; ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Need Improvement (<60)</span>
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-sm">
                                    <?php echo $overall_stats['need_improvement_count']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl">
                        <h3 class="font-medium text-gray-700 mb-3">Score Analysis</h3>
                        <div class="space-y-3">
                            <div>
                                <p class="text-sm text-gray-600">Trend</p>
                                <?php
                                $trend_query = "
                                    SELECT AVG(nilai) as recent_avg
                                    FROM nilai_siswa 
                                    WHERE pelajar_id = $user_id 
                                    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                ";
                                $trend_result = mysqli_query($conn, $trend_query);
                                $recent_avg = mysqli_fetch_assoc($trend_result)['recent_avg'];
                                
                                $trend = 'stable';
                                $trend_color = 'text-yellow-600';
                                $trend_icon = 'fa-minus';
                                
                                if ($recent_avg && $overall_stats['overall_avg']) {
                                    $diff = $recent_avg - $overall_stats['overall_avg'];
                                    if ($diff > 2) {
                                        $trend = 'improving';
                                        $trend_color = 'text-green-600';
                                        $trend_icon = 'fa-arrow-up';
                                    } elseif ($diff < -2) {
                                        $trend = 'declining';
                                        $trend_color = 'text-red-600';
                                        $trend_icon = 'fa-arrow-down';
                                    }
                                }
                                ?>
                                <p class="text-lg font-medium <?php echo $trend_color; ?>">
                                    <i class="fas <?php echo $trend_icon; ?> mr-1"></i> <?php echo ucfirst($trend); ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Recommendation</p>
                                <?php if ($overall_stats['overall_avg'] >= 75): ?>
                                    <p class="text-sm text-green-600">Keep up the excellent work!</p>
                                <?php elseif ($overall_stats['overall_avg'] >= 60): ?>
                                    <p class="text-sm text-yellow-600">Good progress, keep practicing!</p>
                                <?php else: ?>
                                    <p class="text-sm text-red-600">Focus on weaker areas for improvement</p>
                                <?php endif; ?>
                            </div>
                        </div>
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
    
    function printScores() {
        window.open('print_scores.php', '_blank');
    }
    
    function exportScores() {
        window.open('export_scores.php', '_blank');
    }
    
    // Highlight low scores
    document.addEventListener('DOMContentLoaded', function() {
        const lowScores = document.querySelectorAll('span.bg-red-100');
        lowScores.forEach(score => {
            score.addEventListener('mouseenter', function() {
                this.parentElement.parentElement.classList.add('bg-red-50');
            });
            score.addEventListener('mouseleave', function() {
                this.parentElement.parentElement.classList.remove('bg-red-50');
            });
        });
    });
    </script>
</body>
</html>