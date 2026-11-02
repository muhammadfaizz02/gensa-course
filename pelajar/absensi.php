<?php
include 'includes/auth_check.php';
include '../config/database.php'; // INI YANG DITAMBAHKAN!

// Get student profile for sidebar
$profile_query = "SELECT * FROM pelajar_profiles WHERE user_id = $user_id";
$profile_result = mysqli_query($conn, $profile_query);
$student_profile = mysqli_fetch_assoc($profile_result);

// Get attendance records
$attendance_query = "
    SELECT j.*, js.status_kehadiran, u.full_name as guru_nama,
           mk.nama_kelas, ms.nama_subkelas
    FROM jadwal j
    JOIN jadwal_siswa js ON j.id = js.jadwal_id
    JOIN users u ON j.guru_id = u.id
    LEFT JOIN master_kelas mk ON j.kelas_id = mk.id
    LEFT JOIN master_subkelas ms ON j.subkelas_id = ms.id
    WHERE js.pelajar_id = $user_id 
    AND j.status = 'completed'
    ORDER BY j.tanggal DESC
";
$attendance_records = mysqli_query($conn, $attendance_query);

// Calculate attendance statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_classes,
        SUM(CASE WHEN js.status_kehadiran = 'hadir' THEN 1 ELSE 0 END) as attended,
        SUM(CASE WHEN js.status_kehadiran = 'tidak_hadir' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN js.status_kehadiran = 'izin' THEN 1 ELSE 0 END) as permission,
        SUM(CASE WHEN js.status_kehadiran = 'hadir' AND j.bahasa = 'arab' THEN 1 ELSE 0 END) as arab_attended,
        SUM(CASE WHEN js.status_kehadiran = 'hadir' AND j.bahasa = 'inggris' THEN 1 ELSE 0 END) as english_attended
    FROM jadwal j
    JOIN jadwal_siswa js ON j.id = js.jadwal_id
    WHERE js.pelajar_id = $user_id 
    AND j.status = 'completed'
";
$attendance_stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_query));

// Calculate percentages
$attendance_rate = $attendance_stats['total_classes'] > 0 ? 
    round(($attendance_stats['attended'] / $attendance_stats['total_classes']) * 100) : 0;

$absence_rate = $attendance_stats['total_classes'] > 0 ? 
    round(($attendance_stats['absent'] / $attendance_stats['total_classes']) * 100) : 0;

$permission_rate = $attendance_stats['total_classes'] > 0 ? 
    round(($attendance_stats['permission'] / $attendance_stats['total_classes']) * 100) : 0;

