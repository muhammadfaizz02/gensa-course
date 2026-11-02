<?php
include '../includes/auth_check.php';
include '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

ob_start();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error'] = "Invalid teacher ID";
    header('Location: teachers_report.php');
    exit();
}

// ===== DATA GURU =====
$query = "
    SELECT u.id, u.username, u.email, u.full_name, u.status, u.created_at,
           g.foto_profil, g.spesialisasi
    FROM users u
    LEFT JOIN guru_profiles g ON u.id = g.user_id
    WHERE u.id = $id AND u.role = 'guru'
    LIMIT 1
";
$teacher = mysqli_fetch_assoc(mysqli_query($conn, $query));

if (!$teacher) {
    $_SESSION['error'] = "Teacher not found";
    header('Location: teachers_report.php');
    exit();
}

$specs = array_filter(array_map('trim', explode(',', $teacher['spesialisasi'] ?? '')));

// ===== STATISTIK ALL-TIME =====
$stats_query = "
    SELECT 
        COUNT(*) AS total_sessions,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) AS approved,
        COUNT(CASE WHEN status = 'pending'  THEN 1 END) AS pending,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) AS rejected,
        COALESCE(SUM(CASE WHEN status = 'approved'
            THEN TIME_TO_SEC(TIMEDIFF(jam_selesai, jam_mulai)) / 3600
            ELSE 0 END), 0) AS total_hours
    FROM kehadiran_guru
    WHERE guru_id = $id
";
$stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_query));
$approval_rate = $stats['total_sessions'] > 0
    ? round($stats['approved'] / $stats['total_sessions'] * 100, 1)
    : 0;

// ===== STATISTIK BULAN INI =====
$month_start = date('Y-m-01');
$month_end   = date('Y-m-t');
$month_query = "
    SELECT 
        COUNT(CASE WHEN status = 'approved' THEN 1 END) AS approved,
        COUNT(CASE WHEN status = 'pending'  THEN 1 END) AS pending,
        COALESCE(SUM(CASE WHEN status = 'approved'
            THEN TIME_TO_SEC(TIMEDIFF(jam_selesai, jam_mulai)) / 3600
            ELSE 0 END), 0) AS hours
    FROM kehadiran_guru
    WHERE guru_id = $id
      AND tanggal BETWEEN '$month_start' AND '$month_end'
";
$month_stats = mysqli_fetch_assoc(mysqli_query($conn, $month_query));

// ===== JADWAL MENGAJAR (kalau ada tabel jadwal) =====
$schedule_exists = mysqli_query($conn, "SHOW TABLES LIKE 'jadwal'");
$upcoming_schedules = null;
if (mysqli_num_rows($schedule_exists) > 0) {
    $upcoming_query = "
        SELECT * FROM jadwal 
        WHERE guru_id = $id 
          AND tanggal >= CURDATE()
        ORDER BY tanggal ASC
        LIMIT 5
    ";
    $upcoming_schedules = @mysqli_query($conn, $upcoming_query);
}

// ===== RIWAYAT ABSENSI TERBARU =====
$history_query = "
    SELECT id, tanggal, jam_mulai, jam_selesai, status, lokasi, foto_bukti, approved_at
    FROM kehadiran_guru
    WHERE guru_id = $id
    ORDER BY tanggal DESC, id DESC
    LIMIT 10
";
$history = mysqli_query($conn, $history_query);

// ===== GRAFIK 7 HARI TERAKHIR =====
$chart_query = "
    SELECT tanggal,
           COUNT(CASE WHEN status = 'approved' THEN 1 END) AS approved_count,
           COALESCE(SUM(CASE WHEN status = 'approved'
               THEN TIME_TO_SEC(TIMEDIFF(jam_selesai, jam_mulai)) / 3600
               ELSE 0 END), 0) AS hours
    FROM kehadiran_guru
    WHERE guru_id = $id
      AND tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY tanggal
    ORDER BY tanggal ASC
";
$chart_data = [];
$chart_result = mysqli_query($conn, $chart_query);
while ($row = mysqli_fetch_assoc($chart_result)) {
    $chart_data[$row['tanggal']] = [
        'approved' => (int) $row['approved_count'],
        'hours'    => (float) $row['hours']
    ];
}

