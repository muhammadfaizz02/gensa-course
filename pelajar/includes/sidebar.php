<?php
// pelajar/includes/sidebar.php
// Get counts for sidebar badges
$pending_assignments = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as count FROM jadwal j
     JOIN jadwal_siswa js ON j.id = js.jadwal_id
     WHERE js.pelajar_id = $user_id 
     AND j.tanggal = CURDATE() 
     AND j.status = 'scheduled'"
))['count'];

$new_scores = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as count FROM nilai_siswa 
     WHERE pelajar_id = $user_id 
     AND DATE(created_at) = CURDATE()"
))['count'];
?>
<!-- Sidebar -->
<div class="w-64 gensa-gradient text-white fixed left-0 top-0 bottom-0 z-40">
    <div class="p-6 h-full flex flex-col">
        <div class="flex items-center space-x-3 mb-8">
            <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center">
                <i class="fas fa-user-graduate text-2xl gensa-text"></i>
            </div>
            <div>
                <h2 class="text-xl font-bold">Student Portal</h2>
                <p class="text-sm opacity-80">Gensa Course</p>
            </div>
        </div>
        
        <nav class="space-y-2 flex-1">
            <a href="dashboard.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-white bg-opacity-20' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="nilai.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition <?php echo basename($_SERVER['PHP_SELF']) == 'nilai.php' ? 'bg-white bg-opacity-20' : ''; ?>">
                <i class="fas fa-graduation-cap"></i>
                <span>My Scores</span>
                <?php if ($new_scores > 0): ?>
                    <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $new_scores; ?></span>
                <?php endif; ?>
            </a>
            <a href="jadwal.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition <?php echo basename($_SERVER['PHP_SELF']) == 'jadwal.php' ? 'bg-white bg-opacity-20' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Schedule</span>
                <?php if ($pending_assignments > 0): ?>
                    <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $pending_assignments; ?></span>
                <?php endif; ?>
            </a>
            <a href="absensi.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition <?php echo basename($_SERVER['PHP_SELF']) == 'absensi.php' ? 'bg-white bg-opacity-20' : ''; ?>">
                <i class="fas fa-clipboard-check"></i>
                <span>Attendance</span>
            </a>
            <a href="riwayat_nilai.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition <?php echo basename($_SERVER['PHP_SELF']) == 'riwayat_nilai.php' ? 'bg-white bg-opacity-20' : ''; ?>">
                <i class="fas fa-history"></i>
                <span>Score History</span>
            </a>
            <a href="sertifikat.php" class="flex items-center space-x-3 p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition <?php echo basename($_SERVER['PHP_SELF']) == 'sertifikat.php' ? 'bg-white bg-opacity-20' : ''; ?>">
                <i class="fas fa-certificate"></i>
                <span>Certificates</span>
            </a>
        </nav>
        
        <div class="pt-6 border-t border-white border-opacity-20">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                        <?php 
                        $initials = strtoupper(substr($full_name, 0, 1));
                        if (strpos($full_name, ' ') !== false) {
                            $initials .= strtoupper(substr($full_name, strpos($full_name, ' ') + 1, 1));
                        }
                        ?>
                        <span class="text-sm font-bold"><?php echo $initials; ?></span>
                    </div>
                    <div>
                        <p class="text-sm font-medium"><?php echo $full_name; ?></p>
                        <p class="text-xs opacity-80">Student</p>
                    </div>
                </div>
                <a href="../../logout.php" class="p-2 hover:bg-white hover:bg-opacity-10 rounded" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>
</div>