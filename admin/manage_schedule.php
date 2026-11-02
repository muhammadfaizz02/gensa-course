<?php
include '../includes/auth_check.php';
include '../config/database.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kelas_id = mysqli_real_escape_string($conn, $_POST['kelas_id']);
    $subkelas_id = mysqli_real_escape_string($conn, $_POST['subkelas_id']);
    $guru_id = mysqli_real_escape_string($conn, $_POST['guru_id']);
    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $jam_mulai = mysqli_real_escape_string($conn, $_POST['jam_mulai']);
    $jam_selesai = mysqli_real_escape_string($conn, $_POST['jam_selesai']);
    $bahasa = mysqli_real_escape_string($conn, $_POST['bahasa']);
    $jenis_pelajaran = mysqli_real_escape_string($conn, $_POST['jenis_pelajaran']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $student_ids = isset($_POST['student_ids']) ? $_POST['student_ids'] : [];
    
    // Check for schedule conflict
    $conflict_query = "
        SELECT * FROM jadwal 
        WHERE guru_id = '$guru_id' 
        AND tanggal = '$tanggal'
        AND (
            (jam_mulai < '$jam_selesai' AND jam_selesai > '$jam_mulai')
            OR jam_mulai = '$jam_mulai'
        )
        AND status = 'scheduled'
    ";
    
    $conflict_result = mysqli_query($conn, $conflict_query);
    
    if (mysqli_num_rows($conflict_result) > 0) {
        $error = "Teacher already has a schedule at this time!";
    } else {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert schedule
            $schedule_query = "
                INSERT INTO jadwal 
                (kelas_id, subkelas_id, guru_id, tanggal, jam_mulai, jam_selesai, 
                 bahasa, jenis_pelajaran, deskripsi, created_by)
                VALUES ('$kelas_id', '$subkelas_id', '$guru_id', '$tanggal', 
                        '$jam_mulai', '$jam_selesai', '$bahasa', '$jenis_pelajaran', 
                        '$deskripsi', '$user_id')
            ";
            
            mysqli_query($conn, $schedule_query);
            $schedule_id = mysqli_insert_id($conn);
            
            // Add students to schedule
            foreach ($student_ids as $student_id) {
                $student_id = mysqli_real_escape_string($conn, $student_id);
                $student_query = "
                    INSERT INTO jadwal_siswa (jadwal_id, pelajar_id)
                    VALUES ('$schedule_id', '$student_id')
                ";
                mysqli_query($conn, $student_query);
            }
            
            mysqli_commit($conn);
            $success = "Schedule successfully added!";
            
            // Clear form
            $_POST = array();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Failed to add schedule: " . $e->getMessage();
        }
    }
}

// Get data for dropdowns
$kelas_query = "SELECT * FROM master_kelas WHERE status = 'active' ORDER BY urutan";
$kelas_result = mysqli_query($conn, $kelas_query);

$guru_query = "SELECT u.id, u.full_name, g.spesialisasi 
               FROM users u 
               LEFT JOIN guru_profiles g ON u.id = g.user_id 
               WHERE u.role = 'guru' 
               ORDER BY u.full_name";
