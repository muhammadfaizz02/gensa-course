<?php
include '../includes/auth_check_guru.php';
include '../config/database.php';

// Get filters
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
$status = $_GET['status'] ?? '';
$bahasa = $_GET['bahasa'] ?? '';

// Build query with filters
$where_conditions = ["j.guru_id = $user_id"];
if ($start_date) {
    $where_conditions[] = "j.tanggal >= '$start_date'";
}
if ($end_date) {
    $where_conditions[] = "j.tanggal <= '$end_date'";
}
if ($status) {
    $where_conditions[] = "j.status = '$status'";
}
if ($bahasa) {
    $where_conditions[] = "j.bahasa = '$bahasa'";
}
$where_clause = implode(' AND ', $where_conditions);

// Get schedules
$query = "
    SELECT j.*, mk.nama_kelas, ms.nama_subkelas,
           COUNT(DISTINCT js.pelajar_id) as total_students,
           COUNT(DISTINCT a.pelajar_id) as attended_students
    FROM jadwal j
    LEFT JOIN master_kelas mk ON j.kelas_id = mk.id
    LEFT JOIN master_subkelas ms ON j.subkelas_id = ms.id
    LEFT JOIN jadwal_siswa js ON j.id = js.jadwal_id
    LEFT JOIN absensi_siswa a ON j.id = a.jadwal_id AND a.status = 'hadir'
    WHERE $where_clause
    GROUP BY j.id
    ORDER BY j.tanggal, j.jam_mulai
";
$schedules = mysqli_query($conn, $query);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_schedules,
        COUNT(CASE WHEN tanggal >= CURDATE() THEN 1 END) as upcoming_schedules,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_schedules,
        COUNT(CASE WHEN tanggal = CURDATE() THEN 1 END) as today_schedules
    FROM jadwal 
    WHERE guru_id = $user_id
";
$stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_query));

// Get upcoming schedules (next 7 days)
$upcoming_query = "
    SELECT j.*, mk.nama_kelas, ms.nama_subkelas
    FROM jadwal j
    LEFT JOIN master_kelas mk ON j.kelas_id = mk.id
    LEFT JOIN master_subkelas ms ON j.subkelas_id = ms.id
    WHERE j.guru_id = $user_id 
    AND j.tanggal >= CURDATE()
    AND j.tanggal <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND j.status = 'scheduled'
    ORDER BY j.tanggal, j.jam_mulai
    LIMIT 10
