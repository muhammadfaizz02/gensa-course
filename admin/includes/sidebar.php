<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Pastikan $conn tersedia (untuk halaman yang belum include database.php)
if (!isset($conn)) {
    include_once __DIR__ . '/../../config/database.php';
}

// Hitung pending users (role pelajar, status pending) — dari tabel USERS
$pending_count = 0;
$q = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE status = 'pending' AND role = 'pelajar'");
if ($q) {
    $pending_count = (int) mysqli_fetch_assoc($q)['c'];
}

// Hitung pending attendance
$attendance_count = 0;
$q = mysqli_query($conn, "SELECT COUNT(*) AS c FROM kehadiran_guru WHERE status = 'pending'");
if ($q) {
    $attendance_count = (int) mysqli_fetch_assoc($q)['c'];
}

// [file, icon, label, badge(optional)]
$menu = [
    [null, [
        ['dashboard.php', 'fa-tachometer-alt', 'Dashboard'],
    ]],
    ['User Management', [
        ['approve_user.php', 'fa-user-check', 'Approve Users', $pending_count],
        ['input_guru.php', 'fa-chalkboard-teacher', 'Input Teacher'],
    ]],
    ['Academic', [
        ['input_nilai.php', 'fa-graduation-cap', 'Input Score'],
        ['manage_kelas.php', 'fa-school', 'Manage Classes'],
    ]],
    ['Attendance & Schedule', [
        ['approve_attendance.php', 'fa-clipboard-check', 'Approve Attendance', $attendance_count],
        ['attendance_report.php', 'fa-calendar-alt', 'Attendance Report'],
        ['manage_schedule.php', 'fa-calendar-plus', 'Add Schedule'],
        ['view_schedule.php', 'fa-calendar-day', 'View Schedule'],
    ]],
    ['Reports', [
        ['teacher_payment_report.php', 'fa-money-bill-wave', 'Payment Report'],
        ['absent_report.php', 'fa-user-times', 'Absence Report'],
    ]],
    ['System', [
        ['reset_semester.php', 'fa-redo-alt', 'Reset Semester'],
        ['manage_semester.php', 'fa-calendar', 'Manage Semester'],
    ]],
    ['Certificates', [
        ['certificates.php', 'fa-certificate', 'Manage Certificates'],
    ]],
];
?>

<!-- Top bar mobile -->
<header class="lg:hidden no-print fixed top-0 inset-x-0 h-14 z-30 gensa-gradient text-white flex items-center px-4 shadow">
    <button id="menuBtn" type="button" aria-label="Buka menu" class="p-2 -ml-2 rounded-lg hover:bg-white/10">
        <i class="fas fa-bars text-xl"></i>
    </button>
    <span class="ml-3 font-semibold">Gensa Course</span>
</header>

<!-- Overlay -->
<div id="sidebarOverlay" class="no-print fixed inset-0 bg-black/50 z-40 hidden lg:hidden"></div>

<!-- Sidebar -->
<aside id="sidebar"
    class="no-print fixed inset-y-0 left-0 z-50 w-64 h-dvh gensa-gradient text-white
           -translate-x-full transition-transform duration-200 ease-out
           lg:translate-x-0">
    <div class="p-6 h-full flex flex-col">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center space-x-3 min-w-0">
                <div class="w-12 h-12 shrink-0 bg-white rounded-full flex items-center justify-center">
                    <i class="fas fa-user-shield text-2xl gensa-text"></i>
                </div>
                <div class="min-w-0">
                    <h2 class="text-xl font-bold truncate">Admin Panel</h2>
                    <p class="text-sm opacity-80">Gensa Course</p>
                </div>
            </div>
            <button id="closeBtn" type="button" aria-label="Tutup menu" class="lg:hidden p-2 -mr-2 rounded-lg hover:bg-white/10">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <nav class="space-y-1 flex-1 min-h-0 overflow-y-auto">
            <?php foreach ($menu as [$group, $items]): ?>
                <div class="mb-2">
                    <?php if ($group): ?>
                        <p class="text-xs uppercase tracking-wider text-white/50 px-3 py-2"><?php echo $group; ?></p>
                    <?php endif; ?>
                    <?php foreach ($items as $item): ?>
                        <?php
                        [$file, $icon, $label] = $item;
                        $badge = $item[3] ?? 0;
                        $active = $current_page === $file ? 'bg-white/20' : '';
                        ?>
                        <a href="<?php echo $file; ?>"
                           class="flex items-center space-x-3 p-3 hover:bg-white/10 rounded-lg transition <?php echo $active; ?>">
                            <i class="fas <?php echo $icon; ?> w-5 text-center"></i>
                            <span class="truncate"><?php echo $label; ?></span>
                            <?php if ($badge > 0): ?>
                                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full"><?php echo (int)$badge; ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="pt-4 mt-4 border-t border-white/20">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center space-x-2 min-w-0">
                    <div class="w-8 h-8 shrink-0 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-sm"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium truncate"><?php echo htmlspecialchars($full_name ?? 'Administrator'); ?></p>
                        <p class="text-xs opacity-80">Administrator</p>
                    </div>
                </div>
                <a href="../logout.php" class="p-2 hover:bg-white/10 rounded" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>
</aside>

<script>
(function () {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }
    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    document.getElementById('menuBtn').addEventListener('click', openSidebar);
    document.getElementById('closeBtn').addEventListener('click', closeSidebar);
    overlay.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });
    window.addEventListener('resize', () => { if (window.innerWidth >= 1024) closeSidebar(); });
})();
</script>