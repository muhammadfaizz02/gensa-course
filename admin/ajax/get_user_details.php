<?php
include '../../config/database.php';

if (isset($_GET['id'])) {
    $user_id = mysqli_real_escape_string($conn, $_GET['id']);
    
    $query = "
        SELECT u.*, p.* 
        FROM users u 
        JOIN pelajar_profiles p ON u.id = p.user_id 
        WHERE u.id = $user_id
    ";
    $result = mysqli_query($conn, $query);
    $user = mysqli_fetch_assoc($result);
    
    if ($user) {
        $status_color = $user['status'] == 'approved' ? 'text-green-600' : 
                       ($user['status'] == 'pending' ? 'text-yellow-600' : 'text-red-600');
        $status_icon = $user['status'] == 'approved' ? 'fa-check-circle' : 
                      ($user['status'] == 'pending' ? 'fa-clock' : 'fa-times-circle');
        ?>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-3xl text-yellow-600"></i>
                    </div>
                    <div>
                        <h4 class="text-xl font-bold text-gray-800"><?php echo $user['full_name']; ?></h4>
                        <div class="flex items-center space-x-2">
                            <span class="text-gray-600">@<?php echo $user['username']; ?></span>
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php 
                                echo $user['status'] == 'approved' ? 'bg-green-100 text-green-800' : 
                                ($user['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                            ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-600">Registered</p>
                    <p class="font-medium"><?php echo date('d M Y', strtotime($user['created_at'])); ?></p>
                </div>
            </div>
            
            <!-- Personal Information -->
            <div>
                <h5 class="font-medium text-gray-800 mb-3">Personal Information</h5>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">Email Address</p>
                        <p class="font-medium"><?php echo $user['email']; ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Phone Number</p>
                        <p class="font-medium"><?php echo $user['no_telp']; ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Gender</p>
                        <p class="font-medium"><?php echo $user['gender']; ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Date of Birth</p>
                        <p class="font-medium"><?php echo date('d F Y', strtotime($user['tanggal_lahir'])); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Education Information -->
            <div>
                <h5 class="font-medium text-gray-800 mb-3">Education Information</h5>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">School/Institution</p>
                            <p class="font-medium"><?php echo $user['asal_sekolah']; ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Education Level</p>
                            <p class="font-medium"><?php echo $user['jenjang']; ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Class Level</p>
                            <p class="font-medium"><?php echo ucfirst($user['kelas']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Sub Class</p>
                            <p class="font-medium"><?php echo $user['subkelas']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Subjects -->
            <div>
                <h5 class="font-medium text-gray-800 mb-3">Subjects Enrolled</h5>
                <div class="flex space-x-2">
                    <?php 
                    $subjects = explode(',', $user['mata_pelajaran']);
                    foreach ($subjects as $subject): 
                        $color = $subject == 'arab' ? 'bg-green-100 text-green-800 border border-green-200' : 
                                'bg-blue-100 text-blue-800 border border-blue-200';
                    ?>
                        <span class="px-3 py-2 rounded-lg font-medium <?php echo $color; ?>">
                            <i class="fas fa-<?php echo $subject == 'arab' ? 'language' : 'globe-americas'; ?> mr-2"></i>
                            <?php echo ucfirst($subject); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Guardian Information -->
            <div>
                <h5 class="font-medium text-gray-800 mb-3">Guardian Information</h5>
                <div class="bg-blue-50 p-4 rounded-lg">
                    <p class="text-sm text-gray-600">Guardian's Phone Number</p>
                    <p class="font-medium text-lg"><?php echo $user['no_wali']; ?></p>
                </div>
            </div>
            
            <!-- Actions -->
            <?php if ($user['status'] == 'pending'): ?>
                <div class="pt-4 border-t">
                    <div class="flex space-x-3">
                        <a href="../admin/approve_user.php?approve=<?php echo $user_id; ?>" 
                           onclick="return confirm('Approve this student?')"
                           class="flex-1 py-2 bg-green-500 text-white hover:bg-green-600 rounded-lg text-center">
                            <i class="fas fa-check mr-2"></i> Approve
                        </a>
                        <a href="../admin/approve_user.php?reject=<?php echo $user_id; ?>" 
                           onclick="return confirm('Reject this student?')"
                           class="flex-1 py-2 bg-red-500 text-white hover:bg-red-600 rounded-lg text-center">
                            <i class="fas fa-times mr-2"></i> Reject
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
?>