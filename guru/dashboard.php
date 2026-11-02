<?php
include '../includes/auth_check_guru.php';
include '../config/database.php';

// Get weekly attendance data (last 7 days)
$weekly_data = [];
$days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$today = date('Y-m-d');
$start_of_week = date('Y-m-d', strtotime('monday this week'));

for ($i = 0; $i < 6; $i++) {
    $date = date('Y-m-d', strtotime($start_of_week . " + $i days"));
    $day_name = $days[$i];
    
    $query = "
        SELECT COUNT(*) as count 
        FROM kehadiran_guru 
        WHERE guru_id = $user_id 
        AND tanggal = '$date' 
        AND status = 'approved'
    ";
    $result = mysqli_query($conn, $query);
    $count = mysqli_fetch_assoc($result)['count'];
    
    $weekly_data[] = [
        'day' => $day_name,
        'date' => date('d/m', strtotime($date)),
        'count' => $count
    ];
}

// Get monthly statistics
$current_month = date('Y-m');
$monthly_query = "
    SELECT 
        COUNT(DISTINCT tanggal) as teaching_days,
        COUNT(*) as total_sessions,
        SUM(TIME_TO_SEC(TIMEDIFF(jam_selesai, jam_mulai))) / 3600 as total_hours
    FROM kehadiran_guru 
    WHERE guru_id = $user_id 
    AND status = 'approved'
    AND DATE_FORMAT(tanggal, '%Y-%m') = '$current_month'
";
$monthly_stats = mysqli_fetch_assoc(mysqli_query($conn, $monthly_query));

// Get today's schedule
$today_schedule_query = "
    SELECT j.*, mk.nama_kelas, ms.nama_subkelas,
           COUNT(DISTINCT js.pelajar_id) as total_students
    FROM jadwal j
    LEFT JOIN master_kelas mk ON j.kelas_id = mk.id
    LEFT JOIN master_subkelas ms ON j.subkelas_id = ms.id
    LEFT JOIN jadwal_siswa js ON j.id = js.jadwal_id
    WHERE j.guru_id = $user_id 
    AND j.tanggal = CURDATE()
    AND j.status = 'scheduled'
    GROUP BY j.id
    ORDER BY j.jam_mulai
";
$today_schedule = mysqli_query($conn, $today_schedule_query);

// Get pending attendance approvals
$pending_query = "
    SELECT COUNT(*) as count 
    FROM kehadiran_guru 
    WHERE guru_id = $user_id 
    AND status = 'pending'
";
$pending_count = mysqli_fetch_assoc(mysqli_query($conn, $pending_query))['count'];

// Get upcoming schedules (next 3 days)
$upcoming_query = "
    SELECT j.*, mk.nama_kelas, ms.nama_subkelas
    FROM jadwal j
    LEFT JOIN master_kelas mk ON j.kelas_id = mk.id
    LEFT JOIN master_subkelas ms ON j.subkelas_id = ms.id
    WHERE j.guru_id = $user_id 
    AND j.tanggal > CURDATE()
    AND j.tanggal <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    AND j.status = 'scheduled'
    ORDER BY j.tanggal, j.jam_mulai
    LIMIT 5
