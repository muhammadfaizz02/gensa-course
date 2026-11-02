<?php
include '../includes/auth_check.php';
include '../config/database.php';

// Handle add new semester
if (isset($_POST['add_semester'])) {
    $nama_semester = mysqli_real_escape_string($conn, $_POST['nama_semester']);
    $tahun_ajaran = mysqli_real_escape_string($conn, $_POST['tahun_ajaran']);
    $tanggal_mulai = mysqli_real_escape_string($conn, $_POST['tanggal_mulai']);
    $tanggal_selesai = mysqli_real_escape_string($conn, $_POST['tanggal_selesai']);
    
    // Check if there's an active semester
    $check_active = mysqli_query($conn, "SELECT id FROM master_semester WHERE status = 'active'");
    
    $status = 'active';
    if (mysqli_num_rows($check_active) > 0) {
        // If there's already an active semester, set new one to inactive
        $status = 'inactive';
    }
    
    $query = "
        INSERT INTO master_semester (nama_semester, tahun_ajaran, tanggal_mulai, tanggal_selesai, status)
        VALUES ('$nama_semester', '$tahun_ajaran', '$tanggal_mulai', '$tanggal_selesai', '$status')
    ";
    
    if (mysqli_query($conn, $query)) {
        $success = "Semester added successfully!";
    } else {
        $error = "Failed to add semester: " . mysqli_error($conn);
    }
}

// Handle activate semester
if (isset($_GET['activate'])) {
    $semester_id = mysqli_real_escape_string($conn, $_GET['activate']);
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Deactivate all semesters
        mysqli_query($conn, "UPDATE master_semester SET status = 'inactive'");
        
        // Activate selected semester
        mysqli_query($conn, "UPDATE master_semester SET status = 'active' WHERE id = $semester_id");
        
        mysqli_commit($conn);
        header('Location: manage_semester.php?success=activated');
        exit();
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = "Failed to activate semester: " . $e->getMessage();
    }
}

// Handle delete semester
if (isset($_GET['delete'])) {
    $semester_id = mysqli_real_escape_string($conn, $_GET['delete']);
    
    // Check if it's active
    $check_query = "SELECT status FROM master_semester WHERE id = $semester_id";
    $check_result = mysqli_query($conn, $check_query);
    $semester = mysqli_fetch_assoc($check_result);
    
    if ($semester['status'] == 'active') {
        $error = "Cannot delete active semester!";
    } else {
        $delete_query = "DELETE FROM master_semester WHERE id = $semester_id";
        if (mysqli_query($conn, $delete_query)) {
            header('Location: manage_semester.php?success=deleted');
            exit();
        } else {
            $error = "Failed to delete semester: " . mysqli_error($conn);
        }
    }
}

// Get all semesters
$semesters_query = "SELECT * FROM master_semester ORDER BY tahun_ajaran DESC, FIELD(status, 'active', 'inactive')";
$semesters = mysqli_query($conn, $semesters_query);

