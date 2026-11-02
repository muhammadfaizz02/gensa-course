<?php
include '../includes/auth_check.php';
include '../config/database.php';

// Cek apakah ada schedule_id di session
if (!isset($_SESSION['edit_schedule_id'])) {
    header('Location: view_schedule.php');
    exit();
}

$schedule_id = (int) $_SESSION['edit_schedule_id'];

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
    
    // Check for schedule conflict (kecuali jadwal yang sedang diedit)
    $conflict_query = "
        SELECT * FROM jadwal 
        WHERE guru_id = '$guru_id' 
        AND tanggal = '$tanggal'
        AND id != $schedule_id
        AND (
            (jam_mulai < '$jam_selesai' AND jam_selesai > '$jam_mulai')
            OR jam_mulai = '$jam_mulai'
        )
        AND status = 'scheduled'
    ";
    
    $conflict_result = mysqli_query($conn, $conflict_query);
    
    if (mysqli_num_rows($conflict_result) > 0) {
        $error = "Teacher already has a schedule at this time!";
    } else if (count($student_ids) === 0) {
        $error = "Please select at least one student!";
    } else {
        mysqli_begin_transaction($conn);
        
        try {
            // Update schedule
            $update_query = "
                UPDATE jadwal SET
                    kelas_id = '$kelas_id',
                    subkelas_id = '$subkelas_id',
                    guru_id = '$guru_id',
                    tanggal = '$tanggal',
                    jam_mulai = '$jam_mulai',
                    jam_selesai = '$jam_selesai',
                    bahasa = '$bahasa',
                    jenis_pelajaran = '$jenis_pelajaran',
                    deskripsi = '$deskripsi',
                    updated_at = NOW()
                WHERE id = $schedule_id
            ";
            
            if (!mysqli_query($conn, $update_query)) {
                throw new Exception(mysqli_error($conn));
            }
            
            // Hapus siswa lama, ganti dengan yang baru
            mysqli_query($conn, "DELETE FROM jadwal_siswa WHERE jadwal_id = $schedule_id");
            
            foreach ($student_ids as $student_id) {
                $student_id = mysqli_real_escape_string($conn, $student_id);
                mysqli_query($conn, "
                    INSERT INTO jadwal_siswa (jadwal_id, pelajar_id)
                    VALUES ('$schedule_id', '$student_id')
                ");
            }
            
            mysqli_commit($conn);
            unset($_SESSION['edit_schedule_id']);
            
            header('Location: view_schedule.php?success=updated');
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Failed to update schedule: " . $e->getMessage();
        }
    }
}

// Ambil data jadwal yang akan diedit
$schedule_query = "
    SELECT j.*, 
           mk.nama_kelas, mk.jenjang,
           ms.nama_subkelas,
           u.full_name as guru_nama
    FROM jadwal j
    LEFT JOIN master_kelas mk ON j.kelas_id = mk.id
    LEFT JOIN master_subkelas ms ON j.subkelas_id = ms.id
    LEFT JOIN users u ON j.guru_id = u.id
    WHERE j.id = $schedule_id
";
$schedule_result = mysqli_query($conn, $schedule_query);
$schedule = mysqli_fetch_assoc($schedule_result);

if (!$schedule) {
    unset($_SESSION['edit_schedule_id']);
    header('Location: view_schedule.php');
    exit();
}

// Ambil siswa yang sudah terdaftar di jadwal ini
$enrolled_students = [];
$enrolled_query = "SELECT pelajar_id FROM jadwal_siswa WHERE jadwal_id = $schedule_id";
$enrolled_result = mysqli_query($conn, $enrolled_query);
while ($row = mysqli_fetch_assoc($enrolled_result)) {
    $enrolled_students[] = (int) $row['pelajar_id'];
}

