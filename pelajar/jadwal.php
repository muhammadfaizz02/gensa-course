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

// Get date filters
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d', strtotime('+7 days'));
$subject = $_GET['subject'] ?? '';

// Build query with filters
$where_conditions = ["js.pelajar_id = $user_id"];
if ($start_date) {
    $where_conditions[] = "j.tanggal >= '$start_date'";
}
if ($end_date) {
    $where_conditions[] = "j.tanggal <= '$end_date'";
}
if ($subject) {
    $where_conditions[] = "j.bahasa = '$subject'";
}
$where_clause = implode(' AND ', $where_conditions);

// Get schedule
$schedule_query = "
    SELECT j.*, u.full_name as guru_nama, mk.nama_kelas, ms.nama_subkelas,
           js.status_kehadiran as attendance_status
    FROM jadwal j
    JOIN jadwal_siswa js ON j.id = js.jadwal_id
    JOIN users u ON j.guru_id = u.id
    LEFT JOIN master_kelas mk ON j.kelas_id = mk.id
    LEFT JOIN master_subkelas ms ON j.subkelas_id = ms.id
    WHERE $where_clause
    ORDER BY j.tanggal, j.jam_mulai
";
$schedule = mysqli_query($conn, $schedule_query);

// Get today's schedule for quick view
$today_query = "
    SELECT j.*, u.full_name as guru_nama
    FROM jadwal j
    JOIN jadwal_siswa js ON j.id = js.jadwal_id
    JOIN users u ON j.guru_id = u.id
    WHERE js.pelajar_id = $user_id 
    AND j.tanggal = CURDATE() 
    AND j.status = 'scheduled'
    ORDER BY j.jam_mulai
";
$today_schedule = mysqli_query($conn, $today_query);

// Get next class
$next_class_query = "
    SELECT j.*, u.full_name as guru_nama, mk.nama_kelas, ms.nama_subkelas
    FROM jadwal j
    JOIN jadwal_siswa js ON j.id = js.jadwal_id
    JOIN users u ON j.guru_id = u.id
    LEFT JOIN master_kelas mk ON j.kelas_id = mk.id
    LEFT JOIN master_subkelas ms ON j.subkelas_id = ms.id
    WHERE js.pelajar_id = $user_id 
    AND j.tanggal >= CURDATE()
    AND j.status = 'scheduled'
    ORDER BY j.tanggal, j.jam_mulai
    LIMIT 1
";
$next_class_result = mysqli_query($conn, $next_class_query);
$next_class = mysqli_fetch_assoc($next_class_result);

// Get schedule statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_classes,
        SUM(CASE WHEN j.tanggal < CURDATE() THEN 1 ELSE 0 END) as completed_classes,
        SUM(CASE WHEN j.tanggal = CURDATE() THEN 1 ELSE 0 END) as today_classes,
        SUM(CASE WHEN j.tanggal > CURDATE() THEN 1 ELSE 0 END) as upcoming_classes
    FROM jadwal j
    JOIN jadwal_siswa js ON j.id = js.jadwal_id
    WHERE js.pelajar_id = $user_id 
    AND j.status = 'scheduled'
