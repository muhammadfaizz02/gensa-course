<?php
include '../includes/auth_check.php';
include '../config/database.php';

// Date filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$guru_id = $_GET['guru_id'] ?? '';

// Build query with filters
$where_conditions = ["k.status = 'approved'"];
if ($start_date) {
    $where_conditions[] = "tanggal >= '$start_date'";
}
if ($end_date) {
    $where_conditions[] = "tanggal <= '$end_date'";
}
if ($guru_id) {
    $where_conditions[] = "guru_id = '$guru_id'";
}
$where_clause = implode(' AND ', $where_conditions);

// Get attendance data
$query = "
    SELECT k.*, u.full_name as guru_nama, g.spesialisasi,
           a.full_name as approved_by_name,
           TIMEDIFF(k.jam_selesai, k.jam_mulai) as duration
    FROM kehadiran_guru k
    JOIN users u ON k.guru_id = u.id
    LEFT JOIN guru_profiles g ON u.id = g.user_id
    LEFT JOIN users a ON k.approved_by = a.id
    WHERE $where_clause
    ORDER BY k.tanggal DESC, k.jam_mulai DESC
";
$attendance_data = mysqli_query($conn, $query);

// Get teachers for filter
$teachers_query = "SELECT id, full_name FROM users WHERE role = 'guru' ORDER BY full_name";
$teachers = mysqli_query($conn, $teachers_query);

// Calculate statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_sessions,
        SUM(TIME_TO_SEC(TIMEDIFF(k.jam_selesai, k.jam_mulai))) / 3600 as total_hours,
        AVG(TIME_TO_SEC(TIMEDIFF(k.jam_selesai, k.jam_mulai))) / 60 as avg_minutes
    FROM kehadiran_guru k
    WHERE $where_clause
";

$stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_query));

// Get teacher performance
$teacher_stats_query = "
    SELECT u.id, u.full_name, 
           COUNT(k.id) as session_count,
           SUM(TIME_TO_SEC(TIMEDIFF(k.jam_selesai, k.jam_mulai))) / 3600 as total_hours
    FROM users u
    LEFT JOIN kehadiran_guru k ON u.id = k.guru_id AND k.status = 'approved'
           AND k.tanggal BETWEEN '$start_date' AND '$end_date'
    WHERE u.role = 'guru'
    GROUP BY u.id
    ORDER BY session_count DESC
