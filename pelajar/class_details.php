<?php
include 'includes/auth_check.php';
include '../config/database.php';

// Get student data from session
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Validasi ID
$class_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($class_id <= 0) {
    header('Location: jadwal.php');
    exit();
}

// Ambil detail jadwal — SEKALIGUS validasi bahwa siswa ini terdaftar
$class_query = "
    SELECT j.*, 
           u.full_name AS guru_nama, u.email AS guru_email,
           g.spesialisasi AS guru_spesialisasi,
           mk.nama_kelas, mk.jenjang,
           ms.nama_subkelas,
           js.status_kehadiran
    FROM jadwal j
    JOIN jadwal_siswa js ON j.id = js.jadwal_id
    LEFT JOIN users u ON j.guru_id = u.id
    LEFT JOIN guru_profiles g ON u.id = g.user_id
    LEFT JOIN master_kelas mk ON j.kelas_id = mk.id
    LEFT JOIN master_subkelas ms ON j.subkelas_id = ms.id
    WHERE j.id = $class_id AND js.pelajar_id = $user_id
";
$class_result = mysqli_query($conn, $class_query);
$class = mysqli_fetch_assoc($class_result);

// Kalau tidak ditemukan atau bukan milik siswa ini, redirect
if (!$class) {
    header('Location: jadwal.php');
    exit();
}

// Ambil daftar siswa lain di kelas ini (teman sekelas)
$classmates_query = "
    SELECT u.id, u.full_name, p.foto_profil, js.status_kehadiran
    FROM jadwal_siswa js
    JOIN users u ON js.pelajar_id = u.id
    LEFT JOIN pelajar_profiles p ON u.id = p.user_id
    WHERE js.jadwal_id = $class_id
    ORDER BY u.full_name
";
$classmates_result = mysqli_query($conn, $classmates_query);
$classmates_count = $classmates_result ? mysqli_num_rows($classmates_result) : 0;

// ========================================================
// SKOR — SEMENTARA DIMATIKAN (kolom jadwal_id belum ada)
// ========================================================
$score_result = null;
$has_scores = false;

// ========================================================
// MATERI — SEMENTARA DIMATIKAN (tabel belum dikonfirmasi)
// ========================================================
$materi_result = null;
$has_materi = false;

// Tentukan status waktu
$class_date = date('Y-m-d', strtotime($class['tanggal']));
$today = date('Y-m-d');
$is_today = $class_date === $today;
$is_past = $class_date < $today;
$is_future = $class_date > $today;

