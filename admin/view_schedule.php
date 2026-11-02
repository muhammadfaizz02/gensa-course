<?php
include '../includes/auth_check.php';
include '../config/database.php';

// Handle delete schedule
if (isset($_GET['delete'])) {
    $schedule_id = mysqli_real_escape_string($conn, $_GET['delete']);
    $query = "DELETE FROM jadwal WHERE id = $schedule_id";
    mysqli_query($conn, $query);
    header('Location: view_schedule.php?success=deleted');
    exit();
}

// Handle mark as complete
if (isset($_GET['complete'])) {
    $schedule_id = mysqli_real_escape_string($conn, $_GET['complete']);
    $query = "UPDATE jadwal SET status = 'completed' WHERE id = $schedule_id";
    mysqli_query($conn, $query);
    header('Location: view_schedule.php?success=completed');
    exit();
}

// Handle edit (redirect to edit page)
if (isset($_GET['edit'])) {
    $_SESSION['edit_schedule_id'] = $_GET['edit'];
    header('Location: edit_schedule.php');
    exit();
}

// Get filters
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d', strtotime('+7 days'));
$status = $_GET['status'] ?? '';
$kelas_id = $_GET['kelas_id'] ?? '';
$guru_id = $_GET['guru_id'] ?? '';

// Build query with filters
$where_conditions = ["1=1"];
if ($start_date) {
    $where_conditions[] = "j.tanggal >= '$start_date'";
}
if ($end_date) {
    $where_conditions[] = "j.tanggal <= '$end_date'";
}
if ($status) {
    $where_conditions[] = "j.status = '$status'";
}
if ($kelas_id) {
    $where_conditions[] = "j.kelas_id = '$kelas_id'";
}
if ($guru_id) {
    $where_conditions[] = "j.guru_id = '$guru_id'";
}
$where_clause = implode(' AND ', $where_conditions);

// Get schedules
$query = "
    SELECT j.*, u.full_name as guru_nama, 
           mk.nama_kelas, ms.nama_subkelas,
           COUNT(DISTINCT js.id) as total_students,
           COUNT(DISTINCT CASE WHEN js.status_kehadiran = 'hadir' THEN js.id END) as attended_students
    FROM jadwal j
    LEFT JOIN users u ON j.guru_id = u.id
    LEFT JOIN master_kelas mk ON j.kelas_id = mk.id
    LEFT JOIN master_subkelas ms ON j.subkelas_id = ms.id
    LEFT JOIN jadwal_siswa js ON j.id = js.jadwal_id
    WHERE $where_clause
    GROUP BY j.id
    ORDER BY j.tanggal, j.jam_mulai
";
$schedules = mysqli_query($conn, $query);

