<?php
include '../../config/database.php';
session_start();

if (isset($_GET['id'])) {
    $attendance_id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Get attendance details
    $query = "
        SELECT k.*, u.full_name as guru_nama, u.email as guru_email,
               g.foto_profil as guru_foto, g.spesialisasi,
               a.full_name as approved_by_name,
               TIMEDIFF(k.jam_selesai, k.jam_mulai) as duration
        FROM kehadiran_guru k
        JOIN users u ON k.guru_id = u.id
        LEFT JOIN guru_profiles g ON u.id = g.user_id
        LEFT JOIN users a ON k.approved_by = a.id
        WHERE k.id = $attendance_id
    ";
    
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $attendance = mysqli_fetch_assoc($result);
        
        // Format data
        $duration = explode(':', $attendance['duration']);
        $hours = $duration[0];
        $minutes = $duration[1];
        $total_hours = $hours + ($minutes / 60);
        
        $status_color = $attendance['status'] == 'approved' ? 'text-green-600' : 
                       ($attendance['status'] == 'pending' ? 'text-yellow-600' : 'text-red-600');
        $status_bg = $attendance['status'] == 'approved' ? 'bg-green-100' : 
                    ($attendance['status'] == 'pending' ? 'bg-yellow-100' : 'bg-red-100');
        $status_text = $attendance['status'] == 'approved' ? 'Approved' : 
                      ($attendance['status'] == 'pending' ? 'Pending' : 'Rejected');
        
        ?>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="text-xl font-bold text-gray-800">Attendance Details</h4>
                    <p class="text-sm text-gray-600">ID: #<?php echo str_pad($attendance_id, 6, '0', STR_PAD_LEFT); ?></p>
                </div>
                <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $status_bg; ?> <?php echo $status_color; ?>">
                    <?php echo $status_text; ?>
                </span>
            </div>
            
            <!-- Teacher Information -->
            <div class="bg-blue-50 rounded-lg p-4">
                <h5 class="font-medium text-gray-800 mb-3">Teacher Information</h5>
                <div class="flex items-start space-x-4">
                    <div class="w-16 h-16 rounded-full overflow-hidden bg-yellow-100">
                        <?php if ($attendance['guru_foto']): ?>
                            <img src="<?php echo $attendance['guru_foto']; ?>" 
                                 alt="<?php echo $attendance['guru_nama']; ?>"
                                 class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center">
                                <i class="fas fa-user text-2xl text-yellow-600"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1">
                        <h6 class="text-lg font-bold text-gray-800"><?php echo $attendance['guru_nama']; ?></h6>
                        <p class="text-gray-600"><?php echo $attendance['guru_email']; ?></p>
                        <div class="flex flex-wrap gap-1 mt-2">
                            <?php 
                            $specializations = explode(',', $attendance['spesialisasi']);
                            foreach ($specializations as $spec):
                                $color = $spec == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
                            ?>
                                <span class="px-2 py-1 rounded text-xs font-medium <?php echo $color; ?>">
                                    <?php echo ucfirst($spec); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Attendance Details -->
            <div>
                <h5 class="font-medium text-gray-800 mb-3">Attendance Details</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <div>
                            <p class="text-sm text-gray-600">Date</p>
                            <p class="font-medium">
                                <?php echo date('l, d F Y', strtotime($attendance['tanggal'])); ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Teaching Time</p>
                            <p class="font-medium">
                                <?php echo date('H:i', strtotime($attendance['jam_mulai'])); ?> - 
                                <?php echo date('H:i', strtotime($attendance['jam_selesai'])); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <div>
                            <p class="text-sm text-gray-600">Duration</p>
                            <p class="font-medium">
                                <?php echo $hours; ?> hours <?php echo $minutes; ?> minutes
                                <span class="text-sm text-gray-500">(<?php echo number_format($total_hours, 2); ?> hours)</span>
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Location</p>
                            <p class="font-medium"><?php echo $attendance['lokasi']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Proof Photo -->
            <?php if ($attendance['foto_bukti']): ?>
                <div>
                    <h5 class="font-medium text-gray-800 mb-3">Proof Photo</h5>
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <img src="<?php echo $attendance['foto_bukti']; ?>" 
                             alt="Attendance Proof" 
                             class="w-full h-64 object-cover cursor-pointer"
                             onclick="viewImage('<?php echo $attendance['foto_bukti']; ?>')">
                        <div class="p-3 bg-gray-50 flex justify-between items-center">
                            <p class="text-sm text-gray-600">
                                Taken at <?php echo date('H:i', strtotime($attendance['jam_mulai'])); ?>
                            </p>
                            <button onclick="downloadPhoto('<?php echo $attendance['foto_bukti']; ?>')" 
                                    class="text-sm text-blue-600 hover:text-blue-800">
                                <i class="fas fa-download mr-1"></i> Download
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Notes -->
            <?php if ($attendance['catatan']): ?>
                <div>
                    <h5 class="font-medium text-gray-800 mb-2">Teacher's Note</h5>
                    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-r">
                        <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($attendance['catatan'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Approval Information -->
            <?php if ($attendance['approved_at']): ?>
                <div class="bg-green-50 rounded-lg p-4">
                    <h5 class="font-medium text-gray-800 mb-3">Approval Information</h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Approved By</p>
                            <p class="font-medium"><?php echo $attendance['approved_by_name']; ?></p>
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
            
            <!-- Teacher's Monthly Stats -->
            <div>
                <h5 class="font-medium text-gray-800 mb-3">This Month's Activity</h5>
                <?php
                $month_start = date('Y-m-01', strtotime($attendance['tanggal']));
                $month_end = date('Y-m-t', strtotime($attendance['tanggal']));
                
                $stats_query = "
                    SELECT 
                        COUNT(*) as total_sessions,
                        SUM(TIME_TO_SEC(TIMEDIFF(jam_selesai, jam_mulai))) / 3600 as total_hours,
                        AVG(TIME_TO_SEC(TIMEDIFF(jam_selesai, jam_mulai))) / 60 as avg_minutes
                    FROM kehadiran_guru
                    WHERE guru_id = {$attendance['guru_id']}
                    AND status = 'approved'
                    AND tanggal BETWEEN '$month_start' AND '$month_end'
                ";
                $stats_result = mysqli_query($conn, $stats_query);
                $stats = mysqli_fetch_assoc($stats_result);
                ?>
                
                <div class="grid grid-cols-3 gap-4">
                    <div class="text-center p-3 bg-gray-50 rounded">
                        <p class="text-2xl font-bold text-blue-600"><?php echo $stats['total_sessions']; ?></p>
                        <p class="text-xs text-gray-600">Sessions</p>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded">
                        <p class="text-2xl font-bold text-green-600"><?php echo number_format($stats['total_hours'], 1); ?></p>
                        <p class="text-xs text-gray-600">Total Hours</p>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded">
                        <p class="text-2xl font-bold text-purple-600"><?php echo number_format($stats['avg_minutes'], 0); ?></p>
                        <p class="text-xs text-gray-600">Avg Minutes</p>
                    </div>
                </div>
            </div>
            
            <!-- Actions for Pending Attendance -->
            <?php if ($attendance['status'] == 'pending'): ?>
                <div class="pt-4 border-t">
                    <div class="flex space-x-3">
                        <button onclick="approveAttendance(<?php echo $attendance_id; ?>)" 
                                class="flex-1 py-2 bg-green-500 text-white hover:bg-green-600 rounded-lg font-medium">
                            <i class="fas fa-check mr-2"></i> Approve
                        </button>
                        <button onclick="showRejectForm(<?php echo $attendance_id; ?>)" 
                                class="flex-1 py-2 bg-red-500 text-white hover:bg-red-600 rounded-lg font-medium">
                            <i class="fas fa-times mr-2"></i> Reject
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        function viewImage(src) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center p-4 z-50';
            modal.innerHTML = `
                <div class="relative max-w-4xl w-full">
                    <button onclick="this.parentElement.parentElement.remove()" 
                            class="absolute -top-10 right-0 text-white hover:text-gray-300">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                    <img src="${src}" alt="Full size" class="w-full h-auto rounded-lg">
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        function downloadPhoto(src) {
            const link = document.createElement('a');
            link.href = src;
            link.download = 'attendance_<?php echo $attendance_id; ?>.jpg';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        function approveAttendance(id) {
            if (confirm('Approve this attendance?')) {
                window.location.href = `../approve_attendance.php?approve=${id}`;
            }
        }
        
        function showRejectForm(id) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50';
            modal.innerHTML = `
                <div class="bg-white rounded-xl max-w-md w-full">
                    <div class="p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Reject Attendance</h3>
                        <form id="rejectForm" class="space-y-4">
                            <input type="hidden" name="attendance_id" value="${id}">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Rejection</label>
                                <textarea name="reason" rows="3" required
                                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                          placeholder="Enter reason for rejection..."></textarea>
                            </div>
                            <div class="flex space-x-3">
                                <button type="button" onclick="this.parentElement.parentElement.parentElement.parentElement.remove()" 
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
            `;
            
            document.body.appendChild(modal);
            
            // Handle form submission
            modal.querySelector('#rejectForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('../ajax/reject_attendance.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            });
        }
        </script>
        <?php
    } else {
        echo '<div class="text-center py-8 text-red-500">Attendance record not found!</div>';
    }
} else {
    echo '<div class="text-center py-8 text-red-500">No attendance ID specified!</div>';
}
?>