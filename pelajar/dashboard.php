<?php
include 'includes/auth_check.php';
include '../config/database.php';

// Get student data from session
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$username = $_SESSION['username'];

// Get student profile
$profile_query = "SELECT * FROM pelajar_profiles WHERE user_id = $user_id";
$profile_result = mysqli_query($conn, $profile_query);
$student_profile = mysqli_fetch_assoc($profile_result);

// Get student scores for chart
$scores_query = "
    SELECT mata_pelajaran, jenis_nilai, AVG(nilai) as avg_score
    FROM nilai_siswa 
    WHERE pelajar_id = $user_id 
    GROUP BY mata_pelajaran, jenis_nilai
    ORDER BY mata_pelajaran, FIELD(jenis_nilai, 'listening', 'grammar', 'speaking', 'writing')
";
$scores_result = mysqli_query($conn, $scores_query);

$scores_data = [];
$has_scores = false;
while ($score = mysqli_fetch_assoc($scores_result)) {
    $scores_data[$score['mata_pelajaran']][$score['jenis_nilai']] = $score['avg_score'];
    $has_scores = true;
}

// Get today's schedule
$today = date('Y-m-d');
$schedule_query = "
    SELECT j.*, u.full_name as guru_nama, mk.nama_kelas, ms.nama_subkelas
    FROM jadwal j
    JOIN jadwal_siswa js ON j.id = js.jadwal_id
    JOIN users u ON j.guru_id = u.id
    LEFT JOIN master_kelas mk ON j.kelas_id = mk.id
    LEFT JOIN master_subkelas ms ON j.subkelas_id = ms.id
    WHERE js.pelajar_id = $user_id 
    AND j.tanggal = '$today' 
    AND j.status = 'scheduled'
    ORDER BY j.jam_mulai
";
$today_schedule = mysqli_query($conn, $schedule_query);

// Get attendance summary
$attendance_query = "
    SELECT 
        COUNT(*) as total_schedules,
        SUM(CASE WHEN js.status_kehadiran = 'hadir' THEN 1 ELSE 0 END) as attended,
        SUM(CASE WHEN js.status_kehadiran = 'tidak_hadir' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN js.status_kehadiran = 'izin' THEN 1 ELSE 0 END) as permission
    FROM jadwal_siswa js
    JOIN jadwal j ON js.jadwal_id = j.id
    WHERE js.pelajar_id = $user_id 
    AND j.status = 'completed'
";
$attendance_summary = mysqli_fetch_assoc(mysqli_query($conn, $attendance_query));
$attendance_percentage = $attendance_summary['total_schedules'] > 0 ? 
    round(($attendance_summary['attended'] / $attendance_summary['total_schedules']) * 100) : 0;

// Get upcoming assignments/tests
$upcoming_query = "
    SELECT j.*, mk.nama_kelas, ms.nama_subkelas
    FROM jadwal j
    JOIN jadwal_siswa js ON j.id = js.jadwal_id
    LEFT JOIN master_kelas mk ON j.kelas_id = mk.id
    LEFT JOIN master_subkelas ms ON j.subkelas_id = ms.id
    WHERE js.pelajar_id = $user_id 
    AND j.tanggal >= CURDATE()
    AND j.tanggal <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND j.status = 'scheduled'
    ORDER BY j.tanggal, j.jam_mulai
    LIMIT 3
";
$upcoming = mysqli_query($conn, $upcoming_query);

// Get recent scores
$recent_scores_query = "
    SELECT n.*, u.full_name as teacher_name
    FROM nilai_siswa n
    LEFT JOIN users u ON n.created_by = u.id
    WHERE n.pelajar_id = $user_id
    ORDER BY n.created_at DESC
    LIMIT 5
";
$recent_scores = mysqli_query($conn, $recent_scores_query);

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

// Get current semester
$semester_query = "SELECT nama_semester FROM master_semester WHERE status = 'active' LIMIT 1";
$semester_result = mysqli_query($conn, $semester_query);
$semester = mysqli_fetch_assoc($semester_result);
$semester_name = $semester ? $semester['nama_semester'] : 'Semester 1';