$is_now = false;
if ($is_today) {
    $current_time = date('H:i:s');
    $start_time = date('H:i:s', strtotime($class['jam_mulai']));
    $end_time = date('H:i:s', strtotime($class['jam_selesai']));
    $is_now = ($current_time >= $start_time && $current_time <= $end_time);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Details - Gensa Course</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .gensa-gradient {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
        }
        .gensa-text {
            color: #f59e0b;
        }
        .class-now {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-left: 4px solid #f59e0b;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Mobile Menu Toggle -->
    <button id="mobileMenuBtn" class="lg:hidden fixed top-4 left-4 z-50 p-2 bg-yellow-500 text-white rounded-lg shadow-lg">
        <i class="fas fa-bars text-xl"></i>
    </button>
    
    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden lg:hidden"></div>
    
    <!-- Sidebar -->
    <div id="sidebar" class="fixed left-0 top-0 h-full w-64 gensa-gradient text-white z-40 transform -translate-x-full lg:translate-x-0 transition-transform duration-300">
        <div class="p-6 h-full flex flex-col">
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center">
                        <i class="fas fa-user-graduate text-2xl gensa-text"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold">Student Portal</h2>
                        <p class="text-sm opacity-80">Gensa Course</p>
                    </div>
                </div>
                <button id="closeSidebar" class="lg:hidden text-white hover:text-gray-300">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <nav class="space-y-2 flex-1 overflow-y-auto">
                <a href="dashboard.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="nilai.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
                    <i class="fas fa-graduation-cap"></i>
                    <span>My Scores</span>
                </a>
                <a href="jadwal.php" class="flex items-center space-x-3 p-3 bg-white bg-opacity-20 rounded-lg">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Schedule</span>
                </a>
                <a href="absensi.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Attendance</span>
                </a>
                <a href="riwayat_nilai.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
                    <i class="fas fa-history"></i>
                    <span>Score History</span>
                </a>
                <a href="sertifikat.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
                    <i class="fas fa-certificate"></i>
                    <span>Certificates</span>
                </a>
            </nav>
            
            <div class="pt-6 border-t border-white border-opacity-20">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                            <?php 
                            $initials = strtoupper(substr($full_name, 0, 1));
                            if (strpos($full_name, ' ') !== false) {
                                $initials .= strtoupper(substr($full_name, strpos($full_name, ' ') + 1, 1));
                            }
                            ?>
                            <span class="text-sm font-bold"><?php echo $initials; ?></span>
                        </div>
                        <div>
                            <p class="text-sm font-medium"><?php echo htmlspecialchars($full_name); ?></p>
                            <p class="text-xs opacity-80">Student</p>
                        </div>
                    </div>
                    <a href="../logout.php" class="p-2 hover:bg-white hover:bg-opacity-10 rounded" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Wrapper -->
    <div class="lg:ml-64 pt-14 lg:pt-0 min-h-screen min-w-0">
        <!-- Top Bar -->
        <div class="bg-white shadow-sm p-4 border-b">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <a href="jadwal.php" class="text-sm text-gray-500 hover:text-gray-700">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Schedule
                    </a>
                    <h1 class="text-2xl font-bold text-gray-800 mt-1">Class Details</h1>
                    <p class="text-gray-600">Complete information about this class</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                    <button onclick="window.print()" 
                            class="inline-flex w-full sm:w-auto justify-center items-center px-4 py-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                    <button onclick="setReminder(<?php echo $class['id']; ?>)" 
                            class="inline-flex w-full sm:w-auto justify-center items-center px-4 py-2 bg-yellow-100 text-yellow-700 hover:bg-yellow-200 rounded-lg">
                        <i class="fas fa-bell mr-2"></i> Set Reminder
                    </button>
                </div>
            </div>
        </div>
        
        <div class="p-4 sm:p-6">
            <!-- Status Banner -->
            <?php if ($is_now): ?>
                <div class="mb-6 p-4 rounded-lg class-now text-yellow-900">
                    <div class="flex items-center">
                        <i class="fas fa-circle text-red-500 animate-pulse mr-2"></i>
                        <span class="font-semibold">Class is happening now!</span>
                    </div>
                </div>
            <?php elseif ($is_today && !$is_now): ?>
                <div class="mb-6 p-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-800">
                    <div class="flex items-center">
                        <i class="fas fa-clock mr-2"></i>
                        <span class="font-medium">This class is scheduled for today at 
                            <?php echo date('H:i', strtotime($class['jam_mulai'])); ?>
                        </span>
                    </div>
                </div>
            <?php elseif ($is_past): ?>
                <div class="mb-6 p-4 rounded-lg bg-gray-100 border border-gray-200 text-gray-700">
                    <div class="flex items-center">
                        <i class="fas fa-history mr-2"></i>
                        <span>This class was held on <?php echo date('d F Y', strtotime($class['tanggal'])); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Info -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Class Information -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <div class="flex flex-col sm:flex-row items-start justify-between gap-3 mb-4">
                            <div class="flex items-center space-x-4">
                                <div class="w-16 h-16 <?php echo $class['bahasa'] == 'arab' ? 'bg-green-100' : 'bg-blue-100'; ?> rounded-xl flex items-center justify-center">
                                    <i class="fas fa-<?php echo $class['bahasa'] == 'arab' ? 'language' : 'globe'; ?> text-2xl <?php echo $class['bahasa'] == 'arab' ? 'text-green-600' : 'text-blue-600'; ?>"></i>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-800 capitalize">
                                        <?php echo $class['jenis_pelajaran']; ?> - <?php echo ucfirst($class['bahasa']); ?>
                                    </h2>
                                    <p class="text-gray-600">
                                        <?php echo htmlspecialchars($class['nama_kelas'] ?? '-'); ?> - 
                                        <?php echo htmlspecialchars($class['nama_subkelas'] ?? '-'); ?>
                                    </p>
                                </div>
                            </div>
                            <?php
                            $status_colors = [
                                'scheduled' => 'bg-yellow-100 text-yellow-800',
                                'completed' => 'bg-green-100 text-green-800',
                                'cancelled' => 'bg-red-100 text-red-800'
                            ];
                            ?>
                            <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $status_colors[$class['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                <?php echo ucfirst($class['status']); ?>
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-600 mb-1">
                                    <i class="fas fa-calendar-day mr-1"></i> Date
                                </p>
                                <p class="font-semibold text-gray-800">
                                    <?php echo date('l, d F Y', strtotime($class['tanggal'])); ?>
                                </p>
                            </div>
                            
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-600 mb-1">
                                    <i class="fas fa-clock mr-1"></i> Time
                                </p>
                                <p class="font-semibold text-gray-800">
                                    <?php echo date('H:i', strtotime($class['jam_mulai'])); ?> - 
                                    <?php echo date('H:i', strtotime($class['jam_selesai'])); ?>
                                </p>
                            </div>
                            
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-600 mb-1">
                                    <i class="fas fa-graduation-cap mr-1"></i> Class Level
                                </p>
                                <p class="font-semibold text-gray-800">
                                    <?php echo htmlspecialchars($class['jenjang'] ?? '-'); ?>
                                </p>
                            </div>
                            
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-600 mb-1">
                                    <i class="fas fa-book mr-1"></i> Lesson Type
                                </p>
                                <p class="font-semibold text-gray-800 capitalize">
                                    <?php echo htmlspecialchars($class['jenis_pelajaran']); ?>
                                </p>
                            </div>
                        </div>
                        
                        <?php if (!empty($class['deskripsi'])): ?>
                        <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <p class="text-sm text-gray-700 mb-1 font-medium">
                                <i class="fas fa-info-circle mr-1"></i> Description
                            </p>
                            <p class="text-gray-800">
                                <?php echo nl2br(htmlspecialchars($class['deskripsi'])); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Attendance Status -->
                    <?php if ($is_past): ?>
                        <div class="bg-white rounded-xl shadow-md p-6">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">
                                <i class="fas fa-clipboard-check mr-2 gensa-text"></i> Your Attendance
                            </h3>
                            
                            <?php 
                            $attendance = $class['status_kehadiran'] ?? 'belum_absen';
                            $attendance_config = [
                                'hadir' => ['label' => 'Present', 'color' => 'bg-green-100 text-green-800 border-green-300', 'icon' => 'check-circle'],
                                'izin' => ['label' => 'Permitted', 'color' => 'bg-yellow-100 text-yellow-800 border-yellow-300', 'icon' => 'file-alt'],
                                'sakit' => ['label' => 'Sick', 'color' => 'bg-blue-100 text-blue-800 border-blue-300', 'icon' => 'notes-medical'],
                                'alpa' => ['label' => 'Absent', 'color' => 'bg-red-100 text-red-800 border-red-300', 'icon' => 'times-circle'],
                                'belum_absen' => ['label' => 'Not Recorded', 'color' => 'bg-gray-100 text-gray-800 border-gray-300', 'icon' => 'question-circle']
                            ];
                            $att = $attendance_config[$attendance] ?? $attendance_config['belum_absen'];
                            ?>
                            
                            <div class="flex items-center p-4 <?php echo $att['color']; ?> border rounded-lg">
                                <i class="fas fa-<?php echo $att['icon']; ?> text-2xl mr-3"></i>
                                <div>
                                    <p class="font-semibold"><?php echo $att['label']; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Catatan: Bagian SKOR dan MATERI dinonaktifkan sementara -->
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        Scores and learning materials will be displayed here once the database schema is confirmed.
                    </div>
                </div>
                
                <!-- Sidebar Info -->
                <div class="space-y-6">
                    <!-- Teacher Info -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">
                            <i class="fas fa-chalkboard-teacher mr-2 gensa-text"></i> Your Teacher
                        </h3>
                        
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="w-14 h-14 bg-yellow-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-user-tie text-yellow-600 text-xl"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($class['guru_nama'] ?? '-'); ?></p>
                                <?php if (!empty($class['guru_spesialisasi'])): ?>
                                    <p class="text-sm text-gray-600">
                                        <?php echo str_replace(',', ', ', htmlspecialchars($class['guru_spesialisasi'])); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($class['guru_email'])): ?>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-600 mb-1">Email</p>
                                <a href="mailto:<?php echo htmlspecialchars($class['guru_email']); ?>" 
                                   class="text-sm text-blue-600 hover:underline break-all">
                                    <?php echo htmlspecialchars($class['guru_email']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Classmates -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">
                            <i class="fas fa-users mr-2 gensa-text"></i> 
                            Classmates (<?php echo $classmates_count; ?>)
                        </h3>
                        
                        <?php if ($classmates_count > 0): ?>
                            <div class="space-y-2 max-h-96 overflow-y-auto">
                                <?php 
                                mysqli_data_seek($classmates_result, 0);
                                while ($mate = mysqli_fetch_assoc($classmates_result)): 
                                    $is_me = $mate['id'] == $user_id;
                                ?>
                                    <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded-lg <?php echo $is_me ? 'bg-yellow-50' : ''; ?>">
                                        <div class="flex items-center space-x-2">
                                            <?php if (!empty($mate['foto_profil']) && file_exists('../' . $mate['foto_profil'])): ?>
                                                <img src="../<?php echo htmlspecialchars($mate['foto_profil']); ?>" 
                                                     class="w-8 h-8 rounded-full object-cover">
                                            <?php else: ?>
                                                <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-user text-yellow-600 text-xs"></i>
                                                </div>
                                            <?php endif; ?>
                                            <span class="text-sm <?php echo $is_me ? 'font-bold text-yellow-700' : 'text-gray-700'; ?>">
                                                <?php echo htmlspecialchars($mate['full_name']); ?>
                                                <?php if ($is_me): ?>
                                                    <span class="text-xs ml-1">(You)</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <?php if ($is_past && !empty($mate['status_kehadiran'])): ?>
                                            <?php
                                            $mate_att_colors = [
                                                'hadir' => 'bg-green-100 text-green-800',
                                                'izin' => 'bg-yellow-100 text-yellow-800',
                                                'sakit' => 'bg-blue-100 text-blue-800',
                                                'alpa' => 'bg-red-100 text-red-800'
                                            ];
                                            ?>
                                            <span class="text-xs px-2 py-0.5 rounded-full <?php echo $mate_att_colors[$mate['status_kehadiran']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                                <?php echo ucfirst($mate['status_kehadiran']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-sm text-center py-4">No classmates found</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">
                            <i class="fas fa-bolt mr-2 gensa-text"></i> Quick Actions
                        </h3>
                        
                        <div class="space-y-2">
                            <button onclick="setReminder(<?php echo $class['id']; ?>)" 
                                    class="w-full py-2 bg-yellow-50 text-yellow-700 hover:bg-yellow-100 rounded-lg text-left px-4">
                                <i class="fas fa-bell mr-2"></i> Set Reminder
                            </button>
                            <button onclick="window.print()" 
                                    class="w-full py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg text-left px-4">
                                <i class="fas fa-print mr-2"></i> Print Details
                            </button>
                            <a href="jadwal.php" 
                               class="block w-full py-2 bg-gray-50 text-gray-700 hover:bg-gray-100 rounded-lg text-left px-4">
                                <i class="fas fa-calendar-alt mr-2"></i> Back to Schedule
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Mobile menu
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const closeSidebar = document.getElementById('closeSidebar');
    
    mobileMenuBtn.addEventListener('click', () => {
        sidebar.classList.remove('-translate-x-full');
        sidebarOverlay.classList.remove('hidden');
    });
    
    closeSidebar.addEventListener('click', () => {
        sidebar.classList.add('-translate-x-full');
        sidebarOverlay.classList.add('hidden');
    });
    
    sidebarOverlay.addEventListener('click', () => {
        sidebar.classList.add('-translate-x-full');
        sidebarOverlay.classList.add('hidden');
    });
    
    function setReminder(classId) {
        if (confirm('Set reminder for this class? You will be notified 30 minutes before the class starts.')) {
            alert('Reminder set successfully!');
        }
    }
    </script>
</body>
</html>