// Get active semester
$active_semester = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT * FROM master_semester WHERE status = 'active' LIMIT 1"
));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Semesters - Admin Gensa Course</title>
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
                    <h1 class="text-2xl font-bold text-gray-800">Manage Academic Semesters</h1>
                    <p class="text-gray-600">Create and manage academic periods</p>
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
            <?php if (isset($_GET['success'])): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span>Semester successfully <?php echo $_GET['success']; ?>!</span>
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
                <!-- Add Semester Form -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Add New Semester</h2>
                    
                    <form method="POST" action="" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Semester Name *</label>
                            <input type="text" name="nama_semester" required 
                                   placeholder="e.g., Semester 1, Semester 2, Summer Course"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Academic Year *</label>
                            <input type="text" name="tahun_ajaran" required 
                                   placeholder="e.g., 2023/2024"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Start Date *</label>
                                <input type="date" name="tanggal_mulai" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">End Date *</label>
                                <input type="date" name="tanggal_selesai" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                            </div>
                        </div>
                        
                        <div class="p-3 bg-blue-50 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                                <p class="text-sm text-blue-700">
                                    <?php if ($active_semester): ?>
                                        An active semester already exists. New semester will be added as inactive.
                                    <?php else: ?>
                                        This will be set as the active semester.
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        
                        <button type="submit" name="add_semester" 
                                class="w-full py-3 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold rounded-lg shadow-md transition btn-gensa">
                            <i class="fas fa-plus mr-2"></i> Add Semester
                        </button>
                    </form>
                </div>
                
                <!-- Active Semester -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Active Semester</h2>
                    
                    <?php if ($active_semester): ?>
                        <div class="space-y-4">
                            <div class="p-4 bg-yellow-50 border-2 border-yellow-500 rounded-lg">
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="text-lg font-bold text-yellow-800">
                                        <?php echo $active_semester['nama_semester']; ?>
                                    </h3>
                                    <span class="px-3 py-1 bg-yellow-500 text-white rounded-full text-xs font-medium">
                                        Active
                                    </span>
                                </div>
                                <p class="text-sm text-gray-700">
                                    <?php echo $active_semester['tahun_ajaran']; ?>
                                </p>
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <div>
                                        <p class="text-xs text-gray-500">Start Date</p>
                                        <p class="text-sm font-medium">
                                            <?php echo date('d M Y', strtotime($active_semester['tanggal_mulai'])); ?>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">End Date</p>
                                        <p class="text-sm font-medium">
                                            <?php echo date('d M Y', strtotime($active_semester['tanggal_selesai'])); ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Progress Bar -->
                                <div class="mt-4">
                                    <?php
                                    $total_days = (strtotime($active_semester['tanggal_selesai']) - strtotime($active_semester['tanggal_mulai'])) / (60 * 60 * 24);
                                    $days_passed = (time() - strtotime($active_semester['tanggal_mulai'])) / (60 * 60 * 24);
                                    $progress = min(max($days_passed / $total_days * 100, 0), 100);
                                    $progress_color = $progress > 75 ? 'bg-red-500' : ($progress > 50 ? 'bg-yellow-500' : 'bg-green-500');
                                    ?>
                                    <div class="flex justify-between text-sm text-gray-600 mb-1">
                                        <span>Progress</span>
                                        <span><?php echo round($progress); ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="<?php echo $progress_color; ?> h-2 rounded-full" 
                                             style="width: <?php echo $progress; ?>%"></div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1 text-center">
                                        <?php echo round($days_passed); ?> of <?php echo round($total_days); ?> days passed
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Semester Statistics -->
                            <div class="grid grid-cols-3 gap-2">
                                <?php
                                $stats_queries = [
                                    'scores' => "SELECT COUNT(*) as count FROM nilai_siswa WHERE semester = '{$active_semester['nama_semester']}'",
                                    'attendance' => "SELECT COUNT(*) as count FROM kehadiran_guru WHERE tanggal BETWEEN '{$active_semester['tanggal_mulai']}' AND '{$active_semester['tanggal_selesai']}'",
                                    'schedules' => "SELECT COUNT(*) as count FROM jadwal WHERE tanggal BETWEEN '{$active_semester['tanggal_mulai']}' AND '{$active_semester['tanggal_selesai']}'"
                                ];
                                
                                foreach ($stats_queries as $key => $query):
                                    $result = mysqli_query($conn, $query);
                                    $count = mysqli_fetch_assoc($result)['count'];
                                    $icons = [
                                        'scores' => ['fa-graduation-cap', 'blue'],
                                        'attendance' => ['fa-clipboard-check', 'green'],
                                        'schedules' => ['fa-calendar-alt', 'purple']
                                    ];
                                ?>
                                    <div class="text-center p-2 bg-gray-50 rounded">
                                        <i class="fas <?php echo $icons[$key][0]; ?> text-<?php echo $icons[$key][1]; ?>-500 mb-1"></i>
                                        <p class="text-lg font-bold"><?php echo $count; ?></p>
                                        <p class="text-xs text-gray-500 capitalize"><?php echo $key; ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Actions -->
                            <div class="space-y-2">
                                <a href="reset_semester.php" 
                                   class="block w-full py-2 bg-red-50 text-red-700 hover:bg-red-100 rounded-lg text-center">
                                    <i class="fas fa-redo mr-2"></i> Reset Semester
                                </a>
                                <button onclick="showEditModal(<?php echo $active_semester['id']; ?>)" 
                                        class="w-full py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg">
                                    <i class="fas fa-edit mr-2"></i> Edit Semester
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-times text-4xl text-gray-300 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-700 mb-2">No Active Semester</h3>
                            <p class="text-gray-500">Please add a new semester or activate an existing one</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Semester List -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">All Semesters</h2>
                    
                    <div class="space-y-3 max-h-[400px] overflow-y-auto">
                        <?php if (mysqli_num_rows($semesters) > 0): ?>
                            <?php while ($semester = mysqli_fetch_assoc($semesters)): ?>
                                <div class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 <?php echo $semester['status'] == 'active' ? 'border-yellow-300 bg-yellow-50' : ''; ?>">
                                    <div class="flex items-center justify-between mb-2">
                                        <div>
                                            <p class="font-semibold <?php echo $semester['status'] == 'active' ? 'text-yellow-700' : 'text-gray-800'; ?>">
                                                <?php echo $semester['nama_semester']; ?>
                                            </p>
                                            <p class="text-sm text-gray-500"><?php echo $semester['tahun_ajaran']; ?></p>
                                        </div>
                                        <?php if ($semester['status'] == 'active'): ?>
                                            <span class="px-2 py-1 bg-yellow-500 text-white rounded-full text-xs">
                                                Active
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex justify-between text-xs text-gray-600 mb-3">
                                        <span><?php echo date('d M', strtotime($semester['tanggal_mulai'])); ?></span>
                                        <span>to</span>
                                        <span><?php echo date('d M Y', strtotime($semester['tanggal_selesai'])); ?></span>
                                    </div>
                                    
                                    <div class="flex space-x-1">
                                        <?php if ($semester['status'] != 'active'): ?>
                                            <a href="?activate=<?php echo $semester['id']; ?>" 
                                               onclick="return confirm('Activate this semester?')"
                                               class="flex-1 py-1 bg-green-50 text-green-700 hover:bg-green-100 rounded text-center text-xs">
                                                Activate
                                            </a>
                                        <?php endif; ?>
                                        <button onclick="showEditModal(<?php echo $semester['id']; ?>)" 
                                                class="flex-1 py-1 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded text-xs">
                                            Edit
                                        </button>
                                        <?php if ($semester['status'] != 'active'): ?>
                                            <a href="?delete=<?php echo $semester['id']; ?>" 
                                               onclick="return confirm('Delete this semester?')"
                                               class="flex-1 py-1 bg-red-50 text-red-700 hover:bg-red-100 rounded text-center text-xs">
                                                Delete
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-calendar text-4xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500">No semesters found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Academic Calendar -->
            <div class="mt-6 bg-white rounded-xl shadow-md p-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
                    <h2 class="text-xl font-bold text-gray-800">Academic Calendar</h2>
                    <button onclick="exportCalendar()" 
                            class="w-full sm:w-auto justify-center px-4 py-2 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg">
                        <i class="fas fa-download mr-2"></i> Export Calendar
                    </button>
                </div>
                
                <?php
                // Get current year's semesters
                $current_year = date('Y');
                $calendar_query = "
                    SELECT * FROM master_semester 
                    WHERE YEAR(tanggal_mulai) = $current_year 
                    OR YEAR(tanggal_selesai) = $current_year
                    ORDER BY tanggal_mulai
                ";
                $calendar_semesters = mysqli_query($conn, $calendar_query);
                ?>
                
                <!-- PERUBAHAN 4: Tabel Responsive -->
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px]">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Semester</th>
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Academic Year</th>
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Period</th>
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Duration</th>
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Status</th>
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while ($semester = mysqli_fetch_assoc($calendar_semesters)): 
                                $duration = (strtotime($semester['tanggal_selesai']) - strtotime($semester['tanggal_mulai'])) / (60 * 60 * 24);
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-3 px-4">
                                        <p class="font-medium"><?php echo $semester['nama_semester']; ?></p>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-sm">
                                            <?php echo $semester['tahun_ajaran']; ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <p class="text-sm">
                                            <?php echo date('d M', strtotime($semester['tanggal_mulai'])); ?> - 
                                            <?php echo date('d M Y', strtotime($semester['tanggal_selesai'])); ?>
                                        </p>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="text-sm text-gray-600"><?php echo round($duration); ?> days</span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php if ($semester['status'] == 'active'): ?>
                                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">
                                                Active
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">
                                                Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex space-x-1">
                                            <button onclick="viewSemesterDetails(<?php echo $semester['id']; ?>)" 
                                                    class="p-1 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>
                                            <a href="?activate=<?php echo $semester['id']; ?>" 
                                               class="p-1 bg-green-100 text-green-700 hover:bg-green-200 rounded">
                                                <i class="fas fa-play text-xs"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Calendar Legend -->
                <div class="mt-6 pt-6 border-t">
                    <h3 class="font-medium text-gray-700 mb-2">Calendar Legend</h3>
                    <div class="flex flex-wrap gap-3">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></div>
                            <span class="text-sm text-gray-600">Active Semester</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                            <span class="text-sm text-gray-600">Upcoming</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-gray-400 rounded-full mr-2"></div>
                            <span class="text-sm text-gray-600">Completed</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Edit Semester</h3>
                <div id="editModalContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function showEditModal(semesterId) {
        fetch(`ajax/get_semester_details.php?id=${semesterId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('editModalContent').innerHTML = html;
                document.getElementById('editModal').classList.remove('hidden');
                document.getElementById('editModal').classList.add('flex');
            });
    }
    
    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
        document.getElementById('editModal').classList.remove('flex');
    }
    
    function exportCalendar() {
        window.open('ajax/export_calendar.php', '_blank');
    }
    
    function viewSemesterDetails(semesterId) {
        window.open(`semester_details.php?id=${semesterId}`, '_blank');
    }
    
    // Close modal when clicking outside
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditModal();
        }
    });
    </script>
</body>
</html>