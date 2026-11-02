<?php
include '../../config/database.php';

if (isset($_GET['id'])) {
    $cert_id = mysqli_real_escape_string($conn, $_GET['id']);
    
    $query = "
        SELECT s.*, u.full_name as student_name, u.email as student_email,
               p.kelas, p.subkelas, p.jenjang,
               a.full_name as issued_by_name
        FROM sertifikat s
        JOIN users u ON s.pelajar_id = u.id
        JOIN pelajar_profiles p ON u.id = p.user_id
        LEFT JOIN users a ON s.created_by = a.id
        WHERE s.id = $cert_id
    ";
    $result = mysqli_query($conn, $query);
    $certificate = mysqli_fetch_assoc($result);
    
    if ($certificate) {
        $is_expired = $certificate['tanggal_expired'] && 
                     strtotime($certificate['tanggal_expired']) < time();
        ?>
        <div class="space-y-4">
            <!-- Certificate Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="text-lg font-bold text-gray-800">Certificate Details</h4>
                    <p class="text-sm font-mono text-gray-600">
                        #<?php echo $certificate['nomor_sertifikat']; ?>
                    </p>
                </div>
                <span class="px-3 py-1 rounded-full text-sm font-medium <?php 
                    echo $is_expired ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800';
                ?>">
                    <?php echo $is_expired ? 'Expired' : 'Valid'; ?>
                </span>
            </div>
            
            <!-- Student Information -->
            <div class="p-4 bg-blue-50 rounded-lg">
                <h5 class="font-medium text-gray-800 mb-2">Student Information</h5>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <p class="text-sm text-gray-600">Full Name</p>
                        <p class="font-medium"><?php echo $certificate['student_name']; ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Email</p>
                        <p class="font-medium"><?php echo $certificate['student_email']; ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Class</p>
                        <p class="font-medium">
                            <?php echo $certificate['kelas']; ?> - <?php echo $certificate['subkelas']; ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Education Level</p>
                        <p class="font-medium"><?php echo $certificate['jenjang']; ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Certificate Details -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-600">Certificate Type</p>
                    <span class="inline-block px-3 py-1 rounded-full text-sm font-medium <?php 
                        echo $certificate['jenis_sertifikat'] == 'arab' ? 'bg-green-100 text-green-800' : 
                        ($certificate['jenis_sertifikat'] == 'inggris' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800');
                    ?>">
                        <?php echo ucfirst($certificate['jenis_sertifikat']); ?>
                    </span>
                </div>
                
                <div>
                    <p class="text-sm font-medium text-gray-600">Level</p>
                    <p class="font-medium"><?php echo $certificate['level']; ?></p>
                </div>
                
                <div>
                    <p class="text-sm font-medium text-gray-600">Issue Date</p>
                    <p class="font-medium">
                        <?php echo date('d F Y', strtotime($certificate['tanggal_terbit'])); ?>
                    </p>
                </div>
                
                <div>
                    <p class="text-sm font-medium text-gray-600">Expiry Date</p>
                    <p class="font-medium <?php echo $is_expired ? 'text-red-600' : ''; ?>">
                        <?php 
                        echo $certificate['tanggal_expired'] ? 
                            date('d F Y', strtotime($certificate['tanggal_expired'])) : 
                            'No expiry';
                        ?>
                    </p>
                </div>
            </div>
            
            <!-- File Information -->
            <?php if ($certificate['file_path']): ?>
                <div class="p-3 bg-gray-50 rounded-lg">
                    <p class="text-sm font-medium text-gray-600 mb-2">Certificate File</p>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium">
                                <?php echo basename($certificate['file_path']); ?>
                            </p>
                            <p class="text-xs text-gray-500">
                                Uploaded: <?php echo date('d M Y H:i', strtotime($certificate['created_at'])); ?>
                            </p>
                        </div>
                        <a href="../certificates.php?download=<?php echo $cert_id; ?>" 
                           class="px-4 py-2 bg-blue-500 text-white hover:bg-blue-600 rounded-lg">
                            <i class="fas fa-download mr-2"></i> Download
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="p-3 bg-yellow-50 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                        <p class="text-sm text-yellow-700">No certificate file uploaded</p>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Issued Information -->
            <div class="pt-4 border-t">
                <p class="text-sm text-gray-600">
                    Issued by: <?php echo $certificate['issued_by_name']; ?> on 
                    <?php echo date('d M Y H:i', strtotime($certificate['created_at'])); ?>
                </p>
            </div>
            
            <!-- Actions -->
            <div class="flex space-x-3 pt-4">
                <button onclick="printCertificate(<?php echo $cert_id; ?>)" 
                        class="flex-1 py-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg">
                    <i class="fas fa-print mr-2"></i> Print
                </button>
                <button onclick="regenerateCertificate(<?php echo $cert_id; ?>)" 
                        class="flex-1 py-2 bg-yellow-100 text-yellow-700 hover:bg-yellow-200 rounded-lg">
                    <i class="fas fa-redo mr-2"></i> Regenerate
                </button>
                <button onclick="sendCertificate(<?php echo $cert_id; ?>)" 
                        class="flex-1 py-2 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg">
                    <i class="fas fa-paper-plane mr-2"></i> Email
                </button>
            </div>
        </div>
        <?php
    }
}
?>