<?php
include '../includes/auth_check.php';
include '../config/database.php';

// Add new class
if (isset($_POST['add_kelas'])) {
    $jenjang = mysqli_real_escape_string($conn, $_POST['jenjang']);
    $nama_kelas = mysqli_real_escape_string($conn, $_POST['nama_kelas']);
    $urutan = mysqli_real_escape_string($conn, $_POST['urutan']);
    
    $query = "INSERT INTO master_kelas (jenjang, nama_kelas, urutan) 
              VALUES ('$jenjang', '$nama_kelas', '$urutan')";
    if (mysqli_query($conn, $query)) {
        $success_kelas = "Class added successfully!";
    } else {
        $error_kelas = "Failed to add class: " . mysqli_error($conn);
    }
}

// Add new subclass
if (isset($_POST['add_subkelas'])) {
    $kelas_id = mysqli_real_escape_string($conn, $_POST['kelas_id']);
    $nama_subkelas = mysqli_real_escape_string($conn, $_POST['nama_subkelas']);
    $urutan = mysqli_real_escape_string($conn, $_POST['urutan_subkelas']);
    $kapasitas = mysqli_real_escape_string($conn, $_POST['kapasitas']);
    
    $query = "INSERT INTO master_subkelas (kelas_id, nama_subkelas, urutan, kapasitas) 
              VALUES ('$kelas_id', '$nama_subkelas', '$urutan', '$kapasitas')";
    if (mysqli_query($conn, $query)) {
        $success_subkelas = "Subclass added successfully!";
    } else {
        $error_subkelas = "Failed to add subclass: " . mysqli_error($conn);
    }
}

// Get all classes with subclasses
$query = "
    SELECT mk.*, 
           GROUP_CONCAT(ms.nama_subkelas ORDER BY ms.urutan SEPARATOR ', ') as subkelas_list,
           COUNT(DISTINCT ms.id) as total_subkelas,
           COUNT(DISTINCT p.user_id) as total_students
    FROM master_kelas mk
    LEFT JOIN master_subkelas ms ON mk.id = ms.kelas_id AND ms.status = 'active'
    LEFT JOIN pelajar_profiles p ON mk.nama_kelas = p.kelas AND ms.nama_subkelas = p.subkelas AND p.status = 'approved'
    WHERE mk.status = 'active'
    GROUP BY mk.id
    ORDER BY mk.urutan
";
$classes = mysqli_query($conn, $query);

