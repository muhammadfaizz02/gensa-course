<?php
include '../includes/auth_check.php';
include '../config/database.php';

// Hanya admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

ob_start();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error'] = "Invalid teacher ID";
    header('Location: input_guru.php');
    exit();
}

// Ambil data guru
$query = "
    SELECT u.id, u.username, u.email, u.full_name, u.status, u.created_at,
           g.foto_profil, g.spesialisasi
    FROM users u
    LEFT JOIN guru_profiles g ON u.id = g.user_id
    WHERE u.id = $id AND u.role = 'guru'
    LIMIT 1
";
$teacher = mysqli_fetch_assoc(mysqli_query($conn, $query));

if (!$teacher) {
    $_SESSION['error'] = "Teacher not found";
    header('Location: input_guru.php');
    exit();
}

$error   = null;
$success = null;

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $status    = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    $spec_list = array_values(array_intersect(
        (array)($_POST['spesialisasi'] ?? []),
        ['arab', 'inggris']
    ));

    // Validasi
    if ($full_name === '' || $username === '' || $email === '') {
        $error = "Full name, username, and email are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } elseif (empty($spec_list)) {
        $error = "Please select at least one specialization";
    } elseif ($password !== '' && strlen($password) < 8) {
        $error = "New password must be at least 8 characters long";
    }

    // Cek duplikat username / email (kecuali dirinya sendiri)
    if (!$error) {
        $stmt = mysqli_prepare($conn,
            "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'ssi', $username, $email, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = "Username or email already used by another account!";
        }
        mysqli_stmt_close($stmt);
    }

    // Upload foto baru (opsional)
    $foto_profil = $teacher['foto_profil'];
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
                    // Hapus foto lama
                    if (!empty($teacher['foto_profil']) && file_exists('../' . $teacher['foto_profil'])) {
                        @unlink('../' . $teacher['foto_profil']);
                    }
                    $foto_profil = 'uploads/teachers/' . $filename;
                } else {
                    $error = "Failed to upload profile picture";
                    $uploaded_abs = '';
                }
            }
        }
    }

    // Simpan ke DB
    if (!$error) {
        $spesialisasi = implode(',', $spec_list);

        mysqli_begin_transaction($conn);
        try {
            // Update users
            if ($password !== '') {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn,
                    "UPDATE users SET username=?, email=?, full_name=?, status=?, password=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'sssssi', $username, $email, $full_name, $status, $hashed, $id);
            } else {
                $stmt = mysqli_prepare($conn,
                    "UPDATE users SET username=?, email=?, full_name=?, status=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'ssssi', $username, $email, $full_name, $status, $id);
            }
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to update user account");
            }
            mysqli_stmt_close($stmt);

            // Update / insert guru_profiles
            $check = mysqli_query($conn, "SELECT id FROM guru_profiles WHERE user_id = $id LIMIT 1");
            if (mysqli_num_rows($check) > 0) {
                $stmt = mysqli_prepare($conn,
                    "UPDATE guru_profiles SET foto_profil=?, spesialisasi=? WHERE user_id=?");
                mysqli_stmt_bind_param($stmt, 'ssi', $foto_profil, $spesialisasi, $id);
            } else {
                $stmt = mysqli_prepare($conn,
                    "INSERT INTO guru_profiles (user_id, foto_profil, spesialisasi) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'iss', $id, $foto_profil, $spesialisasi);
            }
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to update teacher profile");
            }
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
            $_SESSION['success'] = "Teacher data updated successfully!";
            ob_end_clean();
            header('Location: edit_teacher.php?id=' . $id);
            exit();

        } catch (Throwable $e) {
            mysqli_rollback($conn);
            if ($uploaded_abs && file_exists($uploaded_abs)) {
                @unlink($uploaded_abs);
            }
            $error = "Failed to update teacher. Please try again.";
        }
    }
}

// Reload data terbaru
$teacher = mysqli_fetch_assoc(mysqli_query($conn, $query));
$spec_list = array_filter(array_map('trim', explode(',', $teacher['spesialisasi'] ?? '')));

