<?php
include '../../config/database.php';

if (isset($_GET['jenjang'])) {
    $jenjang = mysqli_real_escape_string($conn, $_GET['jenjang']);
    
    $query = "SELECT DISTINCT nama_kelas FROM master_kelas WHERE jenjang = '$jenjang' AND status = 'active' ORDER BY urutan";
    $result = mysqli_query($conn, $query);
    
    $kelas = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $kelas[] = $row;
    }
    
    echo json_encode($kelas);
}
?>