";
$upcoming_schedules = mysqli_query($conn, $upcoming_query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teaching Schedule - Teacher Gensa Course</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
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
                <a href="dashboard.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
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
                <a href="jadwal.php" class="flex items-center space-x-3 p-3 bg-white bg-opacity-20 rounded-lg">
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
                    <h1 class="text-2xl font-bold text-gray-800">Teaching Schedule</h1>
                    <p class="text-gray-600">View your teaching schedule and manage classes</p>
                </div>
                <div>
                    <a href="dashboard.php" class="inline-flex w-full sm:w-auto justify-center items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Content -->
        <div class="p-4 sm:p-6">
            <!-- Statistics & Filters -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
                <!-- Statistics -->
                <div class="lg:col-span-1 grid grid-cols-2 gap-4">
                    <div class="bg-white rounded-xl shadow p-4">
                        <p class="text-sm text-gray-600">Total Schedules</p>
                        <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['total_schedules']; ?></p>
                    </div>
                    <div class="bg-white rounded-xl shadow p-4">
                        <p class="text-sm text-gray-600">Today</p>
                        <p class="text-2xl font-bold text-blue-600"><?php echo $stats['today_schedules']; ?></p>
                    </div>
                    <div class="bg-white rounded-xl shadow p-4">
                        <p class="text-sm text-gray-600">Upcoming</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo $stats['upcoming_schedules']; ?></p>
                    </div>
                    <div class="bg-white rounded-xl shadow p-4">
                        <p class="text-sm text-gray-600">Completed</p>
                        <p class="text-2xl font-bold text-purple-600"><?php echo $stats['completed_schedules']; ?></p>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="lg:col-span-3">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Filter Schedule</h3>
                        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                                <input type="date" name="start_date" value="<?php echo $start_date; ?>" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                                <input type="date" name="end_date" value="<?php echo $end_date; ?>" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                    <option value="">All Status</option>
                                    <option value="scheduled" <?php echo $status == 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                    <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Language</label>
                                <select name="bahasa" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                    <option value="">All Languages</option>
                                    <option value="arab" <?php echo $bahasa == 'arab' ? 'selected' : ''; ?>>Arabic</option>
                                    <option value="inggris" <?php echo $bahasa == 'inggris' ? 'selected' : ''; ?>>English</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button type="submit" 
                                        class="w-full py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg">
                                    <i class="fas fa-filter mr-2"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Schedule List -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-800">Schedule List</h2>
                            <div class="text-sm text-gray-600">
                                Showing <?php echo mysqli_num_rows($schedules); ?> schedules
                            </div>
                        </div>
                        
                        <?php if (mysqli_num_rows($schedules) > 0): ?>
                            <div class="space-y-4">
                                <?php while ($schedule = mysqli_fetch_assoc($schedules)): 
                                    $attendance_rate = $schedule['total_students'] > 0 ? 
                                        ($schedule['attended_students'] / $schedule['total_students'] * 100) : 0;
                                    $is_today = $schedule['tanggal'] == date('Y-m-d');
                                    $is_past = $schedule['tanggal'] < date('Y-m-d');
                                ?>
                                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition <?php echo $is_today ? 'border-yellow-300 bg-yellow-50' : ''; ?>">
                                        <div class="flex justify-between items-start mb-3">
                                            <div>
                                                <div class="flex items-center space-x-2 mb-1">
                                                    <p class="font-semibold text-gray-800">
                                                        <?php echo date('l, d F Y', strtotime($schedule['tanggal'])); ?>
                                                    </p>
                                                    <?php if ($is_today): ?>
                                                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs">
                                                            Today
                                                        </span>
                                                    <?php elseif ($is_past): ?>
                                                        <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">
                                                            Past
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-sm text-gray-600">
                                                    <?php echo date('H:i', strtotime($schedule['jam_mulai'])); ?> - 
                                                    <?php echo date('H:i', strtotime($schedule['jam_selesai'])); ?>
                                                </p>
                                            </div>
                                            <div class="flex space-x-2">
                                                <span class="px-2 py-1 <?php echo $schedule['bahasa'] == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?> rounded-full text-xs">
                                                    <?php echo ucfirst($schedule['bahasa']); ?>
                                                </span>
                                                <span class="px-2 py-1 <?php 
                                                    echo $schedule['status'] == 'scheduled' ? 'bg-yellow-100 text-yellow-800' : 
                                                    ($schedule['status'] == 'completed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800');
                                                ?> rounded-full text-xs">
                                                    <?php echo ucfirst($schedule['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <p class="font-medium text-gray-800">
                                                <?php echo $schedule['nama_kelas']; ?> - <?php echo $schedule['nama_subkelas']; ?>
                                            </p>
                                            <p class="text-sm text-gray-600">
                                                <i class="fas fa-book mr-1"></i>
                                                <?php echo ucfirst($schedule['jenis_pelajaran']); ?>
                                            </p>
                                            <?php if ($schedule['deskripsi']): ?>
                                                <p class="text-sm text-gray-600 mt-1">
                                                    <i class="fas fa-sticky-note mr-1"></i>
                                                    <?php echo substr($schedule['deskripsi'], 0, 100); ?>
                                                    <?php echo strlen($schedule['deskripsi']) > 100 ? '...' : ''; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                                            <div class="flex items-center space-x-4">
                                                <div class="text-sm">
                                                    <i class="fas fa-users mr-1"></i>
                                                    <?php echo $schedule['total_students']; ?> students
                                                </div>
                                                <?php if ($schedule['status'] == 'completed'): ?>
                                                    <div class="flex items-center space-x-1">
                                                        <div class="w-16 bg-gray-200 rounded-full h-2">
                                                            <div class="bg-green-500 h-2 rounded-full" 
                                                                 style="width: <?php echo min($attendance_rate, 100); ?>%"></div>
                                                        </div>
                                                        <span class="text-xs text-gray-600"><?php echo round($attendance_rate); ?>%</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="flex space-x-2 w-full sm:w-auto">
                                                <?php if ($schedule['status'] == 'scheduled' && !$is_past): ?>
                                                    <a href="absensi_siswa.php?jadwal_id=<?php echo $schedule['id']; ?>" 
                                                       class="flex-1 sm:flex-none justify-center px-3 py-1 bg-yellow-100 text-yellow-700 hover:bg-yellow-200 rounded text-sm text-center">
                                                        <i class="fas fa-clipboard-check mr-1"></i> Attendance
                                                    </a>
                                                <?php endif; ?>
                                                <button onclick="viewScheduleDetails(<?php echo $schedule['id']; ?>)" 
                                                        class="flex-1 sm:flex-none justify-center px-3 py-1 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded text-sm">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <i class="fas fa-calendar-times text-5xl text-gray-300 mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-700 mb-2">No Schedules Found</h3>
                                <p class="text-gray-500 mb-6">No schedules match your filters</p>
                                <a href="?start_date=<?php echo date('Y-m-d'); ?>&end_date=<?php echo date('Y-m-d', strtotime('+30 days')); ?>" 
                                   class="inline-flex items-center px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg">
                                    <i class="fas fa-calendar-alt mr-2"></i> View Upcoming Schedules
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Upcoming Schedule & Quick Actions -->
                <div class="space-y-6">
                    <!-- Upcoming Schedule -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Upcoming (Next 7 Days)</h2>
                        
                        <?php if (mysqli_num_rows($upcoming_schedules) > 0): ?>
                            <div class="space-y-3">
                                <?php while ($schedule = mysqli_fetch_assoc($upcoming_schedules)): ?>
                                    <div class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50">
                                        <div class="flex justify-between items-start mb-1">
                                            <p class="font-medium">
                                                <?php echo date('D, d M', strtotime($schedule['tanggal'])); ?>
                                            </p>
                                            <span class="px-2 py-1 <?php echo $schedule['bahasa'] == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?> rounded text-xs">
                                                <?php echo ucfirst($schedule['bahasa'][0]); ?>
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-700">
                                            <?php echo date('H:i', strtotime($schedule['jam_mulai'])); ?> - 
                                            <?php echo $schedule['nama_kelas']; ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo ucfirst($schedule['jenis_pelajaran']); ?>
                                        </p>
                                        <div class="mt-2">
                                            <a href="absensi_siswa.php?jadwal_id=<?php echo $schedule['id']; ?>" 
                                               class="text-xs text-yellow-600 hover:text-yellow-700">
                                                <i class="fas fa-clipboard-check mr-1"></i> Prepare Attendance
                                            </a>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            <div class="mt-4 text-center">
                                <a href="?start_date=<?php echo date('Y-m-d'); ?>&end_date=<?php echo date('Y-m-d', strtotime('+7 days')); ?>" 
                                   class="text-sm text-yellow-600 hover:text-yellow-700">
                                    View all upcoming <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-calendar text-4xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500">No upcoming schedule</p>
                                <p class="text-sm text-gray-400 mt-1">Check back later</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Calendar View -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Calendar View</h2>
                        <div id="miniCalendar" class="text-center">
                            <!-- Simple calendar will be loaded via JavaScript -->
                            <div class="text-gray-400">
                                <i class="fas fa-calendar text-3xl mb-2"></i>
                                <p>Calendar loading...</p>
                            </div>
                        </div>
                        <div class="mt-4 text-center">
                            <button onclick="viewFullCalendar()" 
                                    class="px-4 py-2 bg-yellow-50 text-yellow-700 hover:bg-yellow-100 rounded-lg">
                                <i class="fas fa-expand mr-2"></i> View Full Calendar
                            </button>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Quick Actions</h2>
                        
                        <div class="space-y-3">
                            <a href="absensi_siswa.php" 
                               class="block p-3 bg-yellow-50 hover:bg-yellow-100 rounded-lg text-yellow-700">
                                <i class="fas fa-clipboard-check mr-2"></i> Today's Attendance
                            </a>
                            <button onclick="printSchedule()" 
                                    class="w-full p-3 bg-blue-50 hover:bg-blue-100 rounded-lg text-blue-700 text-left">
                                <i class="fas fa-print mr-2"></i> Print Schedule
                            </button>
                            <button onclick="exportSchedule()" 
                                    class="w-full p-3 bg-green-50 hover:bg-green-100 rounded-lg text-green-700 text-left">
                                <i class="fas fa-download mr-2"></i> Export to Calendar
                            </button>
                            <a href="schedule_request.php" 
                               class="block p-3 bg-purple-50 hover:bg-purple-100 rounded-lg text-purple-700">
                                <i class="fas fa-calendar-plus mr-2"></i> Request Change
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View Schedule Modal -->
    <div id="scheduleModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-800">Schedule Details</h3>
                    <button onclick="closeScheduleModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="scheduleModalContent">
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
    
    // View schedule details
    function viewScheduleDetails(scheduleId) {
        fetch(`../ajax/get_schedule_details_guru.php?id=${scheduleId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('scheduleModalContent').innerHTML = html;
                document.getElementById('scheduleModal').classList.remove('hidden');
                document.getElementById('scheduleModal').classList.add('flex');
            });
    }
    
    function closeScheduleModal() {
        document.getElementById('scheduleModal').classList.add('hidden');
        document.getElementById('scheduleModal').classList.remove('flex');
    }
    
    // Mini calendar
    function loadMiniCalendar() {
        const today = new Date();
        const month = today.toLocaleString('default', { month: 'long' });
        const year = today.getFullYear();
        
        const daysInMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0).getDate();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1).getDay();
        
        let calendarHTML = `
            <div class="mb-2">
                <p class="font-bold text-lg">${month} ${year}</p>
            </div>
            <div class="grid grid-cols-7 gap-1 text-xs">
                <div class="text-center font-medium text-gray-500">M</div>
                <div class="text-center font-medium text-gray-500">T</div>
                <div class="text-center font-medium text-gray-500">W</div>
                <div class="text-center font-medium text-gray-500">T</div>
                <div class="text-center font-medium text-gray-500">F</div>
                <div class="text-center font-medium text-gray-500">S</div>
                <div class="text-center font-medium text-gray-500">S</div>
        `;
        
        // Empty cells for first day
        for (let i = 0; i < firstDay; i++) {
            calendarHTML += '<div class="h-6"></div>';
        }
        
        // Days of month
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const hasSchedule = Math.random() > 0.7; // In production, check from database
            
            let dayClass = 'h-6 flex items-center justify-center rounded';
            if (day === today.getDate()) {
                dayClass += ' bg-yellow-500 text-white font-bold';
            } else if (hasSchedule) {
                dayClass += ' bg-yellow-100 text-yellow-700';
            } else {
                dayClass += ' hover:bg-gray-100';
            }
            
            calendarHTML += `<div class="${dayClass}">${day}</div>`;
        }
        
        calendarHTML += '</div>';
        document.getElementById('miniCalendar').innerHTML = calendarHTML;
    }
    
    function viewFullCalendar() {
        window.open('full_calendar.php', '_blank');
    }
    
    function printSchedule() {
        const params = new URLSearchParams(window.location.search);
        window.open(`print_schedule.php?${params.toString()}`, '_blank');
    }
    
    function exportSchedule() {
        window.open('export_schedule_ical.php', '_blank');
    }
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        loadMiniCalendar();
    });
    
    // Close modal when clicking outside
    document.getElementById('scheduleModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeScheduleModal();
        }
    });
    </script>
</body>
</html>