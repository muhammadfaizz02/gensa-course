<?php
include '../includes/auth_check_guru.php';
include '../config/database.php';

// Get active semester
$semester_query = "SELECT * FROM master_semester WHERE status = 'active' LIMIT 1";
$semester_result = mysqli_query($conn, $semester_query);
$active_semester = mysqli_fetch_assoc($semester_result);

// Get classes taught by this teacher
$kelas_query = "
    SELECT DISTINCT mk.id, mk.jenjang, mk.nama_kelas
    FROM jadwal j
    JOIN master_kelas mk ON j.kelas_id = mk.id
    WHERE j.guru_id = $user_id
    AND j.status = 'scheduled'
    ORDER BY mk.jenjang, mk.urutan
";
$kelas_result = mysqli_query($conn, $kelas_query);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pelajar_id = mysqli_real_escape_string($conn, $_POST['pelajar_id']);
    $jenjang = mysqli_real_escape_string($conn, $_POST['jenjang']);
    $kelas = mysqli_real_escape_string($conn, $_POST['kelas']);
    $subkelas = mysqli_real_escape_string($conn, $_POST['subkelas']);
    $mata_pelajaran = mysqli_real_escape_string($conn, $_POST['mata_pelajaran']);
    $jenis_nilai = mysqli_real_escape_string($conn, $_POST['jenis_nilai']);
    $nilai = mysqli_real_escape_string($conn, $_POST['nilai']);
    $semester = $active_semester ? $active_semester['nama_semester'] : 'Semester 1';
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    
    // Check if score already exists
    $check_query = "
        SELECT id FROM nilai_siswa 
        WHERE pelajar_id = '$pelajar_id' 
        AND mata_pelajaran = '$mata_pelajaran'
        AND jenis_nilai = '$jenis_nilai'
        AND semester = '$semester'
    ";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        $error = "Score for this student and criteria already exists!";
    } else {
        // Insert score
        $insert_query = "
            INSERT INTO nilai_siswa 
            (pelajar_id, jenjang, kelas, subkelas, mata_pelajaran, jenis_nilai, nilai, semester, created_by, keterangan)
            VALUES ('$pelajar_id', '$jenjang', '$kelas', '$subkelas', '$mata_pelajaran', 
                    '$jenis_nilai', '$nilai', '$semester', '$user_id', '$keterangan')
        ";
        
        if (mysqli_query($conn, $insert_query)) {
            $success = "Score successfully added!";
            
            // Clear form
            echo '<script>document.getElementById("scoreForm").reset();</script>';
        } else {
            $error = "Failed to add score: " . mysqli_error($conn);
        }
    }
}

// Get recent scores added by this teacher
$recent_scores_query = "
    SELECT n.*, u.full_name as student_name
    FROM nilai_siswa n
    JOIN users u ON n.pelajar_id = u.id
    WHERE n.created_by = $user_id
    ORDER BY n.created_at DESC
    LIMIT 10
