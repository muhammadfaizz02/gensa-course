<?php
include 'includes/auth_check.php';
include '../config/database.php'; // TAMBAHKAN INI

// Get student data from session
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get filter parameters
$semester = $_GET['semester'] ?? '';
$subject = $_GET['subject'] ?? '';
$type = $_GET['type'] ?? '';
$year = $_GET['year'] ?? '';

// Build query with filters
$where_conditions = ["pelajar_id = $user_id"];
if ($semester) {
    $where_conditions[] = "semester = '$semester'";
}
if ($subject) {
    $where_conditions[] = "mata_pelajaran = '$subject'";
}
if ($type) {
    $where_conditions[] = "jenis_nilai = '$type'";
}
if ($year) {
    $where_conditions[] = "YEAR(created_at) = '$year'";
}
$where_clause = implode(' AND ', $where_conditions);

// Get score history
$history_query = "
    SELECT n.*, u.full_name as teacher_name
    FROM nilai_siswa n
    LEFT JOIN users u ON n.created_by = u.id
    WHERE $where_clause
    ORDER BY created_at DESC
";
$score_history = mysqli_query($conn, $history_query);

// Get distinct semesters, subjects, types, and years for filters
$semesters_query = "SELECT DISTINCT semester FROM nilai_siswa WHERE pelajar_id = $user_id ORDER BY semester DESC";
$semesters = mysqli_query($conn, $semesters_query);

$subjects_query = "SELECT DISTINCT mata_pelajaran FROM nilai_siswa WHERE pelajar_id = $user_id ORDER BY mata_pelajaran";
$subjects = mysqli_query($conn, $subjects_query);

$types_query = "SELECT DISTINCT jenis_nilai FROM nilai_siswa WHERE pelajar_id = $user_id ORDER BY jenis_nilai";
$types = mysqli_query($conn, $types_query);

$years_query = "SELECT DISTINCT YEAR(created_at) as year FROM nilai_siswa WHERE pelajar_id = $user_id ORDER BY year DESC";
$years = mysqli_query($conn, $years_query);

// Calculate statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_scores,
        AVG(nilai) as average_score,
        MAX(nilai) as highest_score,
        MIN(nilai) as lowest_score,
        SUM(CASE WHEN nilai >= 75 THEN 1 ELSE 0 END) as excellent_count,
        SUM(CASE WHEN nilai >= 60 AND nilai < 75 THEN 1 ELSE 0 END) as good_count,
        SUM(CASE WHEN nilai < 60 THEN 1 ELSE 0 END) as need_improvement_count
    FROM nilai_siswa 
    WHERE $where_clause
";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get score trend by month
$trend_query = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        AVG(nilai) as avg_score,
        COUNT(*) as score_count
    FROM nilai_siswa 
    WHERE pelajar_id = $user_id
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
";
$score_trend = mysqli_query($conn, $trend_query);

