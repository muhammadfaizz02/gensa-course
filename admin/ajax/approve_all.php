<?php
session_start();
include '../config/database.php';

// Cek apakah admin
if ($_SESSION['role'] != 'admin') {
    die('Access denied!');
}

// Query sederhana untuk approve semua user pending
$query = "UPDATE users SET status = 'active' 
          WHERE status = 'pending' AND role = 'pelajar'";

if (mysqli_query($conn, $query)) {
    $affected_rows = mysqli_affected_rows($conn);
    $_SESSION['success'] = "Successfully approved $affected_rows student(s)!";
} else {
    $_SESSION['error'] = "Error: " . mysqli_error($conn);
}

header('Location: ../admin/approve_user.php');
exit();