";
$recent_scores = mysqli_query($conn, $recent_scores_query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Scores - Teacher Gensa Course</title>
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
                <a href="input_nilai.php" class="flex items-center space-x-3 p-3 bg-white bg-opacity-20 rounded-lg">
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
                    <h1 class="text-2xl font-bold text-gray-800">Input Student Scores</h1>
                    <p class="text-gray-600">Add scores for your students</p>
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
                <!-- Input Form -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Add New Score</h2>
                        
                        <form method="POST" action="" id="scoreForm" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Education Level *</label>
                                    <select name="jenjang" id="jenjang" required 
                                            onchange="loadKelas()"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select">
                                        <option value="">Select Level</option>
                                        <option value="TK">TK</option>
                                        <option value="SD">SD</option>
                                        <option value="SMP">SMP</option>
                                        <option value="SMA">SMA</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Class Level *</label>
                                    <select name="kelas" id="kelas" required 
                                            onchange="loadSubkelas()"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select">
                                        <option value="">Select Level First</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Sub Class *</label>
                                    <select name="subkelas" id="subkelas" required 
                                            onchange="loadStudents()"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select">
                                        <option value="">Select Class First</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Student *</label>
                                    <select name="pelajar_id" id="pelajar" required 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select">
                                        <option value="">Select Sub Class First</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject *</label>
                                    <select name="mata_pelajaran" required 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select">
                                        <option value="">Select Subject</option>
                                        <option value="arab">Arabic</option>
                                        <option value="inggris">English</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Score Type *</label>
                                    <select name="jenis_nilai" required 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select">
                                        <option value="">Select Type</option>
                                        <option value="listening">Listening</option>
                                        <option value="grammar">Grammar</option>
                                        <option value="speaking">Speaking</option>
                                        <option value="writing">Writing</option>
                                        <option value="reading">Reading</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Score (0-100) *</label>
                                    <input type="number" name="nilai" required min="0" max="100" step="0.01"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa"
                                           placeholder="Enter score">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                                    <input type="text" value="<?php echo $active_semester ? $active_semester['nama_semester'] : 'Semester 1'; ?>" 
                                           readonly
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Note (Optional)</label>
                                <textarea name="keterangan" rows="2" 
                                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent"
                                          placeholder="Add notes about this score..."></textarea>
                            </div>
                            
                            <div class="pt-4">
                                <button type="submit" 
                                        class="w-full py-3 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold rounded-lg shadow-md transition btn-gensa">
                                    <i class="fas fa-save mr-2"></i> Save Score
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Recent Scores -->
                    <div class="mt-6 bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Recently Added Scores</h2>
                        
                        <?php if (mysqli_num_rows($recent_scores) > 0): ?>
                            <!-- PERUBAHAN 4: Tabel Responsive -->
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[640px]">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Student</th>
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Subject</th>
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Type</th>
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Score</th>
                                            <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php while ($score = mysqli_fetch_assoc($recent_scores)): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="py-3 px-4">
                                                    <p class="font-medium"><?php echo $score['student_name']; ?></p>
                                                    <p class="text-sm text-gray-500"><?php echo $score['kelas']; ?> - <?php echo $score['subkelas']; ?></p>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $score['mata_pelajaran'] == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                        <?php echo ucfirst($score['mata_pelajaran']); ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <span class="capitalize"><?php echo $score['jenis_nilai']; ?></span>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $score['nilai'] >= 75 ? 'bg-green-100 text-green-800' : ($score['nilai'] >= 60 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                                        <?php echo $score['nilai']; ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <p class="text-sm"><?php echo date('d M', strtotime($score['created_at'])); ?></p>
                                                    <p class="text-xs text-gray-500">
                                                        <?php echo date('H:i', strtotime($score['created_at'])); ?>
                                                    </p>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-graduation-cap text-4xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500">No scores added yet</p>
                                <p class="text-sm text-gray-400 mt-1">Start by adding your first score</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Teacher's Classes & Statistics -->
                <div>
                    <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Your Classes</h2>
                        
                        <div class="space-y-3">
                            <?php if (mysqli_num_rows($kelas_result) > 0): ?>
                                <?php while ($kelas = mysqli_fetch_assoc($kelas_result)): ?>
                                    <div class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50">
                                        <div class="flex items-center justify-between mb-2">
                                            <div>
                                                <p class="font-semibold"><?php echo $kelas['nama_kelas']; ?></p>
                                                <p class="text-sm text-gray-500"><?php echo $kelas['jenjang']; ?></p>
                                            </div>
                                            <button onclick="selectClass('<?php echo $kelas['jenjang']; ?>', '<?php echo $kelas['nama_kelas']; ?>')" 
                                                    class="px-3 py-1 bg-yellow-100 text-yellow-700 hover:bg-yellow-200 rounded text-sm">
                                                Select
                                            </button>
                                        </div>
                                        
                                        <?php
                                        // Get student count in this class
                                        $student_count = mysqli_fetch_assoc(mysqli_query($conn, "
                                            SELECT COUNT(DISTINCT js.pelajar_id) as count
                                            FROM jadwal j
                                            JOIN jadwal_siswa js ON j.id = js.jadwal_id
                                            WHERE j.guru_id = $user_id
                                            AND j.kelas_id = {$kelas['id']}
                                        "))['count'];
                                        ?>
                                        
                                        <div class="text-sm text-gray-600">
                                            <i class="fas fa-users mr-1"></i> <?php echo $student_count; ?> students
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-school text-3xl text-gray-300 mb-2"></i>
                                    <p class="text-gray-500">No classes assigned</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Score Statistics -->
                    <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Score Statistics</h2>
                        
                        <?php
                        $stats_query = "
                            SELECT 
                                COUNT(*) as total_scores,
                                AVG(nilai) as average_score,
                                MAX(nilai) as highest_score,
                                MIN(nilai) as lowest_score
                            FROM nilai_siswa
                            WHERE created_by = $user_id
                            AND semester = '" . ($active_semester ? $active_semester['nama_semester'] : 'Semester 1') . "'
                        ";
                        $stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_query));
                        ?>
                        
                        <div class="space-y-4">
                            <div class="p-3 bg-blue-50 rounded-lg">
                                <p class="text-sm text-gray-600">Total Scores Added</p>
                                <p class="text-2xl font-bold text-blue-600"><?php echo $stats['total_scores']; ?></p>
                            </div>
                            
                            <div class="p-3 bg-green-50 rounded-lg">
                                <p class="text-sm text-gray-600">Average Score</p>
                                <p class="text-2xl font-bold text-green-600">
                                    <?php echo number_format($stats['average_score'] ?? 0, 1); ?>
                                </p>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <div class="p-3 bg-yellow-50 rounded-lg">
                                    <p class="text-sm text-gray-600">Highest</p>
                                    <p class="text-xl font-bold text-yellow-600"><?php echo $stats['highest_score'] ?? 0; ?></p>
                                </div>
                                
                                <div class="p-3 bg-red-50 rounded-lg">
                                    <p class="text-sm text-gray-600">Lowest</p>
                                    <p class="text-xl font-bold text-red-600"><?php echo $stats['lowest_score'] ?? 0; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Quick Actions</h2>
                        
                        <div class="space-y-3">
                            <button onclick="bulkInput()" 
                                    class="w-full py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg">
                                <i class="fas fa-file-import mr-2"></i> Bulk Score Input
                            </button>
                            <a href="print_scores.php" 
                               class="block w-full py-2 bg-green-50 text-green-700 hover:bg-green-100 rounded-lg text-center">
                                <i class="fas fa-print mr-2"></i> Print Score Report
                            </a>
                            <button onclick="viewScoreDistribution()" 
                                    class="w-full py-2 bg-purple-50 text-purple-700 hover:bg-purple-100 rounded-lg">
                                <i class="fas fa-chart-pie mr-2"></i> View Distribution
                            </button>
                        </div>
                    </div>
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
    
    // Function to auto-select class
    function selectClass(jenjang, kelasNama) {
        document.getElementById('jenjang').value = jenjang;
        loadKelas();
        
        setTimeout(() => {
            const kelasSelect = document.getElementById('kelas');
            
            for (let i = 0; i < kelasSelect.options.length; i++) {
                if (kelasSelect.options[i].text === kelasNama) {
                    kelasSelect.selectedIndex = i;
                    loadSubkelas();
                    break;
                }
            }
        }, 500);
    }
    
    // AJAX to load classes based on jenjang
    function loadKelas() {
        const jenjang = document.getElementById('jenjang').value;
        if (!jenjang) return;
        
        fetch(`ajax/get_kelas_guru.php?jenjang=${jenjang}&guru_id=<?php echo $user_id; ?>`)
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('kelas');
                select.innerHTML = '<option value="">Select Class Level</option>';
                
                data.forEach(kelas => {
                    const option = document.createElement('option');
                    
                    option.value = kelas.id;               // 🔥 WAJIB ID
                    option.textContent = kelas.nama_kelas;
                    
                    select.appendChild(option);
                });
                
                document.getElementById('subkelas').innerHTML =
                  '<option value="">Select Class First</option>';
                
                document.getElementById('pelajar').innerHTML =
                  '<option value="">Select Sub Class First</option>';
            });
    }
    
    // AJAX to load subclasses based on kelas
    function loadSubkelas() {
        const kelasId = document.getElementById('kelas').value;   // <-- sudah ID
        const jenjang = document.getElementById('jenjang').value;
        
        if (!kelasId || !jenjang) return;
        
        fetch(`ajax/get_subkelas_guru.php?jenjang=${jenjang}&kelas_id=${kelasId}`)
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('subkelas');
                select.innerHTML = '<option value="">Select Sub Class</option>';
                
                data.forEach(subkelas => {
                    const option = document.createElement('option');
                    option.value = subkelas.id;               // <-- pakai ID
                    option.textContent = subkelas.nama_subkelas;
                    select.appendChild(option);
                });
                
                document.getElementById('pelajar').innerHTML =
                    '<option value="">Select Sub Class First</option>';
            })
            .catch(err => console.error(err));
    }
    
    // AJAX to load students based on subkelas
    function loadStudents() {
        const subkelas = document.getElementById('subkelas').value;
        const kelas = document.getElementById('kelas').value;
        if (!subkelas || !kelas) return;
        
        fetch(`ajax/get_students_guru.php?kelas=${kelas}&subkelas=${subkelas}&guru_id=<?php echo $user_id; ?>`)
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('pelajar');
                select.innerHTML = '<option value="">Select Student</option>';
                data.forEach(student => {
                    const option = document.createElement('option');
                    option.value = student.id;
                    option.textContent = student.full_name;
                    select.appendChild(option);
                });
            });
    }
    
    // Form validation
    document.getElementById('scoreForm').addEventListener('submit', function(e) {
        const score = document.querySelector('input[name="nilai"]').value;
        if (score < 0 || score > 100) {
            e.preventDefault();
            alert('Score must be between 0 and 100');
        }
    });
    
    // Quick action functions
    function bulkInput() {
        alert('Bulk input feature coming soon!');
    }
    
    function viewScoreDistribution() {
        window.open('score_distribution.php', '_blank');
    }
    
    // Initialize with teacher's first class if available
    document.addEventListener('DOMContentLoaded', function() {
        <?php 
        if ($kelas_result && mysqli_num_rows($kelas_result) > 0) {
            mysqli_data_seek($kelas_result, 0);
            if ($first_class = mysqli_fetch_assoc($kelas_result)) {
        ?>
            selectClass('<?php echo $first_class['jenjang']; ?>', '<?php echo $first_class['nama_kelas']; ?>');
        <?php 
            }
        }
        ?>
    });
    </script>
</body>
</html>