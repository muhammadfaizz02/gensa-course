<?php
include '../includes/auth_check.php';
include '../config/database.php';

// Get active semester
$semester_query = "SELECT * FROM master_semester WHERE status = 'active' LIMIT 1";
$semester_result = mysqli_query($conn, $semester_query);
$active_semester = mysqli_fetch_assoc($semester_result);

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
            (pelajar_id, jenjang, kelas, subkelas, mata_pelajaran, jenis_nilai, nilai, semester, created_by)
            VALUES ('$pelajar_id', '$jenjang', '$kelas', '$subkelas', '$mata_pelajaran', '$jenis_nilai', '$nilai', '$semester', '$user_id')
        ";
        
        if (mysqli_query($conn, $insert_query)) {
            $success = "Score successfully added!";
        } else {
            $error = "Failed to add score: " . mysqli_error($conn);
        }
    }
}

// Get all classes
$kelas_query = "SELECT DISTINCT nama_kelas as kelas FROM master_kelas WHERE status = 'active'";
$kelas_result = mysqli_query($conn, $kelas_query);

// Get all education levels
$jenjang_query = "SELECT DISTINCT jenjang FROM master_kelas WHERE status = 'active'";
$jenjang_result = mysqli_query($conn, $jenjang_query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Scores - Admin Gensa Course</title>
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
            <!-- PERUBAHAN 2: Header Responsive (flex-col di HP, flex-row di Desktop) -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Input Student Scores</h1>
                    <p class="text-gray-600">Add scores for students</p>
                </div>
                <div>
                    <!-- Tombol full width di HP -->
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
                                        <?php while ($jenjang = mysqli_fetch_assoc($jenjang_result)): ?>
                                            <option value="<?php echo $jenjang['jenjang']; ?>">
                                                <?php echo $jenjang['jenjang']; ?>
                                            </option>
                                        <?php endwhile; ?>
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
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Recent Scores Added</h2>
                        
                        <?php
                        $recent_scores = mysqli_query($conn, "
                            SELECT n.*, u.full_name as student_name, a.full_name as added_by
                            FROM nilai_siswa n
                            JOIN users u ON n.pelajar_id = u.id
                            LEFT JOIN users a ON n.created_by = a.id
                            ORDER BY n.created_at DESC
                            LIMIT 10
                        ");
                        ?>
                        
                        <!-- PERUBAHAN 4: Tabel Responsive -->
                        <!-- overflow-x-auto tetap ada untuk scroll, dan table diberi min-w-[640px] -->
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[640px]">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Student</th>
                                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Subject</th>
                                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Type</th>
                                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Score</th>
                                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Added By</th>
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
                                                <p class="text-sm"><?php echo $score['added_by'] ?: 'Admin'; ?></p>
                                                <p class="text-xs text-gray-500">
                                                    <?php echo date('d M', strtotime($score['created_at'])); ?>
                                                </p>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Score Summary -->
                <div>
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
                            WHERE semester = '" . ($active_semester ? $active_semester['nama_semester'] : 'Semester 1') . "'
                        ";
                        $stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_query));
                        ?>
                        
                        <div class="space-y-4">
                            <div class="p-4 bg-blue-50 rounded-lg">
                                <p class="text-sm text-gray-600">Total Scores</p>
                                <p class="text-2xl font-bold text-blue-600"><?php echo $stats['total_scores']; ?></p>
                            </div>
                            
                            <div class="p-4 bg-green-50 rounded-lg">
                                <p class="text-sm text-gray-600">Average Score</p>
                                <p class="text-2xl font-bold text-green-600">
                                    <?php echo number_format($stats['average_score'] ?? 0, 1); ?>
                                </p>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div class="p-4 bg-yellow-50 rounded-lg">
                                    <p class="text-sm text-gray-600">Highest</p>
                                    <p class="text-xl font-bold text-yellow-600"><?php echo $stats['highest_score'] ?? 0; ?></p>
                                </div>
                                
                                <div class="p-4 bg-red-50 rounded-lg">
                                    <p class="text-sm text-gray-600">Lowest</p>
                                    <p class="text-xl font-bold text-red-600"><?php echo $stats['lowest_score'] ?? 0; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 pt-6 border-t">
                            <h3 class="font-medium text-gray-700 mb-2">Quick Filters</h3>
                            <div class="space-y-2">
                                <a href="?filter=arab" class="block p-2 bg-green-50 hover:bg-green-100 rounded-lg text-green-700">
                                    <i class="fas fa-language mr-2"></i> Arabic Scores
                                </a>
                                <a href="?filter=inggris" class="block p-2 bg-blue-50 hover:bg-blue-100 rounded-lg text-blue-700">
                                    <i class="fas fa-language mr-2"></i> English Scores
                                </a>
                                <a href="?filter=low" class="block p-2 bg-red-50 hover:bg-red-100 rounded-lg text-red-700">
                                    <i class="fas fa-exclamation-triangle mr-2"></i> Low Scores (< 60)
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bulk Upload -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Bulk Upload</h2>
                        <div class="space-y-3">
                            <p class="text-sm text-gray-600">Upload multiple scores via Excel file</p>
                            <a href="templates/scores_template.xlsx" 
                               class="inline-flex items-center px-4 py-2 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg">
                                <i class="fas fa-download mr-2"></i> Download Template
                            </a>
                            <form class="space-y-3">
                                <input type="file" accept=".xlsx,.xls" 
                                       class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100">
                                <button type="button" 
                                        class="w-full py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg">
                                    <i class="fas fa-upload mr-2"></i> Upload Excel
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // AJAX to load classes based on jenjang
    function loadKelas() {
        const jenjang = document.getElementById('jenjang').value;
        if (!jenjang) return;
        
        fetch(`ajax/get_kelas.php?jenjang=${jenjang}`)
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('kelas');
                select.innerHTML = '<option value="">Select Class Level</option>';
                data.forEach(kelas => {
                    const option = document.createElement('option');
                    option.value = kelas.nama_kelas;
                    option.textContent = kelas.nama_kelas;
                    select.appendChild(option);
                });
                
                // Clear dependent fields
                document.getElementById('subkelas').innerHTML = '<option value="">Select Class First</option>';
                document.getElementById('pelajar').innerHTML = '<option value="">Select Sub Class First</option>';
            });
    }
    
    // AJAX to load subclasses based on kelas
    function loadSubkelas() {
        const kelas = document.getElementById('kelas').value;
        const jenjang = document.getElementById('jenjang').value;
        if (!kelas || !jenjang) return;
        
        fetch(`ajax/get_subkelas.php?jenjang=${jenjang}&kelas=${kelas}`)
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('subkelas');
                select.innerHTML = '<option value="">Select Sub Class</option>';
                data.forEach(subkelas => {
                    const option = document.createElement('option');
                    option.value = subkelas.nama_subkelas;
                    option.textContent = subkelas.nama_subkelas;
                    select.appendChild(option);
                });
                
                // Clear dependent field
                document.getElementById('pelajar').innerHTML = '<option value="">Select Sub Class First</option>';
            });
    }
    
    // AJAX to load students based on subkelas
    function loadStudents() {
        const subkelas = document.getElementById('subkelas').value;
        const kelas = document.getElementById('kelas').value;
        if (!subkelas || !kelas) return;
        
        fetch(`ajax/get_students.php?kelas=${kelas}&subkelas=${subkelas}`)
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
    </script>
</body>
</html>