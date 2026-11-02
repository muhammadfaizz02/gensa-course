<?php
include '../includes/auth_check.php';
include '../config/database.php';

ob_start();

// Pastikan $user_id ada
if (!isset($user_id) || !$user_id) {
    $user_id = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0;
}
$user_id = (int) $user_id;

// ============ APPROVE ============
if (isset($_GET['approve'])) {
    $attendance_id = (int) $_GET['approve'];
    $query = "UPDATE kehadiran_guru 
              SET status = 'approved', approved_at = NOW(), approved_by = $user_id 
              WHERE id = $attendance_id";
    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Attendance approved successfully!";
    } else {
        $_SESSION['error'] = "Error: " . mysqli_error($conn);
    }
    ob_end_clean();
    // Pertahankan filter saat redirect
    $redirect_params = $_GET;
    unset($redirect_params['approve']);
    $qs = http_build_query($redirect_params);
    header('Location: approve_attendance.php' . ($qs ? "?$qs" : ''));
    exit();
}

// ============ REJECT (via POST dari modal) ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_attendance'])) {
    $attendance_id = (int) $_POST['attendance_id'];
    $reason = mysqli_real_escape_string($conn, $_POST['reason'] ?? '');

    $query = "UPDATE kehadiran_guru 
              SET status = 'rejected', approved_at = NOW(), approved_by = $user_id, catatan_admin = '$reason' 
              WHERE id = $attendance_id";
    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Attendance rejected successfully!";
    } else {
        $_SESSION['error'] = "Error: " . mysqli_error($conn);
    }
    ob_end_clean();
    $redirect_params = $_POST;
    unset($redirect_params['reject_attendance'], $redirect_params['attendance_id'], $redirect_params['reason']);
    $qs = http_build_query($redirect_params);
    header('Location: approve_attendance.php' . ($qs ? "?$qs" : ''));
    exit();
}

// ============ FILTERS ============
// Default: tampilkan dari 7 hari terakhir sampai hari ini
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date   = $_GET['end_date']   ?? date('Y-m-d');
$filter_guru = isset($_GET['guru_id']) ? (int) $_GET['guru_id'] : 0;
$group_by_date = isset($_GET['group']) ? $_GET['group'] === '1' : true; // default: grouped per tanggal

// Build WHERE clause untuk pending
$pending_where = ["k.status = 'pending'"];
if ($start_date) {
    $pending_where[] = "k.tanggal >= '$start_date'";
}
if ($end_date) {
    $pending_where[] = "k.tanggal <= '$end_date'";
}
if ($filter_guru > 0) {
    $pending_where[] = "k.guru_id = $filter_guru";
}
$pending_where_clause = implode(' AND ', $pending_where);

// ============ AMBIL PENDING ============
$query = "
    SELECT k.*, u.full_name as guru_nama, u.email as guru_email,
           g.foto_profil as guru_foto
    FROM kehadiran_guru k
    JOIN users u ON k.guru_id = u.id
    LEFT JOIN guru_profiles g ON u.id = g.user_id
    WHERE $pending_where_clause
    ORDER BY k.tanggal DESC, k.jam_mulai DESC
";
$pending_attendance = mysqli_query($conn, $query);

// Hitung total pending keseluruhan (tanpa filter tanggal) untuk info
$total_pending_query = "SELECT COUNT(*) as c FROM kehadiran_guru WHERE status = 'pending'";
$total_pending = mysqli_fetch_assoc(mysqli_query($conn, $total_pending_query))['c'];

// Hitung pending dalam rentang filter
$pending_in_range = $pending_attendance ? mysqli_num_rows($pending_attendance) : 0;

// ============ HISTORY ============
// History juga difilter tanggalnya
$history_where = ["k.status != 'pending'"];
if ($start_date) {
    $history_where[] = "k.tanggal >= '$start_date'";
}
if ($end_date) {
    $history_where[] = "k.tanggal <= '$end_date'";
}
if ($filter_guru > 0) {
    $history_where[] = "k.guru_id = $filter_guru";
}
$history_where_clause = implode(' AND ', $history_where);

$history_query = "
    SELECT k.*, u.full_name as guru_nama, a.full_name as approved_by_name
    FROM kehadiran_guru k
    JOIN users u ON k.guru_id = u.id
    LEFT JOIN users a ON k.approved_by = a.id
    WHERE $history_where_clause
    ORDER BY k.approved_at DESC
    LIMIT 50
";
$history = mysqli_query($conn, $history_query);

