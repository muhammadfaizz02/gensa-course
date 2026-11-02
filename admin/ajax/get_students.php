<?php
include '../../config/database.php';

if (isset($_GET['kelas']) && isset($_GET['subkelas'])) {
    $kelas = mysqli_real_escape_string($conn, $_GET['kelas']);
    $subkelas = mysqli_real_escape_string($conn, $_GET['subkelas']);
    
    $query = "
        SELECT u.id, u.full_name 
        FROM users u
        JOIN pelajar_profiles p ON u.id = p.user_id
        WHERE p.kelas = '$kelas' 
        AND p.subkelas = '$subkelas'
        AND p.status = 'approved'
        ORDER BY u.full_name
    ";
    $result = mysqli_query($conn, $query);
    
    $students = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $students[] = [
            'id' => $row['id'],
            'full_name' => $row['full_name']
        ];
    }
    
    echo json_encode($students);
}
?>