// Get student profile for sidebar badges
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
    <title>Score History - Gensa Course</title>
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
                <a href="dashboard.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="nilai.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
                    <i class="fas fa-graduation-cap"></i>
                    <span>My Scores</span>
                    <?php if ($new_scores > 0): ?>
                        <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full sidebar-badge"><?php echo $new_scores; ?></span>
                    <?php endif; ?>
                </a>
                <a href="jadwal.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
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
                <a href="riwayat_nilai.php" class="flex items-center space-x-3 p-3 bg-white bg-opacity-20 rounded-lg relative">
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
                    <h1 class="text-2xl font-bold text-gray-800">Score History</h1>
                    <p class="text-gray-600">Track your academic progress over time</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                    <button onclick="printHistory()" class="w-full sm:w-auto justify-center px-4 py-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg transition">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                    <button onclick="exportHistory()" class="w-full sm:w-auto justify-center px-4 py-2 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg transition">
                        <i class="fas fa-download mr-2"></i> Export
                    </button>
                    <a href="nilai.php" class="w-full sm:w-auto justify-center px-4 py-2 bg-yellow-100 text-yellow-700 hover:bg-yellow-200 rounded-lg transition">
                        <i class="fas fa-graduation-cap mr-2"></i> Current Scores
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Content -->
        <div class="p-4 sm:p-6">
            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Filter Scores</h2>
                
                <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                        <select name="semester" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                            <option value="">All Semesters</option>
                            <?php while ($sem = mysqli_fetch_assoc($semesters)): ?>
                                <option value="<?php echo htmlspecialchars($sem['semester']); ?>" <?php echo $semester == $sem['semester'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sem['semester']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                        <select name="subject" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                            <option value="">All Subjects</option>
                            <?php mysqli_data_seek($subjects, 0); ?>
                            <?php while ($subj = mysqli_fetch_assoc($subjects)): ?>
                                <option value="<?php echo htmlspecialchars($subj['mata_pelajaran']); ?>" <?php echo $subject == $subj['mata_pelajaran'] ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(htmlspecialchars($subj['mata_pelajaran'])); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Score Type</label>
                        <select name="type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                            <option value="">All Types</option>
                            <?php mysqli_data_seek($types, 0); ?>
                            <?php while ($t = mysqli_fetch_assoc($types)): ?>
                                <option value="<?php echo htmlspecialchars($t['jenis_nilai']); ?>" <?php echo $type == $t['jenis_nilai'] ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(htmlspecialchars($t['jenis_nilai'])); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                        <select name="year" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                            <option value="">All Years</option>
                            <?php mysqli_data_seek($years, 0); ?>
                            <?php while ($y = mysqli_fetch_assoc($years)): ?>
                                <option value="<?php echo htmlspecialchars($y['year']); ?>" <?php echo $year == $y['year'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($y['year']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="md:col-span-4 flex flex-col sm:flex-row gap-3">
                        <button type="submit" 
                                class="w-full sm:w-auto justify-center px-6 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg transition font-medium">
                            <i class="fas fa-filter mr-2"></i> Apply Filters
                        </button>
                        <a href="riwayat_nilai.php" 
                           class="w-full sm:w-auto justify-center px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition font-medium">
                            <i class="fas fa-redo mr-2"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Statistics -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Scores</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_scores'] ?? 0; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-list-ol text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Average Score</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php echo isset($stats['average_score']) ? number_format($stats['average_score'], 1) : 'N/A'; ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-chart-line text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Highest Score</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php echo $stats['highest_score'] ?? 'N/A'; ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-trophy text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Lowest Score</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php echo $stats['lowest_score'] ?? 'N/A'; ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Score History List -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-800">Score History</h2>
                            <div class="text-sm text-gray-600">
                                <?php echo mysqli_num_rows($score_history); ?> records found
                            </div>
                        </div>
                        
                        <?php if (mysqli_num_rows($score_history) > 0): ?>
                            <!-- PERUBAHAN 4: Tabel Responsive -->
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[640px]">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Date</th>
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Subject & Type</th>
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Score</th>
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Semester</th>
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Teacher</th>
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php mysqli_data_seek($score_history, 0); ?>
                                        <?php while ($score = mysqli_fetch_assoc($score_history)): 
                                            $score_color = $score['nilai'] >= 75 ? 'text-green-600' : 
                                                         ($score['nilai'] >= 60 ? 'text-yellow-600' : 'text-red-600');
                                            $bg_color = $score['nilai'] >= 75 ? 'bg-green-50' : 
                                                       ($score['nilai'] >= 60 ? 'bg-yellow-50' : 'bg-red-50');
                                        ?>
                                            <tr class="hover:bg-gray-50 <?php echo $bg_color; ?> transition">
                                                <td class="py-3 px-4">
                                                    <p class="text-sm"><?php echo date('d M Y', strtotime($score['created_at'])); ?></p>
                                                    <p class="text-xs text-gray-500"><?php echo date('H:i', strtotime($score['created_at'])); ?></p>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <div class="flex items-center space-x-2">
                                                        <span class="px-2 py-1 <?php echo $score['mata_pelajaran'] == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?> rounded text-xs font-medium">
                                                            <?php echo ucfirst($score['mata_pelajaran']); ?>
                                                        </span>
                                                        <span class="text-sm capitalize"><?php echo $score['jenis_nilai']; ?></span>
                                                    </div>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <span class="text-lg font-bold <?php echo $score_color; ?>">
                                                        <?php echo $score['nilai']; ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <span class="text-sm bg-gray-100 px-2 py-1 rounded"><?php echo $score['semester']; ?></span>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <p class="text-sm"><?php echo $score['teacher_name'] ? htmlspecialchars($score['teacher_name']) : 'Admin'; ?></p>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <?php if ($score['nilai'] >= 75): ?>
                                                        <span class="text-xs text-green-600 font-medium">
                                                            <i class="fas fa-check-circle mr-1"></i> Excellent
                                                        </span>
                                                    <?php elseif ($score['nilai'] >= 60): ?>
                                                        <span class="text-xs text-yellow-600 font-medium">
                                                            <i class="fas fa-exclamation-circle mr-1"></i> Good
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-xs text-red-600 font-medium">
                                                            <i class="fas fa-times-circle mr-1"></i> Needs Work
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-3">
                                <div class="text-sm text-gray-600">
                                    Showing <?php echo mysqli_num_rows($score_history); ?> records
                                </div>
                                <div class="flex space-x-2">
                                    <button class="px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition">
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                    <button class="px-3 py-1 bg-yellow-500 text-white rounded">1</button>
                                    <button class="px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition">2</button>
                                    <button class="px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition">3</button>
                                    <button class="px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <i class="fas fa-history text-5xl text-gray-300 mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-700 mb-2">No Score History</h3>
                                <p class="text-gray-500">No scores found matching your filters</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Side Panel -->
                <div class="space-y-6">
                    <!-- Score Distribution -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Score Distribution</h2>
                        
                        <div class="space-y-4">
                            <div>
                                <div class="flex justify-between text-sm text-gray-600 mb-1">
                                    <span>Excellent (≥75)</span>
                                    <span><?php echo $stats['excellent_count'] ?? 0; ?> scores</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-500 h-2 rounded-full" 
                                         style="width: <?php echo ($stats['total_scores'] ?? 0) > 0 ? (($stats['excellent_count'] ?? 0) / $stats['total_scores'] * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                            
                            <div>
                                <div class="flex justify-between text-sm text-gray-600 mb-1">
                                    <span>Good (60-74)</span>
                                    <span><?php echo $stats['good_count'] ?? 0; ?> scores</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-500 h-2 rounded-full" 
                                         style="width: <?php echo ($stats['total_scores'] ?? 0) > 0 ? (($stats['good_count'] ?? 0) / $stats['total_scores'] * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                            
                            <div>
                                <div class="flex justify-between text-sm text-gray-600 mb-1">
                                    <span>Needs Improvement (<60)</span>
                                    <span><?php echo $stats['need_improvement_count'] ?? 0; ?> scores</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-red-500 h-2 rounded-full" 
                                         style="width: <?php echo ($stats['total_scores'] ?? 0) > 0 ? (($stats['need_improvement_count'] ?? 0) / $stats['total_scores'] * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div>
                                    <p class="text-lg font-bold text-green-600"><?php echo $stats['excellent_count'] ?? 0; ?></p>
                                    <p class="text-xs text-gray-600">Excellent</p>
                                </div>
                                <div>
                                    <p class="text-lg font-bold text-yellow-600"><?php echo $stats['good_count'] ?? 0; ?></p>
                                    <p class="text-xs text-gray-600">Good</p>
                                </div>
                                <div>
                                    <p class="text-lg font-bold text-red-600"><?php echo $stats['need_improvement_count'] ?? 0; ?></p>
                                    <p class="text-xs text-gray-600">Needs Work</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Monthly Trend -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Monthly Trend</h2>
                        
                        <?php if (mysqli_num_rows($score_trend) > 0): ?>
                            <div class="space-y-3">
                                <?php mysqli_data_seek($score_trend, 0); ?>
                                <?php while ($trend = mysqli_fetch_assoc($score_trend)): 
                                    $month_name = date('M Y', strtotime($trend['month'] . '-01'));
                                    $trend_color = $trend['avg_score'] >= 75 ? 'text-green-600' : 
                                                 ($trend['avg_score'] >= 60 ? 'text-yellow-600' : 'text-red-600');
                                ?>
                                    <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded transition">
                                        <div>
                                            <p class="text-sm font-medium"><?php echo $month_name; ?></p>
                                            <p class="text-xs text-gray-500"><?php echo $trend['score_count']; ?> scores</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-bold <?php echo $trend_color; ?>">
                                                <?php echo number_format($trend['avg_score'], 1); ?>
                                            </p>
                                            <p class="text-xs text-gray-500">Average</p>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            
                            <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-700">Trend:</span>
                                    <?php
                                    mysqli_data_seek($score_trend, 0);
                                    $first_month = mysqli_fetch_assoc($score_trend);
                                    mysqli_data_seek($score_trend, 1);
                                    $second_month = mysqli_fetch_assoc($score_trend);
                                    
                                    $trend_icon = 'fa-minus';
                                    $trend_text = 'Stable';
                                    $trend_color = 'text-yellow-600';
                                    
                                    if ($first_month && $second_month) {
                                        $diff = $first_month['avg_score'] - $second_month['avg_score'];
                                        if ($diff > 2) {
                                            $trend_icon = 'fa-arrow-up';
                                            $trend_text = 'Improving';
                                            $trend_color = 'text-green-600';
                                        } elseif ($diff < -2) {
                                            $trend_icon = 'fa-arrow-down';
                                            $trend_text = 'Declining';
                                            $trend_color = 'text-red-600';
                                        }
                                    }
                                    ?>
                                    <span class="font-medium <?php echo $trend_color; ?>">
                                        <i class="fas <?php echo $trend_icon; ?> mr-1"></i> <?php echo $trend_text; ?>
                                    </span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-chart-line text-3xl text-gray-300 mb-3"></i>
                                <p class="text-gray-500">No trend data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Performance Insights -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Performance Insights</h2>
                        
                        <div class="space-y-3">
                            <?php
                            // Calculate best and worst subjects
                            $subject_stats_query = "
                                SELECT 
                                    mata_pelajaran,
                                    AVG(nilai) as avg_score,
                                    COUNT(*) as score_count
                                FROM nilai_siswa 
                                WHERE pelajar_id = $user_id
                                GROUP BY mata_pelajaran
                                ORDER BY avg_score DESC
                            ";
                            $subject_stats = mysqli_query($conn, $subject_stats_query);
                            
                            if (mysqli_num_rows($subject_stats) > 0):
                                $best_subject = mysqli_fetch_assoc($subject_stats);
                                mysqli_data_seek($subject_stats, mysqli_num_rows($subject_stats) - 1);
                                $worst_subject = mysqli_fetch_assoc($subject_stats);
                            ?>
                                <div class="p-3 bg-green-50 rounded-lg border border-green-200">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-green-800">Best Subject</p>
                                            <p class="text-lg font-bold text-green-600">
                                                <?php echo ucfirst($best_subject['mata_pelajaran']); ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xl font-bold"><?php echo number_format($best_subject['avg_score'], 1); ?></p>
                                            <p class="text-xs text-green-600">Average</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="p-3 bg-red-50 rounded-lg border border-red-200">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-red-800">Needs Improvement</p>
                                            <p class="text-lg font-bold text-red-600">
                                                <?php echo ucfirst($worst_subject['mata_pelajaran']); ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xl font-bold"><?php echo number_format($worst_subject['avg_score'], 1); ?></p>
                                            <p class="text-xs text-red-600">Average</p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="p-3 bg-blue-50 rounded-lg border border-blue-200">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-blue-800">Recommendation</p>
                                        <?php if (($stats['average_score'] ?? 0) >= 75): ?>
                                            <p class="text-sm text-blue-700">Excellent performance! Keep it up.</p>
                                        <?php elseif (($stats['average_score'] ?? 0) >= 60): ?>
                                            <p class="text-sm text-blue-700">Good progress. Focus on weaker areas.</p>
                                        <?php else: ?>
                                            <p class="text-sm text-blue-700">Focus on improving scores below 60.</p>
                                        <?php endif; ?>
                                    </div>
                                    <i class="fas fa-lightbulb text-blue-500 text-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="mt-8 text-center text-gray-500 text-sm">
                <p>Gensa Course Student Portal &copy; <?php echo date('Y'); ?> - All rights reserved</p>
                <p class="mt-1">Score History - Last updated: <?php echo date('d M Y H:i'); ?></p>
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
    
    function printHistory() {
        const params = new URLSearchParams(window.location.search);
        window.open(`print_score_history.php?${params.toString()}`, '_blank');
    }
    
    function exportHistory() {
        const params = new URLSearchParams(window.location.search);
        window.open(`export_score_history.php?${params.toString()}`, '_blank');
    }
    
    // Highlight improvement trends
    document.addEventListener('DOMContentLoaded', function() {
        const scoreCells = document.querySelectorAll('td.py-3.px-4 span.text-lg');
        scoreCells.forEach(cell => {
            const score = parseFloat(cell.textContent);
            const row = cell.closest('tr');
            if (score >= 80) {
                row.classList.add('border-l-4', 'border-green-500');
            } else if (score < 60) {
                row.classList.add('border-l-4', 'border-red-500');
            }
        });
        
        // Add active state to current page in sidebar
        const currentPage = 'riwayat_nilai.php';
        const navLinks = document.querySelectorAll('nav a');
        navLinks.forEach(link => {
            if (link.href.includes(currentPage)) {
                link.classList.add('bg-white', 'bg-opacity-20');
            }
        });
    });
    
    // Auto-submit form when filter changes (optional)
    const filterSelects = document.querySelectorAll('select[name]');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            if (this.value !== '') {
                this.form.submit();
            }
        });
    });
    </script>
</body>
</html>