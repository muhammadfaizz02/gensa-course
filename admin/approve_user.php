<?php
include '../includes/auth_check.php';
include '../config/database.php';

// Hapus semua debug output sebelum redirect
ob_start(); // Start output buffering

// ============================================
// APPROVE USER - Update kedua tabel
// ============================================
if (isset($_GET['approve'])) {
    $user_id = (int) $_GET['approve'];
    
    mysqli_begin_transaction($conn);
    try {
        // 1. Update tabel users
        $q1 = "UPDATE users SET status = 'active' WHERE id = $user_id AND role = 'pelajar'";
        if (!mysqli_query($conn, $q1)) {
            throw new Exception(mysqli_error($conn));
        }
        
        // 2. Update tabel pelajar_profiles juga
        $q2 = "UPDATE pelajar_profiles SET status = 'approved' WHERE user_id = $user_id";
        if (!mysqli_query($conn, $q2)) {
            throw new Exception(mysqli_error($conn));
        }
        
        mysqli_commit($conn);
        $_SESSION['success'] = "User approved successfully!";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    ob_end_clean();
    header('Location: approve_user.php');
    exit();
}

// ============================================
// REJECT USER - Update kedua tabel
// ============================================
if (isset($_GET['reject'])) {
    $user_id = (int) $_GET['reject'];
    
    mysqli_begin_transaction($conn);
    try {
        // 1. Update tabel users
        $q1 = "UPDATE users SET status = 'inactive' WHERE id = $user_id AND role = 'pelajar'";
        if (!mysqli_query($conn, $q1)) {
            throw new Exception(mysqli_error($conn));
        }
        
        // 2. Update tabel pelajar_profiles juga
        //    NOTE: pastikan kolom status di pelajar_profiles mendukung nilai 'rejected'
        //    Kalau enum-nya cuma ('pending','approved'), ganti 'rejected' jadi 'pending'
        $q2 = "UPDATE pelajar_profiles SET status = 'rejected' WHERE user_id = $user_id";
        if (!mysqli_query($conn, $q2)) {
            throw new Exception(mysqli_error($conn));
        }
        
        mysqli_commit($conn);
        $_SESSION['success'] = "User rejected successfully!";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    ob_end_clean();
    header('Location: approve_user.php');
    exit();
}

// Get pending users
$query = "SELECT u.id, u.username, u.email, u.full_name, u.created_at, u.status,
                 p.asal_sekolah, p.kelas, p.subkelas, p.jenjang, p.mata_pelajaran,
                 p.no_telp, p.no_wali, p.foto_profil
          FROM users u 
          LEFT JOIN pelajar_profiles p ON u.id = p.user_id 
          WHERE u.status = 'pending' AND u.role = 'pelajar'
          ORDER BY u.created_at DESC";
$pending_users = mysqli_query($conn, $query);

// Get approval history
$history_query = "SELECT id, username, full_name, email, status, created_at 
                  FROM users 
                  WHERE role = 'pelajar' AND status IN ('active', 'inactive')
                  ORDER BY created_at DESC";
$history = mysqli_query($conn, $history_query);

// Check messages
$success = '';
$error = '';
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

ob_end_flush(); // Output semua buffer
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Users - Admin Gensa Course</title>
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
                    <h1 class="text-2xl font-bold text-gray-800">Approve Student Registrations</h1>
                    <p class="text-gray-600">Review and approve/reject student applications</p>
                </div>
                <div>
                    <a href="dashboard.php" class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <div class="p-4 sm:p-6">
            <!-- Success Message -->
            <?php if ($success): ?>
                <div class="mb-6 p-4 rounded-lg bg-green-50 text-green-800 border border-green-200">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span><?php echo $success; ?></span>
                    </div>
                </div>
                <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: '<?php echo addslashes($success); ?>',
                    timer: 3000,
                    showConfirmButton: false
                });
                </script>
            <?php endif; ?>
            
            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="mb-6 p-4 rounded-lg bg-red-50 text-red-800 border border-red-200">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-md p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Pending Approvals</p>
                            <?php
                            $pending_count = mysqli_num_rows($pending_users);
                            mysqli_data_seek($pending_users, 0);
                            ?>
                            <p class="text-2xl font-bold text-yellow-600"><?php echo $pending_count; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-clock text-yellow-600"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Approved</p>
                            <?php
                            $approved_query = "SELECT COUNT(*) as count FROM users WHERE status = 'active' AND role = 'pelajar'";
                            $approved_result = mysqli_query($conn, $approved_query);
                            $approved_count = mysqli_fetch_assoc($approved_result)['count'];
                            ?>
                            <p class="text-2xl font-bold text-green-600"><?php echo $approved_count; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-check text-green-600"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Rejected</p>
                            <?php
                            $rejected_query = "SELECT COUNT(*) as count FROM users WHERE status = 'inactive' AND role = 'pelajar'";
                            $rejected_result = mysqli_query($conn, $rejected_query);
                            $rejected_count = mysqli_fetch_assoc($rejected_result)['count'];
                            ?>
                            <p class="text-2xl font-bold text-red-600"><?php echo $rejected_count; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-times text-red-600"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pending Approvals -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800">Pending Approvals</h2>
                    <?php if ($pending_count > 0): ?>
                        <button onclick="approveAll()" class="px-4 py-2 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg text-sm font-medium">
                            <i class="fas fa-check-double mr-1"></i> Approve All
                        </button>
                    <?php endif; ?>
                </div>
                
                <?php if (mysqli_num_rows($pending_users) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px]">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Student Info</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Class & Subjects</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Contact Info</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($user = mysqli_fetch_assoc($pending_users)): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-4 px-4">
                                            <div class="flex items-center space-x-3">
                                                <?php if (!empty($user['foto_profil']) && file_exists('../' . $user['foto_profil'])): ?>
                                                    <img src="../<?php echo $user['foto_profil']; ?>" 
                                                         alt="Profile" 
                                                         class="w-12 h-12 rounded-full object-cover">
                                                <?php else: ?>
                                                    <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                                                        <i class="fas fa-user text-yellow-600"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($user['full_name']); ?></p>
                                                    <p class="text-sm text-gray-500">@<?php echo htmlspecialchars($user['username']); ?></p>
                                                    <p class="text-xs text-gray-400">
                                                        <i class="fas fa-calendar mr-1"></i>
                                                        <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                                                    </p>
                                                    <p class="text-xs text-yellow-600">
                                                        <i class="fas fa-info-circle mr-1"></i>
                                                        ID: <?php echo $user['id']; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <p class="font-medium">
                                                <?php echo ucfirst($user['kelas'] ?? 'Not specified'); ?> - <?php echo $user['subkelas'] ?? 'Not specified'; ?>
                                            </p>
                                            <p class="text-sm text-gray-600">
                                                <i class="fas fa-graduation-cap mr-1"></i>
                                                <?php echo $user['jenjang'] ?? 'Not specified'; ?>
                                            </p>
                                            <?php if (!empty($user['mata_pelajaran'])): ?>
                                            <div class="flex flex-wrap gap-1 mt-1">
                                                <?php 
                                                $subjects = explode(',', $user['mata_pelajaran']);
                                                foreach ($subjects as $subject): 
                                                    $color = trim($subject) == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
                                                ?>
                                                    <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $color; ?>">
                                                        <i class="fas fa-book mr-1"></i>
                                                        <?php echo ucfirst(trim($subject)); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-4">
                                            <p class="text-sm mb-1">
                                                <i class="fas fa-envelope mr-2 text-gray-400"></i> 
                                                <?php echo htmlspecialchars($user['email']); ?>
                                            </p>
                                            <p class="text-sm mb-1">
                                                <i class="fas fa-phone mr-2 text-gray-400"></i> 
                                                <?php echo htmlspecialchars($user['no_telp'] ?? 'Not specified'); ?>
                                            </p>
                                            <p class="text-sm">
                                                <i class="fas fa-school mr-2 text-gray-400"></i> 
                                                <?php echo htmlspecialchars($user['asal_sekolah'] ?: 'Not specified'); ?>
                                            </p>
                                            <?php if ($user['no_wali']): ?>
                                            <p class="text-sm mt-1">
                                                <i class="fas fa-user-friends mr-2 text-gray-400"></i> 
                                                Guardian: <?php echo htmlspecialchars($user['no_wali']); ?>
                                            </p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="flex flex-col space-y-2">
                                                <div class="grid grid-cols-2 gap-2">
                                                    <a href="?approve=<?php echo $user['id']; ?>" 
                                                       onclick="return confirmApprove('<?php echo addslashes($user['full_name']); ?>', <?php echo $user['id']; ?>)"
                                                       class="px-3 py-2 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg text-sm font-medium text-center">
                                                        <i class="fas fa-check mr-1"></i> Approve
                                                    </a>
                                                    <a href="?reject=<?php echo $user['id']; ?>" 
                                                       onclick="return confirmReject('<?php echo addslashes($user['full_name']); ?>', <?php echo $user['id']; ?>)"
                                                       class="px-3 py-2 bg-red-100 text-red-700 hover:bg-red-200 rounded-lg text-sm font-medium text-center">
                                                        <i class="fas fa-times mr-1"></i> Reject
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-users text-5xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 text-lg font-medium">No pending approvals</p>
                        <p class="text-gray-400">All student registrations have been processed</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Approval History -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Approval History</h2>
                
                <?php if (mysqli_num_rows($history) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Student</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Status</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Email</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Registered Date</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($record = mysqli_fetch_assoc($history)): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-3 px-4">
                                            <p class="font-medium"><?php echo htmlspecialchars($record['full_name']); ?></p>
                                            <p class="text-sm text-gray-500">@<?php echo htmlspecialchars($record['username']); ?></p>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $record['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <i class="fas fa-<?php echo $record['status'] == 'active' ? 'check' : 'times'; ?> mr-1"></i>
                                                <?php echo ucfirst($record['status']); ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <?php echo htmlspecialchars($record['email']); ?>
                                        </td>
                                        <td class="py-3 px-4">
                                            <p class="text-sm text-gray-800">
                                                <?php echo date('d M Y', strtotime($record['created_at'])); ?>
                                            </p>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex space-x-1">
                                                <?php if ($record['status'] == 'active'): ?>
                                                    <a href="pelajar_details.php?id=<?php echo $record['id']; ?>" 
                                                       class="p-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="?reject=<?php echo $record['id']; ?>" 
                                                       onclick="return confirm('Reject <?php echo addslashes($record['full_name']); ?>?')"
                                                       class="p-2 bg-red-100 text-red-700 hover:bg-red-200 rounded">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="?approve=<?php echo $record['id']; ?>" 
                                                       onclick="return confirm('Approve <?php echo addslashes($record['full_name']); ?>?')"
                                                       class="p-2 bg-green-100 text-green-700 hover:bg-green-200 rounded">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-history text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No approval history yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    function confirmApprove(name, id) {
        return Swal.fire({
            title: 'Approve Student?',
            html: `Approve student <b>${name}</b> (ID: ${id})?<br>This will allow them to login to the system.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, approve!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            return result.isConfirmed;
        });
    }
    
    function confirmReject(name, id) {
        return Swal.fire({
            title: 'Reject Student?',
            html: `Reject student <b>${name}</b> (ID: ${id})?<br>This will prevent them from logging in.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, reject!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            return result.isConfirmed;
        });
    }
    
    function approveAll() {
        Swal.fire({
            title: 'Approve All?',
            text: 'Approve all pending students? This will allow all to login.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, approve all!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Redirect dengan parameter batch approve
                window.location.href = 'ajax/approve_all.php';
            }
        });
    }
    </script>
</body>
</html>