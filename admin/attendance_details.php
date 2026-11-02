<?php
include '../includes/auth_check.php';
include '../config/database.php';

ob_start();

// Validasi ID
$attendance_id = (int) ($_GET['id'] ?? 0);
if ($attendance_id <= 0) {
    $_SESSION['error'] = "Invalid attendance ID";
    header('Location: attendance_report.php');
    exit();
}

// Handle approve langsung dari halaman ini
if (isset($_GET['approve'])) {
    $query = "UPDATE kehadiran_guru 
              SET status = 'approved', approved_by = {$_SESSION['user_id']}, approved_at = NOW() 
              WHERE id = $attendance_id";
    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Attendance approved successfully!";
    } else {
        $_SESSION['error'] = "Error: " . mysqli_error($conn);
    }
    ob_end_clean();
    header('Location: attendance_details.php?id=' . $attendance_id);
    exit();
}

// Handle reject langsung dari halaman ini
if (isset($_GET['reject'])) {
    $query = "UPDATE kehadiran_guru 
              SET status = 'rejected', approved_by = {$_SESSION['user_id']}, approved_at = NOW() 
              WHERE id = $attendance_id";
    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Attendance rejected successfully!";
    } else {
        $_SESSION['error'] = "Error: " . mysqli_error($conn);
    }
    ob_end_clean();
    header('Location: attendance_details.php?id=' . $attendance_id);
    exit();
}

// Ambil data kehadiran
$query = "
    SELECT k.*, u.full_name as guru_nama, u.email as guru_email,
           g.foto_profil as guru_foto, g.spesialisasi,
           a.full_name as approved_by_name
    FROM kehadiran_guru k
    JOIN users u ON k.guru_id = u.id
    LEFT JOIN guru_profiles g ON u.id = g.user_id
    LEFT JOIN users a ON k.approved_by = a.id
    WHERE k.id = $attendance_id
";
$attendance = mysqli_fetch_assoc(mysqli_query($conn, $query));

if (!$attendance) {
    $_SESSION['error'] = "Attendance record not found";
    header('Location: attendance_report.php');
    exit();
}

