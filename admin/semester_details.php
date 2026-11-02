<?php
include '../includes/auth_check.php';
include '../config/database.php';

ob_start();

// Validasi ID
$semester_id = (int) ($_GET['id'] ?? 0);
if ($semester_id <= 0) {
    $_SESSION['error'] = "Invalid semester ID";
    header('Location: manage_semester.php');
    exit();
}

// Ambil data semester
$query = "SELECT * FROM master_semester WHERE id = $semester_id LIMIT 1";
$result = mysqli_query($conn, $query);
$semester = mysqli_fetch_assoc($result);

if (!$semester) {
    $_SESSION['error'] = "Semester tidak ditemukan";
    header('Location: manage_semester.php');
    exit();
}

// ===== STATISTIK TERKAIT SEMESTER =====
$start = $semester['tanggal_mulai'];
$end   = $semester['tanggal_selesai'];

// Jumlah nilai siswa di semester ini
$scores_query = "SELECT COUNT(*) as c FROM nilai_siswa WHERE semester = '{$semester['nama_semester']}'";
$scores_count = (int) (mysqli_fetch_assoc(mysqli_query($conn, $scores_query))['c'] ?? 0);

// Jumlah kehadiran guru
$attendance_query = "SELECT COUNT(*) as c FROM kehadiran_guru 
                     WHERE tanggal BETWEEN '$start' AND '$end'";
$attendance_count = (int) (mysqli_fetch_assoc(mysqli_query($conn, $attendance_query))['c'] ?? 0);

// Jumlah jadwal
$schedules_query = "SELECT COUNT(*) as c FROM jadwal 
                    WHERE tanggal BETWEEN '$start' AND '$end'";
$schedules_count = (int) (mysqli_fetch_assoc(mysqli_query($conn, $schedules_query))['c'] ?? 0);

// Jumlah siswa aktif (pelajar)
$students_query = "SELECT COUNT(*) as c FROM users WHERE role = 'pelajar' AND status = 'active'";
$students_count = (int) (mysqli_fetch_assoc(mysqli_query($conn, $students_query))['c'] ?? 0);

// Jumlah guru aktif
$teachers_query = "SELECT COUNT(*) as c FROM users WHERE role = 'guru' AND status = 'active'";
$teachers_count = (int) (mysqli_fetch_assoc(mysqli_query($conn, $teachers_query))['c'] ?? 0);

// Progress semester
$total_days   = max(1, (strtotime($end) - strtotime($start)) / 86400);
$days_passed  = (time() - strtotime($start)) / 86400;
$progress     = min(max($days_passed / $total_days * 100, 0), 100);
$days_left    = max(0, round($total_days - $days_passed));
$progress_color = $progress >= 100 ? 'bg-gray-500' : ($progress > 75 ? 'bg-red-500' : ($progress > 50 ? 'bg-yellow-500' : 'bg-green-500'));

// Daftar guru yang aktif absen di periode ini
$top_teachers_query = "
    SELECT u.full_name, u.email, COUNT(k.id) as total_hadir,
           COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(k.jam_selesai, k.jam_mulai))) / 3600, 0) as total_jam
    FROM kehadiran_guru k
    JOIN users u ON k.guru_id = u.id
    WHERE k.tanggal BETWEEN '$start' AND '$end'
      AND k.status = 'approved'
    GROUP BY k.guru_id
    ORDER BY total_hadir DESC
    LIMIT 5
";
$top_teachers = mysqli_query($conn, $top_teachers_query);

