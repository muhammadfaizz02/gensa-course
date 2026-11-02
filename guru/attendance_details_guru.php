<?php
include '../includes/auth_check_guru.php';
include '../config/database.php';

$attendance_id = (int) ($_GET['id'] ?? 0);
if ($attendance_id <= 0) {
    header('Location: rekap_kehadiran.php');
    exit();
}

// Pastikan $user_id ada
if (!isset($user_id) || !$user_id) {
    $user_id = $_SESSION['user_id'] ?? $_SESSION['guru_id'] ?? 0;
}
$user_id = (int) $user_id;

// Ambil data — hanya milik guru yang login
$query = "
    SELECT k.*, a.full_name AS approved_by_name
    FROM kehadiran_guru k
    LEFT JOIN users a ON k.approved_by = a.id
    WHERE k.id = $attendance_id AND k.guru_id = $user_id
    LIMIT 1
";
$attendance = mysqli_fetch_assoc(mysqli_query($conn, $query));

if (!$attendance) {
    $_SESSION['error'] = "Data absensi tidak ditemukan atau bukan milik Anda.";
    header('Location: rekap_kehadiran.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Details - Teacher Gensa Course</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-50">

    <!-- Mobile Menu -->
    <button id="mobileMenuBtn" class="lg:hidden fixed top-4 left-4 z-50 p-2 bg-yellow-500 text-white rounded-lg shadow-lg">
        <i class="fas fa-bars text-xl"></i>
    </button>
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
                    <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
                </a>
                <a href="attendance.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
                    <i class="fas fa-calendar-check"></i><span>Attendance</span>
                </a>
                <a href="input_nilai.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
                    <i class="fas fa-graduation-cap"></i><span>Input Scores</span>
                </a>
                <a href="absensi_siswa.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
                    <i class="fas fa-user-check"></i><span>Student Attendance</span>
                </a>
                <a href="jadwal.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
                    <i class="fas fa-calendar-alt"></i><span>Teaching Schedule</span>
                </a>
                <a href="rekap_kehadiran.php" class="flex items-center space-x-3 p-3 bg-white bg-opacity-20 rounded-lg">
                    <i class="fas fa-chart-bar"></i><span>Attendance Report</span>
                </a>
                <a href="../logout.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition mt-8">
                    <i class="fas fa-sign-out-alt"></i><span>Logout</span>
                </a>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="lg:ml-64 pt-14 lg:pt-0 min-h-screen min-w-0">
        <div class="bg-white shadow-sm p-4 border-b">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Attendance Details</h1>
                    <p class="text-gray-600">ID: #<?php echo str_pad($attendance_id, 6, '0', STR_PAD_LEFT); ?></p>
                </div>
                <div class="flex gap-2">
                    <button onclick="window.print()" class="px-4 py-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                    <a href="rekap_kehadiran.php" class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <div class="p-4 sm:p-6">
            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
                    <!-- Status Banner -->
                    <div class="<?php
                        echo $attendance['status'] == 'approved' ? 'bg-green-500' :
                            ($attendance['status'] == 'rejected' ? 'bg-red-500' : 'bg-yellow-500');
                    ?> text-white p-4">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div class="flex items-center">
                                <i class="fas fa-<?php
                                    echo $attendance['status'] == 'approved' ? 'check-circle' :
                                        ($attendance['status'] == 'rejected' ? 'times-circle' : 'clock');
                                ?> text-2xl mr-3"></i>
                                <div>
                                    <h2 class="text-xl font-bold">Attendance <?php echo ucfirst($attendance['status']); ?></h2>
                                    <p class="opacity-90">
                                        <?php
                                        if ($attendance['status'] == 'approved') echo 'Disetujui oleh ' . htmlspecialchars($attendance['approved_by_name'] ?? '-');
                                        elseif ($attendance['status'] == 'rejected') echo 'Ditolak oleh ' . htmlspecialchars($attendance['approved_by_name'] ?? '-');
                                        else echo 'Menunggu persetujuan admin';
                                        ?>
                                    </p>
                                </div>
                            </div>
                            <span class="px-3 py-1 bg-white bg-opacity-20 rounded-full text-sm">
                                <?php echo date('l, d F Y', strtotime($attendance['tanggal'])); ?>
                            </span>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-sm font-medium text-gray-600 mb-1">Jam Mengajar</p>
                                <p class="text-xl font-bold text-gray-800">
                                    <?php echo date('H:i', strtotime($attendance['jam_mulai'])); ?> -
                                    <?php echo date('H:i', strtotime($attendance['jam_selesai'])); ?>
                                </p>
                                <p class="text-sm text-gray-600">
                                    Durasi:
                                    <?php
                                    $duration = (strtotime($attendance['jam_selesai']) - strtotime($attendance['jam_mulai'])) / 3600;
                                    echo number_format($duration, 2);
                                    ?> jam
                                </p>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-sm font-medium text-gray-600 mb-1">Lokasi</p>
                                <p class="text-lg font-medium text-gray-800"><?php echo htmlspecialchars($attendance['lokasi'] ?? '-'); ?></p>
                                <p class="text-sm text-gray-600">GPS Verified</p>
                            </div>
                            <?php if (!empty($attendance['jenis_kehadiran'])): ?>
                            <div class="p-4 bg-gray-50 rounded-lg md:col-span-2">
                                <p class="text-sm font-medium text-gray-600 mb-1">Jenis Kehadiran</p>
                                <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">
                                    <?php echo ucfirst(htmlspecialchars($attendance['jenis_kehadiran'])); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Catatan Guru -->
                        <?php if (!empty($attendance['catatan'])): ?>
                            <div class="mb-6">
                                <h4 class="font-medium text-gray-800 mb-2">Catatan Guru</h4>
                                <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-r">
                                    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($attendance['catatan'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Catatan Admin (kalau ada) -->
                        <?php if (!empty($attendance['catatan_admin'])): ?>
                            <div class="mb-6">
                                <h4 class="font-medium text-gray-800 mb-2">Catatan Admin</h4>
                                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r">
                                    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($attendance['catatan_admin'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Approval Info -->
                        <?php if (!empty($attendance['approved_at'])): ?>
                            <div class="p-4 bg-blue-50 rounded-lg mb-6">
                                <h4 class="font-medium text-gray-800 mb-2">Informasi Approval</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm text-gray-600">Diproses Oleh</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($attendance['approved_by_name'] ?? '-'); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Waktu</p>
                                        <p class="font-medium"><?php echo date('d M Y H:i', strtotime($attendance['approved_at'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Foto Bukti -->
                        <div>
                            <h4 class="font-medium text-gray-800 mb-3">Foto Bukti</h4>
                            <?php if (!empty($attendance['foto_bukti']) && file_exists('../' . $attendance['foto_bukti'])): ?>
                                <img src="../<?php echo $attendance['foto_bukti']; ?>"
                                     alt="Foto Bukti"
                                     class="w-full max-h-96 object-contain rounded-lg border cursor-pointer"
                                     onclick="showPhoto('../<?php echo $attendance['foto_bukti']; ?>')">
                            <?php else: ?>
                                <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                                    <i class="fas fa-camera text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500">Tidak ada foto bukti</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Photo Modal -->
    <div id="photoModal" class="fixed inset-0 bg-black bg-opacity-75 hidden items-center justify-center p-4 z-50">
        <div class="relative max-w-4xl w-full">
            <button onclick="document.getElementById('photoModal').classList.add('hidden');document.getElementById('photoModal').classList.remove('flex')"
                    class="absolute -top-10 right-0 text-white hover:text-gray-300">
                <i class="fas fa-times text-2xl"></i>
            </button>
            <img id="modalImg" src="" class="w-full h-auto rounded-lg">
        </div>
    </div>

    <script>
    // Sidebar
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

    function showPhoto(src) {
        document.getElementById('modalImg').src = src;
        document.getElementById('photoModal').classList.remove('hidden');
        document.getElementById('photoModal').classList.add('flex');
    }
    </script>
</body>
</html>