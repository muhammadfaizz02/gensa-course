<?php
include '../includes/auth_check.php';
include '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

ob_start();

// ===== FILTER =====
$filter_spec    = $_GET['spec'] ?? '';       // '', 'arab', 'inggris'
$filter_status  = $_GET['status'] ?? '';     // '', 'active', 'inactive'
$date_from      = $_GET['from'] ?? date('Y-m-01');
$date_to        = $_GET['to']   ?? date('Y-m-t');

$where = ["u.role = 'guru'"];
if ($filter_spec === 'arab')    $where[] = "g.spesialisasi LIKE '%arab%'";
if ($filter_spec === 'inggris') $where[] = "g.spesialisasi LIKE '%inggris%'";
if ($filter_status === 'active')   $where[] = "u.status = 'active'";
if ($filter_status === 'inactive') $where[] = "u.status = 'inactive'";
$where_sql = implode(' AND ', $where);

// ===== DATA GURU + STATISTIK =====
$query = "
    SELECT 
        u.id, u.username, u.email, u.full_name, u.status, u.created_at,
        g.spesialisasi, g.foto_profil,
        COALESCE(att.total_sessions, 0)   AS total_sessions,
        COALESCE(att.approved_sessions, 0) AS approved_sessions,
        COALESCE(att.pending_sessions, 0)  AS pending_sessions,
        COALESCE(att.rejected_sessions, 0) AS rejected_sessions,
        COALESCE(att.total_hours, 0)       AS total_hours
    FROM users u
    LEFT JOIN guru_profiles g ON u.id = g.user_id
    LEFT JOIN (
        SELECT guru_id,
               COUNT(*) AS total_sessions,
               COUNT(CASE WHEN status = 'approved' THEN 1 END) AS approved_sessions,
               COUNT(CASE WHEN status = 'pending'  THEN 1 END) AS pending_sessions,
               COUNT(CASE WHEN status = 'rejected' THEN 1 END) AS rejected_sessions,
               COALESCE(SUM(CASE WHEN status = 'approved'
                    THEN TIME_TO_SEC(TIMEDIFF(jam_selesai, jam_mulai)) / 3600
                    ELSE 0 END), 0) AS total_hours
        FROM kehadiran_guru
        WHERE tanggal BETWEEN '$date_from' AND '$date_to'
        GROUP BY guru_id
    ) att ON att.guru_id = u.id
    WHERE $where_sql
    ORDER BY total_hours DESC, total_sessions DESC, u.full_name ASC
";
$teachers = mysqli_query($conn, $query);

// ===== SUMMARY =====
$summary_query = "
    SELECT 
        COUNT(*) AS total_teachers,
        COUNT(CASE WHEN u.status = 'active' THEN 1 END) AS active_teachers,
        COUNT(CASE WHEN g.spesialisasi LIKE '%arab%'    THEN 1 END) AS arab_teachers,
        COUNT(CASE WHEN g.spesialisasi LIKE '%inggris%' THEN 1 END) AS english_teachers
    FROM users u
    LEFT JOIN guru_profiles g ON u.id = g.user_id
    WHERE u.role = 'guru'
";
$summary = mysqli_fetch_assoc(mysqli_query($conn, $summary_query));

// ===== TOTAL SESI APPROVED GLOBAL PERIODE =====
$grand_query = "
    SELECT 
        COUNT(*) AS total_all,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) AS approved_all,
        COUNT(CASE WHEN status = 'pending'  THEN 1 END) AS pending_all,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) AS rejected_all,
        COALESCE(SUM(CASE WHEN status = 'approved'
            THEN TIME_TO_SEC(TIMEDIFF(jam_selesai, jam_mulai)) / 3600
            ELSE 0 END), 0) AS hours_all
    FROM kehadiran_guru
    WHERE tanggal BETWEEN '$date_from' AND '$date_to'
