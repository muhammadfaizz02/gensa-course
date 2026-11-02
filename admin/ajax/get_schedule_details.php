<?php
include '../../config/database.php';

if (isset($_GET['id'])) {
    $schedule_id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Get schedule details
    $query = "
        SELECT j.*, u.full_name as guru_nama, 
               mk.nama_kelas, mk.jenjang,
               ms.nama_subkelas,
               a.full_name as created_by_name
        FROM jadwal j
        LEFT JOIN users u ON j.guru_id = u.id
        LEFT JOIN master_kelas mk ON j.kelas_id = mk.id
        LEFT JOIN master_subkelas ms ON j.subkelas_id = ms.id
        LEFT JOIN users a ON j.created_by = a.id
        WHERE j.id = $schedule_id
    ";
    $result = mysqli_query($conn, $query);
    $schedule = mysqli_fetch_assoc($result);
    
    // Get students in this schedule
    $students_query = "
        SELECT u.full_name, js.status_kehadiran
        FROM jadwal_siswa js
        JOIN users u ON js.pelajar_id = u.id
        WHERE js.jadwal_id = $schedule_id
        ORDER BY u.full_name
    ";
    $students_result = mysqli_query($conn, $students_query);
    
    // Calculate attendance rate
    $attendance_query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status_kehadiran = 'hadir' THEN 1 ELSE 0 END) as attended
        FROM jadwal_siswa
        WHERE jadwal_id = $schedule_id
    ";
    $attendance = mysqli_fetch_assoc(mysqli_query($conn, $attendance_query));
    $attendance_rate = $attendance['total'] > 0 ? ($attendance['attended'] / $attendance['total'] * 100) : 0;
    
    if ($schedule) {
        ?>
        <div class="space-y-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="text-lg font-bold text-gray-800">Schedule Details</h4>
                    <p class="text-sm text-gray-600">ID: #<?php echo str_pad($schedule_id, 6, '0', STR_PAD_LEFT); ?></p>
                </div>
                <span class="px-3 py-1 rounded-full text-sm font-medium <?php 
                    echo $schedule['status'] == 'scheduled' ? 'bg-yellow-100 text-yellow-800' : 
                    ($schedule['status'] == 'completed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800');
                ?>">
                    <?php echo ucfirst($schedule['status']); ?>
                </span>
            </div>
            
            <!-- Basic Info -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-600">Date & Time</p>
                    <p class="font-medium">
                        <?php echo date('l, d F Y', strtotime($schedule['tanggal'])); ?>
                    </p>
                    <p class="text-sm text-gray-600">
                        <?php echo date('H:i', strtotime($schedule['jam_mulai'])); ?> - 
                        <?php echo date('H:i', strtotime($schedule['jam_selesai'])); ?>
                    </p>
                </div>
                
                <div>
                    <p class="text-sm font-medium text-gray-600">Duration</p>
                    <p class="font-medium">
                        <?php 
                        $duration = (strtotime($schedule['jam_selesai']) - strtotime($schedule['jam_mulai'])) / 3600;
                        echo number_format($duration, 1); ?> hours
                    </p>
                </div>
            </div>
            
            <!-- Class & Teacher -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-600">Class</p>
                    <p class="font-medium">
                        <?php echo $schedule['jenjang']; ?> - 
                        <?php echo $schedule['nama_kelas']; ?> - 
                        <?php echo $schedule['nama_subkelas']; ?>
                    </p>
                </div>
                
                <div>
                    <p class="text-sm font-medium text-gray-600">Teacher</p>
                    <p class="font-medium"><?php echo $schedule['guru_nama']; ?></p>
                </div>
            </div>
            
            <!-- Subject Details -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-600">Subject</p>
                    <span class="inline-block px-2 py-1 <?php 
                        echo $schedule['bahasa'] == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
                    ?> rounded text-sm">
                        <?php echo ucfirst($schedule['bahasa']); ?>
                    </span>
                </div>
                
                <div>
                    <p class="text-sm font-medium text-gray-600">Lesson Type</p>
                    <p class="font-medium"><?php echo ucfirst($schedule['jenis_pelajaran']); ?></p>
                </div>
            </div>
            
            <!-- Description -->
            <?php if ($schedule['deskripsi']): ?>
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Description</p>
                    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($schedule['deskripsi'])); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Students List -->
            <div>
                <p class="text-sm font-medium text-gray-600 mb-2">Students</p>
                <div class="border border-gray-200 rounded-lg p-4 max-h-60 overflow-y-auto">
                    <?php if (mysqli_num_rows($students_result) > 0): ?>
                        <div class="space-y-2">
                            <?php while ($student = mysqli_fetch_assoc($students_result)): ?>
                                <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                                    <span class="font-medium"><?php echo $student['full_name']; ?></span>
                                    <span class="px-2 py-1 text-xs rounded <?php 
                                        echo $student['status_kehadiran'] == 'hadir' ? 'bg-green-100 text-green-800' :
                                        ($student['status_kehadiran'] == 'tidak_hadir' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800');
                                    ?>">
                                        <?php echo str_replace('_', ' ', ucfirst($student['status_kehadiran'])); ?>
                                    </span>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-2">No students assigned</p>
                    <?php endif; ?>
                </div>
                
                <!-- Attendance Summary -->
                <?php if ($attendance['total'] > 0): ?>
                    <div class="mt-3">
                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                            <span>Attendance Rate</span>
                            <span><?php echo round($attendance_rate); ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-500 h-2 rounded-full" 
                                 style="width: <?php echo $attendance_rate; ?>%"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1 text-center">
                            <?php echo $attendance['attended']; ?> of <?php echo $attendance['total']; ?> students attended
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Created Info -->
            <div class="pt-4 border-t">
                <p class="text-sm text-gray-600">
                    Created by: <?php echo $schedule['created_by_name']; ?> on 
                    <?php echo date('d M Y H:i', strtotime($schedule['created_at'])); ?>
                </p>
            </div>
        </div>
        <?php
    }
}
?>