// Pesan session
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
    <title>Attendance Details - Admin Gensa Course</title>
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
                    <h1 class="text-2xl font-bold text-gray-800">Attendance Details</h1>
                    <p class="text-gray-600">ID: #<?php echo str_pad($attendance_id, 6, '0', STR_PAD_LEFT); ?></p>
                </div>
                <div class="flex gap-2">
                    <button onclick="window.print()"
                            class="px-4 py-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                    <a href="attendance_report.php"
                       class="px-4 py-2 bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-lg">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <div class="p-4 sm:p-6">
            <div class="max-w-4xl mx-auto">
                <!-- Attendance Details -->
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
                                        echo $attendance['status'] == 'approved' ? 'Approved by ' . htmlspecialchars($attendance['approved_by_name'] ?? '-') :
                                            ($attendance['status'] == 'rejected' ? 'Rejected by ' . htmlspecialchars($attendance['approved_by_name'] ?? '-') : 'Pending Approval');
                                        ?>
                                    </p>
                                </div>
                            </div>
                            <span class="px-3 py-1 bg-white bg-opacity-20 rounded-full text-sm">
                                <?php echo date('l, d F Y', strtotime($attendance['tanggal'])); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="p-6">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Teacher Info -->
                            <div class="lg:col-span-2">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Teacher Information</h3>

                                <div class="flex items-start space-x-4 mb-6">
                                    <div class="w-20 h-20 rounded-full overflow-hidden bg-yellow-100 flex-shrink-0">
                                        <?php if (!empty($attendance['guru_foto']) && file_exists('../' . $attendance['guru_foto'])): ?>
                                            <img src="../<?php echo $attendance['guru_foto']; ?>"
                                                 alt="<?php echo htmlspecialchars($attendance['guru_nama']); ?>"
                                                 class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center">
                                                <i class="fas fa-user text-3xl text-yellow-600"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h4 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($attendance['guru_nama']); ?></h4>
                                        <p class="text-gray-600"><?php echo htmlspecialchars($attendance['guru_email']); ?></p>
                                        <?php if (!empty($attendance['spesialisasi'])): ?>
                                        <div class="flex flex-wrap gap-1 mt-2">
                                            <?php
                                            foreach (explode(',', $attendance['spesialisasi']) as $spec):
                                                $spec = trim($spec);
                                                $color = $spec == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
                                            ?>
                                                <span class="px-2 py-1 rounded text-xs font-medium <?php echo $color; ?>">
                                                    <?php echo ucfirst($spec); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Attendance Details -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                    <div class="p-4 bg-gray-50 rounded-lg">
                                        <p class="text-sm font-medium text-gray-600 mb-1">Teaching Hours</p>
                                        <p class="text-xl font-bold text-gray-800">
                                            <?php echo date('H:i', strtotime($attendance['jam_mulai'])); ?> -
                                            <?php echo date('H:i', strtotime($attendance['jam_selesai'])); ?>
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            Duration:
                                            <?php
                                            $duration = (strtotime($attendance['jam_selesai']) - strtotime($attendance['jam_mulai'])) / 3600;
                                            echo number_format($duration, 2); ?> hours
                                        </p>
                                    </div>

                                    <div class="p-4 bg-gray-50 rounded-lg">
                                        <p class="text-sm font-medium text-gray-600 mb-1">Location</p>
                                        <p class="text-lg font-medium text-gray-800"><?php echo htmlspecialchars($attendance['lokasi'] ?? '-'); ?></p>
                                        <p class="text-sm text-gray-600">GPS Verified</p>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <?php if (!empty($attendance['catatan'])): ?>
                                    <div class="mb-6">
                                        <h4 class="font-medium text-gray-800 mb-2">Teacher's Note</h4>
                                        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-r">
                                            <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($attendance['catatan'])); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Approval Info -->
                                <?php if (!empty($attendance['approved_at'])): ?>
                                    <div class="p-4 bg-blue-50 rounded-lg">
                                        <h4 class="font-medium text-gray-800 mb-2">Approval Information</h4>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <p class="text-sm text-gray-600">Approved By</p>
                                                <p class="font-medium"><?php echo htmlspecialchars($attendance['approved_by_name'] ?? '-'); ?></p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-600">Approval Time</p>
                                                <p class="font-medium">
                                                    <?php echo date('d M Y H:i', strtotime($attendance['approved_at'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Proof Photo -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Proof Photo</h3>

                                <?php if (!empty($attendance['foto_bukti']) && file_exists('../' . $attendance['foto_bukti'])): ?>
                                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                                        <img src="../<?php echo $attendance['foto_bukti']; ?>"
                                             alt="Attendance Proof"
                                             class="w-full h-64 object-cover">
                                        <div class="p-3 bg-gray-50">
                                            <p class="text-sm text-gray-600 text-center">
                                                Taken at <?php echo date('H:i', strtotime($attendance['jam_mulai'])); ?>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="mt-4 space-y-2">
                                        <button onclick="downloadPhoto()"
                                                class="w-full py-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg">
                                            <i class="fas fa-download mr-2"></i> Download Photo
                                        </button>
                                        <button onclick="viewOriginal()"
                                                class="w-full py-2 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg">
                                            <i class="fas fa-expand mr-2"></i> View Original
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                                        <i class="fas fa-camera text-4xl text-gray-300 mb-4"></i>
                                        <p class="text-gray-500">No proof photo uploaded</p>
                                    </div>
                                <?php endif; ?>

                                <!-- Statistics -->
                                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                                    <h4 class="font-medium text-gray-800 mb-3">This Month's Activity</h4>
                                    <?php
                                    $month_start = date('Y-m-01');
                                    $month_end = date('Y-m-t');
                                    $guru_id = (int) $attendance['guru_id'];

                                    $stats_query = "
                                        SELECT
                                            COUNT(*) as total_sessions,
                                            COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(jam_selesai, jam_mulai))) / 3600, 0) as total_hours
                                        FROM kehadiran_guru
                                        WHERE guru_id = $guru_id
                                        AND status = 'approved'
                                        AND tanggal BETWEEN '$month_start' AND '$month_end'
                                    ";
                                    $stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_query));
                                    ?>
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-600">Sessions</span>
                                            <span class="font-medium"><?php echo $stats['total_sessions']; ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-600">Total Hours</span>
                                            <span class="font-medium"><?php echo number_format($stats['total_hours'], 1); ?>h</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-600">Average/Session</span>
                                            <span class="font-medium">
                                                <?php
                                                $avg = $stats['total_sessions'] > 0 ? $stats['total_hours'] / $stats['total_sessions'] : 0;
                                                echo number_format($avg, 1); ?>h
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <?php if ($attendance['status'] == 'pending'): ?>
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Administrator Actions</h3>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <a href="?id=<?php echo $attendance_id; ?>&approve=<?php echo $attendance_id; ?>"
                               onclick="return confirmApprove()"
                               class="flex-1 py-3 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-lg text-center">
                                <i class="fas fa-check mr-2"></i> Approve Attendance
                            </a>
                            <a href="?id=<?php echo $attendance_id; ?>&reject=<?php echo $attendance_id; ?>"
                               onclick="return confirmReject()"
                               class="flex-1 py-3 bg-red-500 hover:bg-red-600 text-white font-semibold rounded-lg text-center">
                                <i class="fas fa-times mr-2"></i> Reject Attendance
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Photo Modal -->
    <div id="photoModal" class="fixed inset-0 bg-black bg-opacity-75 hidden items-center justify-center p-4 z-50">
        <div class="relative max-w-4xl w-full">
            <button onclick="closePhotoModal()"
                    class="absolute -top-10 right-0 text-white hover:text-gray-300">
                <i class="fas fa-times text-2xl"></i>
            </button>
            <?php if (!empty($attendance['foto_bukti'])): ?>
                <img src="../<?php echo $attendance['foto_bukti']; ?>"
                     alt="Attendance Proof"
                     class="w-full h-auto rounded-lg">
            <?php endif; ?>
        </div>
    </div>

    <script>
    // Notifikasi sukses
    <?php if ($success): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?php echo addslashes($success); ?>',
            timer: 2500,
            showConfirmButton: false
        });
    <?php endif; ?>

    // Notifikasi error
    <?php if ($error): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: '<?php echo addslashes($error); ?>'
        });
    <?php endif; ?>

    function confirmApprove() {
        return Swal.fire({
            title: 'Approve Attendance?',
            text: 'Setujui kehadiran ini?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, approve!',
            cancelButtonText: 'Cancel'
        }).then((result) => result.isConfirmed);
    }

    function confirmReject() {
        return Swal.fire({
            title: 'Reject Attendance?',
            text: 'Tolak kehadiran ini?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, reject!',
            cancelButtonText: 'Cancel'
        }).then((result) => result.isConfirmed);
    }

    function viewOriginal() {
        document.getElementById('photoModal').classList.remove('hidden');
        document.getElementById('photoModal').classList.add('flex');
    }

    function closePhotoModal() {
        document.getElementById('photoModal').classList.add('hidden');
        document.getElementById('photoModal').classList.remove('flex');
    }

    function downloadPhoto() {
        <?php if (!empty($attendance['foto_bukti'])): ?>
            const link = document.createElement('a');
            link.href = '../<?php echo $attendance['foto_bukti']; ?>';
            link.download = 'attendance_<?php echo $attendance_id; ?>.jpg';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        <?php endif; ?>
    }

    // Print styles
    const style = document.createElement('style');
    style.innerHTML = `
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 20px; }
            .bg-gray-50 { background: white !important; }
            .shadow-md { box-shadow: none !important; }
            .rounded-xl { border-radius: 0 !important; }
            button, a { display: none !important; }
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>