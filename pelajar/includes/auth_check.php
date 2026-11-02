<?php
// pelajar/includes/auth_check.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../login.php');
    exit();
}

// Check if user is a student
if ($_SESSION['role'] != 'pelajar') {
    header('Location: ../../login.php');
    exit();
}

// Check if account is active
if ($_SESSION['status'] != 'active') {
    session_destroy();
    header('Location: ../../login.php?error=Akun Anda belum aktif. Silahkan hubungi admin.');
    exit();
}

// Set user data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];
?>