";
$grand = mysqli_fetch_assoc(mysqli_query($conn, $grand_query));
$approval_rate = $grand['total_all'] > 0 ? round($grand['approved_all'] / $grand['total_all'] * 100, 1) : 0;

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
    <title>Teachers Report - Admin Gensa Course</title>
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
                    <h1 class="text-2xl font-bold text-gray-800">Teachers Report</h1>
                    <p class="text-gray-600">Rekap performa guru & statistik mengajar</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="window.print()"
                            class="px-4 py-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                    <a href="input_guru.php"
                       class="px-4 py-2 bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-lg">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <div class="p-4 sm:p-6">

            <!-- Filter Periode -->
            <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dari Tanggal</label>
                        <input type="date" name="from" value="<?php echo htmlspecialchars($date_from); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sampai Tanggal</label>
                        <input type="date" name="to" value="<?php echo htmlspecialchars($date_to); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Spesialisasi</label>
                        <select name="spec" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500">
                            <option value="">Semua</option>
                            <option value="arab"    <?php echo $filter_spec === 'arab' ? 'selected' : ''; ?>>Arabic</option>
                            <option value="inggris" <?php echo $filter_spec === 'inggris' ? 'selected' : ''; ?>>English</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500">
                            <option value="">Semua</option>
                            <option value="active"   <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit"
                                class="flex-1 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg font-medium">
                            <i class="fas fa-filter mr-1"></i> Filter
                        </button>
                        <a href="teachers_report.php"
                           class="py-2 px-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-md p-4 border-l-4 border-yellow-500">
                    <p class="text-xs text-gray-500">Total Guru</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo (int)$summary['total_teachers']; ?></p>
                    <p class="text-xs text-gray-500 mt-1"><?php echo (int)$summary['active_teachers']; ?> aktif</p>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border-l-4 border-blue-500">
                    <p class="text-xs text-gray-500">Arabic</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo (int)$summary['arab_teachers']; ?></p>
                    <p class="text-xs text-gray-500 mt-1">Guru bahasa Arab</p>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border-l-4 border-purple-500">
                    <p class="text-xs text-gray-500">English</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo (int)$summary['english_teachers']; ?></p>
                    <p class="text-xs text-gray-500 mt-1">Guru bahasa Inggris</p>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border-l-4 border-green-500">
                    <p class="text-xs text-gray-500">Approval Rate</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $approval_rate; ?>%</p>
                    <p class="text-xs text-gray-500 mt-1">
                        <?php echo $grand['approved_all']; ?>/<?php echo $grand['total_all']; ?> sesi
                    </p>
                </div>
            </div>

            <!-- Grand Total Periode -->
            <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 mb-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">
                    Statistik Periode
                    <span class="text-gray-400 font-normal">
                        (<?php echo date('d M Y', strtotime($date_from)); ?> -
                        <?php echo date('d M Y', strtotime($date_to)); ?>)
                    </span>
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <p class="text-xl font-bold text-gray-800"><?php echo $grand['total_all']; ?></p>
                        <p class="text-xs text-gray-500">Total Sesi</p>
                    </div>
                    <div class="text-center p-3 bg-green-50 rounded-lg">
                        <p class="text-xl font-bold text-green-600"><?php echo $grand['approved_all']; ?></p>
                        <p class="text-xs text-gray-500">Approved</p>
                    </div>
                    <div class="text-center p-3 bg-yellow-50 rounded-lg">
                        <p class="text-xl font-bold text-yellow-600"><?php echo $grand['pending_all']; ?></p>
                        <p class="text-xs text-gray-500">Pending</p>
                    </div>
                    <div class="text-center p-3 bg-purple-50 rounded-lg">
                        <p class="text-xl font-bold text-purple-600"><?php echo number_format($grand['hours_all'], 1); ?>h</p>
                        <p class="text-xs text-gray-500">Total Jam</p>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="p-4 sm:p-6 border-b">
                    <h2 class="text-lg font-bold text-gray-800">Detail Performa Guru</h2>
                    <p class="text-sm text-gray-500">
                        Diurutkan dari total jam mengajar tertinggi
                    </p>
                </div>

                <?php if (mysqli_num_rows($teachers) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px]">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-700 uppercase">#</th>
                                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-700 uppercase">Guru</th>
                                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-700 uppercase">Spesialisasi</th>
                                    <th class="py-3 px-4 text-center text-xs font-medium text-gray-700 uppercase">Total Sesi</th>
                                    <th class="py-3 px-4 text-center text-xs font-medium text-gray-700 uppercase">Approved</th>
                                    <th class="py-3 px-4 text-center text-xs font-medium text-gray-700 uppercase">Pending</th>
                                    <th class="py-3 px-4 text-center text-xs font-medium text-gray-700 uppercase">Rejected</th>
                                    <th class="py-3 px-4 text-center text-xs font-medium text-gray-700 uppercase">Total Jam</th>
                                    <th class="py-3 px-4 text-center text-xs font-medium text-gray-700 uppercase">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php $rank = 1; while ($t = mysqli_fetch_assoc($teachers)):
                                    $specs = array_filter(array_map('trim', explode(',', $t['spesialisasi'] ?? '')));
                                    $rate  = $t['total_sessions'] > 0
                                             ? round($t['approved_sessions'] / $t['total_sessions'] * 100)
                                             : 0;
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-3 px-4">
                                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold
                                                <?php echo $rank == 1 ? 'bg-yellow-100 text-yellow-700' : ($rank == 2 ? 'bg-gray-200 text-gray-700' : ($rank == 3 ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-600')); ?>">
                                                <?php echo $rank; ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-full overflow-hidden bg-yellow-100 shrink-0">
                                                    <?php if (!empty($t['foto_profil']) && file_exists('../' . $t['foto_profil'])): ?>
                                                        <img src="../<?php echo htmlspecialchars($t['foto_profil']); ?>"
                                                             class="w-full h-full object-cover">
                                                    <?php else: ?>
                                                        <div class="w-full h-full flex items-center justify-center">
                                                            <i class="fas fa-user text-yellow-600 text-xs"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="font-medium text-gray-800 truncate"><?php echo htmlspecialchars($t['full_name']); ?></p>
                                                    <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($t['email']); ?></p>
                                                    <span class="inline-block mt-0.5 px-2 py-0.5 rounded text-xs
                                                        <?php echo $t['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                        <?php echo ucfirst($t['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex flex-wrap gap-1">
                                                <?php foreach ($specs as $s):
                                                    $c = $s === 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
                                                ?>
                                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium <?php echo $c; ?>">
                                                        <?php echo ucfirst($s); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 text-center font-semibold text-gray-800">
                                            <?php echo $t['total_sessions']; ?>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-medium">
                                                <?php echo $t['approved_sessions']; ?>
                                            </span>
                                            <div class="text-[10px] text-gray-500 mt-0.5"><?php echo $rate; ?>%</div>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs font-medium">
                                                <?php echo $t['pending_sessions']; ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-medium">
                                                <?php echo $t['rejected_sessions']; ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span class="font-bold text-gray-800">
                                                <?php echo number_format($t['total_hours'], 1); ?>
                                            </span>
                                            <span class="text-xs text-gray-500">jam</span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <a href="teacher_details.php?id=<?php echo $t['id']; ?>"
                                               class="inline-block px-3 py-1 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded text-xs"
                                               title="Lihat detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php $rank++; endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-chalkboard-teacher text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">Tidak ada data guru dengan filter ini</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script>
    // Print styling
    const style = document.createElement('style');
    style.innerHTML = `
        @media print {
            body { background: white !important; }
            aside, nav, button, a[href="input_guru.php"] { display: none !important; }
            .lg\\:ml-64 { margin-left: 0 !important; }
            .shadow-md { box-shadow: none !important; }
            .rounded-xl { border-radius: 0 !important; }
            .p-4, .sm\\:p-6 { padding: 8px !important; }
            .lg\\:ml-64.pt-14.lg\\:pt-0 { padding-top: 0 !important; }
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>