$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semester Details - Admin Gensa Course</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50">
    <?php include 'includes/sidebar.php'; ?>

    <div class="lg:ml-64 pt-14 lg:pt-0 min-h-screen min-w-0">
        <!-- Top Bar -->
        <div class="bg-white shadow-sm p-4 border-b">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Semester Details</h1>
                    <p class="text-gray-600">#<?php echo str_pad($semester['id'], 4, '0', STR_PAD_LEFT); ?></p>
                </div>
                <div class="flex gap-2">
                    <button onclick="window.print()"
                            class="px-4 py-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                    <a href="manage_semester.php"
                       class="px-4 py-2 bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-lg">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <div class="p-4 sm:p-6">

            <!-- Header Card -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
                <div class="<?php
                    echo $semester['status'] == 'active' ? 'bg-yellow-500' : 'bg-gray-500';
                ?> text-white p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($semester['nama_semester']); ?></h2>
                            <p class="opacity-90">Tahun Ajaran <?php echo htmlspecialchars($semester['tahun_ajaran']); ?></p>
                        </div>
                        <span class="px-4 py-1 bg-white bg-opacity-20 rounded-full text-sm font-medium self-start sm:self-auto">
                            <i class="fas fa-<?php echo $semester['status'] == 'active' ? 'play-circle' : 'pause-circle'; ?> mr-1"></i>
                            <?php echo ucfirst($semester['status']); ?>
                        </span>
                    </div>

                    <!-- Progress -->
                    <div class="mt-4">
                        <div class="flex justify-between text-sm mb-1">
                            <span>Progress Semester</span>
                            <span><?php echo round($progress); ?>%</span>
                        </div>
                        <div class="w-full bg-white bg-opacity-30 rounded-full h-3">
                            <div class="bg-white h-3 rounded-full transition-all"
                                 style="width: <?php echo $progress; ?>%"></div>
                        </div>
                        <div class="flex justify-between text-xs mt-2 opacity-90">
                            <span><?php echo date('d M Y', strtotime($start)); ?></span>
                            <span><?php echo $days_left; ?> hari tersisa</span>
                            <span><?php echo date('d M Y', strtotime($end)); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Info Grid -->
                <div class="p-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Tanggal Mulai</p>
                        <p class="font-semibold text-gray-800">
                            <?php echo date('d F Y', strtotime($start)); ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Tanggal Selesai</p>
                        <p class="font-semibold text-gray-800">
                            <?php echo date('d F Y', strtotime($end)); ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Durasi</p>
                        <p class="font-semibold text-gray-800">
                            <?php echo round($total_days); ?> hari
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Status</p>
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-medium
                            <?php echo $semester['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'; ?>">
                            <?php echo ucfirst($semester['status']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Statistik -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-md p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Nilai Siswa</p>
                            <p class="text-3xl font-bold text-blue-600"><?php echo $scores_count; ?></p>
                            <p class="text-xs text-gray-500 mt-1">Total entri nilai</p>
                        </div>
                        <div class="w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-graduation-cap text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Kehadiran Guru</p>
                            <p class="text-3xl font-bold text-green-600"><?php echo $attendance_count; ?></p>
                            <p class="text-xs text-gray-500 mt-1">Total sesi absensi</p>
                        </div>
                        <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-clipboard-check text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Jadwal</p>
                            <p class="text-3xl font-bold text-purple-600"><?php echo $schedules_count; ?></p>
                            <p class="text-xs text-gray-500 mt-1">Total jadwal</p>
                        </div>
                        <div class="w-14 h-14 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-calendar-alt text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info Users -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-md p-5 flex items-center gap-4">
                    <div class="w-14 h-14 bg-yellow-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-users text-yellow-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Siswa Aktif</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $students_count; ?> siswa</p>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-5 flex items-center gap-4">
                    <div class="w-14 h-14 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-chalkboard-teacher text-indigo-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Guru Aktif</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $teachers_count; ?> guru</p>
                    </div>
                </div>
            </div>

            <!-- Top Teachers -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">
                    <i class="fas fa-star text-yellow-500 mr-2"></i> Guru Paling Aktif
                </h3>

                <?php if (mysqli_num_rows($top_teachers) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[500px]">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">#</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Nama</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Email</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Sesi</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Total Jam</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php $rank = 1; while ($t = mysqli_fetch_assoc($top_teachers)): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-3 px-4">
                                            <span class="w-7 h-7 inline-flex items-center justify-center rounded-full text-xs font-bold
                                                <?php echo $rank == 1 ? 'bg-yellow-100 text-yellow-700' : ($rank == 2 ? 'bg-gray-200 text-gray-700' : ($rank == 3 ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-600')); ?>">
                                                <?php echo $rank; ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 font-medium"><?php echo htmlspecialchars($t['full_name']); ?></td>
                                        <td class="py-3 px-4 text-sm text-gray-600"><?php echo htmlspecialchars($t['email']); ?></td>
                                        <td class="py-3 px-4">
                                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-medium">
                                                <?php echo $t['total_hadir']; ?> sesi
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-sm"><?php echo number_format($t['total_jam'], 1); ?> jam</td>
                                    </tr>
                                <?php $rank++; endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-user-clock text-4xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500">Belum ada kehadiran guru yang disetujui di periode ini</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Aksi</h3>
                <div class="flex flex-wrap gap-3">
                    <?php if ($semester['status'] != 'active'): ?>
                        <a href="manage_semester.php?activate=<?php echo $semester['id']; ?>"
                           onclick="return confirmActivate()"
                           class="px-4 py-2 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg font-medium">
                            <i class="fas fa-play mr-1"></i> Aktifkan Semester
                        </a>
                    <?php else: ?>
                        <span class="px-4 py-2 bg-yellow-50 text-yellow-700 rounded-lg font-medium">
                            <i class="fas fa-check-circle mr-1"></i> Semester sedang aktif
                        </span>
                    <?php endif; ?>

                    <a href="manage_semester.php"
                       class="px-4 py-2 bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-lg font-medium">
                        <i class="fas fa-arrow-left mr-1"></i> Kembali
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
    function confirmActivate() {
        return Swal.fire({
            title: 'Aktifkan Semester?',
            text: 'Semester lain akan otomatis dinonaktifkan.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Ya, aktifkan!',
            cancelButtonText: 'Batal'
        }).then(r => r.isConfirmed);
    }

    <?php if ($success): ?>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?php echo addslashes($success); ?>', timer: 2500, showConfirmButton: false });
    <?php endif; ?>
    <?php if ($error): ?>
        Swal.fire({ icon: 'error', title: 'Gagal!', text: '<?php echo addslashes($error); ?>' });
    <?php endif; ?>
    </script>
</body>
</html>