// Data dropdown
$kelas_result = mysqli_query($conn, "SELECT * FROM master_kelas WHERE status = 'active' ORDER BY urutan");
$guru_result = mysqli_query($conn, "
    SELECT u.id, u.full_name, g.spesialisasi 
    FROM users u 
    LEFT JOIN guru_profiles g ON u.id = g.user_id 
    WHERE u.role = 'guru' 
    ORDER BY u.full_name
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Schedule - Admin Gensa Course</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="lg:ml-64 pt-14 lg:pt-0 min-h-screen min-w-0">
        
        <!-- Top Bar -->
        <div class="bg-white shadow-sm p-4 border-b">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Edit Schedule</h1>
                    <p class="text-gray-600">Update teaching schedule information</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                    <a href="view_schedule.php" 
                       class="inline-flex w-full sm:w-auto justify-center items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Schedules
                    </a>
                </div>
            </div>
        </div>
        
        <div class="p-4 sm:p-6">
            <?php if (isset($error)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Edit Form -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-bold text-gray-800">Schedule Information</h2>
                            <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">
                                ID: #<?php echo $schedule['id']; ?>
                            </span>
                        </div>
                        
                        <form method="POST" action="" id="scheduleForm" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Class Level *</label>
                                    <select name="kelas_id" id="kelas_id" required 
                                            onchange="loadSubkelas()"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select">
                                        <option value="">Select Class</option>
                                        <?php 
                                        mysqli_data_seek($kelas_result, 0);
                                        while ($kelas = mysqli_fetch_assoc($kelas_result)): 
                                        ?>
                                            <option value="<?php echo $kelas['id']; ?>" 
                                                    data-jenjang="<?php echo $kelas['jenjang']; ?>"
                                                    <?php echo $kelas['id'] == $schedule['kelas_id'] ? 'selected' : ''; ?>>
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
                                        <option value="">Loading...</option>
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
                                                    data-specialization="<?php echo $guru['spesialisasi']; ?>"
                                                    <?php echo $guru['id'] == $schedule['guru_id'] ? 'selected' : ''; ?>>
                                                <?php echo $guru['full_name']; ?>
                                                (<?php echo str_replace(',', '/', $guru['spesialisasi']); ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                                    <input type="date" name="tanggal" required 
                                           value="<?php echo $schedule['tanggal']; ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Time *</label>
                                    <input type="time" name="jam_mulai" required 
                                           value="<?php echo date('H:i', strtotime($schedule['jam_mulai'])); ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">End Time *</label>
                                    <input type="time" name="jam_selesai" required 
                                           value="<?php echo date('H:i', strtotime($schedule['jam_selesai'])); ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Language *</label>
                                    <select name="bahasa" required 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select">
                                        <option value="">Select Language</option>
                                        <option value="arab" <?php echo $schedule['bahasa'] == 'arab' ? 'selected' : ''; ?>>Arabic</option>
                                        <option value="inggris" <?php echo $schedule['bahasa'] == 'inggris' ? 'selected' : ''; ?>>English</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Lesson Type *</label>
                                    <select name="jenis_pelajaran" required 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select">
                                        <option value="">Select Type</option>
                                        <option value="listening" <?php echo $schedule['jenis_pelajaran'] == 'listening' ? 'selected' : ''; ?>>Listening</option>
                                        <option value="grammar" <?php echo $schedule['jenis_pelajaran'] == 'grammar' ? 'selected' : ''; ?>>Grammar</option>
                                        <option value="speaking" <?php echo $schedule['jenis_pelajaran'] == 'speaking' ? 'selected' : ''; ?>>Speaking</option>
                                        <option value="writing" <?php echo $schedule['jenis_pelajaran'] == 'writing' ? 'selected' : ''; ?>>Writing</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea name="deskripsi" rows="3" 
                                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent"
                                          placeholder="Enter schedule description (optional)"><?php echo htmlspecialchars($schedule['deskripsi']); ?></textarea>
                            </div>
                            
                            <!-- Student Selection -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select Students</label>
                                <div id="studentsContainer" class="border border-gray-300 rounded-lg p-4 min-h-[200px] max-h-[300px] overflow-y-auto">
                                    <p class="text-gray-500 text-center py-8">
                                        <i class="fas fa-spinner fa-spin mr-2"></i> Loading students...
                                    </p>
                                </div>
                            </div>
                            
                            <div class="pt-4 flex flex-col sm:flex-row gap-3">
                                <button type="submit" 
                                        class="flex-1 py-3 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold rounded-lg shadow-md transition btn-gensa">
                                    <i class="fas fa-save mr-2"></i> Update Schedule
                                </button>
                                <a href="view_schedule.php" 
                                   class="flex-1 sm:flex-none sm:px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg text-center">
                                    <i class="fas fa-times mr-2"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Side Panel -->
                <div class="space-y-6">
                    <!-- Schedule Info -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Schedule Info</h2>
                        
                        <div class="space-y-3">
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-600">Created At</p>
                                <p class="font-medium text-sm">
                                    <?php echo date('d M Y, H:i', strtotime($schedule['created_at'])); ?>
                                </p>
                            </div>
                            
                            <?php if (!empty($schedule['updated_at'])): ?>
                            <div class="p-3 bg-blue-50 rounded-lg">
                                <p class="text-sm text-gray-600">Last Updated</p>
                                <p class="font-medium text-sm text-blue-700">
                                    <?php echo date('d M Y, H:i', strtotime($schedule['updated_at'])); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-600">Status</p>
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-medium 
                                    <?php 
                                    $status_colors = [
                                        'scheduled' => 'bg-yellow-100 text-yellow-800',
                                        'completed' => 'bg-green-100 text-green-800',
                                        'cancelled' => 'bg-red-100 text-red-800'
                                    ];
                                    echo $status_colors[$schedule['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>">
                                    <?php echo ucfirst($schedule['status']); ?>
                                </span>
                            </div>
                            
                            <div class="p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                <p class="text-sm text-yellow-800">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Only <b>scheduled</b> schedules can be edited.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Schedule Preview -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Schedule Preview</h2>
                        
                        <div class="space-y-3">
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-600">Date & Time</p>
                                <p class="font-medium" id="previewDateTime">Not set</p>
                            </div>
                            
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-600">Class</p>
                                <p class="font-medium" id="previewClass">Not set</p>
                            </div>
                            
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-600">Teacher</p>
                                <p class="font-medium" id="previewTeacher">Not set</p>
                            </div>
                            
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-600">Students</p>
                                <p class="font-medium" id="previewStudents">0 selected</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Time -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Quick Time</h2>
                        
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Data siswa yang sudah terdaftar (dari PHP)
    const enrolledStudents = <?php echo json_encode($enrolled_students); ?>;
    let selectedStudents = [...enrolledStudents];
    
    // Subkelas terpilih dari jadwal ini
    const currentSubkelasId = <?php echo (int) $schedule['subkelas_id']; ?>;
    const currentKelasId = <?php echo (int) $schedule['kelas_id']; ?>;
    
    // Load subkelas saat halaman pertama dibuka
    document.addEventListener('DOMContentLoaded', function() {
        loadSubkelasOnInit();
        
        // Update preview on form changes
        const formInputs = document.querySelectorAll('#scheduleForm input, #scheduleForm select');
        formInputs.forEach(input => {
            input.addEventListener('change', updatePreview);
            input.addEventListener('input', updatePreview);
        });
    });
    
    // Load subkelas pertama kali (initial state)
    function loadSubkelasOnInit() {
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
                    if (subkelas.id == currentSubkelasId) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
                
                // Setelah subkelas ke-set, load siswa
                loadStudents();
                updatePreview();
            });
    }
    
    // Load subkelas saat kelas diganti
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
                
                // Reset siswa saat kelas diganti
                selectedStudents = [];
                document.getElementById('studentsContainer').innerHTML = 
                    '<p class="text-gray-500 text-center py-8">Select sub class to load students</p>';
                
                updatePreview();
            });
    }
    
    // Load siswa berdasarkan subkelas
    function loadStudents() {
        const subkelasId = document.getElementById('subkelas_id').value;
        if (!subkelasId) return;
        
        fetch(`ajax/get_students_by_subkelas.php?subkelas_id=${subkelasId}`)
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('studentsContainer');
                container.innerHTML = '';
                
                const students = Array.isArray(data) ? data : (data.data || []);
                
                if (students.length > 0) {
                    students.forEach(student => {
                        const isEnrolled = enrolledStudents.includes(parseInt(student.id));
                        const div = document.createElement('div');
                        div.className = 'flex items-center space-x-3 p-2 hover:bg-gray-50 rounded';
                        div.innerHTML = `
                            <input type="checkbox" name="student_ids[]" 
                                   value="${student.id}" 
                                   id="student_${student.id}"
                                   class="rounded text-yellow-600 focus:ring-yellow-500"
                                   ${isEnrolled ? 'checked' : ''}
                                   onchange="toggleStudent(${student.id})">
                            <label for="student_${student.id}" class="flex-1 cursor-pointer">
                                <p class="font-medium">${student.full_name}</p>
                                <p class="text-sm text-gray-500">${student.kelas || ''} - ${student.subkelas || ''}</p>
                            </label>
                        `;
                        container.appendChild(div);
                    });
                    
                    // Pastikan selectedStudents sinkron
                    selectedStudents = students
                        .filter(s => enrolledStudents.includes(parseInt(s.id)))
                        .map(s => parseInt(s.id));
                } else {
                    container.innerHTML = '<p class="text-gray-500 text-center py-8">No students in this class</p>';
                    selectedStudents = [];
                }
                
                updatePreview();
            })
            .catch(error => {
                console.error('Error fetching students:', error);
                document.getElementById('studentsContainer').innerHTML = 
                    '<p class="text-red-500 text-center py-8">Failed to load students</p>';
            });
    }
    
    // Toggle siswa
    function toggleStudent(studentId) {
        const checkbox = document.getElementById(`student_${studentId}`);
        const id = parseInt(studentId);
        
        if (checkbox.checked) {
            if (!selectedStudents.includes(id)) {
                selectedStudents.push(id);
            }
        } else {
            selectedStudents = selectedStudents.filter(sid => sid !== id);
        }
        updatePreview();
    }
    
    // Update preview
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
        
        if (tanggalInput.value && jamMulaiInput.value && jamSelesaiInput.value) {
            const date = new Date(tanggalInput.value);
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('previewDateTime').textContent = 
                `${date.toLocaleDateString('id-ID', options)}, ${jamMulaiInput.value} - ${jamSelesaiInput.value}`;
        }
        
        if (kelasOption.value && subkelasOption && subkelasOption.value) {
            document.getElementById('previewClass').textContent = 
                `${kelasOption.text} - ${subkelasOption.text}`;
        }
        
        if (guruOption && guruOption.value) {
            document.getElementById('previewTeacher').textContent = guruOption.text;
        }
        
        document.getElementById('previewStudents').textContent = 
            `${selectedStudents.length} student${selectedStudents.length !== 1 ? 's' : ''} selected`;
    }
    
    // Quick time
    function setQuickTime(start, end) {
        document.querySelector('input[name="jam_mulai"]').value = start;
        document.querySelector('input[name="jam_selesai"]').value = end;
        updatePreview();
    }
    
    // Validasi form
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