$success = $_SESSION['success'] ?? '';
$error   = $error ?: ($_SESSION['error'] ?? '');
unset($_SESSION['success'], $_SESSION['error']);

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Teacher - Admin Gensa Course</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50">
    <?php include 'includes/sidebar.php'; ?>

    <div class="lg:ml-64 pt-14 lg:pt-0 min-h-screen min-w-0">
        <!-- Top Bar -->
        <div class="bg-white shadow-sm p-4 border-b">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Edit Teacher</h1>
                    <p class="text-gray-600">
                        Update data for <b><?php echo htmlspecialchars($teacher['full_name']); ?></b>
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="input_guru.php"
                       class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <div class="p-4 sm:p-6">
            <div class="max-w-3xl mx-auto">
                <?php if ($success): ?>
                    <script>
                    Swal.fire({
                        icon: 'success', title: 'Berhasil!',
                        text: <?php echo json_encode($success); ?>,
                        timer: 3000, showConfirmButton: false
                    });
                    </script>
                <?php endif; ?>
                <?php if ($error): ?>
                    <script>
                    Swal.fire({
                        icon: 'error', title: 'Gagal!',
                        text: <?php echo json_encode($error); ?>
                    });
                    </script>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">

                    <!-- Header Card -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                            <div class="w-24 h-24 rounded-full overflow-hidden bg-yellow-100 border-4 border-white shadow flex-shrink-0"
                                 id="imagePreview">
                                <?php if (!empty($teacher['foto_profil']) && file_exists('../' . $teacher['foto_profil'])): ?>
                                    <img src="../<?php echo htmlspecialchars($teacher['foto_profil']); ?>"
                                         alt="<?php echo htmlspecialchars($teacher['full_name']); ?>"
                                         class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center">
                                        <i class="fas fa-user text-yellow-600 text-3xl"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($teacher['full_name']); ?></p>
                                <p class="text-sm text-gray-500">@<?php echo htmlspecialchars($teacher['username']); ?></p>
                                <p class="text-xs text-gray-400 mt-1">
                                    <i class="fas fa-calendar mr-1"></i>
                                    Joined <?php echo date('d M Y', strtotime($teacher['created_at'])); ?>
                                </p>
                                <span class="inline-block mt-2 px-3 py-1 rounded-full text-xs font-medium <?php echo $teacher['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo ucfirst($teacher['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Form Fields -->
                    <div class="bg-white rounded-xl shadow-md p-6 space-y-4">
                        <h2 class="text-lg font-bold text-gray-800 mb-2">Account Information</h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Full Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="full_name" required
                                       value="<?php echo htmlspecialchars($teacher['full_name']); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Username <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="username" required
                                       value="<?php echo htmlspecialchars($teacher['username']); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Email <span class="text-red-500">*</span>
                                </label>
                                <input type="email" name="email" required
                                       value="<?php echo htmlspecialchars($teacher['email']); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    New Password
                                    <span class="text-xs text-gray-500 font-normal">(kosongkan kalau tidak ingin ganti)</span>
                                </label>
                                <input type="password" name="password"
                                       placeholder="Biarkan kosong jika tidak diganti"
                                       autocomplete="new-password"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500">
                                <p class="text-xs text-gray-500 mt-1">Minimal 8 karakter kalau diisi.</p>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Status <span class="text-red-500">*</span>
                                </label>
                                <select name="status" required
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500">
                                    <option value="active" <?php echo $teacher['status'] === 'active' ? 'selected' : ''; ?>>
                                        Active (bisa login)
                                    </option>
                                    <option value="inactive" <?php echo $teacher['status'] === 'inactive' ? 'selected' : ''; ?>>
                                        Inactive (tidak bisa login)
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Specialization & Photo -->
                    <div class="bg-white rounded-xl shadow-md p-6 space-y-4">
                        <h2 class="text-lg font-bold text-gray-800 mb-2">Profile</h2>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Specialization <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="flex items-center gap-3 p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                                    <input type="checkbox" name="spesialisasi[]" value="arab" class="h-4 w-4"
                                           <?php echo in_array('arab', $spec_list) ? 'checked' : ''; ?>>
                                    <span class="text-sm text-gray-700">Arabic</span>
                                </label>
                                <label class="flex items-center gap-3 p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                                    <input type="checkbox" name="spesialisasi[]" value="inggris" class="h-4 w-4"
                                           <?php echo in_array('inggris', $spec_list) ? 'checked' : ''; ?>>
                                    <span class="text-sm text-gray-700">English</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Ganti Foto Profil
                                <span class="text-xs text-gray-500 font-normal">(opsional)</span>
                            </label>
                            <input type="file" name="foto_profil" accept="image/*"
                                   onchange="previewImage(this)"
                                   class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100">
                            <p class="text-xs text-gray-500 mt-1">Max 2MB • JPG, PNG, WebP</p>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button type="submit"
                                class="flex-1 py-3 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold rounded-lg shadow-md transition">
                            <i class="fas fa-save mr-2"></i> Save Changes
                        </button>
                        <a href="input_guru.php"
                           class="flex-1 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg text-center transition">
                            <i class="fas fa-times mr-2"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function previewImage(input) {
        const preview = document.getElementById('imagePreview');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                preview.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    </script>
</body>
</html>