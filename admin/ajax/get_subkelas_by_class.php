<?php
include '../../config/database.php';

if (isset($_GET['kelas_id'])) {
    $kelas_id = mysqli_real_escape_string($conn, $_GET['kelas_id']);
    
    $query = "
        SELECT id, nama_subkelas 
        FROM master_subkelas 
        WHERE kelas_id = $kelas_id 
        AND status = 'active'
        ORDER BY urutan
    ";
    $result = mysqli_query($conn, $query);
    
    $subkelas = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $subkelas[] = $row;
    }
    
    echo json_encode($subkelas);
}
?>