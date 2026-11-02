<?php
// logout.php
session_start();

// Simpan pesan logout
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';

// Hapus semua data session
$_SESSION = array();

// Hapus cookie session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hancurkan session
session_destroy();

// Set pesan logout berdasarkan role
$logout_message = "You have been logged out successfully.";
if ($role == 'admin') {
    $logout_message = "Admin logged out successfully.";
} elseif ($role == 'guru') {
    $logout_message = "Teacher logged out successfully.";
} elseif ($role == 'pelajar') {
    $logout_message = "Student logged out successfully.";
}

// Redirect ke login dengan pesan
header('Location: login.php?message=' . urlencode($logout_message));
exit();
?>