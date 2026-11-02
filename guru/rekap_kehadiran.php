<?php
include '../includes/auth_check_guru.php';
include '../config/database.php';

// Get filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$status = $_GET['status'] ?? '';

// Build query with filters
$where_conditions = ["guru_id = $user_id"];
if ($start_date) {
    $where_conditions[] = "tanggal >= '$start_date'";
}
if ($end_date) {
    $where_conditions[] = "tanggal <= '$end_date'";
}
if ($status) {
    $where_conditions[] = "status = '$status'";
}
$where_clause = implode(' AND ', $where_conditions);

// Get attendance data
$query = "
    SELECT * FROM kehadiran_guru 
    WHERE $where_clause
    ORDER BY tanggal DESC
";
$attendance_data = mysqli_query($conn, $query);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_records,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
        SUM(TIME_TO_SEC(TIMEDIFF(jam_selesai, jam_mulai))) / 3600 as total_hours,
        AVG(TIME_TO_SEC(TIMEDIFF(jam_selesai, jam_mulai))) / 60 as avg_minutes
    FROM kehadiran_guru 
    WHERE $where_clause
";
$stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_query));

// Get monthly data for chart
$monthly_query = "
    SELECT 
        DATE_FORMAT(tanggal, '%Y-%m') as month,
        COUNT(*) as session_count,
        SUM(TIME_TO_SEC(TIMEDIFF(jam_selesai, jam_mulai))) / 3600 as total_hours
    FROM kehadiran_guru
    WHERE guru_id = $user_id AND status = 'approved'
    GROUP BY DATE_FORMAT(tanggal, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
";
$monthly_data = mysqli_query($conn, $monthly_query);

// Get daily attendance for current week
$weekly_query = "
    SELECT 
        DAYNAME(tanggal) as day_name,
        COUNT(*) as session_count
    FROM kehadiran_guru
    WHERE guru_id = $user_id 
    AND status = 'approved'
    AND tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DAYNAME(tanggal), tanggal
    ORDER BY tanggal
";
$weekly_data = mysqli_query($conn, $weekly_query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report - Teacher Gensa Course</title>
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
                <a href="jadwal.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Teaching Schedule</span>
                </a>
                <a href="rekap_kehadiran.php" class="flex items-center space-x-3 p-3 bg-white bg-opacity-20 rounded-lg">
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
                    <h1 class="text-2xl font-bold text-gray-800">Attendance Report</h1>
                    <p class="text-gray-600">View and analyze your teaching attendance</p>
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
                        <p class="text-sm text-gray-600">Total Sessions</p>
                        <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['total_records']; ?></p>
                    </div>
                    <div class="bg-white rounded-xl shadow p-4">
                        <p class="text-sm text-gray-600">Approved</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo $stats['approved_count']; ?></p>
                    </div>
                    <div class="bg-white rounded-xl shadow p-4">
                        <p class="text-sm text-gray-600">Pending</p>
                        <p class="text-2xl font-bold text-blue-600"><?php echo $stats['pending_count']; ?></p>
                    </div>
                    <div class="bg-white rounded-xl shadow p-4">
                        <p class="text-sm text-gray-600">Total Hours</p>
                        <p class="text-2xl font-bold text-purple-600"><?php echo number_format($stats['total_hours'] ?? 0, 1); ?></p>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="lg:col-span-3">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Filter Report</h3>
                        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
                                    <option value="approved" <?php echo $status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
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
                <!-- Attendance Chart -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6">Monthly Attendance Trend</h2>
                        <div class="h-64">
                            <canvas id="attendanceChart"></canvas>
                        </div>
                        
                        <div class="mt-4 grid grid-cols-3 sm:grid-cols-6 gap-2">
                            <?php 
                            $months = [];
                            $session_counts = [];
                            $hour_counts = [];
                            
                            mysqli_data_seek($monthly_data, 0);
                            while ($month = mysqli_fetch_assoc($monthly_data)) {
                                $months[] = date('M Y', strtotime($month['month'] . '-01'));
                                $session_counts[] = $month['session_count'];
                                $hour_counts[] = $month['total_hours'];
                            }
                            
                            // Reverse for chronological order
                            $months = array_reverse($months);
                            $session_counts = array_reverse($session_counts);
                            $hour_counts = array_reverse($hour_counts);
                            
                            foreach ($months as $index => $month):
                            ?>
                                <div class="text-center">
                                    <p class="text-xs text-gray-500"><?php echo $month; ?></p>
                                    <p class="text-lg font-bold text-yellow-600"><?php echo $session_counts[$index]; ?></p>
                                    <p class="text-xs text-gray-400"><?php echo number_format($hour_counts[$index], 1); ?>h</p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Summary Cards -->
                <div>
                    <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Performance Summary</h2>
                        
                        <div class="space-y-4">
                            <div class="p-3 bg-green-50 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-gray-600">Approval Rate</p>
                                        <p class="text-2xl font-bold text-green-600">
                                            <?php 
                                            $approval_rate = $stats['total_records'] > 0 ? 
                                                ($stats['approved_count'] / $stats['total_records'] * 100) : 0;
                                            echo round($approval_rate, 1); 
                                            ?>%
                                        </p>
                                    </div>
                                    <i class="fas fa-check-circle text-green-400 text-2xl"></i>
                                </div>
                            </div>
                            
                            <div class="p-3 bg-blue-50 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-gray-600">Average Session</p>
                                        <p class="text-2xl font-bold text-blue-600">
                                            <?php echo round($stats['avg_minutes'] ?? 0, 0); ?> min
                                        </p>
                                    </div>
                                    <i class="fas fa-clock text-blue-400 text-2xl"></i>
                                </div>
                            </div>
                            
                            <div class="p-3 bg-yellow-50 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-gray-600">Efficiency Score</p>
                                        <p class="text-2xl font-bold text-yellow-600">
                                            <?php echo round(($stats['total_hours'] ?? 0) * 10 + ($approval_rate / 10), 0); ?>
                                        </p>
                                    </div>
                                    <i class="fas fa-chart-line text-yellow-400 text-2xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Export -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Export Report</h2>
                        
                        <div class="space-y-3">
                            <button onclick="exportToPDF()" 
                                    class="w-full py-2 bg-red-50 text-red-700 hover:bg-red-100 rounded-lg">
                                <i class="fas fa-file-pdf mr-2"></i> Export as PDF
                            </button>
                            <button onclick="exportToExcel()" 
                                    class="w-full py-2 bg-green-50 text-green-700 hover:bg-green-100 rounded-lg">
                                <i class="fas fa-file-excel mr-2"></i> Export as Excel
                            </button>
                            <button onclick="printReport()" 
                                    class="w-full py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg">
                                <i class="fas fa-print mr-2"></i> Print Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Attendance List -->
            <div class="mt-6 bg-white rounded-xl shadow-md p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">Attendance Records</h2>
                    <div class="text-sm text-gray-600">
                        Showing <?php echo mysqli_num_rows($attendance_data); ?> records
                    </div>
                </div>
                
                <?php if (mysqli_num_rows($attendance_data) > 0): ?>
                    <!-- PERUBAHAN 4: Tabel Responsive -->
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px]">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Date</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Time</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Duration</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Location</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Status</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($record = mysqli_fetch_assoc($attendance_data)): 
                                    $duration = explode(':', $record['jam_selesai'] . ':' . $record['jam_mulai']);
                                    $hours = abs($duration[0] - $duration[2]);
                                    $minutes = abs($duration[1] - $duration[3]);
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-3 px-4">
                                            <p class="font-medium"><?php echo date('d M Y', strtotime($record['tanggal'])); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo date('l', strtotime($record['tanggal'])); ?></p>
                                        </td>
                                        <td class="py-3 px-4">
                                            <p class="text-sm">
                                                <?php echo date('H:i', strtotime($record['jam_mulai'])); ?> - 
                                                <?php echo date('H:i', strtotime($record['jam_selesai'])); ?>
                                            </p>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-sm">
                                                <?php echo $hours; ?>h <?php echo $minutes; ?>m
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <p class="text-sm truncate max-w-xs"><?php echo $record['lokasi']; ?></p>
                                        </td>
                                        <td class="py-3 px-4">
                                            <?php
                                            $status_colors = [
                                                'approved' => 'bg-green-100 text-green-800',
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'rejected' => 'bg-red-100 text-red-800'
                                            ];
                                            ?>
                                            <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $status_colors[$record['status']]; ?>">
                                                <?php echo ucfirst($record['status']); ?>
                                            </span>
                                            <?php if ($record['approved_at']): ?>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    <?php echo date('d/m H:i', strtotime($record['approved_at'])); ?>
                                                </p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-4">
                                            <button onclick="viewAttendanceDetails(<?php echo $record['id']; ?>)" 
                                                    class="px-3 py-1 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded text-sm">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($record['status'] == 'pending'): ?>
                                                <button onclick="editAttendance(<?php echo $record['id']; ?>)" 
                                                        class="ml-1 px-3 py-1 bg-yellow-100 text-yellow-700 hover:bg-yellow-200 rounded text-sm">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-clipboard-list text-5xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-700 mb-2">No Attendance Records</h3>
                        <p class="text-gray-500 mb-6">No attendance records found for the selected period</p>
                        <a href="attendance.php" 
                           class="inline-flex items-center px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg">
                            <i class="fas fa-calendar-check mr-2"></i> Mark Attendance
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Weekly Summary -->
            <div class="mt-6 bg-white rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Weekly Summary</h2>
                
                <?php
                $week_days = ['Monday' => 0, 'Tuesday' => 0, 'Wednesday' => 0, 
                             'Thursday' => 0, 'Friday' => 0, 'Saturday' => 0, 'Sunday' => 0];
                
                mysqli_data_seek($weekly_data, 0);
                while ($day = mysqli_fetch_assoc($weekly_data)) {
                    $week_days[$day['day_name']] = $day['session_count'];
                }
                ?>
                
                <div class="grid grid-cols-7 gap-2">
                    <?php foreach ($week_days as $day => $count): ?>
                        <div class="text-center">
                            <p class="text-xs text-gray-500"><?php echo substr($day, 0, 3); ?></p>
                            <div class="mt-2 relative">
                                <div class="w-8 h-24 bg-gray-200 rounded mx-auto relative">
                                    <?php if ($count > 0): 
                                        $height = min($count * 20, 100);
                                    ?>
                                        <div class="absolute bottom-0 left-0 right-0 bg-yellow-500 rounded" 
                                             style="height: <?php echo $height; ?>%"></div>
                                    <?php endif; ?>
                                </div>
                                <p class="mt-1 text-sm font-bold <?php echo $count > 0 ? 'text-yellow-600' : 'text-gray-400'; ?>">
                                    <?php echo $count; ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View Details Modal -->
    <div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-800">Attendance Details</h3>
                    <button onclick="closeDetailsModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="detailsModalContent">
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
    
    // Attendance Chart
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    const attendanceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [
                {
                    label: 'Sessions',
                    data: <?php echo json_encode($session_counts); ?>,
                    borderColor: 'rgb(251, 177, 23)',
                    backgroundColor: 'rgba(251, 177, 23, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Hours',
                    data: <?php echo json_encode($hour_counts); ?>,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
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
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            }
        }
    });
    
    function viewAttendanceDetails(attendanceId) {
    window.location.href = `attendance_details_guru.php?id=${attendanceId}`;
}
    
    function closeDetailsModal() {
        document.getElementById('detailsModal').classList.add('hidden');
        document.getElementById('detailsModal').classList.remove('flex');
    }
    
    function editAttendance(attendanceId) {
        // In production, this would open an edit form
        alert('Edit feature coming soon!');
    }
    
    function exportToPDF() {
        const params = new URLSearchParams(window.location.search);
        window.open(`export_report_pdf.php?${params.toString()}`, '_blank');
    }
    
    function exportToExcel() {
        const params = new URLSearchParams(window.location.search);
        window.open(`export_report_excel.php?${params.toString()}`, '_blank');
    }
    
    function printReport() {
        window.print();
    }
    
    // Close modal when clicking outside
    document.getElementById('detailsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDetailsModal();
        }
    });
    </script>
</body>
</html>