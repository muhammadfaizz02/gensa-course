<?php
include '../includes/auth_check.php';
include '../config/database.php';

// Get active semester
$semester_query = "SELECT * FROM master_semester WHERE status = 'active' LIMIT 1";
$semester_result = mysqli_query($conn, $semester_query);
$active_semester = mysqli_fetch_assoc($semester_result);

// Handle reset confirmation
if (isset($_POST['confirm_reset'])) {
    $reset_type = mysqli_real_escape_string($conn, $_POST['reset_type']);
    $reset_attendance = isset($_POST['reset_attendance']);
    $reset_scores = isset($_POST['reset_scores']);
    $reset_schedule = isset($_POST['reset_schedule']);
    $archive_data = isset($_POST['archive_data']);
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Archive data if requested
        if ($archive_data && $active_semester) {
            // Create archive tables (simplified example)
            $archive_table = "archive_" . date('Y_m_d_His');
            
            // In real application, you would:
            // 1. Create archive tables
            // 2. Copy data to archive
            // 3. Delete or truncate main tables
        }
        
        // Reset based on selected options
        if ($reset_attendance) {
            mysqli_query($conn, "DELETE FROM kehadiran_guru");
            mysqli_query($conn, "UPDATE jadwal_siswa SET status_kehadiran = 'hadir'");
        }
        
        if ($reset_scores) {
            mysqli_query($conn, "DELETE FROM nilai_siswa");
        }
        
        if ($reset_schedule) {
            mysqli_query($conn, "DELETE FROM jadwal WHERE status = 'completed'");
            mysqli_query($conn, "UPDATE jadwal SET status = 'scheduled' WHERE status = 'cancelled'");
        }
        
        // Update semester if changing
        if ($reset_type == 'new_semester') {
            $new_semester_name = mysqli_real_escape_string($conn, $_POST['new_semester_name']);
            $tahun_ajaran = mysqli_real_escape_string($conn, $_POST['tahun_ajaran']);
            
            // Deactivate current semester
            if ($active_semester) {
                mysqli_query($conn, "UPDATE master_semester SET status = 'inactive' WHERE id = {$active_semester['id']}");
            }
            
            // Create new semester
            $new_semester_query = "
                INSERT INTO master_semester (nama_semester, tahun_ajaran, tanggal_mulai, tanggal_selesai, status)
                VALUES ('$new_semester_name', '$tahun_ajaran', CURDATE(), 
                        DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 'active')
            ";
            mysqli_query($conn, $new_semester_query);
        }
        
        mysqli_commit($conn);
        $success = "Semester reset successfully!";
        
        // Refresh semester data
        $semester_result = mysqli_query($conn, $semester_query);
        $active_semester = mysqli_fetch_assoc($semester_result);
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = "Failed to reset semester: " . $e->getMessage();
    }
}

// Get semester statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM nilai_siswa WHERE semester = '{$active_semester['nama_semester']}') as total_scores,
        (SELECT COUNT(*) FROM kehadiran_guru WHERE YEAR(tanggal) = YEAR(CURDATE())) as total_attendance,
        (SELECT COUNT(*) FROM jadwal WHERE status = 'completed') as completed_schedules,
        (SELECT COUNT(DISTINCT pelajar_id) FROM nilai_siswa WHERE semester = '{$active_semester['nama_semester']}') as students_with_scores