// Get data for filters
$kelas_filter = mysqli_query($conn, "SELECT * FROM master_kelas WHERE status = 'active'");
$guru_filter = mysqli_query($conn, "SELECT id, full_name FROM users WHERE role = 'guru'");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Schedule - Admin Gensa Course</title>
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
                    <h1 class="text-2xl font-bold text-gray-800">View & Manage Schedules</h1>
                    <p class="text-gray-600">View, edit, and manage teaching schedules</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                    <a href="manage_schedule.php" 
                       class="inline-flex w-full sm:w-auto justify-center items-center px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg">
                        <i class="fas fa-plus mr-2"></i> Add Schedule
                    </a>
                    <a href="dashboard.php" 
                       class="inline-flex w-full sm:w-auto justify-center items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                </div>
            </div>
        </div>
        
        <!-- PERUBAHAN 3: Wrapper Konten (p-6 -> p-4 sm:p-6) -->
        <div class="p-4 sm:p-6">
            <?php if (isset($_GET['success'])): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span>Schedule successfully <?php echo $_GET['success']; ?>!</span>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Filter Schedules</h2>
                
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                        <select name="kelas_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <option value="">All Classes</option>
                            <?php while ($kelas = mysqli_fetch_assoc($kelas_filter)): ?>
                                <option value="<?php echo $kelas['id']; ?>" <?php echo $kelas_id == $kelas['id'] ? 'selected' : ''; ?>>
                                    <?php echo $kelas['jenjang']; ?> - <?php echo $kelas['nama_kelas']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Teacher</label>
                        <select name="guru_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <option value="">All Teachers</option>
                            <?php while ($guru = mysqli_fetch_assoc($guru_filter)): ?>
                                <option value="<?php echo $guru['id']; ?>" <?php echo $guru_id == $guru['id'] ? 'selected' : ''; ?>>
                                    <?php echo $guru['full_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <!-- PERUBAHAN: Tombol filter responsive -->
                    <div class="md:col-span-5 flex flex-col sm:flex-row gap-3">
                        <button type="submit" 
                                class="w-full sm:w-auto justify-center px-6 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg">
                            <i class="fas fa-filter mr-2"></i> Apply Filters
                        </button>
                        <a href="view_schedule.php" 
                           class="w-full sm:w-auto justify-center px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg">
                            <i class="fas fa-redo mr-2"></i> Reset
                        </a>
                        <button type="button" onclick="exportSchedule()" 
                                class="w-full sm:w-auto justify-center px-6 py-2 bg-green-100 hover:bg-green-200 text-green-700 rounded-lg">
                            <i class="fas fa-download mr-2"></i> Export
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Schedule List -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">Schedule List</h2>
                    <div class="text-sm text-gray-600">
                        Showing <?php echo mysqli_num_rows($schedules); ?> schedules
                    </div>
                </div>
                
                <?php if (mysqli_num_rows($schedules) > 0): ?>
                    <!-- PERUBAHAN 4: Tabel Responsive -->
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px]">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Date & Time</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Class & Teacher</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Subject Details</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Students</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Status</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($schedule = mysqli_fetch_assoc($schedules)): 
                                    $attendance_rate = $schedule['total_students'] > 0 ? 
                                        ($schedule['attended_students'] / $schedule['total_students'] * 100) : 0;
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-4 px-4">
                                            <p class="font-semibold">
                                                <?php echo date('d M Y', strtotime($schedule['tanggal'])); ?>
                                            </p>
                                            <p class="text-sm text-gray-600">
                                                <?php echo date('H:i', strtotime($schedule['jam_mulai'])); ?> - 
                                                <?php echo date('H:i', strtotime($schedule['jam_selesai'])); ?>
                                            </p>
                                            <?php if ($schedule['tanggal'] == date('Y-m-d')): ?>
                                                <span class="inline-block mt-1 px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs">
                                                    Today
                                                </span>
                                            <?php elseif ($schedule['tanggal'] < date('Y-m-d')): ?>
                                                <span class="inline-block mt-1 px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">
                                                    Past
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-4">
                                            <p class="font-medium"><?php echo $schedule['nama_kelas']; ?> - <?php echo $schedule['nama_subkelas']; ?></p>
                                            <p class="text-sm text-gray-600"><?php echo $schedule['guru_nama']; ?></p>
                                            <p class="text-xs text-gray-500">
                                                Created: <?php echo date('d/m H:i', strtotime($schedule['created_at'])); ?>
                                            </p>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="flex space-x-2 mb-1">
                                                <span class="px-2 py-1 <?php echo $schedule['bahasa'] == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?> rounded-full text-xs">
                                                    <?php echo ucfirst($schedule['bahasa']); ?>
                                                </span>
                                                <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs">
                                                    <?php echo ucfirst($schedule['jenis_pelajaran']); ?>
                                                </span>
                                            </div>
                                            <?php if ($schedule['deskripsi']): ?>
                                                <p class="text-sm text-gray-600 truncate max-w-xs" title="<?php echo htmlspecialchars($schedule['deskripsi']); ?>">
                                                    <?php echo substr($schedule['deskripsi'], 0, 50); ?>
                                                    <?php echo strlen($schedule['deskripsi']) > 50 ? '...' : ''; ?>
                                                </p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="space-y-1">
                                                <p class="text-sm">
                                                    <span class="font-medium"><?php echo $schedule['total_students']; ?></span> students
                                                </p>
                                                <?php if ($schedule['status'] == 'completed'): ?>
                                                    <div class="flex items-center space-x-2">
                                                        <div class="w-16 bg-gray-200 rounded-full h-2">
                                                            <div class="bg-green-500 h-2 rounded-full" 
                                                                 style="width: <?php echo min($attendance_rate, 100); ?>%"></div>
                                                        </div>
                                                        <span class="text-xs text-gray-600">
                                                            <?php echo round($attendance_rate); ?>%
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <?php
                                            $status_colors = [
                                                'scheduled' => 'bg-yellow-100 text-yellow-800',
                                                'completed' => 'bg-green-100 text-green-800',
                                                'cancelled' => 'bg-red-100 text-red-800'
                                            ];
                                            ?>
                                            <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $status_colors[$schedule['status']]; ?>">
                                                <?php echo ucfirst($schedule['status']); ?>
                                            </span>
                                            <?php if ($schedule['status'] == 'scheduled' && $schedule['tanggal'] < date('Y-m-d')): ?>
                                                <p class="text-xs text-red-600 mt-1">Overdue</p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="flex space-x-1">
                                                <button onclick="viewSchedule(<?php echo $schedule['id']; ?>)" 
                                                        class="p-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <?php if ($schedule['status'] == 'scheduled'): ?>
                                                    <a href="?edit=<?php echo $schedule['id']; ?>" 
                                                       class="p-2 bg-yellow-100 text-yellow-700 hover:bg-yellow-200 rounded">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?complete=<?php echo $schedule['id']; ?>" 
                                                       onclick="return confirm('Mark this schedule as completed?')"
                                                       class="p-2 bg-green-100 text-green-700 hover:bg-green-200 rounded">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <a href="?delete=<?php echo $schedule['id']; ?>" 
                                                   onclick="return confirm('Delete this schedule? This action cannot be undone!')"
                                                   class="p-2 bg-red-100 text-red-700 hover:bg-red-200 rounded">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-3">
                        <div class="text-sm text-gray-600">
                            Showing all <?php echo mysqli_num_rows($schedules); ?> results
                        </div>
                        <div class="flex space-x-2">
                            <button class="px-3 py-1 bg-gray-100 text-gray-700 rounded">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="px-3 py-1 bg-yellow-500 text-white rounded">1</button>
                            <button class="px-3 py-1 bg-gray-100 text-gray-700 rounded">2</button>
                            <button class="px-3 py-1 bg-gray-100 text-gray-700 rounded">3</button>
                            <button class="px-3 py-1 bg-gray-100 text-gray-700 rounded">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-calendar-times text-5xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-700 mb-2">No Schedules Found</h3>
                        <p class="text-gray-500 mb-6">No schedules match your filters</p>
                        <a href="manage_schedule.php" 
                           class="inline-flex items-center px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg">
                            <i class="fas fa-plus mr-2"></i> Add New Schedule
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Calendar View -->
            <div class="mt-6 bg-white rounded-xl shadow-md p-6">
                <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-3">
                    <h2 class="text-xl font-bold text-gray-800">Calendar View</h2>
                    <div class="flex space-x-2">
                        <button onclick="prevWeek()" class="px-3 py-1 bg-gray-100 text-gray-700 rounded">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span class="px-4 py-1 bg-yellow-100 text-yellow-800 rounded" id="weekRange">
                            Loading...
                        </span>
                        <button onclick="nextWeek()" class="px-3 py-1 bg-gray-100 text-gray-700 rounded">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
                
                <div id="calendarContainer" class="overflow-x-auto">
                    <!-- Calendar will be loaded via JavaScript -->
                    <div class="text-center py-8">
                        <i class="fas fa-spinner fa-spin text-2xl text-yellow-500 mb-2"></i>
                        <p>Loading calendar...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View Schedule Modal -->
    <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-800">Schedule Details</h3>
                    <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="viewModalContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
    
    <script>
    let currentWeek = 0; // 0 = current week
    
    function viewSchedule(scheduleId) {
        fetch(`ajax/get_schedule_details.php?id=${scheduleId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('viewModalContent').innerHTML = html;
                document.getElementById('viewModal').classList.remove('hidden');
                document.getElementById('viewModal').classList.add('flex');
            });
    }
    
    function closeViewModal() {
        document.getElementById('viewModal').classList.add('hidden');
        document.getElementById('viewModal').classList.remove('flex');
    }
    
    function exportSchedule() {
        const params = new URLSearchParams(window.location.search);
        window.open(`ajax/export_schedule.php?${params.toString()}`, '_blank');
    }
    
    function loadCalendar(weekOffset = 0) {
        currentWeek = weekOffset;
        
        // Calculate week range
        const now = new Date();
        const start = new Date(now);
        start.setDate(now.getDate() - now.getDay() + (weekOffset * 7));
        const end = new Date(start);
        end.setDate(start.getDate() + 6);
        
        // Update week range display
        const options = { day: 'numeric', month: 'short' };
        document.getElementById('weekRange').textContent = 
            `${start.toLocaleDateString('id-ID', options)} - ${end.toLocaleDateString('id-ID', options)} ${end.getFullYear()}`;
        
        // Load calendar data
        fetch(`ajax/get_calendar.php?start_date=${start.toISOString().split('T')[0]}&end_date=${end.toISOString().split('T')[0]}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('calendarContainer').innerHTML = html;
            });
    }
    
    function prevWeek() {
        loadCalendar(currentWeek - 1);
    }
    
    function nextWeek() {
        loadCalendar(currentWeek + 1);
    }
    
    // Initialize calendar
    document.addEventListener('DOMContentLoaded', function() {
        loadCalendar();
        
        // Auto-refresh every 30 seconds if on today's view
        if (currentWeek === 0) {
            setInterval(() => {
                if (!document.getElementById('viewModal').classList.contains('flex')) {
                    loadCalendar(0);
                }
            }, 30000);
        }
    });
    </script>
</body>
</html>