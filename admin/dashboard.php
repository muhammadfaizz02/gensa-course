<?php
include '../includes/auth_check.php';
include '../config/database.php';

// Statistik (tetap dipakai oleh kartu di bawah)
$pending_count      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM pelajar_profiles WHERE status = 'pending'"))['count'];
$attendance_count   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM kehadiran_guru WHERE status = 'pending'"))['count'];
$total_students     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'pelajar'"))['count'];
$total_teachers     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'guru'"))['count'];

// Recent approvals
$recent_approvals = mysqli_query($conn, "
    SELECT u.full_name, p.status, p.approved_at
    FROM pelajar_profiles p
    JOIN users u ON p.user_id = u.id
    WHERE p.status != 'pending'
    ORDER BY p.approved_at DESC
    LIMIT 5
");

// Jadwal hari ini
$today_schedule = mysqli_query($conn, "
    SELECT j.*, u.full_name as guru_nama, mk.nama_kelas, ms.nama_subkelas
    FROM jadwal j
    LEFT JOIN users u ON j.guru_id = u.id
    LEFT JOIN master_kelas mk ON j.kelas_id = mk.id
    LEFT JOIN master_subkelas ms ON j.subkelas_id = ms.id
    WHERE j.tanggal = CURDATE() AND j.status = 'scheduled'
    ORDER BY j.jam_mulai
    LIMIT 5
");

$safe_name = htmlspecialchars($full_name);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Gensa Course</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-50">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 pt-14 lg:pt-0 min-h-screen min-w-0">

        <!-- Top Bar -->
        <div class="bg-white shadow-sm p-4 border-b">
            <div class="flex justify-between items-center gap-3">
                <div class="min-w-0">
                    <h1 class="text-lg sm:text-2xl font-bold text-gray-800 truncate">Welcome, <?php echo $safe_name; ?>!</h1>
                    <p class="hidden sm:block text-gray-600">Admin Dashboard - Gensa Course Management System</p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <div class="relative">
                        <button class="p-2 hover:bg-gray-100 rounded-full" aria-label="Notifikasi">
                            <i class="fas fa-bell text-gray-600 text-xl"></i>
                        </button>
                        <?php if ($pending_count > 0 || $attendance_count > 0): ?>
                            <span class="notification-badge absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-10 h-10 bg-yellow-500 rounded-full flex items-center justify-center text-white font-semibold">
                            <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                        </div>
                        <div class="hidden md:block">
                            <p class="font-semibold"><?php echo $safe_name; ?></p>
                            <p class="text-sm text-gray-500">Administrator</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="p-4 sm:p-6">

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
                <div class="bg-white rounded-xl shadow-md p-5 sm:p-6 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Pending Approvals</p>
                            <p class="text-3xl font-bold text-gray-800"><?php echo $pending_count; ?></p>
                        </div>
                        <div class="w-12 h-12 shrink-0 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user-clock text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                    <a href="approve_user.php" class="mt-4 inline-flex items-center text-sm text-yellow-600 hover:text-yellow-700">
                        View details <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>

                <div class="bg-white rounded-xl shadow-md p-5 sm:p-6 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Pending Attendance</p>
                            <p class="text-3xl font-bold text-gray-800"><?php echo $attendance_count; ?></p>
                        </div>
                        <div class="w-12 h-12 shrink-0 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-clipboard-check text-blue-600 text-xl"></i>
                        </div>
                    </div>
                    <a href="approve_attendance.php" class="mt-4 inline-flex items-center text-sm text-blue-600 hover:text-blue-700">
                        View details <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>

                <div class="bg-white rounded-xl shadow-md p-5 sm:p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Students</p>
                            <p class="text-3xl font-bold text-gray-800"><?php echo $total_students; ?></p>
                        </div>
                        <div class="w-12 h-12 shrink-0 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-users text-green-600 text-xl"></i>
                        </div>
                    </div>
                    <a href="#" class="mt-4 inline-flex items-center text-sm text-green-600 hover:text-green-700">
                        View all <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>

                <div class="bg-white rounded-xl shadow-md p-5 sm:p-6 border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Teachers</p>
                            <p class="text-3xl font-bold text-gray-800"><?php echo $total_teachers; ?></p>
                        </div>
                        <div class="w-12 h-12 shrink-0 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-chalkboard-teacher text-purple-600 text-xl"></i>
                        </div>
                    </div>
                    <a href="input_guru.php" class="mt-4 inline-flex items-center text-sm text-purple-600 hover:text-purple-700">
                        Manage teachers <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>

            <!-- Recent Activity and Quick Actions -->
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 sm:gap-6">

                <!-- Recent Approvals -->
                <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 min-w-0">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Recent User Approvals</h3>
                        <a href="approve_user.php" class="text-sm text-yellow-600 hover:text-yellow-700">View All</a>
                    </div>
                    <div class="space-y-2">
                        <?php if (mysqli_num_rows($recent_approvals) > 0): ?>
                            <?php while ($approval = mysqli_fetch_assoc($recent_approvals)): ?>
                                <?php $ok = $approval['status'] == 'approved'; ?>
                                <div class="flex items-center justify-between gap-3 p-3 hover:bg-gray-50 rounded-lg">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-10 h-10 shrink-0 rounded-full <?php echo $ok ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?> flex items-center justify-center">
                                            <i class="fas fa-<?php echo $ok ? 'check' : 'times'; ?>"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-medium truncate"><?php echo htmlspecialchars($approval['full_name']); ?></p>
                                            <p class="text-sm text-gray-500">
                                                <?php echo date('d M Y H:i', strtotime($approval['approved_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <span class="shrink-0 px-3 py-1 rounded-full text-xs font-medium <?php echo $ok ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo ucfirst($approval['status']); ?>
                                    </span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4">No recent approvals</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 min-w-0">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                    <div class="grid grid-cols-2 gap-3 sm:gap-4">
                        <a href="input_guru.php" class="p-3 sm:p-4 bg-yellow-50 border border-yellow-200 rounded-lg hover:bg-yellow-100 transition text-center">
                            <i class="fas fa-user-plus text-yellow-600 text-2xl mb-2"></i>
                            <p class="font-medium text-gray-800 text-sm sm:text-base">Add Teacher</p>
                        </a>
                        <a href="manage_schedule.php" class="p-3 sm:p-4 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition text-center">
                            <i class="fas fa-calendar-plus text-blue-600 text-2xl mb-2"></i>
                            <p class="font-medium text-gray-800 text-sm sm:text-base">Add Schedule</p>
                        </a>
                        <a href="input_nilai.php" class="p-3 sm:p-4 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition text-center">
                            <i class="fas fa-edit text-green-600 text-2xl mb-2"></i>
                            <p class="font-medium text-gray-800 text-sm sm:text-base">Input Score</p>
                        </a>
                        <a href="certificates.php" class="p-3 sm:p-4 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition text-center">
                            <i class="fas fa-certificate text-purple-600 text-2xl mb-2"></i>
                            <p class="font-medium text-gray-800 text-sm sm:text-base">Issue Certificate</p>
                        </a>
                    </div>

                    <!-- System Status -->
                    <div class="mt-6 pt-6 border-t">
                        <h4 class="font-medium text-gray-700 mb-2">System Status</h4>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Database</span>
                                <span class="inline-flex items-center text-green-600">
                                    <i class="fas fa-circle text-xs mr-1"></i> Online
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Active Users</span>
                                <span class="text-sm font-medium text-gray-800"><?php echo $total_students + $total_teachers + 1; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Schedule -->
            <div class="mt-4 sm:mt-6 bg-white rounded-xl shadow-md p-4 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Today's Schedule</h3>
                    <a href="view_schedule.php" class="text-sm text-yellow-600 hover:text-yellow-700">View Calendar</a>
                </div>

                <?php if (mysqli_num_rows($today_schedule) > 0): ?>
                    <div class="overflow-x-auto -mx-4 sm:mx-0">
                        <table class="w-full min-w-[560px]">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700 whitespace-nowrap">Time</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700 whitespace-nowrap">Class</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700 whitespace-nowrap">Teacher</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700 whitespace-nowrap">Subject</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700 whitespace-nowrap">Type</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($schedule = mysqli_fetch_assoc($today_schedule)): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-3 px-4 whitespace-nowrap font-medium">
                                            <?php echo date('H:i', strtotime($schedule['jam_mulai'])); ?> - <?php echo date('H:i', strtotime($schedule['jam_selesai'])); ?>
                                        </td>
                                        <td class="py-3 px-4 whitespace-nowrap">
                                            <?php echo htmlspecialchars($schedule['nama_kelas']); ?> - <?php echo htmlspecialchars($schedule['nama_subkelas']); ?>
                                        </td>
                                        <td class="py-3 px-4 whitespace-nowrap"><?php echo htmlspecialchars($schedule['guru_nama']); ?></td>
                                        <td class="py-3 px-4 whitespace-nowrap">
                                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $schedule['bahasa'] == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                <?php echo ucfirst($schedule['bahasa']); ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 whitespace-nowrap"><?php echo ucfirst($schedule['jenis_pelajaran']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-center py-4">No schedule for today</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Auto refresh notifikasi tiap 30 detik
    setInterval(function () {
        fetch('ajax/get_notifications.php')
            .then(r => r.json())
            .then(data => {
                const badge = document.querySelector('.notification-badge');
                if (badge && data.pending_count > 0) badge.textContent = data.pending_count;
            })
            .catch(() => {});
    }, 30000);
    </script>
</body>
</html>