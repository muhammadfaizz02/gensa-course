<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];

// Get guru profile
include '../config/database.php';
$query = "SELECT * FROM guru_profiles WHERE user_id = $user_id";
$result = mysqli_query($conn, $query);
$guru_profile = mysqli_fetch_assoc($result);
?>