$guru_result = mysqli_query($conn, $guru_query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Schedule - Admin Gensa Course</title>
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
                    <h1 class="text-2xl font-bold text-gray-800">Add New Schedule</h1>
                    <p class="text-gray-600">Create teaching schedules for teachers and students</p>
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
                <!-- Schedule Form -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Schedule Information</h2>
                        
                        <form method="POST" action="" id="scheduleForm" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Class Level *</label>
                                    <select name="kelas_id" id="kelas_id" required 
                                            onchange="loadSubkelas()"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select">
                                        <option value="">Select Class</option>
                                        <?php while ($kelas = mysqli_fetch_assoc($kelas_result)): ?>
                                            <option value="<?php echo $kelas['id']; ?>" 
                                                    data-jenjang="<?php echo $kelas['jenjang']; ?>">
                                                <?php echo $kelas['jenjang']; ?> - <?php echo $kelas['nama_kelas']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Sub Class *</label>
                                    <select name="subkelas_id" id="subkelas_id" required 
                                            onchange="loadStudents()"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select">
                                        <option value="">Select Class First</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Teacher *</label>
                                    <select name="guru_id" id="guru_id" required 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select">
                                        <option value="">Select Teacher</option>
                                        <?php 
                                        mysqli_data_seek($guru_result, 0);
                                        while ($guru = mysqli_fetch_assoc($guru_result)): 
                                        ?>
                                            <option value="<?php echo $guru['id']; ?>" 
                                                    data-specialization="<?php echo $guru['spesialisasi']; ?>">
                                                <?php echo $guru['full_name']; ?>
                                                (<?php echo str_replace(',', '/', $guru['spesialisasi']); ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                                    <input type="date" name="tanggal" required 
                                           value="<?php echo date('Y-m-d'); ?>"
                                           min="<?php echo date('Y-m-d'); ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Time *</label>
                                    <input type="time" name="jam_mulai" required 
                                           value="08:00"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">End Time *</label>
                                    <input type="time" name="jam_selesai" required 
                                           value="09:00"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Language *</label>
                                    <select name="bahasa" required 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select">
                                        <option value="">Select Language</option>
                                        <option value="arab">Arabic</option>
                                        <option value="inggris">English</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Lesson Type *</label>
                                    <select name="jenis_pelajaran" required 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select">
                                        <option value="">Select Type</option>
                                        <option value="listening">Listening</option>
                                        <option value="grammar">Grammar</option>
                                        <option value="speaking">Speaking</option>
                                        <option value="writing">Writing</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea name="deskripsi" rows="3" 
                                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent"
                                          placeholder="Enter schedule description (optional)"></textarea>
                            </div>
                            
                            <!-- Student Selection -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select Students</label>
                                <div id="studentsContainer" class="border border-gray-300 rounded-lg p-4 min-h-[200px] max-h-[300px] overflow-y-auto">
                                    <p class="text-gray-500 text-center py-8">Select class and subclass first to load students</p>
                                </div>
                            </div>
                            
                            <div class="pt-4">
                                <button type="submit" 
                                        class="w-full py-3 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold rounded-lg shadow-md transition btn-gensa">
                                    <i class="fas fa-calendar-plus mr-2"></i> Create Schedule
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Teacher Availability & Preview -->
                <div class="space-y-6">
                    <!-- Teacher Availability -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Teacher Availability</h2>
                        
                        <div class="space-y-4">
                            <div id="teacherSchedule" class="text-center py-4">
                                <p class="text-gray-500">Select a teacher to view their schedule</p>
                            </div>
                            
                            <div class="p-3 bg-blue-50 rounded-lg">
                                <p class="text-sm text-gray-600">Next Available Slot</p>
                                <p class="font-semibold text-blue-700" id="nextAvailable">-</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Schedule Preview -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Schedule Preview</h2>
                        
                        <div id="schedulePreview" class="space-y-3">
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-600">Date & Time</p>
                                <p class="font-medium" id="previewDateTime">Not set</p>
                            </div>
                            
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-600">Class</p>
                                <p class="font-medium" id="previewClass">Not set</p>
                            </div>
                            
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-700">Teacher</p>
                                <p class="font-medium" id="previewTeacher">Not set</p>
                            </div>
                            
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-700">Students</p>
                                <p class="font-medium" id="previewStudents">0 selected</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Schedule -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Quick Schedule</h2>
                        
                        <div class="space-y-3">
                            <button type="button" onclick="setQuickTime('08:00', '09:00')" 
                                    class="w-full py-2 bg-yellow-50 text-yellow-700 hover:bg-yellow-100 rounded-lg">
                                Morning (8:00 - 9:00)
                            </button>
                            <button type="button" onclick="setQuickTime('13:00', '14:00')" 
                                    class="w-full py-2 bg-yellow-50 text-yellow-700 hover:bg-yellow-100 rounded-lg">
                                Afternoon (13:00 - 14:00)
                            </button>
                            <button type="button" onclick="setQuickTime('15:00', '16:00')" 
                                    class="w-full py-2 bg-yellow-50 text-yellow-700 hover:bg-yellow-100 rounded-lg">
                                Evening (15:00 - 16:00)
                            </button>
                            <button type="button" onclick="setToday()" 
                                    class="w-full py-2 bg-green-50 text-green-700 hover:bg-green-100 rounded-lg">
                                Schedule for Today
                            </button>
                            <button type="button" onclick="setTomorrow()" 
                                    class="w-full py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg">
                                Schedule for Tomorrow
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Today's Schedule -->
            <div class="mt-6 bg-white rounded-xl shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800">Today's Schedule</h2>
                    <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">
                        <?php echo date('l, d F Y'); ?>
                    </span>
                </div>
                
                <?php
                $today_query = "
                    SELECT j.*, u.full_name as guru_nama, 
                           mk.nama_kelas, ms.nama_subkelas,
                           COUNT(js.id) as total_students
                    FROM jadwal j
                    LEFT JOIN users u ON j.guru_id = u.id
                    LEFT JOIN master_kelas mk ON j.kelas_id = mk.id
                    LEFT JOIN master_subkelas ms ON j.subkelas_id = ms.id
                    LEFT JOIN jadwal_siswa js ON j.id = js.jadwal_id
                    WHERE j.tanggal = CURDATE() AND j.status = 'scheduled'
                    GROUP BY j.id
                    ORDER BY j.jam_mulai
                ";
                $today_schedule = mysqli_query($conn, $today_query);
                ?>
                
                <?php if (mysqli_num_rows($today_schedule) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php while ($schedule = mysqli_fetch_assoc($today_schedule)): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <p class="font-semibold text-gray-800">
                                            <?php echo date('H:i', strtotime($schedule['jam_mulai'])); ?> - 
                                            <?php echo date('H:i', strtotime($schedule['jam_selesai'])); ?>
                                        </p>
                                        <p class="text-sm text-gray-500"><?php echo $schedule['guru_nama']; ?></p>
                                    </div>
                                    <span class="px-2 py-1 <?php echo $schedule['bahasa'] == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?> rounded-full text-xs">
                                        <?php echo ucfirst($schedule['bahasa']); ?>
                                    </span>
                                </div>
                                <p class="text-sm font-medium">
                                    <?php echo $schedule['nama_kelas']; ?> - <?php echo $schedule['nama_subkelas']; ?>
                                </p>
                                <p class="text-sm text-gray-600"><?php echo ucfirst($schedule['jenis_pelajaran']); ?></p>
                                <div class="mt-2 flex justify-between items-center">
                                    <span class="text-sm text-gray-500">
                                        <?php echo $schedule['total_students']; ?> students
                                    </span>
                                    <a href="view_schedule.php?edit=<?php echo $schedule['id']; ?>" 
                                       class="text-yellow-600 hover:text-yellow-700 text-sm">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-calendar-day text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No schedule for today</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    let selectedStudents = [];
    
    // Load subclasses based on selected class
    function loadSubkelas() {
        const kelasId = document.getElementById('kelas_id').value;
        if (!kelasId) return;
        
        fetch(`ajax/get_subkelas_by_class.php?kelas_id=${kelasId}`)
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('subkelas_id');
                select.innerHTML = '<option value="">Select Sub Class</option>';
                data.forEach(subkelas => {
                    const option = document.createElement('option');
                    option.value = subkelas.id;
                    option.textContent = subkelas.nama_subkelas;
                    select.appendChild(option);
                });
                
                updatePreview();
                loadStudents();
            });
    }
    
    // Load students based on selected subclass
    function loadStudents() {
        const subkelasId = document.getElementById('subkelas_id').value;
        if (!subkelasId) return;
        
        fetch(`ajax/get_students_by_subkelas.php?subkelas_id=${subkelasId}`)
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('studentsContainer');
                container.innerHTML = '';
                selectedStudents = [];
                
                if (data.length > 0) {
                    data.forEach(student => {
                        const div = document.createElement('div');
                        div.className = 'flex items-center space-x-3 p-2 hover:bg-gray-50 rounded';
                        div.innerHTML = `
                            <input type="checkbox" name="student_ids[]" 
                                   value="${student.id}" 
                                   id="student_${student.id}"
                                   class="rounded text-yellow-600 focus:ring-yellow-500"
                                   onchange="toggleStudent(${student.id})">
                            <label for="student_${student.id}" class="flex-1 cursor-pointer">
                                <p class="font-medium">${student.full_name}</p>
                                <p class="text-sm text-gray-500">${student.kelas} - ${student.subkelas}</p>
                            </label>
                        `;
                        container.appendChild(div);
                    });
                } else {
                    container.innerHTML = '<p class="text-gray-500 text-center py-8">No students in this class</p>';
                }
                
                updatePreview();
            });
    }
    
    // Toggle student selection
    function toggleStudent(studentId) {
        const checkbox = document.getElementById(`student_${studentId}`);
        if (checkbox.checked) {
            if (!selectedStudents.includes(studentId)) {
                selectedStudents.push(studentId);
            }
        } else {
            selectedStudents = selectedStudents.filter(id => id !== studentId);
        }
        updatePreview();
    }
    
    // Update schedule preview
    function updatePreview() {
        const kelasSelect = document.getElementById('kelas_id');
        const kelasOption = kelasSelect.options[kelasSelect.selectedIndex];
        const subkelasSelect = document.getElementById('subkelas_id');
        const subkelasOption = subkelasSelect.options[subkelasSelect.selectedIndex];
        const guruSelect = document.getElementById('guru_id');
        const guruOption = guruSelect.options[guruSelect.selectedIndex];
        const tanggalInput = document.querySelector('input[name="tanggal"]');
        const jamMulaiInput = document.querySelector('input[name="jam_mulai"]');
        const jamSelesaiInput = document.querySelector('input[name="jam_selesai"]');
        
        // Update date time preview
        if (tanggalInput.value && jamMulaiInput.value && jamSelesaiInput.value) {
            const date = new Date(tanggalInput.value);
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('previewDateTime').textContent = 
                `${date.toLocaleDateString('id-ID', options)}, ${jamMulaiInput.value} - ${jamSelesaiInput.value}`;
        }
        
        // Update class preview
        if (kelasOption.value && subkelasOption.value) {
            document.getElementById('previewClass').textContent = 
                `${kelasOption.text} - ${subkelasOption.text}`;
        }
        
        // Update teacher preview
        if (guruOption.value) {
            document.getElementById('previewTeacher').textContent = guruOption.text;
        }
        
        // Update students preview
        document.getElementById('previewStudents').textContent = 
            `${selectedStudents.length} students selected`;
    }
    
    // Quick schedule functions
    function setQuickTime(start, end) {
        document.querySelector('input[name="jam_mulai"]').value = start;
        document.querySelector('input[name="jam_selesai"]').value = end;
        updatePreview();
    }
    
    function setToday() {
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('input[name="tanggal"]').value = today;
        updatePreview();
    }
    
    function setTomorrow() {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const tomorrowStr = tomorrow.toISOString().split('T')[0];
        document.querySelector('input[name="tanggal"]').value = tomorrowStr;
        updatePreview();
    }
    
    // Update preview on form changes
    document.addEventListener('DOMContentLoaded', function() {
        const formInputs = document.querySelectorAll('#scheduleForm input, #scheduleForm select');
        formInputs.forEach(input => {
            input.addEventListener('change', updatePreview);
            input.addEventListener('input', updatePreview);
        });
        
        // Initialize preview
        updatePreview();
    });
    
    // Form validation
    document.getElementById('scheduleForm').addEventListener('submit', function(e) {
        const jamMulai = document.querySelector('input[name="jam_mulai"]').value;
        const jamSelesai = document.querySelector('input[name="jam_selesai"]').value;
        
        if (jamMulai >= jamSelesai) {
            e.preventDefault();
            alert('End time must be after start time!');
            return false;
        }
        
        if (selectedStudents.length === 0) {
            e.preventDefault();
            alert('Please select at least one student!');
            return false;
        }
    });
    </script>
</body>
</html>