// Get monthly attendance
$monthly_query = "
    SELECT 
        DATE_FORMAT(j.tanggal, '%Y-%m') as month,
        COUNT(*) as total_classes,
        SUM(CASE WHEN js.status_kehadiran = 'hadir' THEN 1 ELSE 0 END) as attended
    FROM jadwal j
    JOIN jadwal_siswa js ON j.id = js.jadwal_id
    WHERE js.pelajar_id = $user_id 
    AND j.status = 'completed'
    GROUP BY DATE_FORMAT(j.tanggal, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
";
$monthly_attendance = mysqli_query($conn, $monthly_query);

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
    <title>My Attendance - Gensa Course</title>
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
        .sidebar-active {
            background-color: rgba(255, 255, 255, 0.2);
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
                <a href="absensi.php" class="flex items-center space-x-3 p-3 bg-white bg-opacity-20 rounded-lg sidebar-active relative">
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
                    <h1 class="text-2xl font-bold text-gray-800">My Attendance</h1>
                    <p class="text-gray-600">Track your class attendance records</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                    <button onclick="printAttendance()" class="w-full sm:w-auto justify-center px-4 py-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg transition">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                    <button onclick="requestAttendanceReview()" class="w-full sm:w-auto justify-center px-4 py-2 bg-yellow-100 text-yellow-700 hover:bg-yellow-200 rounded-lg transition">
                        <i class="fas fa-edit mr-2"></i> Request Review
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Content -->
        <div class="p-4 sm:p-6">
            <!-- Attendance Summary -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Attendance Rate</p>
                            <p class="text-3xl font-bold text-gray-800"><?php echo $attendance_rate; ?>%</p>
                            <p class="text-sm text-gray-600"><?php echo $attendance_stats['attended']; ?> of <?php echo $attendance_stats['total_classes']; ?> classes</p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo $attendance_rate; ?>%"></div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Absence Rate</p>
                            <p class="text-3xl font-bold text-gray-800"><?php echo $absence_rate; ?>%</p>
                            <p class="text-sm text-gray-600"><?php echo $attendance_stats['absent']; ?> absences</p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-times-circle text-red-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-red-500 h-2 rounded-full" style="width: <?php echo $absence_rate; ?>%"></div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">With Permission</p>
                            <p class="text-3xl font-bold text-gray-800"><?php echo $permission_rate; ?>%</p>
                            <p class="text-sm text-gray-600"><?php echo $attendance_stats['permission']; ?> permissions</p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-file-signature text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-yellow-500 h-2 rounded-full" style="width: <?php echo $permission_rate; ?>%"></div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Classes</p>
                            <p class="text-3xl font-bold text-gray-800"><?php echo $attendance_stats['total_classes']; ?></p>
                            <p class="text-sm text-gray-600">Completed sessions</p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-calendar-check text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Attendance Records -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-800">Attendance History</h2>
                            <div class="text-sm text-gray-600">
                                <?php echo mysqli_num_rows($attendance_records); ?> records
                            </div>
                        </div>
                        
                        <?php if (mysqli_num_rows($attendance_records) > 0): ?>
                            <!-- PERUBAHAN 4: Tabel Responsive -->
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[640px]">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Date</th>
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Class & Teacher</th>
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Subject</th>
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Status</th>
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Details</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php while ($record = mysqli_fetch_assoc($attendance_records)): 
                                            $status_colors = [
                                                'hadir' => ['bg-green-100 text-green-800', 'fa-check-circle'],
                                                'tidak_hadir' => ['bg-red-100 text-red-800', 'fa-times-circle'],
                                                'izin' => ['bg-yellow-100 text-yellow-800', 'fa-file-signature']
                                            ];
                                            $status = $record['status_kehadiran'];
                                        ?>
                                            <tr class="hover:bg-gray-50 transition">
                                                <td class="py-3 px-4">
                                                    <p class="text-sm font-medium"><?php echo date('d M Y', strtotime($record['tanggal'])); ?></p>
                                                    <p class="text-xs text-gray-500"><?php echo date('H:i', strtotime($record['jam_mulai'])); ?></p>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <p class="font-medium"><?php echo htmlspecialchars($record['nama_kelas'] ?? 'Class'); ?> - <?php echo htmlspecialchars($record['nama_subkelas'] ?? 'Subclass'); ?></p>
                                                    <p class="text-sm text-gray-500">Teacher: <?php echo htmlspecialchars($record['guru_nama']); ?></p>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <span class="px-2 py-1 <?php echo $record['bahasa'] == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?> rounded-full text-xs font-medium">
                                                        <?php echo ucfirst($record['bahasa']); ?>
                                                    </span>
                                                    <p class="text-xs text-gray-500 mt-1 capitalize"><?php echo $record['jenis_pelajaran'] ?? 'Lesson'; ?></p>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php echo $status_colors[$status][0]; ?>">
                                                        <i class="fas <?php echo $status_colors[$status][1]; ?> mr-1"></i>
                                                        <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <button onclick="viewAttendanceDetails(<?php echo $record['id']; ?>)" 
                                                            class="px-3 py-1 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded text-sm transition">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-3">
                                <div class="text-sm text-gray-600">
                                    Showing <?php echo mysqli_num_rows($attendance_records); ?> records
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
                                <i class="fas fa-clipboard-list text-5xl text-gray-300 mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-700 mb-2">No Attendance Records</h3>
                                <p class="text-gray-500">Your attendance records will appear here after classes are completed</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Side Panel -->
                <div class="space-y-6">
                    <!-- Monthly Attendance -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Monthly Attendance</h2>
                        
                        <div class="space-y-4">
                            <?php if (mysqli_num_rows($monthly_attendance) > 0): ?>
                                <?php while ($month = mysqli_fetch_assoc($monthly_attendance)): 
                                    $month_rate = $month['total_classes'] > 0 ? 
                                        round(($month['attended'] / $month['total_classes']) * 100) : 0;
                                    $month_name = date('F Y', strtotime($month['month'] . '-01'));
                                ?>
                                    <div class="p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="font-medium text-gray-800"><?php echo $month_name; ?></span>
                                            <span class="text-sm font-medium <?php echo $month_rate >= 80 ? 'text-green-600' : ($month_rate >= 60 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                                <?php echo $month_rate; ?>%
                                            </span>
                                        </div>
                                        <div class="flex justify-between text-xs text-gray-600 mb-2">
                                            <span><?php echo $month['attended']; ?> attended</span>
                                            <span><?php echo $month['total_classes']; ?> total</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="<?php echo $month_rate >= 80 ? 'bg-green-500' : ($month_rate >= 60 ? 'bg-yellow-500' : 'bg-red-500'); ?> h-2 rounded-full" 
                                                 style="width: <?php echo $month_rate; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <p class="text-gray-500">No monthly data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-6 p-3 bg-blue-50 rounded-lg">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-700">Best Month:</span>
                                <?php
                                mysqli_data_seek($monthly_attendance, 0);
                                $best_month = null;
                                $best_rate = 0;
                                while ($month = mysqli_fetch_assoc($monthly_attendance)) {
                                    $rate = $month['total_classes'] > 0 ? 
                                        ($month['attended'] / $month['total_classes']) * 100 : 0;
                                    if ($rate > $best_rate) {
                                        $best_rate = $rate;
                                        $best_month = $month;
                                    }
                                }
                                ?>
                                <span class="font-medium">
                                    <?php echo $best_month ? date('F', strtotime($best_month['month'] . '-01')) : 'N/A'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Subject Attendance -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Attendance by Subject</h2>
                        
                        <div class="space-y-4">
                            <!-- Arabic Attendance -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                        <span class="font-medium">Arabic</span>
                                    </div>
                                    <span class="text-sm font-medium">
                                        <?php 
                                        $arab_rate = $attendance_stats['total_classes'] > 0 ? 
                                            round(($attendance_stats['arab_attended'] / $attendance_stats['total_classes']) * 100) : 0;
                                        echo $arab_rate; ?>%
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo $arab_rate; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-600 mt-1">
                                    <span><?php echo $attendance_stats['arab_attended']; ?> attended</span>
                                    <span><?php echo $attendance_stats['total_classes']; ?> total</span>
                                </div>
                            </div>
                            
                            <!-- English Attendance -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                                        <span class="font-medium">English</span>
                                    </div>
                                    <span class="text-sm font-medium">
                                        <?php 
                                        $english_rate = $attendance_stats['total_classes'] > 0 ? 
                                            round(($attendance_stats['english_attended'] / $attendance_stats['total_classes']) * 100) : 0;
                                        echo $english_rate; ?>%
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo $english_rate; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-600 mt-1">
                                    <span><?php echo $attendance_stats['english_attended']; ?> attended</span>
                                    <span><?php echo $attendance_stats['total_classes']; ?> total</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Attendance Tips -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Attendance Tips</h2>
                        
                        <div class="space-y-3">
                            <div class="flex items-start space-x-2">
                                <i class="fas fa-bell text-yellow-600 mt-1"></i>
                                <div>
                                    <p class="text-sm font-medium">Set Reminders</p>
                                    <p class="text-xs text-gray-600">Set reminders 30 minutes before class</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-2">
                                <i class="fas fa-calendar-check text-green-600 mt-1"></i>
                                <div>
                                    <p class="text-sm font-medium">Regular Attendance</p>
                                    <p class="text-xs text-gray-600">Maintain ≥80% attendance for better learning</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-2">
                                <i class="fas fa-file-signature text-blue-600 mt-1"></i>
                                <div>
                                    <p class="text-sm font-medium">Permission Requests</p>
                                    <p class="text-xs text-gray-600">Submit permission requests at least 24 hours in advance</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 p-3 bg-yellow-50 rounded-lg">
                            <p class="text-sm text-yellow-800">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                Current attendance status: 
                                <span class="font-medium">
                                    <?php echo $attendance_rate >= 80 ? 'Excellent' : ($attendance_rate >= 60 ? 'Good' : 'Needs Improvement'); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Quick Actions</h2>
                        
                        <div class="space-y-3">
                            <button onclick="requestPermission()" 
                                    class="w-full py-2 bg-yellow-50 text-yellow-700 hover:bg-yellow-100 rounded-lg text-left px-4 transition">
                                <i class="fas fa-file-signature mr-2"></i> Request Permission
                            </button>
                            <button onclick="downloadReport()" 
                                    class="w-full py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg text-left px-4 transition">
                                <i class="fas fa-download mr-2"></i> Download Report
                            </button>
                            <a href="jadwal.php" 
                               class="block w-full py-2 bg-green-50 text-green-700 hover:bg-green-100 rounded-lg text-left px-4 transition">
                                <i class="fas fa-calendar-alt mr-2"></i> View Schedule
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Attendance Statistics -->
            <div class="mt-6 bg-white rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Detailed Statistics</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="text-center p-6 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl">
                        <div class="text-5xl font-bold text-green-600 mb-2"><?php echo $attendance_rate; ?>%</div>
                        <p class="text-lg font-medium text-gray-800">Attendance Rate</p>
                        <p class="text-sm text-gray-600 mt-2">
                            <?php echo $attendance_stats['attended']; ?> attended out of <?php echo $attendance_stats['total_classes']; ?> classes
                        </p>
                    </div>
                    
                    <div class="text-center p-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl">
                        <div class="text-5xl font-bold text-blue-600 mb-2"><?php echo $attendance_stats['absent']; ?></div>
                        <p class="text-lg font-medium text-gray-800">Total Absences</p>
                        <p class="text-sm text-gray-600 mt-2">
                            <?php echo $absence_rate; ?>% absence rate
                        </p>
                        <?php if ($attendance_stats['absent'] > 0): ?>
                            <div class="mt-3">
                                <a href="#" class="text-sm text-blue-600 hover:text-blue-700 transition">
                                    <i class="fas fa-info-circle mr-1"></i> Review absences
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-center p-6 bg-gradient-to-r from-yellow-50 to-orange-50 rounded-xl">
                        <div class="text-5xl font-bold text-yellow-600 mb-2"><?php echo $attendance_stats['permission']; ?></div>
                        <p class="text-lg font-medium text-gray-800">With Permission</p>
                        <p class="text-sm text-gray-600 mt-2">
                            <?php echo $permission_rate; ?>% of total classes
                        </p>
                    </div>
                </div>
                
                <!-- Attendance Trend -->
                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                    <h3 class="font-medium text-gray-800 mb-3">Attendance Trend</h3>
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full <?php echo $attendance_rate >= 80 ? 'bg-green-500' : ($attendance_rate >= 60 ? 'bg-yellow-500' : 'bg-red-500'); ?> mr-2"></div>
                        <span class="text-sm text-gray-700">
                            <?php 
                            if ($attendance_rate >= 80) {
                                echo "Excellent attendance! Keep up the good work.";
                            } elseif ($attendance_rate >= 60) {
                                echo "Good attendance. Aim for 80% or higher.";
                            } else {
                                echo "Attendance needs improvement. Please attend more classes.";
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View Details Modal -->
    <div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-800">Attendance Details</h3>
                    <button onclick="closeDetailsModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="detailsContent">
                    <!-- Content will be loaded via AJAX -->
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
    
    function printAttendance() {
        alert('Print feature will be available soon!');
        // window.open('print_attendance.php', '_blank');
    }
    
    function requestAttendanceReview() {
        alert('Attendance review request feature will be available soon!');
        // window.open('request_review.php', '_blank');
    }
    
    function viewAttendanceDetails(recordId) {
        // Simulate loading details
        document.getElementById('detailsContent').innerHTML = `
            <div class="text-center py-8">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-yellow-500 mx-auto mb-4"></div>
                <p class="text-gray-600">Loading details...</p>
            </div>
        `;
        document.getElementById('detailsModal').classList.remove('hidden');
        document.getElementById('detailsModal').classList.add('flex');
        
        // Simulate API call
        setTimeout(() => {
            document.getElementById('detailsContent').innerHTML = `
                <div class="space-y-4">
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-600">Date</p>
                        <p class="font-medium">15 January 2024</p>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-600">Time</p>
                        <p class="font-medium">10:00 - 11:30</p>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-600">Status</p>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            <i class="fas fa-check-circle mr-1"></i> Present
                        </span>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-600">Teacher Notes</p>
                        <p class="text-gray-700">Good participation in class discussion</p>
                    </div>
                </div>
                <div class="mt-6">
                    <button onclick="closeDetailsModal()" class="w-full py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition">
                        Close
                    </button>
                </div>
            `;
        }, 1000);
    }
    
    function closeDetailsModal() {
        document.getElementById('detailsModal').classList.add('hidden');
        document.getElementById('detailsModal').classList.remove('flex');
    }
    
    function requestPermission() {
        alert('Permission request feature will be available soon!');
        // window.open('request_permission.php', '_blank');
    }
    
    function downloadReport() {
        alert('Download report feature will be available soon!');
        // window.open('download_attendance_report.php', '_blank');
    }
    
    // Close modal when clicking outside
    document.getElementById('detailsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDetailsModal();
        }
    });
    
    // Highlight recent absences
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date();
        const sevenDaysAgo = new Date(today);
        sevenDaysAgo.setDate(today.getDate() - 7);
        
        const rows = document.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const statusSpan = row.querySelector('td:nth-child(4) span');
            if (statusSpan && statusSpan.textContent.includes('Absent')) {
                row.classList.add('bg-red-50');
            }
        });
        
        // Update attendance status colors
        const attendanceRate = <?php echo $attendance_rate; ?>;
        const attendanceBox = document.querySelector('.border-l-4.border-green-500');
        if (attendanceBox) {
            if (attendanceRate < 60) {
                attendanceBox.classList.remove('border-green-500');
                attendanceBox.classList.add('border-red-500');
                attendanceBox.querySelector('.text-3xl').classList.remove('text-gray-800');
                attendanceBox.querySelector('.text-3xl').classList.add('text-red-600');
            } else if (attendanceRate < 80) {
                attendanceBox.classList.remove('border-green-500');
                attendanceBox.classList.add('border-yellow-500');
                attendanceBox.querySelector('.text-3xl').classList.remove('text-gray-800');
                attendanceBox.querySelector('.text-3xl').classList.add('text-yellow-600');
            }
        }
    });
    
    // Auto-refresh every 30 minutes
    setInterval(() => {
        // In a real app, you would fetch new data via AJAX
        console.log('Auto-refreshing attendance data...');
    }, 1800000); // 30 minutes
    </script>
</body>
</html>