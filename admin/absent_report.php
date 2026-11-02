<?php
include '../includes/auth_check.php';
include '../config/database.php';

// Date filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$guru_id = $_GET['guru_id'] ?? '';

// Build query
$where_conditions = ["1=1"];
if ($start_date) $where_conditions[] = "j.tanggal >= '$start_date'";
if ($end_date) $where_conditions[] = "j.tanggal <= '$end_date'";
if ($guru_id) $where_conditions[] = "j.guru_id = '$guru_id'";
$where_clause = implode(' AND ', $where_conditions);

// Get scheduled classes without attendance
$query = "
    SELECT j.*, u.full_name as guru_nama, mk.nama_kelas, ms.nama_subkelas,
           COUNT(js.id) as total_students
    FROM jadwal j
    LEFT JOIN users u ON j.guru_id = u.id
    LEFT JOIN master_kelas mk ON j.kelas_id = mk.id
    LEFT JOIN master_subkelas ms ON j.subkelas_id = ms.id
    LEFT JOIN jadwal_siswa js ON j.id = js.jadwal_id
    WHERE j.status = 'scheduled'
    AND j.tanggal < CURDATE()
    AND $where_clause
    GROUP BY j.id
    ORDER BY j.tanggal DESC
";

$absent_schedules = mysqli_query($conn, $query);

