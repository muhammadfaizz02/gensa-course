<?php
include '../includes/auth_check.php';
include '../config/database.php';

// Hanya admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$success = null;
$error   = null;
$form    = ['full_name' => '', 'username' => '', 'email' => '', 'spesialisasi' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $spec_list = array_values(array_intersect((array)($_POST['spesialisasi'] ?? []), ['arab', 'inggris']));

    // Simpan input untuk ditampilkan ulang jika error
    $form = [
        'full_name'    => $full_name,
        'username'     => $username,
        'email'        => $email,
        'spesialisasi' => $spec_list,
    ];

    // Validasi
    if ($full_name === '' || $username === '' || $email === '') {
        $error = "Full name, username, and email are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } elseif (empty($spec_list)) {
        $error = "Please select at least one specialization";
    }

    // Cek username / email sudah dipakai
    if (!$error) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'ss', $username, $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = "Username or email already exists!";
        }
        mysqli_stmt_close($stmt);
    }

    // Upload foto (opsional)
    $foto_profil  = '';
    $uploaded_abs = '';
    if (!$error && isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['foto_profil'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = "Failed to upload profile picture";
        } elseif ($file['size'] > 2097152) {
            $error = "File size must be less than 2MB";
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

            if (!isset($allowed[$mime])) {
                $error = "Only JPG, PNG, GIF, and WebP files are allowed";
            } else {
                $target_dir = "../uploads/teachers/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }
                $filename     = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
                $uploaded_abs = $target_dir . $filename;
                if (move_uploaded_file($file['tmp_name'], $uploaded_abs)) {
                    $foto_profil = 'uploads/teachers/' . $filename;
                } else {
                    $error = "Failed to upload profile picture";
                    $uploaded_abs = '';
                }
            }
        }
    }

    // Simpan ke database
    if (!$error) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $spesialisasi    = implode(',', $spec_list);

        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO users (username, email, password, full_name, role, status)
                 VALUES (?, ?, ?, ?, 'guru', 'active')");
            mysqli_stmt_bind_param($stmt, 'ssss', $username, $email, $hashed_password, $full_name);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to create user account");
            }
            $user_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn,
                "INSERT INTO guru_profiles (user_id, foto_profil, spesialisasi) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'iss', $user_id, $foto_profil, $spesialisasi);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to create teacher profile");
            }
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
            $success = "Teacher successfully added! They can now login with their credentials.";
            $form = ['full_name' => '', 'username' => '', 'email' => '', 'spesialisasi' => []];
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            if ($uploaded_abs && file_exists($uploaded_abs)) {
                unlink($uploaded_abs);
            }
            $error = "Failed to add teacher. Please try again.";
        }
    }
}

