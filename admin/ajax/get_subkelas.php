<?php
include '../../config/database.php';

if (isset($_GET['jenjang']) && isset($_GET['kelas'])) {
    $jenjang = mysqli_real_escape_string($conn, $_GET['jenjang']);
    $kelas = mysqli_real_escape_string($conn, $_GET['kelas']);
    
    $query = "
        SELECT ms.nama_subkelas 
        FROM master_subkelas ms
        JOIN master_kelas mk ON ms.kelas_id = mk.id
        WHERE mk.jenjang = '$jenjang' 
        AND mk.nama_kelas = '$kelas'
        AND ms.status = 'active'
        ORDER BY ms.urutan
    ";
    $result = mysqli_query($conn, $query);
    
    $subkelas = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $subkelas[] = $row;
    }
    
    echo json_encode($subkelas);
}
?>