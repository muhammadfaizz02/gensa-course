<?php
include '../includes/auth_check_guru.php';
include '../config/database.php';

ob_start();

// Pastikan $user_id tersedia dari session
if (!isset($user_id) || !$user_id) {
    $user_id = $_SESSION['user_id'] ?? $_SESSION['guru_id'] ?? 0;
}
$user_id = (int) $user_id;

if ($user_id <= 0) {
    header('Location: ../login.php');
    exit();
}

$success = '';
$error   = '';

// ============ HANDLE FORM SUBMIT ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal     = mysqli_real_escape_string($conn, $_POST['tanggal'] ?? '');
    $jam_mulai   = mysqli_real_escape_string($conn, $_POST['jam_mulai'] ?? '');
    $jam_selesai = mysqli_real_escape_string($conn, $_POST['jam_selesai'] ?? '');
    $status_absen = mysqli_real_escape_string($conn, $_POST['status'] ?? 'hadir');
    $lokasi      = mysqli_real_escape_string($conn, $_POST['lokasi'] ?? '');
    $catatan     = mysqli_real_escape_string($conn, $_POST['catatan'] ?? '');

    // Validasi dasar
    if (!$tanggal || !$jam_mulai || !$jam_selesai || !$lokasi) {
        $error = "Mohon lengkapi semua field yang wajib (tanggal, jam, lokasi).";
    } elseif ($jam_mulai >= $jam_selesai) {
        $error = "Jam selesai harus lebih besar dari jam mulai.";
    } else {
        // Cek duplikat absen hari ini
        $check_query = "SELECT id FROM kehadiran_guru 
                        WHERE guru_id = $user_id 
                        AND tanggal = '$tanggal' 
                        AND status != 'rejected'";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            $error = "Anda sudah absen untuk tanggal ini!";
        } else {
            // ============ HANDLE FOTO ============
            $foto_bukti = '';
            $target_dir = "../uploads/attendance/";

            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            // Prioritas 1: File upload dari input type="file"
            if (isset($_FILES['foto_bukti']) && $_FILES['foto_bukti']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $ext = strtolower(pathinfo($_FILES['foto_bukti']['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed)) {
                    $error = "Format foto harus JPG, PNG, atau WEBP.";
                } elseif ($_FILES['foto_bukti']['size'] > 2 * 1024 * 1024) {
                    $error = "Ukuran foto maksimal 2MB.";
                } else {
                    $filename = 'attendance_' . $user_id . '_' . date('YmdHis') . '.' . $ext;
                    $target_file = $target_dir . $filename;
                    if (move_uploaded_file($_FILES['foto_bukti']['tmp_name'], $target_file)) {
                        $foto_bukti = 'uploads/attendance/' . $filename;
                    } else {
                        $error = "Gagal mengupload foto.";
                    }
                }
            }
            // Prioritas 2: Foto dari kamera (base64 di $_POST['photo_data'])
            elseif (!empty($_POST['photo_data']) && strpos($_POST['photo_data'], 'data:image') === 0) {
                $data = $_POST['photo_data'];
                // Format: data:image/jpeg;base64,xxxxx
                list($meta, $content) = explode(',', $data, 2);
                if (preg_match('/data:image\/(\w+);base64/', $meta, $m)) {
                    $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
                    $filename = 'attendance_' . $user_id . '_' . date('YmdHis') . '.' . $ext;
                    $target_file = $target_dir . $filename;
                    if (file_put_contents($target_file, base64_decode($content))) {
                        $foto_bukti = 'uploads/attendance/' . $filename;
                    } else {
                        $error = "Gagal menyimpan foto dari kamera.";
                    }
                } else {
                    $error = "Format foto kamera tidak valid.";
                }
            } else {
                $error = "Foto bukti wajib diupload (pilih file atau ambil dari kamera).";
            }

            // ============ INSERT KE DATABASE ============
            if (!$error) {
                $query = "INSERT INTO kehadiran_guru 
                          (guru_id, tanggal, jam_mulai, jam_selesai, foto_bukti, lokasi, catatan, status, jenis_kehadiran)
                          VALUES 
                          ($user_id, '$tanggal', '$jam_mulai', '$jam_selesai', 
                           '$foto_bukti', '$lokasi', '$catatan', 'pending', '$status_absen')";

                if (mysqli_query($conn, $query)) {
                    $_SESSION['success'] = "Absensi berhasil dikirim! Menunggu persetujuan admin.";
                    ob_end_clean();
                    header('Location: attendance.php');
                    exit();
                } else {
                    $error = "Gagal menyimpan absensi: " . mysqli_error($conn);
                }
            }
        }
    }
}