// Daftar guru
$teachers = mysqli_query($conn, "
    SELECT u.id, u.username, u.email, u.full_name, u.status, u.created_at,
           g.spesialisasi, g.foto_profil
    FROM users u
    LEFT JOIN guru_profiles g ON u.id = g.user_id
    WHERE u.role = 'guru'
    ORDER BY u.created_at DESC
");

// Statistik guru
$stats_result = mysqli_query($conn, "
    SELECT
        COUNT(*) as total_teachers,
        COUNT(CASE WHEN u.status = 'active' THEN 1 END) as active_teachers,
        COUNT(CASE WHEN g.spesialisasi LIKE '%arab%' THEN 1 END) as arab_teachers,
        COUNT(CASE WHEN g.spesialisasi LIKE '%inggris%' THEN 1 END) as english_teachers
    FROM users u
    LEFT JOIN guru_profiles g ON u.id = g.user_id
    WHERE u.role = 'guru'
");
$teacher_stats = mysqli_fetch_assoc($stats_result);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Teacher - Admin Gensa Course</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .password-strength { height: 4px; border-radius: 2px; margin-top: 4px; }
        .strength-weak   { background-color: #ef4444; width: 25%; }
        .strength-fair   { background-color: #f59e0b; width: 50%; }
        .strength-good   { background-color: #3b82f6; width: 75%; }
        .strength-strong { background-color: #10b981; width: 100%; }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'includes/sidebar.php'; ?>

    <div class="lg:ml-64 pt-14 lg:pt-0 min-h-screen min-w-0">
        <!-- Top Bar -->
        <div class="bg-white shadow-sm p-4 border-b">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-lg sm:text-2xl font-bold text-gray-800">Teacher Management</h1>
                    <p class="text-sm sm:text-base text-gray-600">Add and manage teaching staff</p>
                </div>
                <a href="dashboard.php" class="inline-flex items-center justify-center px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700 transition shrink-0">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <div class="p-4 sm:p-6">
            <!-- Messages -->
            <?php if ($success): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
                    <div class="flex items-start gap-2">
                        <i class="fas fa-check-circle mt-1"></i>
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                </div>
                <script>
                Swal.fire({
                    icon: 'success', title: 'Success!',
                    text: <?php echo json_encode($success); ?>,
                    timer: 5000, showConfirmButton: true,
                    confirmButtonText: 'OK', confirmButtonColor: '#f59e0b'
                });
                </script>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">
                    <div class="flex items-start gap-2">
                        <i class="fas fa-exclamation-circle mt-1"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
                <script>
                Swal.fire({
                    icon: 'error', title: 'Error!',
                    text: <?php echo json_encode($error); ?>,
                    confirmButtonText: 'OK', confirmButtonColor: '#f59e0b'
                });
                </script>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6 mb-6 sm:mb-8">
                <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-gray-500 text-xs sm:text-sm truncate">Total Teachers</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo (int)$teacher_stats['total_teachers']; ?></p>
                        </div>
                        <div class="hidden sm:flex w-12 h-12 shrink-0 bg-yellow-100 rounded-full items-center justify-center">
                            <i class="fas fa-users text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-gray-500 text-xs sm:text-sm truncate">Active Teachers</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo (int)$teacher_stats['active_teachers']; ?></p>
                        </div>
                        <div class="hidden sm:flex w-12 h-12 shrink-0 bg-green-100 rounded-full items-center justify-center">
                            <i class="fas fa-user-check text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-gray-500 text-xs sm:text-sm truncate">Arabic Teachers</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo (int)$teacher_stats['arab_teachers']; ?></p>
                        </div>
                        <div class="hidden sm:flex w-12 h-12 shrink-0 bg-blue-100 rounded-full items-center justify-center">
                            <i class="fas fa-language text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 border-l-4 border-purple-500">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-gray-500 text-xs sm:text-sm truncate">English Teachers</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo (int)$teacher_stats['english_teachers']; ?></p>
                        </div>
                        <div class="hidden sm:flex w-12 h-12 shrink-0 bg-purple-100 rounded-full items-center justify-center">
                            <i class="fas fa-globe text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 sm:gap-6">
                <!-- Add Teacher Form -->
                <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 min-w-0">
                    <h2 class="text-lg sm:text-xl font-bold text-gray-800 mb-4 sm:mb-6">Add New Teacher</h2>

                    <form method="POST" action="" enctype="multipart/form-data" class="space-y-4" id="teacherForm">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="min-w-0">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Full Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="full_name" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa"
                                       placeholder="Enter full name"
                                       value="<?php echo htmlspecialchars($form['full_name']); ?>">
                            </div>

                            <div class="min-w-0">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Username <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="username" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa"
                                       placeholder="Choose a username"
                                       value="<?php echo htmlspecialchars($form['username']); ?>"
                                       onblur="checkUsername()">
                                <div id="username-feedback" class="text-xs mt-1"></div>
                            </div>

                            <div class="sm:col-span-2 min-w-0">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Email <span class="text-red-500">*</span>
                                </label>
                                <input type="email" name="email" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa"
                                       placeholder="teacher@example.com"
                                       value="<?php echo htmlspecialchars($form['email']); ?>"
                                       onblur="checkEmail()">
                                <div id="email-feedback" class="text-xs mt-1"></div>
                            </div>

                            <div class="sm:col-span-2 min-w-0">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Password <span class="text-red-500">*</span>
                                </label>
                                <input type="password" name="password" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-gensa"
                                       placeholder="Create a strong password"
                                       id="password"
                                       autocomplete="new-password"
                                       onkeyup="checkPasswordStrength()">
                                <div id="password-strength" class="password-strength"></div>
                                <div id="password-feedback" class="text-xs mt-1"></div>
                                <div class="mt-2">
                                    <button type="button" onclick="generatePassword()" class="text-xs text-yellow-600 hover:text-yellow-700">
                                        <i class="fas fa-random mr-1"></i> Generate Strong Password
                                    </button>
                                </div>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Specialization <span class="text-red-500">*</span>
                                </label>
                                <div class="grid grid-cols-1 xs:grid-cols-2 sm:grid-cols-2 gap-3">
                                    <label class="flex items-center gap-3 p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input type="checkbox" name="spesialisasi[]" value="arab" class="spec-check h-4 w-4"
                                               <?php echo in_array('arab', $form['spesialisasi']) ? 'checked' : ''; ?>>
                                        <span class="text-sm text-gray-700">Arabic</span>
                                    </label>
                                    <label class="flex items-center gap-3 p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input type="checkbox" name="spesialisasi[]" value="inggris" class="spec-check h-4 w-4"
                                               <?php echo in_array('inggris', $form['spesialisasi']) ? 'checked' : ''; ?>>
                                        <span class="text-sm text-gray-700">English</span>
                                    </label>
                                </div>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Profile Photo</label>
                                <div class="flex items-center gap-4">
                                    <div class="w-20 h-20 shrink-0 border-2 border-dashed border-gray-300 rounded-lg flex items-center justify-center overflow-hidden bg-gray-50" id="imagePreview">
                                        <i class="fas fa-camera text-gray-400 text-xl"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <input type="file" name="foto_profil" accept="image/*"
                                               class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100"
                                               onchange="previewImage(this)">
                                        <p class="text-xs text-gray-500 mt-1">Max 2MB • JPG, PNG, WebP</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-gray-200">
                            <button type="submit" id="submitBtn"
                                    class="w-full py-3 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold rounded-lg shadow-md transition disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fas fa-user-plus mr-2"></i> Add Teacher Account
                            </button>

                            <p class="text-xs text-gray-500 mt-2 text-center">
                                <i class="fas fa-info-circle mr-1"></i>
                                Teacher accounts are automatically activated and can login immediately
                            </p>
                        </div>
                    </form>
                </div>

                <!-- Teacher List -->
                <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 min-w-0">
                    <div class="flex flex-wrap justify-between items-center gap-2 mb-4 sm:mb-6">
                        <h2 class="text-lg sm:text-xl font-bold text-gray-800">Teacher List</h2>
                        <div class="flex gap-2">
                            <button onclick="exportTeachers()" class="px-3 py-1 bg-green-100 text-green-700 hover:bg-green-200 rounded text-sm">
                                <i class="fas fa-file-export mr-1"></i> Export
                            </button>
                            <button onclick="refreshList()" class="px-3 py-1 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded text-sm">
                                <i class="fas fa-sync-alt mr-1"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <div class="space-y-3 sm:space-y-4 xl:max-h-[550px] xl:overflow-y-auto xl:pr-2">
                        <?php if (mysqli_num_rows($teachers) > 0): ?>
                            <?php while ($teacher = mysqli_fetch_assoc($teachers)):
                                $specializations = array_filter(array_map('trim', explode(',', $teacher['spesialisasi'] ?? '')));
                                $is_active = $teacher['status'] === 'active';
                            ?>
                                <div class="border border-gray-200 rounded-lg p-3 sm:p-4 hover:bg-gray-50 transition">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 shrink-0 rounded-full overflow-hidden bg-yellow-100 border-2 border-white shadow">
                                            <?php if (!empty($teacher['foto_profil']) && file_exists('../' . $teacher['foto_profil'])): ?>
                                                <img src="../<?php echo htmlspecialchars($teacher['foto_profil']); ?>"
                                                     alt="<?php echo htmlspecialchars($teacher['full_name']); ?>"
                                                     class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center">
                                                    <i class="fas fa-user text-yellow-600"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                <p class="font-semibold text-gray-800 truncate max-w-full"><?php echo htmlspecialchars($teacher['full_name']); ?></p>
                                                <span class="shrink-0 px-2 py-0.5 rounded-full text-xs font-medium <?php echo $is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($teacher['status'])); ?>
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-500 truncate">@<?php echo htmlspecialchars($teacher['username']); ?></p>
                                            <p class="text-xs text-gray-400 truncate">
                                                <i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($teacher['email']); ?>
                                            </p>
                                            <p class="text-xs text-gray-400">
                                                <i class="fas fa-calendar mr-1"></i>Joined <?php echo date('d M Y', strtotime($teacher['created_at'])); ?>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap justify-between items-center gap-2 mt-3">
                                        <div class="flex flex-wrap gap-1">
                                            <?php foreach ($specializations as $spec):
                                                $color = $spec === 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
                                            ?>
                                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $color; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($spec)); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="flex gap-2">
                                            <button onclick="editTeacher(<?php echo (int)$teacher['id']; ?>)"
                                                    aria-label="Edit"
                                                    class="w-9 h-9 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="toggleTeacherStatus(<?php echo (int)$teacher['id']; ?>, '<?php echo $is_active ? 'active' : 'inactive'; ?>')"
                                                    aria-label="Toggle status"
                                                    class="w-9 h-9 <?php echo $is_active ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-green-100 text-green-700 hover:bg-green-200'; ?> rounded">
                                                <i class="fas fa-<?php echo $is_active ? 'times' : 'check'; ?>"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <i class="fas fa-chalkboard-teacher text-4xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500">No teachers registered yet</p>
                                <p class="text-sm text-gray-400 mt-1">Add your first teacher using the form</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4 pt-4 border-t">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span class="text-gray-600">
                                Showing <?php echo mysqli_num_rows($teachers); ?> teachers •
                                <span class="font-medium"><?php echo (int)$teacher_stats['active_teachers']; ?> active</span>
                            </span>
                            <a href="teachers_report.php" class="text-yellow-600 hover:text-yellow-700 font-medium">
                                View Detailed Report <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Preview foto
    function previewImage(input) {
        const preview = document.getElementById('imagePreview');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                preview.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Kekuatan password
    function checkPasswordStrength() {
        const password = document.getElementById('password').value;
        const strengthBar = document.getElementById('password-strength');
        const feedback = document.getElementById('password-feedback');

        if (password.length === 0) {
            strengthBar.className = 'password-strength';
            feedback.innerHTML = '';
            return;
        }

        let strength = 0;
        const messages = [];

        if (password.length >= 8) strength++; else messages.push('At least 8 characters');
        if (/[A-Z]/.test(password)) strength++; else messages.push('One uppercase letter');
        if (/[a-z]/.test(password)) strength++; else messages.push('One lowercase letter');
        if (/[0-9]/.test(password)) strength++; else messages.push('One number');
        if (/[^A-Za-z0-9]/.test(password)) strength++; else messages.push('One special character');

        const classes = ['strength-weak', 'strength-weak', 'strength-fair', 'strength-good', 'strength-strong'];
        const labels  = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
        const colors  = ['text-red-500', 'text-orange-500', 'text-yellow-600', 'text-blue-500', 'text-green-500'];
        const idx = Math.max(strength - 1, 0);

        strengthBar.className = 'password-strength ' + classes[idx];
        feedback.innerHTML = `
            <span class="${colors[idx]}">
                <i class="fas fa-shield-alt mr-1"></i>Password Strength: ${labels[idx]}
            </span>
            ${messages.length ? '<br><small class="text-gray-500">' + messages.join(', ') + '</small>' : ''}
        `;
    }

    // Generate password
    function generatePassword() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+-=';
        const arr = new Uint32Array(12);
        crypto.getRandomValues(arr);
        let password = '';
        arr.forEach(n => { password += chars.charAt(n % chars.length); });
        const input = document.getElementById('password');
        input.value = password;
        input.type = 'text'; // tampilkan sekali supaya bisa dicatat admin
        checkPasswordStrength();
    }

    // Cek username
    function checkUsername() {
        const username = document.querySelector('input[name="username"]').value;
        const feedback = document.getElementById('username-feedback');
        if (username.length < 3) { feedback.innerHTML = ''; return; }

        fetch(`ajax/check_username.php?username=${encodeURIComponent(username)}`)
            .then(r => r.json())
            .then(data => {
                if (data.available) {
                    feedback.className = 'text-xs mt-1 text-green-600';
                    feedback.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Username available';
                } else {
                    feedback.className = 'text-xs mt-1 text-red-600';
                    feedback.innerHTML = '<i class="fas fa-times-circle mr-1"></i> Username already taken';
                }
            })
            .catch(() => {});
    }

    // Cek email
    function checkEmail() {
        const email = document.querySelector('input[name="email"]').value;
        const feedback = document.getElementById('email-feedback');
        if (!email.includes('@')) { feedback.innerHTML = ''; return; }

        fetch(`ajax/check_email.php?email=${encodeURIComponent(email)}`)
            .then(r => r.json())
            .then(data => {
                if (data.available) {
                    feedback.className = 'text-xs mt-1 text-green-600';
                    feedback.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Email available';
                } else {
                    feedback.className = 'text-xs mt-1 text-red-600';
                    feedback.innerHTML = '<i class="fas fa-times-circle mr-1"></i> Email already registered';
                }
            })
            .catch(() => {});
    }

    // Validasi form
    document.getElementById('teacherForm').addEventListener('submit', function (e) {
        const password = document.getElementById('password').value;
        const submitBtn = document.getElementById('submitBtn');
        const specChecked = document.querySelectorAll('.spec-check:checked').length;

        if (password.length < 8) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning', title: 'Weak Password',
                text: 'Password must be at least 8 characters long',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }

        if (specChecked === 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning', title: 'Specialization Required',
                text: 'Please select at least one specialization',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }

        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Adding Teacher...';
        submitBtn.disabled = true;
    });

    // Aksi guru
    function editTeacher(id) {
        window.open(`edit_teacher.php?id=${id}`, '_blank');
    }

    function toggleTeacherStatus(id, currentStatus) {
        const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
        const action = currentStatus === 'active' ? 'deactivate' : 'activate';

        Swal.fire({
            title: `${action.charAt(0).toUpperCase() + action.slice(1)} Teacher?`,
            text: `Are you sure you want to ${action} this teacher?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: newStatus === 'active' ? '#10b981' : '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: `Yes, ${action}!`,
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (!result.isConfirmed) return;

            fetch(`ajax/toggle_teacher_status.php?id=${id}&status=${newStatus}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success', title: 'Success!',
                            text: `Teacher ${action}d successfully`,
                            timer: 2000, showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({
                            icon: 'error', title: 'Error',
                            text: data.message || 'Failed to update teacher status'
                        });
                    }
                })
                .catch(() => {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed' });
                });
        });
    }

    function exportTeachers() { window.open('export_teachers.php', '_blank'); }
    function refreshList() { location.reload(); }

    document.addEventListener('DOMContentLoaded', checkPasswordStrength);
    </script>
</body>
</html>