// Get overall average score
$avg_score_query = "SELECT AVG(nilai) as avg_score FROM nilai_siswa WHERE pelajar_id = $user_id";
$avg_score_result = mysqli_query($conn, $avg_score_query);
$avg_score = mysqli_fetch_assoc($avg_score_result)['avg_score'];

// Get upcoming count for next 3 days
$upcoming_count = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as count FROM jadwal j 
     JOIN jadwal_siswa js ON j.id = js.jadwal_id
     WHERE js.pelajar_id = $user_id 
     AND j.tanggal >= CURDATE()
     AND j.status = 'scheduled'
     AND j.tanggal <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)"
))['count'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gensa Course</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .chart-container {
            position: relative;
            height: 320px;
            width: 100%;
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
                <a href="dashboard.php" class="flex items-center space-x-3 p-3 bg-white bg-opacity-20 rounded-lg relative">
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
                    <h1 class="text-2xl font-bold text-gray-800">Welcome back, <?php echo htmlspecialchars($full_name); ?>!</h1>
                    <p class="text-gray-600">
                        <?php echo htmlspecialchars($student_profile['kelas'] ?? 'Not set'); ?> - 
                        <?php echo htmlspecialchars($student_profile['subkelas'] ?? 'Not set'); ?> | 
                        <?php echo htmlspecialchars($student_profile['jenjang'] ?? 'Not set'); ?> Student
                    </p>
                </div>
                <div class="flex items-center justify-between sm:justify-end space-x-4">
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Current Semester</p>
                        <p class="font-semibold"><?php echo htmlspecialchars($semester_name); ?></p>
                    </div>
                    <div class="w-10 h-10 bg-yellow-500 rounded-full flex items-center justify-center text-white font-semibold">
                        <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Content -->
        <div class="p-4 sm:p-6">
            <!-- Quick Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Class</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php echo htmlspecialchars($student_profile['kelas'] ?? 'N/A'); ?>
                            </p>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($student_profile['subkelas'] ?? 'Not set'); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-school text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Attendance Rate</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $attendance_percentage; ?>%</p>
                            <p class="text-sm text-gray-600">
                                <?php echo $attendance_summary['attended']; ?> of <?php echo $attendance_summary['total_schedules']; ?> sessions
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-clipboard-check text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Average Score</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php echo $avg_score ? number_format($avg_score, 1) : 'N/A'; ?>
                            </p>
                            <p class="text-sm text-gray-600">Overall performance</p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-chart-line text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Upcoming Classes</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $upcoming_count; ?></p>
                            <p class="text-sm text-gray-600">Next 3 days</p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-calendar-day text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Scores Chart -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
                        <h2 class="text-xl font-bold text-gray-800">Performance Analysis</h2>
                        <div class="flex space-x-2">
                            <button onclick="filterChart('arab')" 
                                    class="px-3 py-1 bg-green-100 text-green-700 hover:bg-green-200 rounded text-sm transition">
                                Arabic
                            </button>
                            <button onclick="filterChart('inggris')" 
                                    class="px-3 py-1 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded text-sm transition">
                                English
                            </button>
                            <button onclick="filterChart('all')" 
                                    class="px-3 py-1 bg-yellow-100 text-yellow-700 hover:bg-yellow-200 rounded text-sm transition">
                                All
                            </button>
                        </div>
                    </div>
                    
                    <?php if ($has_scores): ?>
                        <div class="chart-container">
                            <canvas id="performanceChart"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="fas fa-chart-bar text-5xl text-gray-300 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-700 mb-2">No Scores Yet</h3>
                            <p class="text-gray-500">Your scores will appear here once your teachers add them</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Today's Schedule -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
                        <h2 class="text-xl font-bold text-gray-800">Today's Schedule</h2>
                        <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">
                            <?php echo date('l, d F Y'); ?>
                        </span>
                    </div>
                    
                    <?php if (mysqli_num_rows($today_schedule) > 0): ?>
                        <div class="space-y-4">
                            <?php while ($schedule = mysqli_fetch_assoc($today_schedule)): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition duration-200">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <p class="font-semibold text-gray-800">
                                                <?php echo date('H:i', strtotime($schedule['jam_mulai'])); ?> - 
                                                <?php echo date('H:i', strtotime($schedule['jam_selesai'])); ?>
                                            </p>
                                            <p class="text-sm text-gray-600">Teacher: <?php echo htmlspecialchars($schedule['guru_nama']); ?></p>
                                        </div>
                                        <span class="px-2 py-1 <?php echo $schedule['bahasa'] == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?> rounded-full text-xs font-medium">
                                            <?php echo ucfirst($schedule['bahasa']); ?>
                                        </span>
                                    </div>
                                    <p class="text-sm font-medium">
                                        <?php echo htmlspecialchars($schedule['nama_kelas'] ?? 'Class'); ?> - <?php echo htmlspecialchars($schedule['nama_subkelas'] ?? 'Subclass'); ?>
                                    </p>
                                    <p class="text-sm text-gray-600"><?php echo ucfirst($schedule['jenis_pelajaran'] ?? 'Lesson'); ?></p>
                                    <?php if (!empty($schedule['deskripsi'])): ?>
                                        <p class="text-sm text-gray-500 mt-2"><?php echo htmlspecialchars($schedule['deskripsi']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-day text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No classes scheduled for today</p>
                            <a href="jadwal.php" class="inline-block mt-2 text-yellow-600 hover:text-yellow-700 font-medium transition">
                                View full schedule <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Upcoming -->
                    <div class="mt-6 pt-6 border-t">
                        <h3 class="font-medium text-gray-800 mb-3">Upcoming This Week</h3>
                        <?php if (mysqli_num_rows($upcoming) > 0): ?>
                            <div class="space-y-2">
                                <?php while ($item = mysqli_fetch_assoc($upcoming)): ?>
                                    <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded transition">
                                        <div>
                                            <p class="text-sm font-medium">
                                                <?php echo date('D, d M', strtotime($item['tanggal'])); ?>
                                            </p>
                                            <p class="text-xs text-gray-500"><?php echo ucfirst($item['jenis_pelajaran'] ?? 'Lesson'); ?></p>
                                        </div>
                                        <span class="text-xs px-2 py-1 <?php echo $item['bahasa'] == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?> rounded font-medium">
                                            <?php echo date('H:i', strtotime($item['jam_mulai'])); ?>
                                        </span>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            <div class="mt-3 text-center">
                                <a href="jadwal.php" class="text-sm text-yellow-600 hover:text-yellow-700">
                                    View all upcoming classes <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="text-sm text-gray-500 text-center py-2">No upcoming classes this week</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Scores & Quick Links -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                <!-- Recent Scores -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-800">Recent Scores</h2>
                        <a href="nilai.php" class="text-yellow-600 hover:text-yellow-700 font-medium text-sm transition">
                            View all <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    
                    <?php if (mysqli_num_rows($recent_scores) > 0): ?>
                        <div class="space-y-3">
                            <?php while ($score = mysqli_fetch_assoc($recent_scores)): ?>
                                <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 <?php echo $score['mata_pelajaran'] == 'arab' ? 'bg-green-100' : 'bg-blue-100'; ?> rounded-full flex items-center justify-center">
                                            <i class="fas fa-<?php echo $score['mata_pelajaran'] == 'arab' ? 'language' : 'globe'; ?> <?php echo $score['mata_pelajaran'] == 'arab' ? 'text-green-600' : 'text-blue-600'; ?>"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium"><?php echo ucfirst($score['jenis_nilai']); ?></p>
                                            <p class="text-sm text-gray-500">
                                                <?php echo ucfirst($score['mata_pelajaran']); ?> | 
                                                <?php echo date('d M', strtotime($score['created_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-lg font-bold <?php echo $score['nilai'] >= 75 ? 'text-green-600' : ($score['nilai'] >= 60 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                            <?php echo $score['nilai']; ?>
                                        </span>
                                        <?php if (!empty($score['teacher_name'])): ?>
                                            <p class="text-xs text-gray-500">By: <?php echo htmlspecialchars($score['teacher_name']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-graduation-cap text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No scores recorded yet</p>
                            <p class="text-sm text-gray-400 mt-1">Your teachers will add scores after assessments</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Links & Subjects -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-6">My Subjects</h2>
                    
                    <?php
                    $subjects = !empty($student_profile['mata_pelajaran']) ? explode(',', $student_profile['mata_pelajaran']) : [];
                    $subject_colors = [
                        'arab' => ['bg-green-50', 'text-green-700', 'border-green-200', 'fa-language'],
                        'inggris' => ['bg-blue-50', 'text-blue-700', 'border-blue-200', 'fa-globe']
                    ];
                    ?>
                    
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <?php if (!empty($subjects)): ?>
                            <?php foreach ($subjects as $subject): 
                                $subject = trim($subject);
                                if (isset($subject_colors[$subject])):
                            ?>
                                <div class="p-4 <?php echo $subject_colors[$subject][0]; ?> border <?php echo $subject_colors[$subject][2]; ?> rounded-lg text-center">
                                    <i class="fas <?php echo $subject_colors[$subject][3]; ?> text-2xl <?php echo $subject_colors[$subject][1]; ?> mb-2"></i>
                                    <p class="font-bold <?php echo $subject_colors[$subject][1]; ?>">
                                        <?php echo ucfirst($subject); ?>
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1">Language</p>
                                </div>
                            <?php 
                                endif;
                            endforeach; 
                        else: ?>
                            <div class="col-span-2 text-center py-4">
                                <p class="text-gray-500">No subjects assigned yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Quick Links -->
                    <div class="mt-6 pt-6 border-t">
                        <h3 class="font-medium text-gray-800 mb-3">Quick Access</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <a href="jadwal.php" class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg hover:bg-yellow-100 transition text-center">
                                <i class="fas fa-calendar-alt text-yellow-600 mb-2"></i>
                                <p class="text-sm font-medium">View Schedule</p>
                            </a>
                            <a href="absensi.php" class="p-3 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition text-center">
                                <i class="fas fa-clipboard-check text-green-600 mb-2"></i>
                                <p class="text-sm font-medium">Attendance</p>
                            </a>
                            <a href="sertifikat.php" class="p-3 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition text-center">
                                <i class="fas fa-certificate text-blue-600 mb-2"></i>
                                <p class="text-sm font-medium">Certificates</p>
                            </a>
                            <a href="riwayat_nilai.php" class="p-3 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition text-center">
                                <i class="fas fa-history text-purple-600 mb-2"></i>
                                <p class="text-sm font-medium">Score History</p>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="mt-6 pt-6 border-t">
                        <h3 class="font-medium text-gray-800 mb-3">Quick Stats</h3>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="text-center p-2 bg-gray-50 rounded">
                                <p class="text-lg font-bold text-gray-800"><?php echo $attendance_percentage; ?>%</p>
                                <p class="text-xs text-gray-600">Attendance</p>
                            </div>
                            <div class="text-center p-2 bg-gray-50 rounded">
                                <p class="text-lg font-bold text-gray-800"><?php echo $upcoming_count; ?></p>
                                <p class="text-xs text-gray-600">Upcoming</p>
                            </div>
                            <div class="text-center p-2 bg-gray-50 rounded">
                                <p class="text-lg font-bold text-gray-800"><?php echo $new_scores; ?></p>
                                <p class="text-xs text-gray-600">New Scores</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="mt-8 text-center text-gray-500 text-sm">
                <p>Gensa Course Student Portal &copy; <?php echo date('Y'); ?> - All rights reserved</p>
                <p class="mt-1">Logged in as: <?php echo htmlspecialchars($username); ?></p>
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
    
    <?php if ($has_scores): ?>
    // Prepare chart data
    const scoreData = <?php echo json_encode($scores_data); ?>;
    let performanceChart;
    
    function initializeChart(filter = 'all') {
        const ctx = document.getElementById('performanceChart').getContext('2d');
        
        // Filter data
        let filteredData = {};
        if (filter === 'all') {
            filteredData = scoreData;
        } else {
            filteredData[filter] = scoreData[filter] || {};
        }
        
        // Prepare datasets
        const datasets = [];
        const subjects = Object.keys(filteredData);
        const colors = {
            'arab': { bg: 'rgba(16, 185, 129, 0.2)', border: '#10b981' },
            'inggris': { bg: 'rgba(59, 130, 246, 0.2)', border: '#3b82f6' }
        };
        
        subjects.forEach((subject, index) => {
            const subjectScores = filteredData[subject];
            const color = colors[subject] || colors.arab;
            
            const data = [
                subjectScores['listening'] || 0,
                subjectScores['grammar'] || 0,
                subjectScores['speaking'] || 0,
                subjectScores['writing'] || 0
            ];
            
            datasets.push({
                label: subject.charAt(0).toUpperCase() + subject.slice(1),
                data: data,
                backgroundColor: color.bg,
                borderColor: color.border,
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: color.border,
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            });
        });
        
        // Destroy existing chart if exists
        if (performanceChart) {
            performanceChart.destroy();
        }
        
        // Create new chart
        performanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Listening', 'Grammar', 'Speaking', 'Writing'],
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: {
                                size: 12,
                                family: "'Inter', sans-serif"
                            },
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255, 255, 255, 0.9)',
                        titleColor: '#1f2937',
                        bodyColor: '#4b5563',
                        borderColor: '#e5e7eb',
                        borderWidth: 1,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: ${context.raw.toFixed(1)}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            callback: function(value) {
                                return value + '';
                            },
                            font: {
                                size: 11
                            }
                        },
                        title: {
                            display: true,
                            text: 'Score',
                            font: {
                                size: 12,
                                weight: '600'
                            }
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                animation: {
                    duration: 1000,
                    easing: 'easeOutQuart'
                }
            }
        });
    }
    
    function filterChart(subject) {
        initializeChart(subject);
        // Update button states
        document.querySelectorAll('button[onclick^="filterChart"]').forEach(btn => {
            btn.classList.remove('bg-opacity-100');
            btn.classList.add('bg-opacity-50');
        });
        event.target.classList.remove('bg-opacity-50');
        event.target.classList.add('bg-opacity-100');
    }
    
    // Initialize on load
    document.addEventListener('DOMContentLoaded', function() {
        initializeChart('all');
        // Set initial button state
        document.querySelector('button[onclick="filterChart(\'all\')"]').classList.add('bg-opacity-100');
    });
    <?php endif; ?>
    
    // Update attendance progress bar color
    document.addEventListener('DOMContentLoaded', function() {
        const attendancePercentage = <?php echo $attendance_percentage; ?>;
        const attendanceElement = document.querySelector('.border-l-4.border-blue-500');
        if (attendanceElement) {
            const statusElement = attendanceElement.querySelector('.text-2xl');
            if (statusElement) {
                if (attendancePercentage >= 80) {
                    statusElement.classList.add('text-green-600');
                    attendanceElement.classList.remove('border-blue-500');
                    attendanceElement.classList.add('border-green-500');
                } else if (attendancePercentage >= 60) {
                    statusElement.classList.add('text-yellow-600');
                    attendanceElement.classList.remove('border-blue-500');
                    attendanceElement.classList.add('border-yellow-500');
                } else {
                    statusElement.classList.add('text-red-600');
                    attendanceElement.classList.remove('border-blue-500');
                    attendanceElement.classList.add('border-red-500');
                }
            }
        }
    });
    
    // Auto-refresh schedule every 5 minutes
    setInterval(() => {
        console.log('Auto-refreshing schedule data...');
        // In a real app, you would fetch new data via AJAX
    }, 300000); // 5 minutes
    </script>
</body>
</html>