// Get classes for dropdown
$kelas_dropdown = mysqli_query($conn, "SELECT * FROM master_kelas WHERE status = 'active' ORDER BY urutan");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes - Admin Gensa Course</title>
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
                    <h1 class="text-2xl font-bold text-gray-800">Manage Classes</h1>
                    <p class="text-gray-600">Add and manage classes and subclasses</p>
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
            <?php if (isset($success_kelas)): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span><?php echo $success_kelas; ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success_subkelas)): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span><?php echo $success_subkelas; ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Add Class Form -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Add New Class</h2>
                    
                    <form method="POST" action="" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Education Level *</label>
                            <select name="jenjang" required 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select">
                                <option value="">Select Level</option>
                                <option value="TK">TK</option>
                                <option value="SD">SD</option>
                                <option value="SMP">SMP</option>
                                <option value="SMA">SMA</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Class Name *</label>
                            <input type="text" name="nama_kelas" required 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa"
                                   placeholder="e.g., Prebeginer, Elementary">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Order *</label>
                            <input type="number" name="urutan" required min="1" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa"
                                   placeholder="1, 2, 3, ...">
                        </div>
                        
                        <button type="submit" name="add_kelas" 
                                class="w-full py-3 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold rounded-lg shadow-md transition btn-gensa">
                            <i class="fas fa-plus mr-2"></i> Add Class
                        </button>
                    </form>
                </div>
                
                <!-- Add Subclass Form -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Add New Subclass</h2>
                    
                    <form method="POST" action="" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Parent Class *</label>
                            <select name="kelas_id" required 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select">
                                <option value="">Select Class</option>
                                <?php while ($kelas = mysqli_fetch_assoc($kelas_dropdown)): ?>
                                    <option value="<?php echo $kelas['id']; ?>">
                                        <?php echo $kelas['jenjang']; ?> - <?php echo $kelas['nama_kelas']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subclass Name *</label>
                            <input type="text" name="nama_subkelas" required 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa"
                                   placeholder="e.g., Kindergarten, Gendkids 1">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Order *</label>
                                <input type="number" name="urutan_subkelas" required min="1" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa"
                                       placeholder="1, 2, 3, ...">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Capacity</label>
                                <input type="number" name="kapasitas" min="1" value="20" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa">
                            </div>
                        </div>
                        
                        <button type="submit" name="add_subkelas" 
                                class="w-full py-3 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold rounded-lg shadow-md transition btn-gensa">
                            <i class="fas fa-plus mr-2"></i> Add Subclass
                        </button>
                    </form>
                </div>
                
                <!-- Class Statistics -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Class Statistics</h2>
                    
                    <?php
                    $stats_query = "
                        SELECT 
                            COUNT(DISTINCT mk.id) as total_classes,
                            COUNT(DISTINCT ms.id) as total_subclasses,
                            SUM(ms.kapasitas) as total_capacity,
                            COUNT(DISTINCT p.user_id) as enrolled_students
                        FROM master_kelas mk
                        LEFT JOIN master_subkelas ms ON mk.id = ms.kelas_id AND ms.status = 'active'
                        LEFT JOIN pelajar_profiles p ON mk.nama_kelas = p.kelas AND ms.nama_subkelas = p.subkelas AND p.status = 'approved'
                        WHERE mk.status = 'active'
                    ";
                    $stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_query));
                    ?>
                    
                    <div class="space-y-4">
                        <div class="p-4 bg-blue-50 rounded-lg">
                            <p class="text-sm text-gray-600">Total Classes</p>
                            <p class="text-2xl font-bold text-blue-600"><?php echo $stats['total_classes']; ?></p>
                        </div>
                        
                        <div class="p-4 bg-green-50 rounded-lg">
                            <p class="text-sm text-gray-600">Total Subclasses</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo $stats['total_subclasses']; ?></p>
                        </div>
                        
                        <div class="p-4 bg-yellow-50 rounded-lg">
                            <p class="text-sm text-gray-600">Enrolled Students</p>
                            <p class="text-2xl font-bold text-yellow-600">
                                <?php echo $stats['enrolled_students']; ?> / <?php echo $stats['total_capacity']; ?>
                            </p>
                            <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-yellow-500 h-2 rounded-full" 
                                     style="width: <?php echo $stats['total_capacity'] > 0 ? ($stats['enrolled_students'] / $stats['total_capacity'] * 100) : 0; ?>%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 pt-6 border-t">
                        <h3 class="font-medium text-gray-700 mb-2">Quick Actions</h3>
                        <div class="space-y-2">
                            <a href="#" class="block p-2 bg-gray-50 hover:bg-gray-100 rounded-lg text-gray-700">
                                <i class="fas fa-file-export mr-2"></i> Export Class List
                            </a>
                            <a href="#" class="block p-2 bg-gray-50 hover:bg-gray-100 rounded-lg text-gray-700">
                                <i class="fas fa-print mr-2"></i> Print Schedule
                            </a>
                            <a href="#" class="block p-2 bg-gray-50 hover:bg-gray-100 rounded-lg text-gray-700">
                                <i class="fas fa-users mr-2"></i> View Student Distribution
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Class List -->
            <div class="mt-6 bg-white rounded-xl shadow-md p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">Class List & Student Distribution</h2>
                    <button onclick="exportClassList()" 
                            class="px-4 py-2 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg">
                        <i class="fas fa-download mr-2"></i> Export
                    </button>
                </div>
                
                <!-- PERUBAHAN 4: Tabel Responsive -->
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px]">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Education Level</th>
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Class</th>
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Subclasses</th>
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Students</th>
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Capacity</th>
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Utilization</th>
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php 
                            mysqli_data_seek($classes, 0); // Reset pointer
                            while ($class = mysqli_fetch_assoc($classes)): 
                                $utilization = $class['total_capacity'] > 0 ? 
                                    ($class['total_students'] / ($class['total_capacity'] ?: 1) * 100) : 0;
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-4 px-4">
                                        <span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-sm font-medium">
                                            <?php echo $class['jenjang']; ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <p class="font-semibold"><?php echo $class['nama_kelas']; ?></p>
                                        <p class="text-sm text-gray-500">Order: <?php echo $class['urutan']; ?></p>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="space-y-1">
                                            <?php 
                                            $subkelas_array = explode(', ', $class['subkelas_list']);
                                            foreach ($subkelas_array as $subkelas):
                                                if ($subkelas): 
                                            ?>
                                                <span class="inline-block px-2 py-1 bg-yellow-50 text-yellow-700 rounded text-xs">
                                                    <?php echo $subkelas; ?>
                                                </span>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                            <?php if ($class['total_subkelas'] == 0): ?>
                                                <span class="text-gray-400 text-sm">No subclasses</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <p class="text-lg font-bold text-gray-800"><?php echo $class['total_students']; ?></p>
                                    </td>
                                    <td class="py-4 px-4">
                                        <p class="text-gray-600"><?php echo $class['total_capacity'] ?: 'N/A'; ?></p>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-24 bg-gray-200 rounded-full h-2">
                                                <div class="bg-<?php echo $utilization > 80 ? 'green' : ($utilization > 50 ? 'yellow' : 'red'); ?>-500 h-2 rounded-full" 
                                                     style="width: <?php echo min($utilization, 100); ?>%"></div>
                                            </div>
                                            <span class="text-sm font-medium"><?php echo round($utilization, 1); ?>%</span>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="flex space-x-1">
                                            <button onclick="editClass(<?php echo $class['id']; ?>)" 
                                                    class="p-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="deleteClass(<?php echo $class['id']; ?>)" 
                                                    class="p-2 bg-red-100 text-red-700 hover:bg-red-200 rounded">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Student Distribution by Class -->
            <div class="mt-6 bg-white rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Student Distribution by Subclass</h2>
                
                <?php
                $distribution_query = "
                    SELECT mk.jenjang, mk.nama_kelas, ms.nama_subkelas, 
                           COUNT(p.user_id) as student_count,
                           ms.kapasitas
                    FROM master_subkelas ms
                    JOIN master_kelas mk ON ms.kelas_id = mk.id AND mk.status = 'active'
                    LEFT JOIN pelajar_profiles p ON mk.nama_kelas = p.kelas AND ms.nama_subkelas = p.subkelas AND p.status = 'approved'
                    WHERE ms.status = 'active'
                    GROUP BY ms.id
                    ORDER BY mk.urutan, ms.urutan
                ";
                $distribution = mysqli_query($conn, $distribution_query);
                ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php while ($item = mysqli_fetch_assoc($distribution)): 
                        $percentage = $item['kapasitas'] > 0 ? ($item['student_count'] / $item['kapasitas'] * 100) : 0;
                    ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <p class="font-semibold text-gray-800"><?php echo $item['nama_subkelas']; ?></p>
                                    <p class="text-sm text-gray-500"><?php echo $item['jenjang']; ?> - <?php echo $item['nama_kelas']; ?></p>
                                </div>
                                <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">
                                    <?php echo $item['student_count']; ?>/<?php echo $item['kapasitas']; ?>
                                </span>
                            </div>
                            
                            <div class="mt-3">
                                <div class="flex justify-between text-sm text-gray-600 mb-1">
                                    <span>Enrollment</span>
                                    <span><?php echo round($percentage, 1); ?>%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-<?php echo $percentage > 80 ? 'green' : ($percentage > 50 ? 'yellow' : 'red'); ?>-500 h-2 rounded-full" 
                                         style="width: <?php echo min($percentage, 100); ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="mt-3 flex space-x-2">
                                <button onclick="viewStudents('<?php echo $item['nama_kelas']; ?>', '<?php echo $item['nama_subkelas']; ?>')" 
                                        class="flex-1 py-1 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded text-sm">
                                    View Students
                                </button>
                                <button onclick="manageClass('<?php echo $item['nama_kelas']; ?>', '<?php echo $item['nama_subkelas']; ?>')" 
                                        class="flex-1 py-1 bg-yellow-50 text-yellow-700 hover:bg-yellow-100 rounded text-sm">
                                    Manage
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function exportClassList() {
        // AJAX to export class list
        window.location.href = 'ajax/export_classes.php';
    }
    
    function editClass(classId) {
        // Open edit modal or redirect to edit page
        window.location.href = `edit_class.php?id=${classId}`;
    }
    
    function deleteClass(classId) {
        if (confirm('Are you sure you want to delete this class? This will also delete all subclasses and student associations!')) {
            window.location.href = `ajax/delete_class.php?id=${classId}`;
        }
    }
    
    function viewStudents(kelas, subkelas) {
        // Open modal with student list
        window.open(`class_students.php?kelas=${encodeURIComponent(kelas)}&subkelas=${encodeURIComponent(subkelas)}`, '_blank');
    }
    
    function manageClass(kelas, subkelas) {
        // Redirect to manage page
        window.location.href = `manage_class_details.php?kelas=${encodeURIComponent(kelas)}&subkelas=${encodeURIComponent(subkelas)}`;
    }
    </script>
</body>
</html>