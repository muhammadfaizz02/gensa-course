<?php
include '../includes/auth_check_guru.php';
include '../config/database.php';

// Get today's schedules for this teacher
$today = date('Y-m-d');
$schedules_query = "
    SELECT j.*, mk.nama_kelas, ms.nama_subkelas
    FROM jadwal j
    LEFT JOIN master_kelas mk ON j.kelas_id = mk.id
    LEFT JOIN master_subkelas ms ON j.subkelas_id = ms.id
    WHERE j.guru_id = $user_id 
    AND j.tanggal = '$today'
    AND j.status = 'scheduled'
    ORDER BY j.jam_mulai
";
$schedules = mysqli_query($conn, $schedules_query);

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $jadwal_id = mysqli_real_escape_string($conn, $_POST['jadwal_id']);
    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $attendances = $_POST['attendance'];
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        foreach ($attendances as $pelajar_id => $status) {
            $pelajar_id = mysqli_real_escape_string($conn, $pelajar_id);
            $status = mysqli_real_escape_string($conn, $status);
            
            // Check if attendance already exists
            $check_query = "
                SELECT id FROM absensi_siswa 
                WHERE jadwal_id = '$jadwal_id' 
                AND pelajar_id = '$pelajar_id'
                AND tanggal = '$tanggal'
            ";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                // Update existing attendance
                $update_query = "
                    UPDATE absensi_siswa 
                    SET status = '$status', updated_by = '$user_id'
                    WHERE jadwal_id = '$jadwal_id' 
                    AND pelajar_id = '$pelajar_id'
                    AND tanggal = '$tanggal'
                ";
            } else {
                // Insert new attendance
                $update_query = "
                    INSERT INTO absensi_siswa 
                    (jadwal_id, pelajar_id, tanggal, status, updated_by)
                    VALUES ('$jadwal_id', '$pelajar_id', '$tanggal', '$status', '$user_id')
                ";
            }
            
            mysqli_query($conn, $update_query);
        }
        
        mysqli_commit($conn);
        $success = "Student attendance saved successfully!";
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = "Failed to save attendance: " . $e->getMessage();
    }
}

// Get selected schedule details
$selected_schedule_id = $_GET['jadwal_id'] ?? ($_POST['jadwal_id'] ?? 0);
$selected_schedule = null;
$students = [];

if ($selected_schedule_id) {
    // Get schedule details
    $schedule_query = "
        SELECT j.*, mk.nama_kelas, ms.nama_subkelas
        FROM jadwal j
        LEFT JOIN master_kelas mk ON j.kelas_id = mk.id
        LEFT JOIN master_subkelas ms ON j.subkelas_id = ms.id
        WHERE j.id = $selected_schedule_id
        AND j.guru_id = $user_id
    ";
    $schedule_result = mysqli_query($conn, $schedule_query);
    $selected_schedule = mysqli_fetch_assoc($schedule_result);
    
    if ($selected_schedule) {
        // Get students for this schedule
        $students_query = "
            SELECT u.id, u.full_name, js.status_kehadiran,
                   a.status as attendance_status
            FROM jadwal_siswa js
            JOIN users u ON js.pelajar_id = u.id
            LEFT JOIN absensi_siswa a ON js.pelajar_id = a.pelajar_id 
                AND a.jadwal_id = $selected_schedule_id 
                AND a.tanggal = '$today'
            WHERE js.jadwal_id = $selected_schedule_id
            ORDER BY u.full_name
        ";
        $students = mysqli_query($conn, $students_query);
    }
}

// Get today's attendance summary
$summary_query = "
    SELECT 
        COUNT(DISTINCT a.pelajar_id) as total_attended,
        COUNT(DISTINCT js.pelajar_id) as total_students
    FROM jadwal j
    LEFT JOIN jadwal_siswa js ON j.id = js.jadwal_id
    LEFT JOIN absensi_siswa a ON j.id = a.jadwal_id 
        AND a.tanggal = '$today' 
        AND a.status = 'hadir'
    WHERE j.guru_id = $user_id 
    AND j.tanggal = '$today'
    AND j.status = 'scheduled'