";
$upcoming_schedule = mysqli_query($conn, $upcoming_query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru - Gensa Course</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        <div class="p-6 h-full overflow-y-auto">
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center">
                        <i class="fas fa-chalkboard-teacher text-2xl gensa-text"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold">Teacher Panel</h2>
                        <p class="text-sm opacity-80">Gensa Course</p>
                    </div>
                </div>
                <button id="closeSidebar" class="lg:hidden text-white hover:text-gray-300">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <nav class="space-y-2">
                <a href="dashboard.php" class="flex items-center space-x-3 p-3 bg-white bg-opacity-20 rounded-lg">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="attendance.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>
                <a href="input_nilai.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Input Scores</span>
                </a>
                <a href="absensi_siswa.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
                    <i class="fas fa-user-check"></i>
                    <span>Student Attendance</span>
                </a>
                <a href="jadwal.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Teaching Schedule</span>
                </a>
                <a href="rekap_kehadiran.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
                    <i class="fas fa-chart-bar"></i>
                    <span>Attendance Report</span>
                </a>
                <a href="../logout.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition mt-8">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </div>
    </div>
    
    <!-- Main Content Wrapper -->
    <div class="lg:ml-64 pt-14 lg:pt-0 min-h-screen min-w-0">
        <!-- Top Bar -->
        <div class="bg-white shadow-sm p-4 border-b">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Welcome, <?php echo $full_name; ?>!</h1>
                    <p class="text-gray-600">Teacher Dashboard - Gensa Course</p>
                </div>
                <div class="flex items-center justify-between sm:justify-end space-x-4">
                    <div class="relative">
                        <button class="p-2 hover:bg-gray-100 rounded-full">
                            <i class="fas fa-bell text-gray-600 text-xl"></i>
                        </button>
                        <?php if ($pending_count > 0): ?>
                            <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full"></span>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-10 h-10 bg-yellow-500 rounded-full flex items-center justify-center text-white font-semibold">
                            <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                        </div>
                        <div>
                            <p class="font-semibold"><?php echo $full_name; ?></p>
                            <p class="text-sm text-gray-500">Teacher</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Content -->
        <div class="p-4 sm:p-6">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Today's Schedule</p>
                            <p class="text-3xl font-bold text-gray-800"><?php echo mysqli_num_rows($today_schedule); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-calendar-day text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                    <a href="jadwal.php" class="mt-4 inline-flex items-center text-sm text-yellow-600 hover:text-yellow-700">
                        View schedule <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Teaching Days (Month)</p>
                            <p class="text-3xl font-bold text-gray-800"><?php echo $monthly_stats['teaching_days'] ?? 0; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-chalkboard-teacher text-green-600 text-xl"></i>
                        </div>
                    </div>
                    <a href="rekap_kehadiran.php" class="mt-4 inline-flex items-center text-sm text-green-600 hover:text-green-700">
                        View report <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Sessions (Month)</p>
                            <p class="text-3xl font-bold text-gray-800"><?php echo $monthly_stats['total_sessions'] ?? 0; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-clock text-blue-600 text-xl"></i>
                        </div>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">
                        <?php echo number_format($monthly_stats['total_hours'] ?? 0, 1); ?> hours
                    </p>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Pending Approvals</p>
                            <p class="text-3xl font-bold text-gray-800"><?php echo $pending_count; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-clock text-purple-600 text-xl"></i>
                        </div>
                    </div>
                    <a href="attendance.php" class="mt-4 inline-flex items-center text-sm text-purple-600 hover:text-purple-700">
                        Check status <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Weekly Attendance Chart -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-800">Weekly Attendance</h2>
                        <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">
                            Week <?php echo date('W'); ?>
                        </span>
                    </div>
                    
                    <div class="h-64">
                        <canvas id="weeklyChart"></canvas>
                    </div>
                    
                    <div class="mt-4 grid grid-cols-6 gap-2">
                        <?php foreach ($weekly_data as $day): ?>
                            <div class="text-center">
                                <p class="text-xs text-gray-500"><?php echo $day['day']; ?></p>
                                <p class="text-lg font-bold <?php echo $day['count'] > 0 ? 'text-yellow-600' : 'text-gray-400'; ?>">
                                    <?php echo $day['count']; ?>
                                </p>
                                <p class="text-xs text-gray-400"><?php echo $day['date']; ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Today's Schedule -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-800">Today's Schedule</h2>
                        <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">
                            <?php echo date('l, d F Y'); ?>
                        </span>
                    </div>
                    
                    <?php if (mysqli_num_rows($today_schedule) > 0): ?>
                        <div class="space-y-4">
                            <?php while ($schedule = mysqli_fetch_assoc($today_schedule)): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <p class="font-semibold text-gray-800">
                                                <?php echo date('H:i', strtotime($schedule['jam_mulai'])); ?> - 
                                                <?php echo date('H:i', strtotime($schedule['jam_selesai'])); ?>
                                            </p>
                                            <p class="text-sm text-gray-600"><?php echo $schedule['nama_kelas']; ?> - <?php echo $schedule['nama_subkelas']; ?></p>
                                        </div>
                                        <span class="px-2 py-1 <?php echo $schedule['bahasa'] == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?> rounded-full text-xs">
                                            <?php echo ucfirst($schedule['bahasa']); ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-700 mb-2">
                                        <i class="fas fa-book mr-1"></i>
                                        <?php echo ucfirst($schedule['jenis_pelajaran']); ?>
                                    </p>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-500">
                                            <i class="fas fa-users mr-1"></i>
                                            <?php echo $schedule['total_students']; ?> students
                                        </span>
                                        <a href="absensi_siswa.php?jadwal_id=<?php echo $schedule['id']; ?>" 
                                           class="text-sm text-yellow-600 hover:text-yellow-700">
                                            <i class="fas fa-clipboard-check mr-1"></i> Take Attendance
                                        </a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-times text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No schedule for today</p>
                            <p class="text-sm text-gray-400 mt-1">Enjoy your day off!</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-6 pt-6 border-t">
                        <a href="attendance.php" class="flex items-center justify-center p-3 bg-yellow-50 text-yellow-700 hover:bg-yellow-100 rounded-lg">
                            <i class="fas fa-calendar-check mr-2"></i>
                            Mark Today's Attendance
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Schedule & Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                <!-- Upcoming Schedule -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Upcoming Schedule (Next 3 Days)</h2>
                    
                    <?php if (mysqli_num_rows($upcoming_schedule) > 0): ?>
                        <div class="space-y-3">
                            <?php while ($schedule = mysqli_fetch_assoc($upcoming_schedule)): ?>
                                <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-yellow-100 rounded flex items-center justify-center">
                                            <i class="fas fa-calendar text-yellow-600"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium">
                                                <?php echo date('D, d M', strtotime($schedule['tanggal'])); ?>
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                <?php echo date('H:i', strtotime($schedule['jam_mulai'])); ?> - 
                                                <?php echo $schedule['nama_kelas']; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <span class="px-2 py-1 <?php echo $schedule['bahasa'] == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?> rounded text-xs">
                                        <?php echo ucfirst($schedule['jenis_pelajaran']); ?>
                                    </span>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <div class="mt-4 text-center">
                            <a href="jadwal.php" class="text-sm text-yellow-600 hover:text-yellow-700">
                                View all schedules <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-calendar text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No upcoming schedule</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Quick Actions</h2>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <a href="attendance.php" class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg hover:bg-yellow-100 transition text-center">
                            <i class="fas fa-calendar-check text-yellow-600 text-2xl mb-2"></i>
                            <p class="font-medium text-gray-800">Mark Attendance</p>
                        </a>
                        <a href="input_nilai.php" class="p-4 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition text-center">
                            <i class="fas fa-graduation-cap text-green-600 text-2xl mb-2"></i>
                            <p class="font-medium text-gray-800">Input Scores</p>
                        </a>
                        <a href="absensi_siswa.php" class="p-4 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition text-center">
                            <i class="fas fa-user-check text-blue-600 text-2xl mb-2"></i>
                            <p class="font-medium text-gray-800">Student Attendance</p>
                        </a>
                        <a href="jadwal.php" class="p-4 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition text-center">
                            <i class="fas fa-calendar-alt text-purple-600 text-2xl mb-2"></i>
                            <p class="font-medium text-gray-800">View Schedule</p>
                        </a>
                    </div>
                    
                    <!-- Teacher Info -->
                    <div class="mt-6 pt-6 border-t">
                        <h3 class="font-medium text-gray-700 mb-2">Your Information</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Specialization:</span>
                                <span class="font-medium">
                                    <?php 
                                    if ($guru_profile && $guru_profile['spesialisasi']) {
                                        $specs = explode(',', $guru_profile['spesialisasi']);
                                        echo ucfirst(implode(', ', $specs));
                                    } else {
                                        echo 'Not set';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Total Students:</span>
                                <span class="font-medium">
                                    <?php
                                    $total_students = mysqli_fetch_assoc(mysqli_query($conn, 
                                        "SELECT COUNT(DISTINCT js.pelajar_id) as count
                                         FROM jadwal j
                                         JOIN jadwal_siswa js ON j.id = js.jadwal_id
                                         WHERE j.guru_id = $user_id"
                                    ))['count'];
                                    echo $total_students;
                                    ?>
                                </span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Joined:</span>
                                <span class="font-medium">
                                    <?php 
                                    $join_date = mysqli_fetch_assoc(mysqli_query($conn, 
                                        "SELECT created_at FROM users WHERE id = $user_id"
                                    ))['created_at'];
                                    echo date('d M Y', strtotime($join_date));
                                    ?>
                                </span>
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
    
    // Weekly Attendance Chart
    const ctx = document.getElementById('weeklyChart').getContext('2d');
    const weeklyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($weekly_data, 'day')); ?>,
            datasets: [{
                label: 'Teaching Sessions',
                data: <?php echo json_encode(array_column($weekly_data, 'count')); ?>,
                backgroundColor: [
                    'rgba(251, 177, 23, 0.7)',
                    'rgba(251, 177, 23, 0.7)',
                    'rgba(251, 177, 23, 0.7)',
                    'rgba(251, 177, 23, 0.7)',
                    'rgba(251, 177, 23, 0.7)',
                    'rgba(251, 177, 23, 0.7)'
                ],
                borderColor: [
                    'rgb(251, 177, 23)',
                    'rgb(251, 177, 23)',
                    'rgb(251, 177, 23)',
                    'rgb(251, 177, 23)',
                    'rgb(251, 177, 23)',
                    'rgb(251, 177, 23)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    },
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `Sessions: ${context.raw}`;
                        }
                    }
                }
            }
        }
    });
    
    // Auto refresh dashboard every minute
    setTimeout(function() {
        location.reload();
    }, 60000);
    </script>
</body>
</html>