// Get teachers for filter
$teachers = mysqli_query($conn, "SELECT id, full_name FROM users WHERE role = 'guru' ORDER BY full_name");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absence Report - Admin Gensa Course</title>
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
                    <h1 class="text-2xl font-bold text-gray-800">Absence Report</h1>
                    <p class="text-gray-600">Track unfulfilled teaching schedules</p>
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
            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Filter Report</h2>
                
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
            
            <!-- Absence Statistics -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Absence Statistics</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <?php
                    // Calculate statistics
                    $total_absent = mysqli_num_rows($absent_schedules);
                    mysqli_data_seek($absent_schedules, 0);
                    
                    $total_students = 0;
                    $total_hours = 0;
                    $affected_teachers = [];
                    
                    while ($schedule = mysqli_fetch_assoc($absent_schedules)) {
                        $total_students += $schedule['total_students'];
                        $duration = (strtotime($schedule['jam_selesai']) - strtotime($schedule['jam_mulai'])) / 3600;
                        $total_hours += $duration;
                        
                        if (!in_array($schedule['guru_id'], $affected_teachers)) {
                            $affected_teachers[] = $schedule['guru_id'];
                        }
                    }
                    
                    mysqli_data_seek($absent_schedules, 0);
                    ?>
                    
                    <div class="p-4 bg-red-50 rounded-lg">
                        <p class="text-sm text-gray-600">Absent Sessions</p>
                        <p class="text-2xl font-bold text-red-600"><?php echo $total_absent; ?></p>
                    </div>
                    
                    <div class="p-4 bg-orange-50 rounded-lg">
                        <p class="text-sm text-gray-600">Affected Students</p>
                        <p class="text-2xl font-bold text-orange-600"><?php echo $total_students; ?></p>
                    </div>
                    
                    <div class="p-4 bg-yellow-50 rounded-lg">
                        <p class="text-sm text-gray-600">Lost Hours</p>
                        <p class="text-2xl font-bold text-yellow-600"><?php echo number_format($total_hours, 1); ?>h</p>
                    </div>
                    
                    <div class="p-4 bg-purple-50 rounded-lg">
                        <p class="text-sm text-gray-600">Teachers Involved</p>
                        <p class="text-2xl font-bold text-purple-600"><?php echo count($affected_teachers); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Absence List -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
                    <h2 class="text-xl font-bold text-gray-800">Absent Schedule List</h2>
                    <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                        <button onclick="exportAbsenceReport()" 
                                class="w-full sm:w-auto justify-center px-4 py-2 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg">
                            <i class="fas fa-download mr-2"></i> Export
                        </button>
                        <button onclick="sendReminders()" 
                                class="w-full sm:w-auto justify-center px-4 py-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg">
                            <i class="fas fa-bell mr-2"></i> Send Reminders
                        </button>
                    </div>
                </div>
                
                <?php if (mysqli_num_rows($absent_schedules) > 0): ?>
                    <!-- PERUBAHAN 4: Tabel Responsive -->
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px]">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Date & Time</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Teacher & Class</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Subject Details</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Students</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Days Overdue</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($schedule = mysqli_fetch_assoc($absent_schedules)): 
                                    $days_overdue = (time() - strtotime($schedule['tanggal'])) / (60 * 60 * 24);
                                    $duration = (strtotime($schedule['jam_selesai']) - strtotime($schedule['jam_mulai'])) / 3600;
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-3 px-4">
                                            <p class="font-semibold">
                                                <?php echo date('d M Y', strtotime($schedule['tanggal'])); ?>
                                            </p>
                                            <p class="text-sm text-gray-600">
                                                <?php echo date('H:i', strtotime($schedule['jam_mulai'])); ?> - 
                                                <?php echo date('H:i', strtotime($schedule['jam_selesai'])); ?>
                                            </p>
                                            <p class="text-xs text-red-600">
                                                <?php echo floor($days_overdue); ?> days overdue
                                            </p>
                                        </td>
                                        <td class="py-3 px-4">
                                            <p class="font-medium"><?php echo $schedule['guru_nama']; ?></p>
                                            <p class="text-sm text-gray-600">
                                                <?php echo $schedule['nama_kelas']; ?> - <?php echo $schedule['nama_subkelas']; ?>
                                            </p>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex space-x-2 mb-1">
                                                <span class="px-2 py-1 <?php 
                                                    echo $schedule['bahasa'] == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
                                                ?> rounded-full text-xs">
                                                    <?php echo ucfirst($schedule['bahasa']); ?>
                                                </span>
                                                <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs">
                                                    <?php echo ucfirst($schedule['jenis_pelajaran']); ?>
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-600">
                                                Duration: <?php echo number_format($duration, 1); ?> hours
                                            </p>
                                        </td>
                                        <td class="py-3 px-4">
                                            <p class="font-medium"><?php echo $schedule['total_students']; ?> students</p>
                                            <p class="text-xs text-gray-500">Affected</p>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-medium">
                                                <?php echo floor($days_overdue); ?> days
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex space-x-1">
                                                <button onclick="reschedule(<?php echo $schedule['id']; ?>)" 
                                                        class="p-2 bg-green-100 text-green-700 hover:bg-green-200 rounded">
                                                    <i class="fas fa-calendar-plus"></i>
                                                </button>
                                                <button onclick="markAsCancelled(<?php echo $schedule['id']; ?>)" 
                                                        class="p-2 bg-red-100 text-red-700 hover:bg-red-200 rounded">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <button onclick="contactTeacher(<?php echo $schedule['guru_id']; ?>)" 
                                                        class="p-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded">
                                                    <i class="fas fa-phone"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Summary by Teacher -->
                    <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="font-medium text-gray-800 mb-3">Absence Summary by Teacher</h3>
                        
                        <?php
                        mysqli_data_seek($absent_schedules, 0);
                        $teacher_summary = [];
                        
                        while ($schedule = mysqli_fetch_assoc($absent_schedules)) {
                            $teacher_id = $schedule['guru_id'];
                            if (!isset($teacher_summary[$teacher_id])) {
                                $teacher_summary[$teacher_id] = [
                                    'name' => $schedule['guru_nama'],
                                    'count' => 0,
                                    'students' => 0
                                ];
                            }
                            $teacher_summary[$teacher_id]['count']++;
                            $teacher_summary[$teacher_id]['students'] += $schedule['total_students'];
                        }
                        ?>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($teacher_summary as $teacher): ?>
                                <div class="p-3 bg-white border border-gray-200 rounded-lg">
                                    <div class="flex justify-between items-center mb-2">
                                        <p class="font-medium"><?php echo $teacher['name']; ?></p>
                                        <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs">
                                            <?php echo $teacher['count']; ?> absences
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600">
                                        Affected students: <?php echo $teacher['students']; ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-calendar-check text-5xl text-green-300 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-700 mb-2">No Absences Found</h3>
                        <p class="text-gray-500">All schedules have been fulfilled for the selected period</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recommendations -->
            <div class="mt-6 bg-white rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Recommendations</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-4 bg-blue-50 rounded-lg">
                        <h3 class="font-semibold text-blue-800 mb-2">
                            <i class="fas fa-exclamation-triangle mr-2"></i> Immediate Action
                        </h3>
                        <ul class="space-y-1 text-sm text-blue-700">
                            <li>Contact teachers with multiple absences</li>
                            <li>Reschedule important classes first</li>
                            <li>Notify affected students</li>
                        </ul>
                    </div>
                    
                    <div class="p-4 bg-yellow-50 rounded-lg">
                        <h3 class="font-semibold text-yellow-800 mb-2">
                            <i class="fas fa-chart-line mr-2"></i> Preventive Measures
                        </h3>
                        <ul class="space-y-1 text-sm text-yellow-700">
                            <li>Implement attendance reminders</li>
                            <li>Set up backup teacher system</li>
                            <li>Regular schedule reviews</li>
                        </ul>
                    </div>
                    
                    <div class="p-4 bg-green-50 rounded-lg">
                        <h3 class="font-semibold text-green-800 mb-2">
                            <i class="fas fa-user-check mr-2"></i> Follow-up Actions
                        </h3>
                        <ul class="space-y-1 text-sm text-green-700">
                            <li>Document all absences</li>
                            <li>Update teacher performance records</li>
                            <li>Review contractual obligations</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function exportAbsenceReport() {
        const params = new URLSearchParams(window.location.search);
        window.open(`ajax/export_absence_report.php?${params.toString()}`, '_blank');
    }
    
    function sendReminders() {
        if (confirm('Send absence reminders to all affected teachers?')) {
            fetch('ajax/send_absence_reminders.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Reminders sent successfully!');
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
        }
    }
    
    function reschedule(scheduleId) {
        window.open(`edit_schedule.php?reschedule=${scheduleId}`, '_blank');
    }
    
    function markAsCancelled(scheduleId) {
        if (confirm('Mark this schedule as cancelled? This cannot be undone.')) {
            fetch(`ajax/mark_as_cancelled.php?id=${scheduleId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Schedule marked as cancelled!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
        }
    }
    
    function contactTeacher(teacherId) {
        const message = prompt('Enter message for teacher:');
        if (message) {
            fetch(`ajax/send_message.php?teacher_id=${teacherId}&message=${encodeURIComponent(message)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Message sent to teacher!');
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
        }
    }
    </script>
</body>
</html>