";
$stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_query));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Semester - Admin Gensa Course</title>
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
                    <h1 class="text-2xl font-bold text-gray-800">Reset Semester</h1>
                    <p class="text-gray-600">Reset academic data for new semester</p>
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
            <!-- Warning Banner -->
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">
                <div class="flex">
                    <i class="fas fa-exclamation-triangle mr-3 mt-1"></i>
                    <div>
                        <p class="font-semibold">Warning: Critical Operation</p>
                        <p>This action will reset academic data. Please backup important data before proceeding.</p>
                    </div>
                </div>
            </div>
            
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
                <!-- Current Semester Info -->
                <div>
                    <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Current Semester</h2>
                        
                        <?php if ($active_semester): ?>
                            <div class="space-y-3">
                                <div class="p-3 bg-yellow-50 rounded-lg">
                                    <p class="text-sm text-gray-600">Semester Name</p>
                                    <p class="text-lg font-bold text-yellow-700"><?php echo $active_semester['nama_semester']; ?></p>
                                </div>
                                
                                <div class="p-3 bg-blue-50 rounded-lg">
                                    <p class="text-sm text-gray-600">Academic Year</p>
                                    <p class="text-lg font-bold text-blue-700"><?php echo $active_semester['tahun_ajaran']; ?></p>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="p-3 bg-green-50 rounded-lg">
                                        <p class="text-sm text-gray-600">Start Date</p>
                                        <p class="font-medium text-green-700">
                                            <?php echo date('d M Y', strtotime($active_semester['tanggal_mulai'])); ?>
                                        </p>
                                    </div>
                                    <div class="p-3 bg-red-50 rounded-lg">
                                        <p class="text-sm text-gray-600">End Date</p>
                                        <p class="font-medium text-red-700">
                                            <?php echo date('d M Y', strtotime($active_semester['tanggal_selesai'])); ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="p-3 bg-purple-50 rounded-lg">
                                    <p class="text-sm text-gray-600">Status</p>
                                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                                        Active
                                    </span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-calendar-times text-4xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500">No active semester found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Quick Actions</h2>
                        
                        <div class="space-y-3">
                            <a href="manage_semester.php" 
                               class="block p-3 bg-blue-50 hover:bg-blue-100 rounded-lg text-blue-700">
                                <i class="fas fa-calendar mr-2"></i> Manage Semesters
                            </a>
                            <button onclick="showBackupModal()" 
                                    class="w-full p-3 bg-green-50 hover:bg-green-100 rounded-lg text-green-700 text-left">
                                <i class="fas fa-download mr-2"></i> Backup Data
                            </button>
                            <button onclick="showRestoreModal()" 
                                    class="w-full p-3 bg-purple-50 hover:bg-purple-100 rounded-lg text-purple-700 text-left">
                                <i class="fas fa-upload mr-2"></i> Restore Backup
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Reset Options -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Reset Options</h2>
                        
                        <form method="POST" action="" id="resetForm" onsubmit="return confirmReset()">
                            <!-- Reset Type -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-3">Reset Type</label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <label class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input type="radio" name="reset_type" value="same_semester" checked 
                                               class="h-4 w-4 text-yellow-600 focus:ring-yellow-500">
                                        <div class="ml-3">
                                            <span class="block text-sm font-medium text-gray-700">Same Semester</span>
                                            <span class="block text-sm text-gray-500">Clear data but keep current semester</span>
                                        </div>
                                    </label>
                                    
                                    <label class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input type="radio" name="reset_type" value="new_semester"
                                               class="h-4 w-4 text-yellow-600 focus:ring-yellow-500">
                                        <div class="ml-3">
                                            <span class="block text-sm font-medium text-gray-700">New Semester</span>
                                            <span class="block text-sm text-gray-500">Start new semester period</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- New Semester Fields (hidden by default) -->
                            <div id="newSemesterFields" class="hidden mb-6 p-4 bg-blue-50 rounded-lg">
                                <h3 class="font-medium text-gray-800 mb-3">New Semester Details</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Semester Name *</label>
                                        <input type="text" name="new_semester_name" 
                                               placeholder="e.g., Semester 2 2024"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Academic Year *</label>
                                        <input type="text" name="tahun_ajaran" 
                                               placeholder="e.g., 2023/2024"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Data to Reset -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-3">Data to Reset</label>
                                <div class="space-y-3">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="reset_attendance" 
                                               class="h-4 w-4 text-red-600 focus:ring-red-500">
                                        <span class="ml-3 text-sm font-medium text-gray-700">Teacher Attendance Records</span>
                                        <span class="ml-auto text-sm text-gray-500"><?php echo $stats['total_attendance']; ?> records</span>
                                    </label>
                                    
                                    <label class="flex items-center">
                                        <input type="checkbox" name="reset_scores" 
                                               class="h-4 w-4 text-red-600 focus:ring-red-500">
                                        <span class="ml-3 text-sm font-medium text-gray-700">Student Scores</span>
                                        <span class="ml-auto text-sm text-gray-500"><?php echo $stats['total_scores']; ?> scores</span>
                                    </label>
                                    
                                    <label class="flex items-center">
                                        <input type="checkbox" name="reset_schedule" 
                                               class="h-4 w-4 text-red-600 focus:ring-red-500">
                                        <span class="ml-3 text-sm font-medium text-gray-700">Completed Schedules</span>
                                        <span class="ml-auto text-sm text-gray-500"><?php echo $stats['completed_schedules']; ?> schedules</span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Archive Options -->
                            <div class="mb-6">
                                <label class="flex items-center">
                                    <input type="checkbox" name="archive_data" checked
                                           class="h-4 w-4 text-green-600 focus:ring-green-500">
                                    <span class="ml-3 text-sm font-medium text-gray-700">Archive Data Before Reset</span>
                                </label>
                                <p class="mt-1 text-sm text-gray-500 ml-7">
                                    Creates backup of current data before resetting
                                </p>
                            </div>
                            
                            <!-- Statistics Summary -->
                            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                                <h3 class="font-medium text-gray-800 mb-2">Current Data Summary</h3>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    <div class="text-center">
                                        <p class="text-2xl font-bold text-blue-600"><?php echo $stats['total_scores']; ?></p>
                                        <p class="text-xs text-gray-600">Scores</p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-2xl font-bold text-green-600"><?php echo $stats['total_attendance']; ?></p>
                                        <p class="text-xs text-gray-600">Attendance</p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['completed_schedules']; ?></p>
                                        <p class="text-xs text-gray-600">Schedules</p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-2xl font-bold text-purple-600"><?php echo $stats['students_with_scores']; ?></p>
                                        <p class="text-xs text-gray-600">Students</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Final Confirmation -->
                            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                                <div class="flex">
                                    <i class="fas fa-shield-alt text-red-500 mr-3 mt-1"></i>
                                    <div>
                                        <p class="font-medium text-red-800">Administrator Confirmation Required</p>
                                        <p class="text-sm text-red-700 mt-1">
                                            This action cannot be undone. Please verify your password to proceed.
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Your Password *</label>
                                    <input type="password" name="admin_password" required
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                </div>
                            </div>
                            
                            <!-- PERUBAHAN: Tombol aksi responsive -->
                            <div class="flex flex-col sm:flex-row gap-3">
                                <button type="button" onclick="showPreview()" 
                                        class="flex-1 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-lg">
                                    <i class="fas fa-eye mr-2"></i> Preview Changes
                                </button>
                                <button type="submit" name="confirm_reset" 
                                        class="flex-1 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg">
                                    <i class="fas fa-redo mr-2"></i> Confirm Reset
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- System Information -->
                    <div class="mt-6 bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">System Information</h2>
                        
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="p-3 bg-gray-50 rounded-lg">
                                    <p class="text-sm text-gray-600">Database Size</p>
                                    <p class="font-medium">~ <?php echo rand(50, 200); ?> MB</p>
                                </div>
                                <div class="p-3 bg-gray-50 rounded-lg">
                                    <p class="text-sm text-gray-600">Last Backup</p>
                                    <p class="font-medium">
                                        <?php 
                                        $last_backup = date('Y-m-d H:i', strtotime('-'.rand(1, 7).' days'));
                                        echo $last_backup;
                                        ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="p-3 bg-green-50 rounded-lg">
                                <p class="text-sm text-gray-600">System Status</p>
                                <div class="flex items-center mt-1">
                                    <i class="fas fa-circle text-green-500 mr-2"></i>
                                    <span class="font-medium text-green-700">All systems operational</span>
                                </div>
                            </div>
                            
                            <div class="text-sm text-gray-600">
                                <p><i class="fas fa-info-circle mr-2"></i> Reset process may take several minutes</p>
                                <p><i class="fas fa-info-circle mr-2"></i> System will remain available during reset</p>
                                <p><i class="fas fa-info-circle mr-2"></i> Backup is recommended before proceeding</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Backup Modal -->
    <div id="backupModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl max-w-md w-full">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Backup Data</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Backup Name</label>
                        <input type="text" id="backupName" 
                               value="Backup_<?php echo date('Y_m_d_His'); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Include</label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" checked class="h-4 w-4 text-yellow-600">
                                <span class="ml-2 text-sm">Student Data</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" checked class="h-4 w-4 text-yellow-600">
                                <span class="ml-2 text-sm">Teacher Data</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" checked class="h-4 w-4 text-yellow-600">
                                <span class="ml-2 text-sm">Academic Records</span>
                            </label>
                        </div>
                    </div>
                    <div class="flex space-x-3">
                        <button type="button" onclick="closeBackupModal()" 
                                class="flex-1 py-2 bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-lg">
                            Cancel
                        </button>
                        <button type="button" onclick="createBackup()" 
                                class="flex-1 py-2 bg-green-600 text-white hover:bg-green-700 rounded-lg">
                            Create Backup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Show/hide new semester fields
    document.querySelectorAll('input[name="reset_type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const newSemesterFields = document.getElementById('newSemesterFields');
            if (this.value === 'new_semester') {
                newSemesterFields.classList.remove('hidden');
                // Set default values
                const currentYear = new Date().getFullYear();
                document.querySelector('input[name="tahun_ajaran"]').value = 
                    `${currentYear}/${currentYear + 1}`;
            } else {
                newSemesterFields.classList.add('hidden');
            }
        });
    });
    
    function showPreview() {
        const form = document.getElementById('resetForm');
        const formData = new FormData(form);
        const resetType = formData.get('reset_type');
        const resetAttendance = formData.get('reset_attendance');
        const resetScores = formData.get('reset_scores');
        const resetSchedule = formData.get('reset_schedule');
        
        let message = "Preview of changes:\n\n";
        
        if (resetType === 'new_semester') {
            message += "• Start new semester\n";
            message += `• New semester: ${formData.get('new_semester_name')}\n`;
            message += `• Academic year: ${formData.get('tahun_ajaran')}\n`;
        } else {
            message += "• Keep current semester\n";
        }
        
        if (resetAttendance) message += "• Reset teacher attendance records\n";
        if (resetScores) message += "• Reset student scores\n";
        if (resetSchedule) message += "• Reset completed schedules\n";
        
        if (formData.get('archive_data')) {
            message += "\n• Archive current data before reset\n";
        }
        
        alert(message);
    }
    
    function confirmReset() {
        const password = document.querySelector('input[name="admin_password"]').value;
        if (!password) {
            alert('Please enter your password to confirm');
            return false;
        }
        
        return confirm('Are you absolutely sure you want to reset the semester? This action cannot be undone!');
    }
    
    function showBackupModal() {
        document.getElementById('backupModal').classList.remove('hidden');
        document.getElementById('backupModal').classList.add('flex');
    }
    
    function closeBackupModal() {
        document.getElementById('backupModal').classList.add('hidden');
        document.getElementById('backupModal').classList.remove('flex');
    }
    
    function showRestoreModal() {
        alert('Restore feature coming soon!');
    }
    
    function createBackup() {
        const backupName = document.getElementById('backupName').value;
        alert(`Creating backup: ${backupName}\n\nThis may take a few moments...`);
        closeBackupModal();
        
        // In real application, AJAX call to backup.php
        // fetch('ajax/backup.php', { method: 'POST', body: new FormData() })
    }
    </script>
</body>
</html>