<?php
include '../includes/auth_check.php';
include '../config/database.php';

ob_start();

// Handle approve/reject langsung dari halaman ini
if (isset($_GET['approve'])) {
    $user_id = (int) $_GET['approve'];
    $query = "UPDATE users SET status = 'active' WHERE id = $user_id AND role = 'pelajar'";
    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "User approved successfully!";
    } else {
        $_SESSION['error'] = "Error: " . mysqli_error($conn);
    }
    ob_end_clean();
    header('Location: pelajar_details.php?id=' . $user_id);
    exit();
}

if (isset($_GET['reject'])) {
    $user_id = (int) $_GET['reject'];
    $query = "UPDATE users SET status = 'inactive' WHERE id = $user_id AND role = 'pelajar'";
    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "User rejected successfully!";
    } else {
        $_SESSION['error'] = "Error: " . mysqli_error($conn);
    }
    ob_end_clean();
    header('Location: pelajar_details.php?id=' . $user_id);
    exit();
}

// Ambil ID
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error'] = "Invalid student ID";
    header('Location: approve_user.php');
    exit();
}

// Ambil data user + profil pelajar
$query = "SELECT u.id, u.username, u.email, u.full_name, u.created_at, u.status,
                 p.asal_sekolah, p.kelas, p.subkelas, p.jenjang, p.mata_pelajaran,
                 p.no_telp, p.no_wali, p.foto_profil
          FROM users u
          LEFT JOIN pelajar_profiles p ON u.id = p.user_id
          WHERE u.id = $id AND u.role = 'pelajar'
          LIMIT 1";
$result = mysqli_query($conn, $query);
$student = mysqli_fetch_assoc($result);

if (!$student) {
    $_SESSION['error'] = "Student not found";
    header('Location: approve_user.php');
    exit();
}

// Ambil pesan session
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
    <title>Student Details - <?php echo htmlspecialchars($student['full_name']); ?></title>
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
                    <h1 class="text-2xl font-bold text-gray-800">Student Details</h1>
                    <p class="text-gray-600">Informasi lengkap siswa</p>
                </div>
                <a href="approve_user.php" class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Approve Users
                </a>
            </div>
        </div>

        <div class="p-4 sm:p-6">
            <!-- Success Message -->
            <?php if ($success): ?>
                <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: '<?php echo addslashes($success); ?>',
                    timer: 2500,
                    showConfirmButton: false
                });
                </script>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if ($error): ?>
                <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: '<?php echo addslashes($error); ?>'
                });
                </script>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-md p-6 max-w-4xl mx-auto">
                <!-- Header Profil -->
                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4 mb-6 pb-6 border-b">
                    <?php if (!empty($student['foto_profil']) && file_exists('../' . $student['foto_profil'])): ?>
                        <img src="../<?php echo $student['foto_profil']; ?>"
                             class="w-24 h-24 rounded-full object-cover border-4 border-blue-100">
                    <?php else: ?>
                        <div class="w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-blue-600 text-3xl"></i>
                        </div>
                    <?php endif; ?>
                    <div class="text-center sm:text-left">
                        <h2 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($student['full_name']); ?></h2>
                        <p class="text-gray-500">@<?php echo htmlspecialchars($student['username']); ?></p>
                        <span class="inline-block mt-2 px-3 py-1 rounded-full text-xs font-medium
                            <?php
                                if ($student['status'] == 'active') echo 'bg-green-100 text-green-800';
                                elseif ($student['status'] == 'pending') echo 'bg-yellow-100 text-yellow-800';
                                else echo 'bg-red-100 text-red-800';
                            ?>">
                            <i class="fas fa-circle mr-1 text-[8px]"></i>
                            <?php echo ucfirst($student['status']); ?>
                        </span>
                    </div>
                </div>

                <!-- Detail Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Email</p>
                        <p class="font-medium"><?php echo htmlspecialchars($student['email']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">No. Telepon</p>
                        <p class="font-medium"><?php echo htmlspecialchars($student['no_telp'] ?? '-'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">No. Wali</p>
                        <p class="font-medium"><?php echo htmlspecialchars($student['no_wali'] ?? '-'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Asal Sekolah</p>
                        <p class="font-medium"><?php echo htmlspecialchars($student['asal_sekolah'] ?? '-'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Jenjang</p>
                        <p class="font-medium"><?php echo htmlspecialchars($student['jenjang'] ?? '-'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Kelas</p>
                        <p class="font-medium">
                            <?php echo htmlspecialchars(($student['kelas'] ?? '-') . ' - ' . ($student['subkelas'] ?? '-')); ?>
                        </p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-500">Mata Pelajaran</p>
                        <div class="flex flex-wrap gap-2 mt-1">
                            <?php
                            if (!empty($student['mata_pelajaran'])) {
                                foreach (explode(',', $student['mata_pelajaran']) as $subject):
                                    $color = trim($subject) == 'arab'
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-blue-100 text-blue-800';
                            ?>
                                <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $color; ?>">
                                    <i class="fas fa-book mr-1"></i>
                                    <?php echo ucfirst(trim($subject)); ?>
                                </span>
                            <?php
                                endforeach;
                            } else {
                                echo '<span class="text-gray-400">-</span>';
                            }
                            ?>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Tanggal Registrasi</p>
                        <p class="font-medium"><?php echo date('d M Y H:i', strtotime($student['created_at'])); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">User ID</p>
                        <p class="font-medium">#<?php echo $student['id']; ?></p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-6 pt-6 border-t flex flex-wrap gap-3">
                    <?php if ($student['status'] != 'active'): ?>
                        <a href="?id=<?php echo $student['id']; ?>&approve=<?php echo $student['id']; ?>"
                           onclick="return confirmApprove('<?php echo addslashes($student['full_name']); ?>')"
                           class="px-4 py-2 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg font-medium">
                            <i class="fas fa-check mr-1"></i> Approve
                        </a>
                    <?php endif; ?>

                    <?php if ($student['status'] != 'inactive'): ?>
                        <a href="?id=<?php echo $student['id']; ?>&reject=<?php echo $student['id']; ?>"
                           onclick="return confirmReject('<?php echo addslashes($student['full_name']); ?>')"
                           class="px-4 py-2 bg-red-100 text-red-700 hover:bg-red-200 rounded-lg font-medium">
                            <i class="fas fa-times mr-1"></i> Reject
                        </a>
                    <?php endif; ?>

                    <a href="approve_user.php"
                       class="px-4 py-2 bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-lg font-medium">
                        <i class="fas fa-arrow-left mr-1"></i> Kembali
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
    function confirmApprove(name) {
        return Swal.fire({
            title: 'Approve Student?',
            html: `Approve student <b>${name}</b>?<br>This will allow them to login.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, approve!',
            cancelButtonText: 'Cancel'
        }).then((result) => result.isConfirmed);
    }

    function confirmReject(name) {
        return Swal.fire({
            title: 'Reject Student?',
            html: `Reject student <b>${name}</b>?<br>This will prevent them from logging in.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, reject!',
            cancelButtonText: 'Cancel'
        }).then((result) => result.isConfirmed);
    }
    </script>
</body>
</html>