<?php
session_start();
include 'config/database.php';

// Cek jika ada notifikasi registrasi berhasil
if (isset($_SESSION['registration_success'])) {
    $success_message = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']);
}

// Cek jika ada pesan logout
if (isset($_SESSION['logout_message'])) {
    $logout_message = $_SESSION['logout_message'];
    unset($_SESSION['logout_message']);
}

// Cek jika ada pesan dari URL
if (isset($_GET['message'])) {
    $success_message = urldecode($_GET['message']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    
    // Query untuk mengambil user dengan status
    $query = "SELECT * FROM users WHERE (username = '$username' OR email = '$username')";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        
        // Cek password
        if (password_verify($password, $user['password'])) {
            
            // CEK STATUS USER: Hanya user dengan status 'active' yang bisa login
            if ($user['status'] != 'active') {
                $error = "Akun Anda belum disetujui oleh admin. Silahkan hubungi admin untuk aktivasi akun.";
                
                // Tampilkan status akun yang lebih spesifik
                $status_message = "";
                switch ($user['status']) {
                    case 'pending':
                        $status_message = "Akun Anda masih menunggu persetujuan admin.";
                        break;
                    case 'inactive':
                        $status_message = "Akun Anda telah dinonaktifkan.";
                        break;
                    default:
                        $status_message = "Status akun: " . $user['status'];
                }
                
                $_SESSION['login_error'] = $error;
                $_SESSION['status_message'] = $status_message;
                
                // Redirect kembali ke login
                header('Location: login.php');
                exit();
                
            } else {
                // Set session data jika status aktif
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['status'] = $user['status'];
                $_SESSION['login_time'] = time(); // Simpan waktu login
                
                // Log login activity
                $log_query = "INSERT INTO login_logs (user_id, login_time, ip_address, status) 
                             VALUES ('{$user['id']}', NOW(), '{$_SERVER['REMOTE_ADDR']}', 'success')";
                mysqli_query($conn, $log_query);
                
                // Redirect berdasarkan role
                switch ($user['role']) {
                    case 'admin':
                        header('Location: admin/dashboard.php');
                        break;
                    case 'guru':
                        header('Location: guru/dashboard.php');
                        break;
                    case 'pelajar':
                        header('Location: pelajar/dashboard.php');
                        break;
                    default:
                        header('Location: login.php');
                }
                exit();
            }
        } else {
            $error = "Username atau password salah!";
            
            // Log failed login attempt
            if (isset($user['id'])) {
                $log_query = "INSERT INTO login_logs (user_id, login_time, ip_address, status) 
                             VALUES ('{$user['id']}', NOW(), '{$_SERVER['REMOTE_ADDR']}', 'failed')";
                mysqli_query($conn, $log_query);
            }
        }
    } else {
        $error = "Username atau password salah!";
    }
    
    // Simpan error di session untuk ditampilkan
    if (isset($error)) {
        $_SESSION['login_error'] = $error;
    }
    
    // Redirect kembali ke login dengan error
    header('Location: login.php');
    exit();
}

// Ambil error dari session jika ada
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