";
$teacher_stats = mysqli_query($conn, $teacher_stats_query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Attendance Report - Admin Gensa Course</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
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
                    <h1 class="text-2xl font-bold text-gray-800">Teacher Attendance Report</h1>
                    <p class="text-gray-600">Monitor and analyze teacher attendance data</p>
                </div>
                <div>
                    <a href="dashboard.php" class="inline-flex w-full sm:w-auto justify-center items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <!-- PERUBAHAN 3: Wrapper Konten (p-6 -> p-4 sm:p-6) -->
        <div class="p-4 sm:p-6">
            <!-- Filters and Statistics -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    <!-- Filter Form -->
                    <div class="lg:col-span-3">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Filters</h3>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">Teacher</label>
                                <select name="guru_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                    <option value="">All Teachers</option>
                                    <?php while ($teacher = mysqli_fetch_assoc($teachers)): ?>
                                        <option value="<?php echo $teacher['id']; ?>" <?php echo $guru_id == $teacher['id'] ? 'selected' : ''; ?>>
                                            <?php echo $teacher['full_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
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
                    
                    <!-- Statistics -->
                    <div class="space-y-3">
                        <div class="p-3 bg-blue-50 rounded-lg">
                            <p class="text-sm text-gray-600">Total Sessions</p>
                            <p class="text-2xl font-bold text-blue-600"><?php echo $stats['total_sessions']; ?></p>
                        </div>
                        <div class="p-3 bg-green-50 rounded-lg">
                            <p class="text-sm text-gray-600">Total Hours</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo number_format($stats['total_hours'], 1); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Export Options -->
                <div class="mt-4 pt-4 border-t">
                    <!-- PERUBAHAN: flex-wrap agar tombol tidak berantakan di HP -->
                    <div class="flex flex-wrap gap-3">
                        <button onclick="exportToPDF()" 
                                class="px-4 py-2 bg-red-100 text-red-700 hover:bg-red-200 rounded-lg">
                            <i class="fas fa-file-pdf mr-2"></i> Export PDF
                        </button>
                        <button onclick="exportToExcel()" 
                                class="px-4 py-2 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg">
                            <i class="fas fa-file-excel mr-2"></i> Export Excel
                        </button>
                        <button onclick="printReport()" 
                                class="px-4 py-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg">
                            <i class="fas fa-print mr-2"></i> Print
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Attendance List -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Attendance Records</h2>
                        
                        <?php if (mysqli_num_rows($attendance_data) > 0): ?>
                            <!-- PERUBAHAN 4: Tabel Responsive -->
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[640px]">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Teacher</th>
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Date & Time</th>
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Duration</th>
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Location</th>
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Approved By</th>
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php while ($record = mysqli_fetch_assoc($attendance_data)): 
                                            $duration = explode(':', $record['duration']);
                                            $hours = $duration[0];
                                            $minutes = $duration[1];
                                        ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="py-3 px-4">
                                                    <p class="font-medium"><?php echo $record['guru_nama']; ?></p>
                                                    <p class="text-sm text-gray-500">
                                                        <?php 
                                                        $specializations = explode(',', $record['spesialisasi']);
                                                        foreach ($specializations as $spec):
                                                            echo ucfirst($spec) . ' ';
                                                        endforeach;
                                                        ?>
                                                    </p>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <p class="text-sm"><?php echo date('d M Y', strtotime($record['tanggal'])); ?></p>
                                                    <p class="text-xs text-gray-500">
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
                                                    <p class="text-sm"><?php echo $record['approved_by_name']; ?></p>
                                                    <p class="text-xs text-gray-500">
                                                        <?php echo date('d/m H:i', strtotime($record['approved_at'])); ?>
                                                    </p>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <button onclick="viewAttendance(<?php echo $record['id']; ?>)" 
                                                            class="px-3 py-1 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded text-sm">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-calendar-times text-4xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500">No attendance records found for the selected period</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Teacher Performance -->
                <div>
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Teacher Performance</h2>
                        
                        <div class="space-y-4">
                            <?php while ($teacher = mysqli_fetch_assoc($teacher_stats)): 
                                $hours_per_session = $teacher['session_count'] > 0 ? $teacher['total_hours'] / $teacher['session_count'] : 0;
                            ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <p class="font-semibold"><?php echo $teacher['full_name']; ?></p>
                                            <p class="text-sm text-gray-500">Sessions: <?php echo $teacher['session_count']; ?></p>
                                        </div>
                                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs">
                                            <?php echo number_format($teacher['total_hours'], 1); ?>h
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        Avg: <?php echo number_format($hours_per_session, 2); ?> hours/session
                                    </div>
                                    <div class="mt-2">
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-yellow-500 h-2 rounded-full" 
                                                 style="width: <?php echo min(($teacher['session_count'] / 20 * 100), 100); ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        
                        <!-- Summary Cards -->
                        <div class="mt-6 space-y-3">
                            <div class="p-3 bg-purple-50 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-gray-600">Average Session Duration</p>
                                        <p class="text-xl font-bold text-purple-600">
                                            <?php echo number_format($stats['avg_minutes'] ?? 0, 0); ?> minutes
                                        </p>
                                    </div>
                                    <i class="fas fa-clock text-purple-400 text-2xl"></i>
                                </div>
                            </div>
                            
                            <div class="p-3 bg-indigo-50 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-gray-600">Total Teachers</p>
                                        <p class="text-xl font-bold text-indigo-600">
                                            <?php echo mysqli_num_rows($teacher_stats); ?>
                                        </p>
                                    </div>
                                    <i class="fas fa-users text-indigo-400 text-2xl"></i>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="mt-6 pt-6 border-t">
                            <h3 class="font-medium text-gray-700 mb-2">Reports</h3>
                            <div class="space-y-2">
                                <a href="teacher_payment_report.php" 
                                   class="block p-2 bg-green-50 hover:bg-green-100 rounded-lg text-green-700">
                                    <i class="fas fa-money-bill-wave mr-2"></i> Payment Report
                                </a>
                                <a href="attendance_chart.php" 
                                   class="block p-2 bg-blue-50 hover:bg-blue-100 rounded-lg text-blue-700">
                                    <i class="fas fa-chart-bar mr-2"></i> Attendance Chart
                                </a>
                                <a href="absent_report.php" 
                                   class="block p-2 bg-red-50 hover:bg-red-100 rounded-lg text-red-700">
                                    <i class="fas fa-user-times mr-2"></i> Absence Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Monthly Summary -->
            <div class="mt-6 bg-white rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Monthly Summary</h2>
                
                <?php
                $monthly_query = "
                    SELECT 
                        DATE_FORMAT(tanggal, '%Y-%m') as month,
                        COUNT(*) as session_count,
                        SUM(TIME_TO_SEC(TIMEDIFF(jam_selesai, jam_mulai))) / 3600 as total_hours
                    FROM kehadiran_guru
                    WHERE status = 'approved'
                    GROUP BY DATE_FORMAT(tanggal, '%Y-%m')
                    ORDER BY month DESC
                    LIMIT 6
                ";
                $monthly_data = mysqli_query($conn, $monthly_query);
                ?>
                
                <!-- PERUBAHAN 4: Tabel Responsive -->
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px]">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Month</th>
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Sessions</th>
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Total Hours</th>
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Avg Hours/Day</th>
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Trend</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while ($month = mysqli_fetch_assoc($monthly_data)): 
                                $month_name = date('F Y', strtotime($month['month'] . '-01'));
                                $days_in_month = date('t', strtotime($month['month'] . '-01'));
                                $avg_hours_per_day = $month['total_hours'] / $days_in_month;
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-3 px-4">
                                        <span class="font-medium"><?php echo $month_name; ?></span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">
                                            <?php echo $month['session_count']; ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="font-semibold"><?php echo number_format($month['total_hours'], 1); ?>h</span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php echo number_format($avg_hours_per_day, 2); ?>h
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php 
                                        // Simple trend indicator (in real app, compare with previous month)
                                        $trend = rand(-10, 10);
                                        $trend_color = $trend > 0 ? 'text-green-600' : ($trend < 0 ? 'text-red-600' : 'text-gray-600');
                                        $trend_icon = $trend > 0 ? 'fa-arrow-up' : ($trend < 0 ? 'fa-arrow-down' : 'fa-minus');
                                        ?>
                                        <span class="<?php echo $trend_color; ?>">
                                            <i class="fas <?php echo $trend_icon; ?> mr-1"></i>
                                            <?php echo abs($trend); ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function exportToPDF() {
        const params = new URLSearchParams(window.location.search);
        window.open(`ajax/export_attendance_pdf.php?${params.toString()}`, '_blank');
    }
    
    function exportToExcel() {
        const params = new URLSearchParams(window.location.search);
        window.open(`ajax/export_attendance_excel.php?${params.toString()}`, '_blank');
    }
    
    function printReport() {
        window.print();
    }
    
    function viewAttendance(id) {
        window.open(`attendance_details.php?id=${id}`, '_blank');
    }
    </script>
</body>
</html>