// ============ AMBIL HISTORY ============
$history_query = "SELECT * FROM kehadiran_guru 
                  WHERE guru_id = $user_id 
                  ORDER BY tanggal DESC, id DESC 
                  LIMIT 10";
$history = mysqli_query($conn, $history_query);

// ============ STATISTIK ============
$stats_query = "SELECT 
                    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
                    COUNT(CASE WHEN tanggal >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as last_30_days,
                    AVG(TIME_TO_SEC(TIMEDIFF(jam_selesai, jam_mulai))) / 3600 as avg_hours
                FROM kehadiran_guru 
                WHERE guru_id = $user_id";
$stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_query));

// Pesan session
if (!empty($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

$today = date('Y-m-d');
$current_time = date('H:i');
$default_end = date('H:i', strtotime('+1 hour'));

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Teacher Gensa Course</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50">

    <!-- Mobile Menu Toggle -->
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
                <a href="attendance.php" class="flex items-center space-x-3 p-3 bg-white bg-opacity-20 rounded-lg">
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
                <a href="rekap_kehadiran.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition">
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
                    <h1 class="text-2xl font-bold text-gray-800">Teacher Attendance</h1>
                    <p class="text-gray-600">Mark your daily teaching attendance</p>
                </div>
                <a href="dashboard.php" class="inline-flex w-full sm:w-auto justify-center items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <div class="p-4 sm:p-6">

            <?php if ($success): ?>
                <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: '<?php echo addslashes($success); ?>',
                    timer: 3000,
                    showConfirmButton: false
                });
                </script>
            <?php endif; ?>

            <?php if ($error): ?>
                <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: '<?php echo addslashes($error); ?>'
                });
                </script>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- ============ FORM ============ -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Mark Today's Attendance</h2>

                    <form method="POST" action="" enctype="multipart/form-data" id="attendanceForm" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                                <input type="date" name="tanggal" required value="<?php echo $today; ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status Kehadiran *</label>
                                <select name="status" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500">
                                    <option value="hadir">Hadir</option>
                                    <option value="sakit">Sakit</option>
                                    <option value="izin">Izin</option>
                                    <option value="remote">Remote Teaching</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Start Time *</label>
                                <input type="time" name="jam_mulai" required value="<?php echo $current_time; ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">End Time *</label>
                                <input type="time" name="jam_selesai" required value="<?php echo $default_end; ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Location *</label>
                            <div class="flex space-x-2">
                                <input type="text" name="lokasi" id="locationInput" required readonly
                                       class="flex-1 px-4 py-2 border border-gray-300 rounded-lg bg-gray-50"
                                       placeholder="Klik tombol di samping untuk ambil lokasi">
                                <button type="button" onclick="getLocation()"
                                        class="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg">
                                    <i class="fas fa-map-marker-alt"></i>
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Lokasi otomatis dari GPS, tidak bisa diubah manual.</p>
                        </div>

                        <!-- FOTO: pilih antara upload file ATAU kamera -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Foto Bukti *</label>

                            <!-- Tab switch -->
                            <div class="flex space-x-2 mb-3">
                                <button type="button" id="tabUpload" onclick="switchMode('upload')"
                                        class="flex-1 py-2 rounded-lg text-sm font-medium bg-yellow-500 text-white">
                                    <i class="fas fa-upload mr-1"></i> Upload File
                                </button>
                                <button type="button" id="tabCamera" onclick="switchMode('camera')"
                                        class="flex-1 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-700">
                                    <i class="fas fa-camera mr-1"></i> Kamera
                                </button>
                            </div>

                            <!-- Mode Upload -->
                            <div id="modeUpload">
                                <input type="file" name="foto_bukti" id="fileInput" accept="image/*"
                                       class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100">
                                <p class="text-xs text-gray-500 mt-1">Format JPG/PNG/WEBP, maksimal 2MB.</p>
                                <div id="filePreview" class="hidden mt-3">
                                    <p class="text-sm font-medium text-gray-700 mb-1">Preview:</p>
                                    <img id="previewImg" class="w-full max-h-64 object-contain rounded-lg border">
                                </div>
                            </div>

                            <!-- Mode Kamera -->
                            <div id="modeCamera" class="hidden">
                                <div class="border-2 border-dashed border-gray-300 rounded-lg p-4">
                                    <video id="video" width="100%" autoplay playsinline class="rounded-lg bg-black"></video>
                                    <canvas id="canvas" class="hidden"></canvas>
                                    <input type="hidden" id="photoData" name="photo_data">
                                    <div class="mt-3 flex gap-2">
                                        <button type="button" id="startCamBtn" onclick="startCamera()"
                                                class="flex-1 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm">
                                            <i class="fas fa-play mr-1"></i> Nyalakan Kamera
                                        </button>
                                        <button type="button" id="captureBtn" onclick="capturePhoto()" disabled
                                                class="flex-1 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm disabled:opacity-50">
                                            <i class="fas fa-camera mr-1"></i> Ambil Foto
                                        </button>
                                        <button type="button" onclick="stopCamera()"
                                                class="py-2 px-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-sm">
                                            <i class="fas fa-stop"></i>
                                        </button>
                                    </div>
                                    <div id="capturePreview" class="hidden mt-3">
                                        <p class="text-sm font-medium text-green-700 mb-1">
                                            <i class="fas fa-check-circle"></i> Foto berhasil diambil:
                                        </p>
                                        <img id="captureImg" class="w-full max-h-64 object-contain rounded-lg border-2 border-green-400">
                                        <button type="button" onclick="retakePhoto()"
                                                class="mt-2 text-sm text-yellow-600 hover:text-yellow-700">
                                            <i class="fas fa-redo mr-1"></i> Ambil Ulang
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Catatan (Opsional)</label>
                            <textarea name="catatan" rows="2"
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500"
                                      placeholder="Tambahkan catatan..."></textarea>
                        </div>

                        <div class="pt-4">
                            <button type="submit"
                                    class="w-full py-3 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold rounded-lg shadow-md transition">
                                <i class="fas fa-calendar-check mr-2"></i> Submit Attendance
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ============ HISTORY ============ -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Attendance History</h2>

                    <?php if (mysqli_num_rows($history) > 0): ?>
                        <div class="space-y-4 max-h-[600px] overflow-y-auto">
                            <?php while ($record = mysqli_fetch_assoc($history)): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <p class="font-semibold text-gray-800">
                                                <?php echo date('l, d F Y', strtotime($record['tanggal'])); ?>
                                            </p>
                                            <p class="text-sm text-gray-600">
                                                <?php echo date('H:i', strtotime($record['jam_mulai'])); ?> -
                                                <?php echo date('H:i', strtotime($record['jam_selesai'])); ?>
                                            </p>
                                        </div>
                                        <span class="px-3 py-1 rounded-full text-xs font-medium <?php
                                            echo $record['status'] == 'approved' ? 'bg-green-100 text-green-800' :
                                                ($record['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                        ?>">
                                            <?php echo ucfirst($record['status']); ?>
                                        </span>
                                    </div>

                                    <div class="space-y-1 text-sm">
                                        <div class="flex items-center text-gray-600">
                                            <i class="fas fa-map-marker-alt mr-2 w-4"></i>
                                            <span class="truncate"><?php echo htmlspecialchars($record['lokasi']); ?></span>
                                        </div>
                                        <?php if ($record['catatan']): ?>
                                            <div class="flex items-start text-gray-600">
                                                <i class="fas fa-sticky-note mr-2 mt-1 w-4"></i>
                                                <span><?php echo htmlspecialchars($record['catatan']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($record['foto_bukti'])): ?>
                                            <div class="mt-2">
                                                <button onclick="viewPhoto('../<?php echo $record['foto_bukti']; ?>')"
                                                        class="text-sm text-yellow-600 hover:text-yellow-700">
                                                    <i class="fas fa-image mr-1"></i> Lihat Foto Bukti
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($record['approved_at']): ?>
                                        <div class="mt-2 pt-2 border-t text-xs text-gray-500">
                                            Approved: <?php echo date('d/m/Y H:i', strtotime($record['approved_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <div class="mt-4 text-center">
                            <a href="rekap_kehadiran.php" class="text-sm text-yellow-600 hover:text-yellow-700">
                                Lihat laporan lengkap <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-history text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">Belum ada riwayat absensi</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ============ STATISTIK ============ -->
            <div class="mt-6 bg-white rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Attendance Statistics</h2>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="text-center p-3 bg-green-50 rounded-lg">
                        <p class="text-2xl font-bold text-green-600"><?php echo $stats['approved_count']; ?></p>
                        <p class="text-sm text-gray-600">Approved</p>
                    </div>
                    <div class="text-center p-3 bg-yellow-50 rounded-lg">
                        <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['pending_count']; ?></p>
                        <p class="text-sm text-gray-600">Pending</p>
                    </div>
                    <div class="text-center p-3 bg-red-50 rounded-lg">
                        <p class="text-2xl font-bold text-red-600"><?php echo $stats['rejected_count']; ?></p>
                        <p class="text-sm text-gray-600">Rejected</p>
                    </div>
                    <div class="text-center p-3 bg-blue-50 rounded-lg">
                        <p class="text-2xl font-bold text-blue-600"><?php echo $stats['last_30_days']; ?></p>
                        <p class="text-sm text-gray-600">Last 30 Days</p>
                    </div>
                    <div class="text-center p-3 bg-purple-50 rounded-lg">
                        <p class="text-2xl font-bold text-purple-600"><?php echo number_format($stats['avg_hours'] ?? 0, 1); ?></p>
                        <p class="text-sm text-gray-600">Avg Hours</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Photo Modal -->
    <div id="photoModal" class="fixed inset-0 bg-black bg-opacity-75 hidden items-center justify-center p-4 z-50">
        <div class="relative max-w-4xl w-full">
            <button onclick="closePhotoModal()" class="absolute -top-10 right-0 text-white hover:text-gray-300">
                <i class="fas fa-times text-2xl"></i>
            </button>
            <img id="modalPhoto" src="" alt="" class="w-full h-auto rounded-lg">
        </div>
    </div>

    <script>
    let videoStream = null;
    let currentMode = 'upload'; // 'upload' | 'camera'

    // ===== Sidebar =====
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

    // ===== Mode switch: upload vs camera =====
    function switchMode(mode) {
        currentMode = mode;
        const tabUpload = document.getElementById('tabUpload');
        const tabCamera = document.getElementById('tabCamera');
        const modeUpload = document.getElementById('modeUpload');
        const modeCamera = document.getElementById('modeCamera');

        if (mode === 'upload') {
            tabUpload.className = 'flex-1 py-2 rounded-lg text-sm font-medium bg-yellow-500 text-white';
            tabCamera.className = 'flex-1 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-700';
            modeUpload.classList.remove('hidden');
            modeCamera.classList.add('hidden');
            stopCamera();
            // Kosongkan photo_data supaya server pakai file upload
            document.getElementById('photoData').value = '';
        } else {
            tabCamera.className = 'flex-1 py-2 rounded-lg text-sm font-medium bg-yellow-500 text-white';
            tabUpload.className = 'flex-1 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-700';
            modeCamera.classList.remove('hidden');
            modeUpload.classList.add('hidden');
            // Kosongkan file input supaya server pakai photo_data
            document.getElementById('fileInput').value = '';
            document.getElementById('filePreview').classList.add('hidden');
        }
    }

    // ===== Preview file upload =====
    document.getElementById('fileInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const preview = document.getElementById('filePreview');
        const img = document.getElementById('previewImg');
        const reader = new FileReader();
        reader.onload = ev => {
            img.src = ev.target.result;
            preview.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    });

    // ===== GPS =====
    function getLocation() {
        const input = document.getElementById('locationInput');
        if (!navigator.geolocation) {
            input.value = 'Geolocation tidak didukung';
            return;
        }
        input.value = 'Mengambil lokasi...';
        input.classList.remove('bg-gray-50', 'bg-green-50', 'bg-red-50');
        input.classList.add('bg-yellow-50');

        navigator.geolocation.getCurrentPosition(
            pos => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                    .then(r => r.json())
                    .then(data => {
                        input.value = data.display_name || `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}`;
                        input.classList.remove('bg-yellow-50');
                        input.classList.add('bg-green-50');
                    })
                    .catch(() => {
                        input.value = `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}`;
                        input.classList.remove('bg-yellow-50');
                        input.classList.add('bg-green-50');
                    });
            },
            () => {
                input.value = 'Akses lokasi ditolak';
                input.classList.remove('bg-yellow-50');
                input.classList.add('bg-red-50');
            }
        );
    }

    // ===== Kamera =====
    function startCamera() {
        if (videoStream) return;
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
            .then(stream => {
                videoStream = stream;
                const video = document.getElementById('video');
                video.srcObject = stream;
                video.play();
                document.getElementById('captureBtn').disabled = false;
                document.getElementById('startCamBtn').disabled = true;
            })
            .catch(err => {
                Swal.fire({
                    icon: 'error',
                    title: 'Kamera gagal',
                    text: 'Akses kamera ditolak atau tidak tersedia: ' + err.message
                });
            });
    }

    function stopCamera() {
        if (videoStream) {
            videoStream.getTracks().forEach(t => t.stop());
            videoStream = null;
        }
        const btn = document.getElementById('startCamBtn');
        const cap = document.getElementById('captureBtn');
        if (btn) btn.disabled = false;
        if (cap) cap.disabled = true;
    }

    function capturePhoto() {
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        if (!video.videoWidth) {
            Swal.fire({ icon: 'warning', title: 'Kamera belum siap', text: 'Tunggu sebentar lalu coba lagi.' });
            return;
        }
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
        document.getElementById('photoData').value = dataUrl;

        // Preview
        document.getElementById('captureImg').src = dataUrl;
        document.getElementById('capturePreview').classList.remove('hidden');

        // Sembunyikan video
        video.classList.add('hidden');
        stopCamera();

        Swal.fire({
            icon: 'success',
            title: 'Foto berhasil diambil!',
            timer: 1500,
            showConfirmButton: false
        });
    }

    function retakePhoto() {
        document.getElementById('photoData').value = '';
        document.getElementById('capturePreview').classList.add('hidden');
        const video = document.getElementById('video');
        video.classList.remove('hidden');
        startCamera();
    }

    // ===== Photo modal =====
    function viewPhoto(url) {
        document.getElementById('modalPhoto').src = url;
        document.getElementById('photoModal').classList.remove('hidden');
        document.getElementById('photoModal').classList.add('flex');
    }
    function closePhotoModal() {
        document.getElementById('photoModal').classList.add('hidden');
        document.getElementById('photoModal').classList.remove('flex');
    }

    // ===== Validasi submit =====
    document.getElementById('attendanceForm').addEventListener('submit', function(e) {
        const jamMulai = document.querySelector('input[name="jam_mulai"]').value;
        const jamSelesai = document.querySelector('input[name="jam_selesai"]').value;
        const lokasi = document.getElementById('locationInput').value;

        if (jamMulai >= jamSelesai) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Jam tidak valid', text: 'Jam selesai harus lebih besar dari jam mulai.' });
            return false;
        }
        if (!lokasi || lokasi === 'Mengambil lokasi...' || lokasi.includes('ditolak')) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Lokasi belum ada', text: 'Klik tombol lokasi dulu untuk mengambil GPS.' });
            return false;
        }

        // Cek foto: harus ada file ATAU photo_data
        const fileInput = document.getElementById('fileInput');
        const photoData = document.getElementById('photoData').value;
        const hasFile = fileInput.files && fileInput.files.length > 0;
        const hasCamera = photoData && photoData.startsWith('data:image');

        if (!hasFile && !hasCamera) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Foto belum ada', text: 'Upload file foto atau ambil foto dari kamera dulu.' });
            return false;
        }
    });

    window.addEventListener('beforeunload', stopCamera);

    // Auto get lokasi saat halaman load
    window.addEventListener('load', () => setTimeout(getLocation, 800));
    </script>
</body>
</html>