";
$summary = mysqli_fetch_assoc(mysqli_query($conn, $summary_query));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Attendance - Teacher Gensa Course</title>
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
                <a href="absensi_siswa.php" class="flex items-center space-x-3 p-3 bg-white bg-opacity-20 rounded-lg">
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
                    <h1 class="text-2xl font-bold text-gray-800">Student Attendance</h1>
                    <p class="text-gray-600">Mark student attendance for today's classes</p>
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
            <?php if (isset($success)): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span><?php echo $success; ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Schedule Selection -->
                <div class="lg:col-span-3">
                    <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Today's Schedules</h2>
                        <p class="text-gray-600 mb-4">Select a schedule to mark attendance</p>
                        
                        <?php if (mysqli_num_rows($schedules) > 0): ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php while ($schedule = mysqli_fetch_assoc($schedules)): 
                                    $is_selected = $selected_schedule_id == $schedule['id'];
                                    $attendance_count = mysqli_fetch_assoc(mysqli_query($conn, "
                                        SELECT COUNT(*) as count 
                                        FROM absensi_siswa 
                                        WHERE jadwal_id = {$schedule['id']} 
                                        AND tanggal = '$today'
                                    "))['count'];
                                ?>
                                    <a href="?jadwal_id=<?php echo $schedule['id']; ?>" 
                                       class="border rounded-lg p-4 hover:shadow-md transition <?php echo $is_selected ? 'border-yellow-500 bg-yellow-50' : 'border-gray-200 hover:border-yellow-300'; ?>">
                                        <div class="flex justify-between items-start mb-2">
                                            <div>
                                                <p class="font-semibold <?php echo $is_selected ? 'text-yellow-700' : 'text-gray-800'; ?>">
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
                                        <div class="flex justify-between items-center text-sm">
                                            <span class="text-gray-500">
                                                <?php echo $attendance_count; ?> attendance marked
                                            </span>
                                            <?php if ($is_selected): ?>
                                                <span class="text-yellow-600">
                                                    <i class="fas fa-check-circle"></i> Selected
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-calendar-times text-4xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500">No schedule for today</p>
                                <p class="text-sm text-gray-400 mt-1">Check back tomorrow or view your full schedule</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($selected_schedule && mysqli_num_rows($students) > 0): ?>
                    <!-- Attendance Form -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-xl shadow-md p-6">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-800">Mark Attendance</h2>
                                    <p class="text-gray-600">
                                        <?php echo $selected_schedule['nama_kelas']; ?> - <?php echo $selected_schedule['nama_subkelas']; ?> | 
                                        <?php echo date('H:i', strtotime($selected_schedule['jam_mulai'])); ?> - <?php echo date('H:i', strtotime($selected_schedule['jam_selesai'])); ?>
                                    </p>
                                </div>
                                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">
                                    <?php echo date('l, d F Y'); ?>
                                </span>
                            </div>
                            
                            <form method="POST" action="" id="attendanceForm">
                                <input type="hidden" name="jadwal_id" value="<?php echo $selected_schedule_id; ?>">
                                <input type="hidden" name="tanggal" value="<?php echo $today; ?>">
                                
                                <!-- PERUBAHAN 4: Tabel Responsive -->
                                <div class="overflow-x-auto">
                                    <table class="w-full min-w-[640px]">
                                        <thead>
                                            <tr class="bg-gray-50">
                                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Student Name</th>
                                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Status</th>
                                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Previous</th>
                                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Note</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <?php while ($student = mysqli_fetch_assoc($students)): 
                                                $default_status = $student['attendance_status'] ?: 'hadir';
                                            ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="py-3 px-4">
                                                        <div class="flex items-center space-x-3">
                                                            <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                                                                <i class="fas fa-user text-gray-500"></i>
                                                            </div>
                                                            <div>
                                                                <p class="font-medium"><?php echo $student['full_name']; ?></p>
                                                                <p class="text-xs text-gray-500">ID: <?php echo $student['id']; ?></p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="py-3 px-4">
                                                        <select name="attendance[<?php echo $student['id']; ?>]" 
                                                                class="w-full px-3 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-yellow-500 focus:border-transparent"
                                                                onchange="updateStatusColor(this)">
                                                            <option value="hadir" <?php echo $default_status == 'hadir' ? 'selected' : ''; ?>>Present</option>
                                                            <option value="izin" <?php echo $default_status == 'izin' ? 'selected' : ''; ?>>Permission</option>
                                                            <option value="sakit" <?php echo $default_status == 'sakit' ? 'selected' : ''; ?>>Sick</option>
                                                            <option value="alfa" <?php echo $default_status == 'alfa' ? 'selected' : ''; ?>>Absent</option>
                                                        </select>
                                                    </td>
                                                    <td class="py-3 px-4">
                                                        <?php if ($student['status_kehadiran']): ?>
                                                            <span class="px-2 py-1 rounded-full text-xs <?php 
                                                                echo $student['status_kehadiran'] == 'hadir' ? 'bg-green-100 text-green-800' : 
                                                                ($student['status_kehadiran'] == 'izin' ? 'bg-yellow-100 text-yellow-800' : 
                                                                ($student['status_kehadiran'] == 'sakit' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800'));
                                                            ?>">
                                                                <?php echo ucfirst($student['status_kehadiran']); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-gray-400 text-xs">No data</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="py-3 px-4">
                                                        <input type="text" 
                                                               name="notes[<?php echo $student['id']; ?>]"
                                                               placeholder="Optional note"
                                                               class="w-full px-3 py-1 border border-gray-300 rounded text-sm">
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-6 pt-6 border-t">
                                    <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
                                        <div class="text-sm text-gray-600">
                                            Total students: <?php echo mysqli_num_rows($students); ?>
                                        </div>
                                        <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                                            <button type="button" onclick="selectAll('hadir')" 
                                                    class="w-full sm:w-auto justify-center px-4 py-2 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg">
                                                <i class="fas fa-check mr-1"></i> All Present
                                            </button>
                                            <button type="button" onclick="selectAll('alfa')" 
                                                    class="w-full sm:w-auto justify-center px-4 py-2 bg-red-100 text-red-700 hover:bg-red-200 rounded-lg">
                                                <i class="fas fa-times mr-1"></i> All Absent
                                            </button>
                                            <button type="submit" 
                                                    class="w-full sm:w-auto justify-center px-6 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg">
                                                <i class="fas fa-save mr-2"></i> Save Attendance
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Attendance Summary & Legend -->
                    <div>
                        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                            <h2 class="text-xl font-bold text-gray-800 mb-4">Attendance Summary</h2>
                            
                            <div class="space-y-4">
                                <div class="p-3 bg-blue-50 rounded-lg">
                                    <p class="text-sm text-gray-600">Today's Attendance Rate</p>
                                    <p class="text-2xl font-bold text-blue-600">
                                        <?php 
                                        $rate = $summary['total_students'] > 0 ? 
                                            ($summary['total_attended'] / $summary['total_students'] * 100) : 0;
                                        echo round($rate, 1); 
                                        ?>%
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <?php echo $summary['total_attended']; ?> of <?php echo $summary['total_students']; ?> students
                                    </p>
                                </div>
                                
                                <div class="p-3 bg-green-50 rounded-lg">
                                    <p class="text-sm text-gray-600">Current Class</p>
                                    <p class="text-lg font-bold text-green-700">
                                        <?php echo mysqli_num_rows($students); ?> students
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <?php echo $selected_schedule['nama_kelas']; ?> - <?php echo $selected_schedule['nama_subkelas']; ?>
                                    </p>
                                </div>
                                
                                <div class="p-3 bg-yellow-50 rounded-lg">
                                    <p class="text-sm text-gray-600">Schedule Time</p>
                                    <p class="text-lg font-bold text-yellow-700">
                                        <?php echo date('H:i', strtotime($selected_schedule['jam_mulai'])); ?> - 
                                        <?php echo date('H:i', strtotime($selected_schedule['jam_selesai'])); ?>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <?php echo ucfirst($selected_schedule['jenis_pelajaran']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-md p-6">
                            <h2 class="text-xl font-bold text-gray-800 mb-4">Attendance Legend</h2>
                            
                            <div class="space-y-2">
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-green-100 border border-green-500 rounded mr-2"></div>
                                    <span class="text-sm text-gray-700">Present (Hadir)</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-yellow-100 border border-yellow-500 rounded mr-2"></div>
                                    <span class="text-sm text-gray-700">Permission (Izin)</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-blue-100 border border-blue-500 rounded mr-2"></div>
                                    <span class="text-sm text-gray-700">Sick (Sakit)</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-red-100 border border-red-500 rounded mr-2"></div>
                                    <span class="text-sm text-gray-700">Absent (Alfa)</span>
                                </div>
                            </div>
                            
                            <div class="mt-6 pt-6 border-t">
                                <h3 class="font-medium text-gray-700 mb-2">Quick Actions</h3>
                                <div class="space-y-2">
                                    <button onclick="printAttendance()" 
                                            class="w-full py-2 bg-gray-50 text-gray-700 hover:bg-gray-100 rounded">
                                        <i class="fas fa-print mr-2"></i> Print Attendance List
                                    </button>
                                    <button onclick="exportAttendance()" 
                                            class="w-full py-2 bg-gray-50 text-gray-700 hover:bg-gray-100 rounded">
                                        <i class="fas fa-download mr-2"></i> Export to Excel
                                    </button>
                                    <a href="attendance_history.php?jadwal_id=<?php echo $selected_schedule_id; ?>" 
                                       class="block w-full py-2 bg-gray-50 text-gray-700 hover:bg-gray-100 rounded text-center">
                                        <i class="fas fa-history mr-2"></i> View History
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($selected_schedule_id && mysqli_num_rows($students) == 0): ?>
                    <div class="lg:col-span-3">
                        <div class="bg-white rounded-xl shadow-md p-6 text-center">
                            <i class="fas fa-users-slash text-4xl text-gray-300 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-700 mb-2">No Students Found</h3>
                            <p class="text-gray-500">This schedule has no students assigned yet.</p>
                            <p class="text-sm text-gray-400 mt-1">Please contact administrator</p>
                        </div>
                    </div>
                <?php elseif (!$selected_schedule_id): ?>
                    <div class="lg:col-span-3">
                        <div class="bg-white rounded-xl shadow-md p-6 text-center">
                            <i class="fas fa-calendar-check text-4xl text-gray-300 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-700 mb-2">Select a Schedule</h3>
                            <p class="text-gray-500">Choose a schedule from above to mark attendance</p>
                        </div>
                    </div>
                <?php endif; ?>
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
    
    // Update select background color based on status
    function updateStatusColor(select) {
        const colors = {
            'hadir': 'bg-green-50 border-green-300',
            'izin': 'bg-yellow-50 border-yellow-300',
            'sakit': 'bg-blue-50 border-blue-300',
            'alfa': 'bg-red-50 border-red-300'
        };
        
        // Remove all color classes
        select.classList.remove('bg-green-50', 'border-green-300', 'bg-yellow-50', 'border-yellow-300', 
                               'bg-blue-50', 'border-blue-300', 'bg-red-50', 'border-red-300');
        
        // Add new color class
        if (colors[select.value]) {
            const colorClasses = colors[select.value].split(' ');
            colorClasses.forEach(cls => select.classList.add(cls));
        }
    }
    
    // Initialize status colors
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('select[name^="attendance"]').forEach(select => {
            updateStatusColor(select);
        });
    });
    
    // Select all students with same status
    function selectAll(status) {
        document.querySelectorAll('select[name^="attendance"]').forEach(select => {
            select.value = status;
            updateStatusColor(select);
        });
    }
    
    // Print attendance list
    function printAttendance() {
        if (!<?php echo $selected_schedule_id ? 'true' : 'false'; ?>) {
            alert('Please select a schedule first');
            return;
        }
        
        window.open(`print_attendance.php?jadwal_id=<?php echo $selected_schedule_id; ?>&tanggal=<?php echo $today; ?>`, '_blank');
    }
    
    // Export to Excel
    function exportAttendance() {
        if (!<?php echo $selected_schedule_id ? 'true' : 'false'; ?>) {
            alert('Please select a schedule first');
            return;
        }
        
        window.open(`export_attendance.php?jadwal_id=<?php echo $selected_schedule_id; ?>&tanggal=<?php echo $today; ?>`, '_blank');
    }
    
    // Auto-save functionality (optional)
    let autoSaveTimer;
    
    function startAutoSave() {
        autoSaveTimer = setInterval(() => {
            const form = document.getElementById('attendanceForm');
            const formData = new FormData(form);
            
            // Show saving indicator
            const saveBtn = form.querySelector('button[type="submit"]');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
            saveBtn.disabled = true;
            
            // Simulate save (in production, use AJAX)
            setTimeout(() => {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
                console.log('Auto-saved attendance data');
            }, 1000);
        }, 30000); // Auto-save every 30 seconds
    }
    
    // Start auto-save when form has changes
    document.getElementById('attendanceForm')?.addEventListener('change', function() {
        if (!autoSaveTimer) {
            startAutoSave();
        }
    });
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        if (autoSaveTimer) {
            clearInterval(autoSaveTimer);
        }
    });
    </script>
</body>
</html>