// ============ DAFTAR GURU (untuk filter) ============
$guru_list = mysqli_query($conn, "
    SELECT DISTINCT u.id, u.full_name 
    FROM users u
    JOIN kehadiran_guru k ON k.guru_id = u.id
    WHERE u.role = 'guru'
    ORDER BY u.full_name
");

// ============ SESSION MESSAGES ============
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
    <title>Approve Attendance - Admin Gensa Course</title>
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
                    <h1 class="text-2xl font-bold text-gray-800">Approve Teacher Attendance</h1>
                    <p class="text-gray-600">Review and approve teacher attendance proofs</p>
                </div>
                <a href="dashboard.php" class="inline-flex w-full sm:w-auto justify-center items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <div class="p-4 sm:p-6">

            <!-- ============================================= -->
            <!-- FILTER SECTION -->
            <!-- ============================================= -->
            <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 mb-6">
                <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                    <h2 class="text-lg font-bold text-gray-800">
                        <i class="fas fa-filter mr-2 gensa-text"></i> Filter Attendance
                    </h2>
                    <?php if ($total_pending > 0): ?>
                        <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">
                            <i class="fas fa-clock mr-1"></i>
                            <?php echo $total_pending; ?> total pending (semua tanggal)
                        </span>
                    <?php endif; ?>
                </div>

                <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Dari Tanggal</label>
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-yellow-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Sampai Tanggal</label>
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-yellow-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Guru</label>
                        <select name="guru_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-yellow-500">
                            <option value="0">Semua Guru</option>
                            <?php 
                            mysqli_data_seek($guru_list, 0);
                            while ($g = mysqli_fetch_assoc($guru_list)): 
                            ?>
                                <option value="<?php echo $g['id']; ?>" <?php echo $filter_guru == $g['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($g['full_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Tampilan</label>
                        <select name="group" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-yellow-500">
                            <option value="1" <?php echo $group_by_date ? 'selected' : ''; ?>>Kelompok per Tanggal</option>
                            <option value="0" <?php echo !$group_by_date ? 'selected' : ''; ?>>Daftar Biasa</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" 
                                class="flex-1 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg text-sm font-medium">
                            <i class="fas fa-search mr-1"></i> Terapkan
                        </button>
                        <a href="approve_attendance.php" 
                           class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </form>

                <!-- Quick date shortcuts -->
                <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-100">
                    <span class="text-xs text-gray-500 py-1">Cepat:</span>
                    <?php
                    $shortcuts = [
                        ['label' => 'Hari Ini', 'start' => date('Y-m-d'), 'end' => date('Y-m-d')],
                        ['label' => 'Kemarin', 'start' => date('Y-m-d', strtotime('-1 day')), 'end' => date('Y-m-d', strtotime('-1 day'))],
                        ['label' => '7 Hari Terakhir', 'start' => date('Y-m-d', strtotime('-6 days')), 'end' => date('Y-m-d')],
                        ['label' => '30 Hari Terakhir', 'start' => date('Y-m-d', strtotime('-29 days')), 'end' => date('Y-m-d')],
                        ['label' => 'Bulan Ini', 'start' => date('Y-m-01'), 'end' => date('Y-m-t')],
                    ];
                    foreach ($shortcuts as $sc):
                        $active = ($start_date === $sc['start'] && $end_date === $sc['end']);
                        $url = "?start_date={$sc['start']}&end_date={$sc['end']}&guru_id=$filter_guru&group=" . ($group_by_date ? '1' : '0');
                    ?>
                        <a href="<?php echo $url; ?>" 
                           class="px-3 py-1 text-xs rounded-full border <?php echo $active ? 'bg-yellow-500 text-white border-yellow-500' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'; ?>">
                            <?php echo $sc['label']; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ============================================= -->
            <!-- PENDING ATTENDANCE -->
            <!-- ============================================= -->
            <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 mb-6">
                <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                    <h2 class="text-xl font-bold text-gray-800">
                        Pending Attendance
                        <?php if ($pending_in_range > 0): ?>
                            <span class="ml-2 px-2 py-1 bg-yellow-100 text-yellow-700 text-sm rounded-full">
                                <?php echo $pending_in_range; ?>
                            </span>
                        <?php endif; ?>
                    </h2>
                    <p class="text-sm text-gray-500">
                        <?php echo date('d M Y', strtotime($start_date)); ?> &mdash; <?php echo date('d M Y', strtotime($end_date)); ?>
                    </p>
                </div>

                <?php if ($pending_in_range > 0): ?>
                    <?php if ($group_by_date): 
                        // Kelompokkan berdasarkan tanggal
                        $grouped = [];
                        mysqli_data_seek($pending_attendance, 0);
                        while ($row = mysqli_fetch_assoc($pending_attendance)) {
                            $grouped[$row['tanggal']][] = $row;
                        }
                    ?>
                        <?php foreach ($grouped as $tanggal => $items): 
                            $is_today = ($tanggal === date('Y-m-d'));
                            $is_yesterday = ($tanggal === date('Y-m-d', strtotime('-1 day')));
                        ?>
                            <div class="mb-8 last:mb-0">
                                <!-- Date Header -->
                                <div class="flex items-center space-x-3 mb-4 pb-2 border-b border-gray-200">
                                    <div class="w-10 h-10 <?php echo $is_today ? 'bg-yellow-500 text-white' : 'bg-gray-100 text-gray-600'; ?> rounded-lg flex flex-col items-center justify-center">
                                        <span class="text-xs leading-none"><?php echo date('M', strtotime($tanggal)); ?></span>
                                        <span class="text-sm font-bold leading-none"><?php echo date('d', strtotime($tanggal)); ?></span>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-gray-800">
                                            <?php echo date('l, d F Y', strtotime($tanggal)); ?>
                                        </h3>
                                        <p class="text-xs text-gray-500">
                                            <?php echo count($items); ?> pengajuan pending
                                        </p>
                                    </div>
                                    <?php if ($is_today): ?>
                                        <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs font-medium">
                                            <i class="fas fa-circle text-xs mr-1"></i>Hari Ini
                                        </span>
                                    <?php elseif ($is_yesterday): ?>
                                        <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs">
                                            Kemarin
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Cards untuk tanggal ini -->
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <?php foreach ($items as $attendance): ?>
                                        <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition">
                                            <div class="p-4">
                                                <!-- Teacher Info -->
                                                <div class="flex items-center space-x-3 mb-4">
                                                    <div class="w-12 h-12 rounded-full overflow-hidden bg-yellow-100 flex-shrink-0">
                                                        <?php if (!empty($attendance['guru_foto']) && file_exists('../' . $attendance['guru_foto'])): ?>
                                                            <img src="../<?php echo $attendance['guru_foto']; ?>"
                                                                 alt="<?php echo htmlspecialchars($attendance['guru_nama']); ?>"
                                                                 class="w-full h-full object-cover">
                                                        <?php else: ?>
                                                            <div class="w-full h-full flex items-center justify-center">
                                                                <i class="fas fa-chalkboard-teacher text-yellow-600"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="font-semibold truncate"><?php echo htmlspecialchars($attendance['guru_nama']); ?></p>
                                                        <p class="text-sm text-gray-500 truncate"><?php echo htmlspecialchars($attendance['guru_email']); ?></p>
                                                    </div>
                                                </div>

                                                <!-- Detail -->
                                                <div class="space-y-2 mb-4">
                                                    <div class="flex items-center text-sm">
                                                        <i class="fas fa-clock text-gray-400 mr-2 w-5"></i>
                                                        <span>
                                                            <?php echo date('H:i', strtotime($attendance['jam_mulai'])); ?> -
                                                            <?php echo date('H:i', strtotime($attendance['jam_selesai'])); ?>
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center text-sm">
                                                        <i class="fas fa-map-marker-alt text-gray-400 mr-2 w-5"></i>
                                                        <span class="truncate" title="<?php echo htmlspecialchars($attendance['lokasi']); ?>">
                                                            <?php echo htmlspecialchars($attendance['lokasi']); ?>
                                                        </span>
                                                    </div>
                                                    <?php if (!empty($attendance['jenis_kehadiran'])): ?>
                                                    <div class="flex items-center text-sm">
                                                        <i class="fas fa-info-circle text-gray-400 mr-2 w-5"></i>
                                                        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-medium">
                                                            <?php echo ucfirst(htmlspecialchars($attendance['jenis_kehadiran'])); ?>
                                                        </span>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- FOTO -->
                                                <?php if (!empty($attendance['foto_bukti']) && file_exists('../' . $attendance['foto_bukti'])): ?>
                                                    <div class="mb-4">
                                                        <p class="text-sm font-medium text-gray-700 mb-2">Proof Photo:</p>
                                                        <div class="relative group">
                                                            <img src="../<?php echo $attendance['foto_bukti']; ?>"
                                                                 alt="Attendance Proof"
                                                                 class="w-full h-48 object-cover rounded-lg cursor-pointer"
                                                                 onclick="viewImage('../<?php echo $attendance['foto_bukti']; ?>')">
                                                            <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition flex items-center justify-center opacity-0 group-hover:opacity-100 pointer-events-none">
                                                                <i class="fas fa-search-plus text-white text-2xl"></i>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="mb-4">
                                                        <p class="text-sm font-medium text-gray-700 mb-2">Proof Photo:</p>
                                                        <div class="w-full h-32 bg-gray-100 rounded-lg flex items-center justify-center border-2 border-dashed border-gray-300">
                                                            <div class="text-center text-gray-400">
                                                                <i class="fas fa-image text-2xl mb-1"></i>
                                                                <p class="text-xs">No photo</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Note -->
                                                <?php if (!empty($attendance['catatan'])): ?>
                                                    <div class="mb-4">
                                                        <p class="text-sm font-medium text-gray-700 mb-1">Note:</p>
                                                        <p class="text-sm text-gray-600 bg-gray-50 p-2 rounded">
                                                            <?php echo htmlspecialchars($attendance['catatan']); ?>
                                                        </p>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Actions -->
                                                <div class="flex space-x-2">
                                                    <a href="?approve=<?php echo $attendance['id']; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&guru_id=<?php echo $filter_guru; ?>&group=<?php echo $group_by_date ? '1' : '0'; ?>"
                                                       onclick="return confirmApprove(<?php echo $attendance['id']; ?>)"
                                                       class="flex-1 py-2 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg text-center text-sm font-medium">
                                                        <i class="fas fa-check mr-1"></i> Approve
                                                    </a>
                                                    <button onclick="showRejectForm(<?php echo $attendance['id']; ?>)"
                                                            class="flex-1 py-2 bg-red-100 text-red-700 hover:bg-red-200 rounded-lg text-sm font-medium">
                                                        <i class="fas fa-times mr-1"></i> Reject
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Tampilan daftar biasa (tanpa grouping) -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php mysqli_data_seek($pending_attendance, 0); ?>
                            <?php while ($attendance = mysqli_fetch_assoc($pending_attendance)): ?>
                                <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition">
                                    <div class="p-4">
                                        <div class="flex items-center space-x-3 mb-4">
                                            <div class="w-12 h-12 rounded-full overflow-hidden bg-yellow-100 flex-shrink-0">
                                                <?php if (!empty($attendance['guru_foto']) && file_exists('../' . $attendance['guru_foto'])): ?>
                                                    <img src="../<?php echo $attendance['guru_foto']; ?>"
                                                         alt="<?php echo htmlspecialchars($attendance['guru_nama']); ?>"
                                                         class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <div class="w-full h-full flex items-center justify-center">
                                                        <i class="fas fa-chalkboard-teacher text-yellow-600"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-semibold truncate"><?php echo htmlspecialchars($attendance['guru_nama']); ?></p>
                                                <p class="text-sm text-gray-500 truncate">
                                                    <?php echo date('d M Y', strtotime($attendance['tanggal'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="space-y-2 mb-4">
                                            <div class="flex items-center text-sm">
                                                <i class="fas fa-clock text-gray-400 mr-2 w-5"></i>
                                                <span>
                                                    <?php echo date('H:i', strtotime($attendance['jam_mulai'])); ?> -
                                                    <?php echo date('H:i', strtotime($attendance['jam_selesai'])); ?>
                                                </span>
                                            </div>
                                            <div class="flex items-center text-sm">
                                                <i class="fas fa-map-marker-alt text-gray-400 mr-2 w-5"></i>
                                                <span class="truncate"><?php echo htmlspecialchars($attendance['lokasi']); ?></span>
                                            </div>
                                        </div>
                                        <div class="flex space-x-2">
                                            <a href="?approve=<?php echo $attendance['id']; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&guru_id=<?php echo $filter_guru; ?>&group=<?php echo $group_by_date ? '1' : '0'; ?>"
                                               onclick="return confirmApprove(<?php echo $attendance['id']; ?>)"
                                               class="flex-1 py-2 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg text-center text-sm font-medium">
                                                <i class="fas fa-check mr-1"></i> Approve
                                            </a>
                                            <button onclick="showRejectForm(<?php echo $attendance['id']; ?>)"
                                                    class="flex-1 py-2 bg-red-100 text-red-700 hover:bg-red-200 rounded-lg text-sm font-medium">
                                                <i class="fas fa-times mr-1"></i> Reject
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-clipboard-check text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No pending attendance in this date range</p>
                        <p class="text-sm text-gray-400 mt-1">
                            Try changing the date filter or click "Reset"
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ============================================= -->
            <!-- ATTENDANCE HISTORY -->
            <!-- ============================================= -->
            <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
                <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                    <h2 class="text-xl font-bold text-gray-800">Attendance History</h2>
                    <p class="text-sm text-gray-500">
                        Menampilkan dalam rentang tanggal filter
                    </p>
                </div>

                <?php if (mysqli_num_rows($history) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px]">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Teacher</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Date & Time</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Location</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Status</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Approved By</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($record = mysqli_fetch_assoc($history)): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-3 px-4">
                                            <p class="font-medium"><?php echo htmlspecialchars($record['guru_nama']); ?></p>
                                            <p class="text-sm text-gray-500">
                                                <?php echo date('H:i', strtotime($record['jam_mulai'])); ?> -
                                                <?php echo date('H:i', strtotime($record['jam_selesai'])); ?>
                                            </p>
                                        </td>
                                        <td class="py-3 px-4">
                                            <p class="text-sm"><?php echo date('d M Y', strtotime($record['tanggal'])); ?></p>
                                            <?php if (!empty($record['approved_at'])): ?>
                                            <p class="text-xs text-gray-500">
                                                Approved: <?php echo date('H:i', strtotime($record['approved_at'])); ?>
                                            </p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-4">
                                            <p class="text-sm truncate max-w-xs" title="<?php echo htmlspecialchars($record['lokasi']); ?>">
                                                <?php echo htmlspecialchars($record['lokasi']); ?>
                                            </p>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $record['status'] == 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?php echo ucfirst($record['status']); ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <?php echo htmlspecialchars($record['approved_by_name'] ?: 'System'); ?>
                                        </td>
                                        <td class="py-3 px-4">
                                            <a href="attendance_details.php?id=<?php echo $record['id']; ?>"
                                               class="inline-block px-3 py-1 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded text-sm">
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
                        <i class="fas fa-history text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No attendance history in this date range</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="fixed inset-0 bg-black bg-opacity-75 hidden items-center justify-center p-4 z-50">
        <div class="relative max-w-4xl w-full">
            <button onclick="closeImageModal()" class="absolute -top-10 right-0 text-white hover:text-gray-300">
                <i class="fas fa-times text-2xl"></i>
            </button>
            <img id="modalImage" src="" alt="" class="w-full h-auto rounded-lg">
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl max-w-md w-full">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Reject Attendance</h3>
                <form method="POST" action="">
                    <input type="hidden" name="reject_attendance" value="1">
                    <input type="hidden" id="attendance_id" name="attendance_id">
                    <!-- Pertahankan filter saat submit reject -->
                    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    <input type="hidden" name="guru_id" value="<?php echo $filter_guru; ?>">
                    <input type="hidden" name="group" value="<?php echo $group_by_date ? '1' : '0'; ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Rejection</label>
                        <textarea name="reason" rows="3" required
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
                                  placeholder="Enter reason..."></textarea>
                    </div>
                    <div class="flex space-x-3 mt-4">
                        <button type="button" onclick="closeRejectModal()"
                                class="flex-1 py-2 bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-lg">
                            Cancel
                        </button>
                        <button type="submit"
                                class="flex-1 py-2 bg-red-600 text-white hover:bg-red-700 rounded-lg">
                            Confirm Reject
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function viewImage(src) {
        document.getElementById('modalImage').src = src;
        document.getElementById('imageModal').classList.remove('hidden');
        document.getElementById('imageModal').classList.add('flex');
    }
    function closeImageModal() {
        document.getElementById('imageModal').classList.add('hidden');
        document.getElementById('imageModal').classList.remove('flex');
    }
    function showRejectForm(id) {
        document.getElementById('attendance_id').value = id;
        document.getElementById('rejectModal').classList.remove('hidden');
        document.getElementById('rejectModal').classList.add('flex');
    }
    function closeRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
        document.getElementById('rejectModal').classList.remove('flex');
    }
    function confirmApprove(id) {
        return Swal.fire({
            title: 'Approve Attendance?',
            text: 'Setujui kehadiran ini?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, approve!',
            cancelButtonText: 'Cancel'
        }).then(r => r.isConfirmed);
    }

    // Notifikasi
    <?php if ($success): ?>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?php echo addslashes($success); ?>', timer: 2500, showConfirmButton: false });
    <?php endif; ?>
    <?php if ($error): ?>
        Swal.fire({ icon: 'error', title: 'Gagal!', text: '<?php echo addslashes($error); ?>' });
    <?php endif; ?>
    </script>
</body>
</html>