<?php
session_start(); // Tambahkan ini di awal
include 'config/database.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");
// Subkelas options based on kelas
$subkelas_options = [
    'prebeginer' => ['Kindergarten', 'Gen Alpha'],
    'beginer' => ['Gendkids 1', 'Gendkids 2', 'Gendkids 3', 'Gendkids 4', 'Gendkids 5', 'Gendkids 6'],
    'elementary' => ['Gen Z Junior 1', 'Gen Z Junior 2', 'Gen Z Junior 3'],
    'pre intermediate' => ['Pre Intermediate'],
    'intermediate' => ['Intermediate'],
    'advanced' => ['Advanced']
];

// Cek jika ada notifikasi dari halaman lain
if (isset($_SESSION['registration_success'])) {
    $success_message = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect form data
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $asal_sekolah = mysqli_real_escape_string($conn, $_POST['asal_sekolah']);
    $no_telp = mysqli_real_escape_string($conn, $_POST['no_telp']);
    $kelas = mysqli_real_escape_string($conn, $_POST['kelas']);
    $subkelas = mysqli_real_escape_string($conn, $_POST['subkelas']);
    $tanggal_lahir = mysqli_real_escape_string($conn, $_POST['tanggal_lahir']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $jenjang = mysqli_real_escape_string($conn, $_POST['jenjang']);
    $mata_pelajaran = isset($_POST['mata_pelajaran']) ? implode(',', $_POST['mata_pelajaran']) : '';
    $no_wali = mysqli_real_escape_string($conn, $_POST['no_wali']);
    
    // Default status: menunggu persetujuan admin
    $status = 'pending';
    
    // Handle file upload
    $foto_profil = '';
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == 0) {
        $target_dir = "uploads/profiles/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_extension = pathinfo($_FILES["foto_profil"]["name"], PATHINFO_EXTENSION);
        $foto_profil = $target_dir . uniqid() . '.' . $file_extension;
        move_uploaded_file($_FILES["foto_profil"]["tmp_name"], $foto_profil);
    }
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Insert into users table dengan status 'pending'
        $user_query = "INSERT INTO users (username, email, password, full_name, role, status) 
                      VALUES ('$username', '$email', '$password', '$full_name', 'pelajar', '$status')";
        mysqli_query($conn, $user_query);
        $user_id = mysqli_insert_id($conn);
        
        // Insert into pelajar_profiles table
        $profile_query = "INSERT INTO pelajar_profiles 
                         (user_id, asal_sekolah, no_telp, kelas, subkelas, tanggal_lahir, 
                          gender, jenjang, mata_pelajaran, no_wali, foto_profil) 
                         VALUES ('$user_id', '$asal_sekolah', '$no_telp', '$kelas', '$subkelas', 
                         '$tanggal_lahir', '$gender', '$jenjang', '$mata_pelajaran', '$no_wali', '$foto_profil')";
        mysqli_query($conn, $profile_query);
        
        // Commit transaction
        mysqli_commit($conn);
        
        // Set session untuk notifikasi
        $_SESSION['registration_success'] = "Registrasi berhasil! Silahkan hubungi admin untuk aktivasi akun Anda.";
        
        // Redirect ke halaman login
        header('Location: login.php');
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $error = "Registration failed. Please try again.";
        error_log("Registration error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Gensa Course</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen py-8">
    <div class="container mx-auto px-4">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <a href="login.php" class="inline-flex items-center text-yellow-600 hover:text-yellow-700 mb-4">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Login
                </a>
                <h1 class="text-4xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-user-plus gensa-text mr-2"></i>Create Account
                </h1>
                <p class="text-gray-600">Join Gensa Course and start your language learning journey</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 max-w-4xl mx-auto">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700"><?php echo $error; ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Registration Form -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden max-w-4xl mx-auto">
                <div class="gensa-gradient-light p-6 text-white">
                    <h2 class="text-2xl font-bold">
                        <i class="fas fa-user-graduate mr-2"></i>Student Registration Form
                    </h2>
                    <p class="opacity-90">Fill in all required information below</p>
                </div>
                
                <form method="POST" action="" enctype="multipart/form-data" class="p-6 lg:p-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Personal Information -->
                        <div class="md:col-span-2">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">
                                <i class="fas fa-id-card gensa-text mr-2"></i>Personal Information
                            </h3>
                        </div>
                        
                        <div>
                            <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   id="full_name" 
                                   name="full_name" 
                                   required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa"
                                   placeholder="Enter your full name">
                        </div>
                        
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-1">
                                Username <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   id="username" 
                                   name="username" 
                                   required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa"
                                   placeholder="Choose a username">
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                Email <span class="text-red-500">*</span>
                            </label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa"
                                   placeholder="your.email@example.com">
                        </div>
                        
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                                Password <span class="text-red-500">*</span>
                            </label>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa"
                                   placeholder="Create a strong password">
                            <div id="password-strength" class="mt-2"></div>
                        </div>
                        
                        <div>
                            <label for="asal_sekolah" class="block text-sm font-medium text-gray-700 mb-1">
                                School/Institution
                            </label>
                            <input type="text" 
                                   id="asal_sekolah" 
                                   name="asal_sekolah" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa"
                                   placeholder="Current school/institution">
                        </div>
                        
                        <div>
                            <label for="no_telp" class="block text-sm font-medium text-gray-700 mb-1">
                                Phone Number <span class="text-red-500">*</span>
                            </label>
                            <input type="tel" 
                                   id="no_telp" 
                                   name="no_telp" 
                                   required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa"
                                   placeholder="0812-3456-7890">
                        </div>
                        
                        <!-- Course Information -->
                        <div class="md:col-span-2 mt-4">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">
                                <i class="fas fa-book-open gensa-text mr-2"></i>Course Information
                            </h3>
                        </div>
                        
                        <div>
                            <label for="kelas" class="block text-sm font-medium text-gray-700 mb-1">
                                Class Level <span class="text-red-500">*</span>
                            </label>
                            <select id="kelas" 
                                    name="kelas" 
                                    required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select"
                                    onchange="updateSubkelas()">
                                <option value="">Select Class Level</option>
                                <option value="prebeginer">Prebeginer</option>
                                <option value="beginer">Beginer</option>
                                <option value="elementary">Elementary</option>
                                <option value="pre intermediate">Pre Intermediate</option>
                                <option value="intermediate">Intermediate</option>
                                <option value="advanced">Advanced</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="subkelas" class="block text-sm font-medium text-gray-700 mb-1">
                                Sub Class <span class="text-red-500">*</span>
                            </label>
                            <select id="subkelas" 
                                    name="subkelas" 
                                    required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select">
                                <option value="">Select Class Level First</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="jenjang" class="block text-sm font-medium text-gray-700 mb-1">
                                Education Level <span class="text-red-500">*</span>
                            </label>
                            <select id="jenjang" 
                                    name="jenjang" 
                                    required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select">
                                <option value="">Select Education Level</option>
                                <option value="TK">TK</option>
                                <option value="SD">SD</option>
                                <option value="SMP">SMP</option>
                                <option value="SMA">SMA</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="mata_pelajaran" class="block text-sm font-medium text-gray-700 mb-1">
                                Subjects <span class="text-red-500">*</span>
                            </label>
                            <select id="mata_pelajaran" 
                                    name="mata_pelajaran[]" 
                                    required 
                                    multiple 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select">
                                <option value="arab">Arabic</option>
                                <option value="inggris">English</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Hold Ctrl to select multiple subjects</p>
                        </div>
                        
                        <!-- Additional Information -->
                        <div class="md:col-span-2 mt-4">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">
                                <i class="fas fa-info-circle gensa-text mr-2"></i>Additional Information
                            </h3>
                        </div>
                        
                        <div>
                            <label for="tanggal_lahir" class="block text-sm font-medium text-gray-700 mb-1">
                                Date of Birth <span class="text-red-500">*</span>
                            </label>
                            <input type="date" 
                                   id="tanggal_lahir" 
                                   name="tanggal_lahir" 
                                   required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa">
                        </div>
                        
                        <div>
                            <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">
                                Gender <span class="text-red-500">*</span>
                            </label>
                            <select id="gender" 
                                    name="gender" 
                                    required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent form-select">
                                <option value="">Select Gender</option>
                                <option value="Laki-laki">Male</option>
                                <option value="Perempuan">Female</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="no_wali" class="block text-sm font-medium text-gray-700 mb-1">
                                Guardian's Phone Number
                            </label>
                            <input type="tel" 
                                   id="no_wali" 
                                   name="no_wali" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa"
                                   placeholder="Guardian's contact number">
                        </div>
                        
                        <div>
                            <label for="foto_profil" class="block text-sm font-medium text-gray-700 mb-1">
                                Profile Photo
                            </label>
                            <div class="mt-1 flex items-center space-x-4">
                                <div class="relative">
                                    <input type="file" 
                                           id="foto_profil" 
                                           name="foto_profil" 
                                           accept="image/*"
                                           class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100">
                                </div>
                                <div class="text-sm text-gray-500">
                                    Max 2MB • JPG, PNG
                                </div>
                            </div>
                        </div>
                        
                        <!-- Information Box -->
                        <div class="md:col-span-2 mt-6">
                            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-info-circle text-blue-500 text-lg"></i>
                                    </div>
                                    <div class="ml-3">
                                        <h4 class="text-sm font-semibold text-blue-800">Important Information</h4>
                                        <p class="text-sm text-blue-700 mt-1">
                                            Setelah registrasi, akun Anda akan menunggu persetujuan admin. 
                                            Silahkan hubungi admin untuk aktivasi akun sebelum dapat login.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Terms and Submit -->
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <div class="flex items-start mb-6">
                            <input type="checkbox" 
                                   id="terms" 
                                   name="terms" 
                                   required 
                                   class="h-4 w-4 text-yellow-600 focus:ring-yellow-500 border-gray-300 rounded mt-1">
                            <label for="terms" class="ml-2 block text-sm text-gray-700">
                                I agree to the <a href="#" class="text-yellow-600 hover:text-yellow-700">Terms of Service</a> 
                                and <a href="#" class="text-yellow-600 hover:text-yellow-700">Privacy Policy</a>
                            </label>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row items-center justify-between">
                            <p class="text-sm text-gray-600 mb-4 sm:mb-0">
                                Already have an account? 
                                <a href="login.php" class="text-yellow-600 hover:text-yellow-700 font-semibold ml-1">
                                    Click here to login
                                </a>
                            </p>
                            
                            <button type="submit" 
                                    class="px-8 py-3 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold rounded-lg shadow-md transition duration-200 btn-gensa">
                                <i class="fas fa-user-plus mr-2"></i>Create Account
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Progress Steps -->
            <div class="mt-8 flex justify-center">
                <div class="flex items-center">
                    <div class="flex items-center text-yellow-600">
                        <div class="w-8 h-8 rounded-full bg-yellow-500 text-white flex items-center justify-center">
                            1
                        </div>
                        <div class="ml-2 font-medium">Registration</div>
                    </div>
                    <div class="w-16 h-1 bg-gray-300 mx-2"></div>
                    <div class="flex items-center text-gray-400">
                        <div class="w-8 h-8 rounded-full bg-gray-300 text-white flex items-center justify-center">
                            2
                        </div>
                        <div class="ml-2">Admin Approval</div>
                    </div>
                    <div class="w-16 h-1 bg-gray-300 mx-2"></div>
                    <div class="flex items-center text-gray-400">
                        <div class="w-8 h-8 rounded-full bg-gray-300 text-white flex items-center justify-center">
                            3
                        </div>
                        <div class="ml-2">Login & Dashboard</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Subkelas options data
    const subkelasOptions = <?php echo json_encode($subkelas_options); ?>;
    
    function updateSubkelas() {
        const kelasSelect = document.getElementById('kelas');
        const subkelasSelect = document.getElementById('subkelas');
        const selectedKelas = kelasSelect.value;
        
        // Clear current options
        subkelasSelect.innerHTML = '<option value="">Select Sub Class</option>';
        
        if (selectedKelas && subkelasOptions[selectedKelas]) {
            subkelasOptions[selectedKelas].forEach(subkelas => {
                const option = document.createElement('option');
                option.value = subkelas;
                option.textContent = subkelas;
                subkelasSelect.appendChild(option);
            });
        }
    }
    
    // Form validation
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const passwordInput = document.getElementById('password');
        
        // Password strength indicator
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = checkPasswordStrength(password);
            updatePasswordStrength(strength);
        });
        
        function checkPasswordStrength(password) {
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            return strength;
        }
        
        function updatePasswordStrength(strength) {
            const indicator = document.getElementById('password-strength');
            if (!indicator) {
                // Create indicator if it doesn't exist
                const div = document.createElement('div');
                div.id = 'password-strength';
                div.className = 'mt-2';
                passwordInput.parentNode.appendChild(div);
            }
            
            const strengthText = ['Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
            const colors = ['text-red-500', 'text-orange-500', 'text-yellow-500', 'text-green-500', 'text-green-600'];
            
            document.getElementById('password-strength').innerHTML = `
                <div class="text-sm ${colors[strength]}">
                    <i class="fas fa-shield-alt mr-1"></i>
                    Password Strength: ${strengthText[strength]}
                </div>
            `;
        }
    });
    </script>
</body>
</html>