";
$stats_result = mysqli_query($conn, $stats_query);
$schedule_stats = mysqli_fetch_assoc($stats_result);

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
    <title>My Schedule - Gensa Course</title>
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
        .class-now {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-left: 4px solid #f59e0b;
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
                <a href="jadwal.php" class="flex items-center space-x-3 p-3 bg-white bg-opacity-20 rounded-lg relative">
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
                    <h1 class="text-2xl font-bold text-gray-800">My Schedule</h1>
                    <p class="text-gray-600">View your class schedule and attendance</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                    <button onclick="printSchedule()" class="w-full sm:w-auto justify-center px-4 py-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg transition">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                    <button onclick="addToCalendar()" class="w-full sm:w-auto justify-center px-4 py-2 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg transition">
                        <i class="fas fa-calendar-plus mr-2"></i> Add to Calendar
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Content -->
        <div class="p-4 sm:p-6">
            <!-- Schedule Overview -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Classes</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $schedule_stats['total_classes'] ?? 0; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-calendar-alt text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Completed</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $schedule_stats['completed_classes'] ?? 0; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Today</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $schedule_stats['today_classes'] ?? 0; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-calendar-day text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Upcoming</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $schedule_stats['upcoming_classes'] ?? 0; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-clock text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Schedule List -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
                            <h2 class="text-xl font-bold text-gray-800">Class Schedule</h2>
                            <div class="flex items-center space-x-2">
                                <button onclick="viewWeek()" class="px-3 py-1 bg-yellow-100 text-yellow-700 hover:bg-yellow-200 rounded text-sm transition">
                                    This Week
                                </button>
                                <button onclick="viewMonth()" class="px-3 py-1 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded text-sm transition">
                                    This Month
                                </button>
                            </div>
                        </div>
                        
                        <!-- Filters -->
                        <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">From</label>
                                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">To</label>
                                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                                    <select name="subject" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                                        <option value="">All Subjects</option>
                                        <option value="arab" <?php echo $subject == 'arab' ? 'selected' : ''; ?>>Arabic</option>
                                        <option value="inggris" <?php echo $subject == 'inggris' ? 'selected' : ''; ?>>English</option>
                                    </select>
                                </div>
                                <div class="flex items-end">
                                    <button type="submit" 
                                            class="w-full py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg transition font-medium">
                                        <i class="fas fa-filter mr-2"></i> Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <?php if (mysqli_num_rows($schedule) > 0): ?>
                            <div class="space-y-4">
                                <?php 
                                $current_date = '';
                                while ($class = mysqli_fetch_assoc($schedule)): 
                                    $class_date = date('Y-m-d', strtotime($class['tanggal']));
                                    $is_today = $class_date == date('Y-m-d');
                                    $is_past = $class_date < date('Y-m-d');
                                    $is_now = false;
                                    
                                    // Check if class is happening now
                                    if ($is_today) {
                                        $current_time = date('H:i:s');
                                        $start_time = date('H:i:s', strtotime($class['jam_mulai']));
                                        $end_time = date('H:i:s', strtotime($class['jam_selesai']));
                                        $is_now = ($current_time >= $start_time && $current_time <= $end_time);
                                    }
                                    
                                    if ($current_date != $class_date): 
                                        $current_date = $class_date;
                                ?>
                                    <div class="flex items-center space-x-2 mb-2">
                                        <h3 class="text-lg font-semibold text-gray-800">
                                            <?php echo date('l, d F Y', strtotime($class_date)); ?>
                                        </h3>
                                        <?php if ($is_today): ?>
                                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">
                                                Today
                                            </span>
                                        <?php elseif ($is_past): ?>
                                            <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">
                                                Past
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition <?php echo $is_past ? 'bg-gray-50' : ($is_now ? 'class-now' : ''); ?>">
                                    <div class="flex flex-col sm:flex-row justify-between items-start gap-3 mb-3">
                                        <div class="flex items-start space-x-3">
                                            <div class="w-12 h-12 <?php echo $class['bahasa'] == 'arab' ? 'bg-green-100' : 'bg-blue-100'; ?> rounded-lg flex items-center justify-center">
                                                <i class="fas fa-<?php echo $class['bahasa'] == 'arab' ? 'language' : 'globe'; ?> <?php echo $class['bahasa'] == 'arab' ? 'text-green-600' : 'text-blue-600'; ?>"></i>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-800">
                                                    <?php echo date('H:i', strtotime($class['jam_mulai'])); ?> - 
                                                    <?php echo date('H:i', strtotime($class['jam_selesai'])); ?>
                                                    <?php if ($is_now): ?>
                                                        <span class="ml-2 px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">
                                                            <i class="fas fa-circle fa-xs mr-1"></i> Now
                                                        </span>
                                                    <?php endif; ?>
                                                </p>
                                                <p class="text-sm text-gray-600">Teacher: <?php echo htmlspecialchars($class['guru_nama']); ?></p>
                                                <p class="text-sm font-medium">
                                                    <?php echo htmlspecialchars($class['nama_kelas'] ?? 'Class'); ?> - <?php echo htmlspecialchars($class['nama_subkelas'] ?? 'Subclass'); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-left sm:text-right">
                                            <span class="inline-block px-2 py-1 <?php echo $class['bahasa'] == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?> rounded-full text-xs mb-2 font-medium">
                                                <?php echo ucfirst($class['bahasa']); ?>
                                            </span>
                                            <p class="text-sm text-gray-600 capitalize"><?php echo ucfirst($class['jenis_pelajaran'] ?? 'lesson'); ?></p>
                                            <?php if ($class['attendance_status'] && $is_past): ?>
                                                <span class="inline-block mt-1 px-2 py-1 <?php echo $class['attendance_status'] == 'hadir' ? 'bg-green-100 text-green-800' : ($class['attendance_status'] == 'izin' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?> rounded text-xs">
                                                    <?php echo ucfirst(str_replace('_', ' ', $class['attendance_status'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($class['deskripsi'])): ?>
                                        <p class="text-sm text-gray-600 mt-2">
                                            <i class="fas fa-info-circle mr-1"></i> <?php echo htmlspecialchars($class['deskripsi']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if (!$is_past): ?>
                                        <div class="mt-3 pt-3 border-t border-gray-200 flex flex-col sm:flex-row justify-between gap-2">
                                            <button onclick="setReminder(<?php echo $class['id']; ?>)" 
                                                    class="text-sm text-yellow-600 hover:text-yellow-700 transition">
                                                <i class="fas fa-bell mr-1"></i> Set Reminder
                                            </button>
                                            <button onclick="viewClassDetails(<?php echo $class['id']; ?>)" 
                                                    class="text-sm text-blue-600 hover:text-blue-700 transition">
                                                View Details <i class="fas fa-arrow-right ml-1"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            
                            <!-- Pagination -->
                            <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-3">
                                <div class="text-sm text-gray-600">
                                    Showing <?php echo mysqli_num_rows($schedule); ?> classes
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
                                <i class="fas fa-calendar-times text-5xl text-gray-300 mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-700 mb-2">No Classes Found</h3>
                                <p class="text-gray-500">No classes scheduled for the selected period</p>
                                <button onclick="viewWeek()" class="mt-4 px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg transition font-medium">
                                    View This Week's Schedule
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Side Panel -->
                <div class="space-y-6">
                    <!-- Next Class -->
                    <?php if ($next_class): ?>
                        <div class="bg-white rounded-xl shadow-md p-6">
                            <h2 class="text-xl font-bold text-gray-800 mb-4">Next Class</h2>
                            
                            <div class="p-4 bg-gradient-to-r from-yellow-50 to-orange-50 rounded-lg">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-10 h-10 <?php echo $next_class['bahasa'] == 'arab' ? 'bg-green-100' : 'bg-blue-100'; ?> rounded-full flex items-center justify-center">
                                            <i class="fas fa-<?php echo $next_class['bahasa'] == 'arab' ? 'language' : 'globe'; ?> <?php echo $next_class['bahasa'] == 'arab' ? 'text-green-600' : 'text-blue-600'; ?>"></i>
                                        </div>
                                        <div>
                                            <p class="font-semibold"><?php echo htmlspecialchars($next_class['guru_nama']); ?></p>
                                            <p class="text-xs text-gray-600"><?php echo ucfirst($next_class['bahasa']); ?></p>
                                        </div>
                                    </div>
                                    <span class="px-2 py-1 <?php echo $next_class['bahasa'] == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?> rounded text-xs font-medium">
                                        <?php echo htmlspecialchars($next_class['nama_kelas'] ?? 'Class'); ?>
                                    </span>
                                </div>
                                
                                <div class="space-y-2">
                                    <div class="flex items-center text-sm">
                                        <i class="fas fa-calendar-day text-gray-400 mr-2 w-5"></i>
                                        <span><?php echo date('l, d F Y', strtotime($next_class['tanggal'])); ?></span>
                                    </div>
                                    <div class="flex items-center text-sm">
                                        <i class="fas fa-clock text-gray-400 mr-2 w-5"></i>
                                        <span><?php echo date('H:i', strtotime($next_class['jam_mulai'])); ?> - <?php echo date('H:i', strtotime($next_class['jam_selesai'])); ?></span>
                                    </div>
                                    <div class="flex items-center text-sm">
                                        <i class="fas fa-book text-gray-400 mr-2 w-5"></i>
                                        <span class="capitalize"><?php echo ucfirst($next_class['jenis_pelajaran'] ?? 'lesson'); ?></span>
                                    </div>
                                </div>
                                
                                <div class="mt-4 flex space-x-2">
                                    <button onclick="setReminder(<?php echo $next_class['id']; ?>)" 
                                            class="flex-1 py-2 bg-yellow-100 text-yellow-700 hover:bg-yellow-200 rounded transition">
                                        <i class="fas fa-bell mr-1"></i> Reminder
                                    </button>
                                    <button onclick="viewClassDetails(<?php echo $next_class['id']; ?>)" 
                                            class="flex-1 py-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded transition">
                                        <i class="fas fa-book-open mr-1"></i> Prepare
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Countdown -->
                            <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-700 mb-2">Time until class:</p>
                                <div class="grid grid-cols-4 gap-1 text-center" id="countdown">
                                    <div>
                                        <div class="text-lg font-bold text-gray-800">--</div>
                                        <div class="text-xs text-gray-600">Days</div>
                                    </div>
                                    <div>
                                        <div class="text-lg font-bold text-gray-800">--</div>
                                        <div class="text-xs text-gray-600">Hours</div>
                                    </div>
                                    <div>
                                        <div class="text-lg font-bold text-gray-800">--</div>
                                        <div class="text-xs text-gray-600">Mins</div>
                                    </div>
                                    <div>
                                        <div class="text-lg font-bold text-gray-800">--</div>
                                        <div class="text-xs text-gray-600">Secs</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Today's Classes -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Today's Classes</h2>
                        
                        <?php if (mysqli_num_rows($today_schedule) > 0): ?>
                            <div class="space-y-3">
                                <?php while ($class = mysqli_fetch_assoc($today_schedule)): 
                                    $is_now = false;
                                    $current_time = date('H:i:s');
                                    $start_time = date('H:i:s', strtotime($class['jam_mulai']));
                                    $end_time = date('H:i:s', strtotime($class['jam_selesai']));
                                    $is_now = ($current_time >= $start_time && $current_time <= $end_time);
                                ?>
                                    <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition <?php echo $is_now ? 'class-now' : ''; ?>">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 <?php echo $class['bahasa'] == 'arab' ? 'bg-green-100' : 'bg-blue-100'; ?> rounded-full flex items-center justify-center">
                                                <i class="fas fa-<?php echo $class['bahasa'] == 'arab' ? 'language' : 'globe'; ?> text-xs <?php echo $class['bahasa'] == 'arab' ? 'text-green-600' : 'text-blue-600'; ?>"></i>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium">
                                                    <?php echo date('H:i', strtotime($class['jam_mulai'])); ?>
                                                    <?php if ($is_now): ?>
                                                        <span class="ml-2 text-red-600 text-xs font-medium">
                                                            <i class="fas fa-circle fa-xs mr-1"></i> Now
                                                        </span>
                                                    <?php endif; ?>
                                                </p>
                                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($class['guru_nama']); ?></p>
                                            </div>
                                        </div>
                                        <span class="text-xs px-2 py-1 <?php echo $class['bahasa'] == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?> rounded font-medium">
                                            <?php echo ucfirst($class['bahasa']); ?>
                                        </span>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-6">
                                <i class="fas fa-coffee text-3xl text-gray-300 mb-2"></i>
                                <p class="text-gray-500">No classes today</p>
                                <p class="text-sm text-gray-400 mt-1">Take a break and prepare for upcoming classes</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Weekly Overview -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Weekly Overview</h2>
                        
                        <?php
                        $weekly_query = "
                            SELECT 
                                DAYOFWEEK(j.tanggal) as day_num,
                                COUNT(*) as class_count
                            FROM jadwal j
                            JOIN jadwal_siswa js ON j.id = js.jadwal_id
                            WHERE js.pelajar_id = $user_id 
                            AND j.tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                            AND j.tanggal <= CURDATE()
                            GROUP BY DAYOFWEEK(j.tanggal)
                            ORDER BY day_num
                        ";
                        $weekly_result = mysqli_query($conn, $weekly_query);
                        
                        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                        $weekly_data = array_fill(0, 7, 0);
                        
                        if ($weekly_result) {
                            while ($stat = mysqli_fetch_assoc($weekly_result)) {
                                // MySQL DAYOFWEEK returns 1=Sunday, 7=Saturday
                                $index = $stat['day_num'] - 1;
                                $weekly_data[$index] = $stat['class_count'];
                            }
                        }
                        ?>
                        
                        <div class="space-y-2">
                            <?php for ($i = 0; $i < 7; $i++): 
                                $is_today = $i == (date('w')); // date('w') returns 0=Sunday, 6=Saturday
                            ?>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm <?php echo $is_today ? 'font-medium text-yellow-600' : 'text-gray-600'; ?>">
                                        <?php echo substr($days[$i], 0, 3); ?>
                                    </span>
                                    <div class="flex items-center space-x-2">
                                        <div class="w-24 bg-gray-200 rounded-full h-2">
                                            <div class="bg-yellow-500 h-2 rounded-full" 
                                                 style="width: <?php echo min($weekly_data[$i] * 20, 100); ?>%"></div>
                                        </div>
                                        <span class="text-xs font-medium w-4 text-right">
                                            <?php echo $weekly_data[$i]; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                        
                        <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                            <?php
                            $max_classes = max($weekly_data);
                            $busiest_day_index = array_search($max_classes, $weekly_data);
                            $total_week_classes = array_sum($weekly_data);
                            ?>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Busiest day:</span>
                                <span class="font-medium"><?php echo $days[$busiest_day_index]; ?></span>
                            </div>
                            <div class="flex justify-between text-sm mt-1">
                                <span class="text-gray-600">Total this week:</span>
                                <span class="font-medium"><?php echo $total_week_classes; ?> classes</span>
                            </div>
                            <div class="flex justify-between text-sm mt-1">
                                <span class="text-gray-600">Average per day:</span>
                                <span class="font-medium"><?php echo number_format($total_week_classes / 7, 1); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Quick Actions</h2>
                        
                        <div class="space-y-3">
                            <button onclick="downloadSchedule()" 
                                    class="w-full py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg text-left px-4 transition">
                                <i class="fas fa-download mr-2"></i> Download Schedule
                            </button>
                            <button onclick="syncCalendar()" 
                                    class="w-full py-2 bg-green-50 text-green-700 hover:bg-green-100 rounded-lg text-left px-4 transition">
                                <i class="fas fa-sync-alt mr-2"></i> Sync with Calendar
                            </button>
                            <button onclick="requestChange()" 
                                    class="w-full py-2 bg-yellow-50 text-yellow-700 hover:bg-yellow-100 rounded-lg text-left px-4 transition">
                                <i class="fas fa-exchange-alt mr-2"></i> Request Schedule Change
                            </button>
                            <a href="absensi.php" 
                               class="block w-full py-2 bg-purple-50 text-purple-700 hover:bg-purple-100 rounded-lg text-left px-4 transition">
                                <i class="fas fa-clipboard-check mr-2"></i> View Attendance
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="mt-8 text-center text-gray-500 text-sm">
                <p>Gensa Course Schedule Portal &copy; <?php echo date('Y'); ?> - <?php echo htmlspecialchars($full_name); ?></p>
                <p class="mt-1">Last updated: <?php echo date('d F Y H:i'); ?></p>
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
    
    <?php if ($next_class): ?>
    // Countdown timer for next class
    function updateCountdown() {
        const classTime = new Date('<?php echo $next_class['tanggal']; ?>T<?php echo $next_class['jam_mulai']; ?>').getTime();
        const now = new Date().getTime();
        const timeLeft = classTime - now;
        
        if (timeLeft > 0) {
            const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
            const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
            
            document.getElementById('countdown').innerHTML = `
                <div>
                    <div class="text-lg font-bold text-gray-800">${days}</div>
                    <div class="text-xs text-gray-600">Days</div>
                </div>
                <div>
                    <div class="text-lg font-bold text-gray-800">${hours}</div>
                    <div class="text-xs text-gray-600">Hours</div>
                </div>
                <div>
                    <div class="text-lg font-bold text-gray-800">${minutes}</div>
                    <div class="text-xs text-gray-600">Mins</div>
                </div>
                <div>
                    <div class="text-lg font-bold text-gray-800">${seconds}</div>
                    <div class="text-xs text-gray-600">Secs</div>
                </div>
            `;
        } else {
            document.getElementById('countdown').innerHTML = `
                <div class="col-span-4 text-center py-2">
                    <span class="text-green-600 font-medium"><i class="fas fa-check-circle mr-1"></i> Class has started!</span>
                </div>
            `;
        }
    }
    
    // Update countdown every second
    setInterval(updateCountdown, 1000);
    updateCountdown(); // Initial call
    <?php endif; ?>
    
    function viewWeek() {
        const today = new Date();
        const start = new Date(today.setDate(today.getDate() - today.getDay()));
        const end = new Date(today.setDate(today.getDate() + 6));
        
        document.querySelector('input[name="start_date"]').value = start.toISOString().split('T')[0];
        document.querySelector('input[name="end_date"]').value = end.toISOString().split('T')[0];
        document.querySelector('form').submit();
    }
    
    function viewMonth() {
        const today = new Date();
        const start = new Date(today.getFullYear(), today.getMonth(), 1);
        const end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        
        document.querySelector('input[name="start_date"]').value = start.toISOString().split('T')[0];
        document.querySelector('input[name="end_date"]').value = end.toISOString().split('T')[0];
        document.querySelector('form').submit();
    }
    
    function printSchedule() {
        window.print();
    }
    
    function addToCalendar() {
        alert('Calendar integration coming soon! You can download the schedule and import it to your calendar app.');
    }
    
    function setReminder(classId) {
        // In a real app, this would be an AJAX call
        if (confirm('Set reminder for this class? You will be notified 30 minutes before the class starts.')) {
            alert('Reminder set successfully!');
        }
    }
    
    function viewClassDetails(classId) {
        window.open(`class_details.php?id=${classId}`, '_blank');
    }
    
    function downloadSchedule() {
        const params = new URLSearchParams(window.location.search);
        alert('Schedule download feature coming soon!');
    }
    
    function syncCalendar() {
        alert('Calendar sync feature coming soon!');
    }
    
    function requestChange() {
        window.open('request_change.php', '_blank');
    }
    
    // Auto-refresh page every 5 minutes to update "Now" status
    setInterval(() => {
        const nowElements = document.querySelectorAll('.class-now');
        if (nowElements.length === 0) {
            // Only refresh if there are no "now" classes to avoid disruption
            console.log('Auto-refreshing schedule...');
            // location.reload(); // Uncomment to enable auto-refresh
        }
    }, 300000); // 5 minutes
    
    // Highlight today's date in the schedule
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        const dateHeaders = document.querySelectorAll('h3.text-lg');
        
        dateHeaders.forEach(header => {
            if (header.textContent.includes(today)) {
                header.classList.add('text-yellow-600');
            }
        });
    });
    </script>
</body>
</html>