// Isi 7 hari terakhir (termasuk yang kosong)
$chart_labels = [];
$chart_values = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d M', strtotime($d));
    $chart_values[] = isset($chart_data[$d]) ? $chart_data[$d]['hours'] : 0;
}

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
    <title>Teacher Details - <?php echo htmlspecialchars($teacher['full_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="bg-gray-50">
    <?php include 'includes/sidebar.php'; ?>

    <div class="lg:ml-64 pt-14 lg:pt-0 min-h-screen min-w-0">
        <!-- Top Bar -->
        <div class="bg-white shadow-sm p-4 border-b">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Teacher Details</h1>
                    <p class="text-gray-600">ID #<?php echo str_pad($id, 4, '0', STR_PAD_LEFT); ?></p>
                </div>
                <div class="flex gap-2">
                    <button onclick="window.print()"
                            class="px-4 py-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                    <a href="teachers_report.php"
                       class="px-4 py-2 bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-lg">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <div class="p-4 sm:p-6">

            <!-- Header Profile -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
                <div class="<?php echo $teacher['status'] === 'active' ? 'bg-gradient-to-r from-yellow-400 to-yellow-500' : 'bg-gradient-to-r from-gray-400 to-gray-500'; ?> h-24"></div>
                <div class="px-6 pb-6">
                    <div class="flex flex-col sm:flex-row sm:items-end gap-4 -mt-12">
                        <div class="w-28 h-28 rounded-full overflow-hidden bg-yellow-100 border-4 border-white shadow-lg shrink-0">
                            <?php if (!empty($teacher['foto_profil']) && file_exists('../' . $teacher['foto_profil'])): ?>
                                <img src="../<?php echo htmlspecialchars($teacher['foto_profil']); ?>"
                                     alt="<?php echo htmlspecialchars($teacher['full_name']); ?>"
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center">
                                    <i class="fas fa-user text-yellow-600 text-4xl"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0 sm:pb-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($teacher['full_name']); ?></h2>
                                <span class="px-3 py-1 rounded-full text-xs font-medium
                                    <?php echo $teacher['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <i class="fas fa-circle text-[6px] mr-1"></i>
                                    <?php echo ucfirst($teacher['status']); ?>
                                </span>
                            </div>
                            <p class="text-gray-500">@<?php echo htmlspecialchars($teacher['username']); ?></p>
                            <div class="flex flex-wrap gap-1 mt-2">
                                <?php foreach ($specs as $s):
                                    $c = $s === 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
                                ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $c; ?>">
                                        <i class="fas fa-language mr-1"></i><?php echo ucfirst($s); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="flex gap-2 sm:pb-2">
                            <a href="edit_teacher.php?id=<?php echo $id; ?>"
                               class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium text-sm">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-md p-5">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center shrink-0">
                            <i class="fas fa-envelope text-blue-600"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm text-gray-500">Email</p>
                            <p class="font-medium text-gray-800 truncate"><?php echo htmlspecialchars($teacher['email']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-5">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center shrink-0">
                            <i class="fas fa-calendar text-purple-600"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm text-gray-500">Tanggal Bergabung</p>
                            <p class="font-medium text-gray-800">
                                <?php echo date('d F Y', strtotime($teacher['created_at'])); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-md p-4 border-l-4 border-gray-700">
                    <p class="text-xs text-gray-500">Total Sesi</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_sessions']; ?></p>
                    <p class="text-xs text-gray-500 mt-1">All time</p>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border-l-4 border-green-500">
                    <p class="text-xs text-gray-500">Approved</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo $stats['approved']; ?></p>
                    <p class="text-xs text-gray-500 mt-1"><?php echo $approval_rate; ?>% approval rate</p>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border-l-4 border-yellow-500">
                    <p class="text-xs text-gray-500">Pending</p>
                    <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['pending']; ?></p>
                    <p class="text-xs text-gray-500 mt-1">Menunggu approval</p>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border-l-4 border-blue-500">
                    <p class="text-xs text-gray-500">Total Jam</p>
                    <p class="text-2xl font-bold text-blue-600"><?php echo number_format($stats['total_hours'], 1); ?></p>
                    <p class="text-xs text-gray-500 mt-1">Jam mengajar</p>
                </div>
            </div>

            <!-- Bulan Ini + Chart -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-md p-5">
                    <h3 class="font-bold text-gray-800 mb-3 text-sm">
                        <i class="fas fa-calendar-day text-yellow-500 mr-1"></i> Bulan Ini
                    </h3>
                    <div class="space-y-3">
                        <div>
                            <p class="text-xs text-gray-500">Approved</p>
                            <p class="text-xl font-bold text-green-600"><?php echo $month_stats['approved']; ?> sesi</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Pending</p>
                            <p class="text-xl font-bold text-yellow-600"><?php echo $month_stats['pending']; ?> sesi</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Total Jam</p>
                            <p class="text-xl font-bold text-blue-600"><?php echo number_format($month_stats['hours'], 1); ?> jam</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-5 lg:col-span-2">
                    <h3 class="font-bold text-gray-800 mb-3 text-sm">
                        <i class="fas fa-chart-line text-blue-500 mr-1"></i> Aktivitas 7 Hari Terakhir
                    </h3>
                    <canvas id="activityChart" height="80"></canvas>
                </div>
            </div>

            <!-- Riwayat Absensi -->
            <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-gray-800">
                        <i class="fas fa-history text-gray-500 mr-2"></i> Riwayat Absensi Terbaru
                    </h3>
                    <a href="approve_attendance.php" class="text-sm text-yellow-600 hover:text-yellow-700">
                        Lihat semua <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>

                <?php if (mysqli_num_rows($history) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[600px]">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-700 uppercase">Tanggal</th>
                                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-700 uppercase">Jam</th>
                                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-700 uppercase">Status</th>
                                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-700 uppercase">Lokasi</th>
                                    <th class="py-3 px-4 text-center text-xs font-medium text-gray-700 uppercase">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($h = mysqli_fetch_assoc($history)): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-3 px-4 text-sm">
                                            <?php echo date('d M Y', strtotime($h['tanggal'])); ?>
                                        </td>
                                        <td class="py-3 px-4 text-sm text-gray-600">
                                            <?php echo date('H:i', strtotime($h['jam_mulai'])); ?> -
                                            <?php echo date('H:i', strtotime($h['jam_selesai'])); ?>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="px-2 py-1 rounded-full text-xs font-medium
                                                <?php
                                                    echo $h['status'] === 'approved' ? 'bg-green-100 text-green-800' :
                                                        ($h['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                                ?>">
                                                <?php echo ucfirst($h['status']); ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-sm text-gray-600 truncate max-w-xs">
                                            <?php echo htmlspecialchars($h['lokasi']); ?>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <a href="attendance_details.php?id=<?php echo $h['id']; ?>"
                                               class="inline-block px-2 py-1 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded text-xs">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-history text-3xl text-gray-300 mb-2"></i>
                        <p class="text-gray-500 text-sm">Belum ada riwayat absensi</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Jadwal Mendatang (kalau tabel jadwal ada) -->
            <?php if ($upcoming_schedules && mysqli_num_rows($upcoming_schedules) > 0): ?>
                <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
                    <h3 class="font-bold text-gray-800 mb-4">
                        <i class="fas fa-calendar-alt text-purple-500 mr-2"></i> Jadwal Mengajar Mendatang
                    </h3>
                    <div class="space-y-2">
                        <?php while ($s = mysqli_fetch_assoc($upcoming_schedules)): ?>
                            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                <div class="w-12 h-12 bg-purple-100 rounded-lg flex flex-col items-center justify-center shrink-0">
                                    <span class="text-xs text-purple-600 font-medium">
                                        <?php echo date('M', strtotime($s['tanggal'])); ?>
                                    </span>
                                    <span class="text-lg font-bold text-purple-700 leading-none">
                                        <?php echo date('d', strtotime($s['tanggal'])); ?>
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-gray-800 truncate">
                                        <?php echo htmlspecialchars($s['mata_pelajaran'] ?? $s['judul'] ?? 'Mengajar'); ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <?php if (isset($s['jam_mulai'], $s['jam_selesai'])): ?>
                                            <?php echo date('H:i', strtotime($s['jam_mulai'])); ?> -
                                            <?php echo date('H:i', strtotime($s['jam_selesai'])); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
    // Chart
    const ctx = document.getElementById('activityChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Jam Mengajar',
                data: <?php echo json_encode($chart_values); ?>,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.3,
                fill: true,
                pointBackgroundColor: '#3b82f6',
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => v + 'h'
                    }
                }
            }
        }
    });

    // Notifikasi
    <?php if ($success): ?>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: <?php echo json_encode($success); ?>, timer: 2500, showConfirmButton: false });
    <?php endif; ?>
    <?php if ($error): ?>
        Swal.fire({ icon: 'error', title: 'Gagal!', text: <?php echo json_encode($error); ?> });
    <?php endif; ?>

    // Print styling
    const style = document.createElement('style');
    style.innerHTML = `
        @media print {
            aside, nav, button, .no-print { display: none !important; }
            .lg\\:ml-64 { margin-left: 0 !important; padding-top: 0 !important; }
            .shadow-md, .shadow-lg { box-shadow: none !important; }
            .rounded-xl { border-radius: 0 !important; }
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>