if (isset($_SESSION['status_message'])) {
    $status_message = $_SESSION['status_message'];
    unset($_SESSION['status_message']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gensa Course</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- SweetAlert2 untuk notifikasi yang lebih baik -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .gensa-gradient {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
        }
        .gensa-text {
            color: #f59e0b;
        }
        .input-gensa:focus {
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
        }
        .btn-gensa {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
        }
        .btn-gensa:hover {
            background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
        }
        .gensa-shadow {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">

    <!-- Wrapper agar konten center -->
    <div class="w-full max-w-6xl mx-auto">

        <div class="flex flex-col lg:flex-row rounded-2xl overflow-hidden gensa-shadow w-full bg-white">

            <!-- Left Side - Image -->
            <div class="lg:w-1/2 gensa-gradient p-8 lg:p-12 flex flex-col justify-center items-center text-white">
                <div class="text-center mb-8">
                    <div class="w-32 h-32 bg-white rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-graduation-cap text-6xl gensa-text"></i>
                    </div>
                    <h1 class="text-4xl font-bold mb-2">GENSA</h1>
                    <h2 class="text-2xl font-semibold mb-4">English & Arabic Center</h2>
                    <p class="text-lg opacity-90">Platform pembelajaran bahasa Inggris dan Arab terintegrasi</p>
                </div>
                
                <div class="mt-8">
                    <div class="flex items-center justify-center space-x-4">
                        <div class="text-center">
                            <i class="fas fa-users text-3xl mb-2"></i>
                            <p class="font-semibold">Expert Tutors</p>
                        </div>
                        <div class="text-center">
                            <i class="fas fa-book-open text-3xl mb-2"></i>
                            <p class="font-semibold">Interactive Lessons</p>
                        </div>
                        <div class="text-center">
                            <i class="fas fa-chart-line text-3xl mb-2"></i>
                            <p class="font-semibold">Progress Tracking</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-12 text-center">
                    <div class="inline-flex items-center bg-white bg-opacity-20 px-4 py-2 rounded-full">
                        <i class="fas fa-shield-alt mr-2"></i>
                        <span>Secure & Reliable Platform</span>
                    </div>
                </div>
            </div>
            
            <!-- Right Side - Login Form -->
            <div class="lg:w-1/2 bg-white p-8 lg:p-12 flex flex-col justify-center">
                <div class="mb-8">
                    <h3 class="text-3xl font-bold text-gray-800 mb-2">Welcome Back!</h3>
                    <p class="text-gray-600">Sign in to continue your language learning journey</p>
                </div>
                
                <!-- Notifikasi Sukses -->
                <?php if (isset($success_message)): ?>
                    <div class="mb-6 p-4 rounded-lg bg-green-50 border border-green-200">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-500"></i>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-semibold text-green-800">Success!</h4>
                                <p class="text-sm text-green-700 mt-1"><?php echo htmlspecialchars($success_message); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Notifikasi Logout -->
                <?php if (isset($logout_message)): ?>
                    <div class="mb-6 p-4 rounded-lg bg-blue-50 border border-blue-200">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-blue-500"></i>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-semibold text-blue-800">Logged Out</h4>
                                <p class="text-sm text-blue-700 mt-1"><?php echo htmlspecialchars($logout_message); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Error Message -->
                <?php if (isset($error)): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700 font-semibold"><?php echo htmlspecialchars($error); ?></p>
                                <?php if (isset($status_message)): ?>
                                    <p class="text-sm text-red-600 mt-1"><?php echo htmlspecialchars($status_message); ?></p>
                                <?php endif; ?>
                                <p class="text-xs text-red-500 mt-2">
                                    <i class="fas fa-phone mr-1"></i>Hubungi admin: admin@gensacourse.com
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Info Box tentang sistem approval -->
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-500"></i>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-semibold text-blue-800">Perhatian!</h4>
                            <p class="text-sm text-blue-700 mt-1">
                                Akun baru memerlukan persetujuan admin sebelum dapat login. 
                                Silahkan hubungi admin jika Anda sudah mendaftar namun belum bisa login.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Login Form -->
                <form method="POST" action="" class="space-y-6">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-user mr-2"></i>Username or Email
                        </label>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition duration-200 input-gensa"
                               placeholder="Enter your username or email"
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                               autocomplete="username">
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-lock mr-2"></i>Password
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition duration-200 input-gensa"
                                   placeholder="Enter your password"
                                   autocomplete="current-password">
                            <button type="button" 
                                    onclick="togglePassword()" 
                                    class="absolute right-3 top-3 text-gray-400 hover:text-gray-600 focus:outline-none">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input type="checkbox" 
                                   id="remember" 
                                   name="remember" 
                                   class="h-4 w-4 text-yellow-600 focus:ring-yellow-500 border-gray-300 rounded">
                            <label for="remember" class="ml-2 block text-sm text-gray-700">
                                Remember me
                            </label>
                        </div>
                        <a href="forgot-password.php" class="text-sm text-yellow-600 hover:text-yellow-700 font-medium">
                            Forgot password?
                        </a>
                    </div>
                    
                    <button type="submit" 
                            class="w-full py-3 px-4 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold rounded-lg shadow-md transition duration-200 btn-gensa transform hover:-translate-y-0.5">
                        <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                    </button>
                </form>
                
                <!-- Register Link -->
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <p class="text-center text-gray-600">
                        Don't have an account? 
                        <a href="register.php" class="text-yellow-600 hover:text-yellow-700 font-semibold ml-1 transition duration-200">
                            Register here
                        </a>
                    </p>
                </div>
                
                <!-- Admin Contact Info -->
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <div class="text-center">
                        <p class="text-sm text-gray-600 mb-3">
                            <i class="fas fa-headset mr-1"></i>Need help with account activation?
                        </p>
                        <div class="flex flex-col sm:flex-row justify-center space-y-2 sm:space-y-0 sm:space-x-6">
                            <a href="mailto:admin@gensacourse.com" 
                               class="text-sm text-yellow-600 hover:text-yellow-700 font-medium inline-flex items-center transition duration-200">
                                <i class="fas fa-envelope mr-1"></i>Email Admin
                            </a>
                            <span class="text-gray-400 hidden sm:block">|</span>
                            <a href="tel:+6281234567890" 
                               class="text-sm text-yellow-600 hover:text-yellow-700 font-medium inline-flex items-center transition duration-200">
                                <i class="fas fa-phone mr-1"></i>Call Admin
                            </a>
                            <span class="text-gray-400 hidden sm:block">|</span>
                            <a href="whatsapp://send?phone=6281234567890" 
                               class="text-sm text-yellow-600 hover:text-yellow-700 font-medium inline-flex items-center transition duration-200">
                                <i class="fab fa-whatsapp mr-1"></i>WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Copyright -->
                <div class="mt-8 text-center">
                    <p class="text-xs text-gray-500">
                        &copy; <?php echo date('Y'); ?> Gensa Course. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Back to Home Button (Mobile) -->
    <div class="fixed bottom-4 right-4 lg:hidden">
        <a href="index.php" class="w-12 h-12 bg-yellow-500 rounded-full flex items-center justify-center shadow-lg hover:bg-yellow-600 transition duration-200">
            <i class="fas fa-home text-white"></i>
        </a>
    </div>
    
    <script>
    // Toggle password visibility
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }
    
    // Auto-focus on username field
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('username').focus();
        
        // SweetAlert notifications
        <?php if (isset($success_message)): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?php echo addslashes($success_message); ?>',
                timer: 5000,
                showConfirmButton: true,
                confirmButtonText: 'OK',
                confirmButtonColor: '#f59e0b',
                position: 'top-end',
                toast: true,
                timerProgressBar: true
            });
        <?php endif; ?>
        
        <?php if (isset($logout_message)): ?>
            Swal.fire({
                icon: 'info',
                title: 'Logged Out',
                text: '<?php echo addslashes($logout_message); ?>',
                timer: 4000,
                showConfirmButton: false,
                position: 'top-end',
                toast: true,
                timerProgressBar: true
            });
        <?php endif; ?>
        
        <?php if (isset($error) && isset($status_message)): ?>
            Swal.fire({
                icon: 'warning',
                title: 'Akun Belum Aktif',
                html: `<?php echo addslashes($error); ?><br><br><small><?php echo addslashes($status_message); ?></small>`,
                showConfirmButton: true,
                confirmButtonText: 'Hubungi Admin',
                confirmButtonColor: '#f59e0b',
                showCancelButton: true,
                cancelButtonText: 'Tutup',
                cancelButtonColor: '#6b7280',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'mailto:admin@gensacourse.com?subject=Account%20Activation%20Request&body=Hello%20Admin,%0A%0APlease%20activate%20my%20account.%0A%0AUsername/Email:%20<?php echo isset($_POST["username"]) ? urlencode($_POST["username"]) : ""; ?>%0A%0AThank you.';
                }
            });
        <?php elseif (isset($error)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Login Failed',
                text: '<?php echo addslashes($error); ?>',
                showConfirmButton: true,
                confirmButtonText: 'Try Again',
                confirmButtonColor: '#f59e0b',
                timer: 5000,
                timerProgressBar: true
            });
        <?php endif; ?>
        
        // Remember me functionality
        const rememberCheckbox = document.getElementById('remember');
        const usernameInput = document.getElementById('username');
        
        // Load saved username from localStorage
        const savedUsername = localStorage.getItem('gensa_remember_username');
        if (savedUsername) {
            usernameInput.value = savedUsername;
            rememberCheckbox.checked = true;
        }
        
        // Save username on form submit if remember is checked
        document.querySelector('form').addEventListener('submit', function() {
            if (rememberCheckbox.checked) {
                localStorage.setItem('gensa_remember_username', usernameInput.value);
            } else {
                localStorage.removeItem('gensa_remember_username');
            }
        });
        
        // Enter key to submit form
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.target.matches('textarea, select')) {
                const activeElement = document.activeElement;
                if (activeElement.matches('input, button, select, textarea')) {
                    if (activeElement.type !== 'submit') {
                        e.preventDefault();
                        if (activeElement.id === 'password') {
                            document.querySelector('button[type="submit"]').click();
                        }
                    }
                }
            }
        });
    });
    
    // Form validation
    document.querySelector('form').addEventListener('submit', function(e) {
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;
        
        if (!username) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Username Required',
                text: 'Please enter your username or email',
                confirmButtonColor: '#f59e0b'
            });
            return false;
        }
        
        if (!password) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Password Required',
                text: 'Please enter your password',
                confirmButtonColor: '#f59e0b'
            });
            return false;
        }
        
        // Show loading state
        const submitBtn = document.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Signing In...';
        submitBtn.disabled = true;
        
        // Re-enable after 5 seconds (in case of error)
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 5000);
    });
    </script>
</body>
</html>