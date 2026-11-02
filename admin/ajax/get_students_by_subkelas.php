<?php
include '../../config/database.php';

if (isset($_GET['subkelas_id'])) {
    $subkelas_id = mysqli_real_escape_string($conn, $_GET['subkelas_id']);
    
    // Get class info first
    $class_query = "
        SELECT mk.nama_kelas, ms.nama_subkelas
        FROM master_subkelas ms
        JOIN master_kelas mk ON ms.kelas_id = mk.id
        WHERE ms.id = $subkelas_id
    ";
    $class_result = mysqli_query($conn, $class_query);
    $class = mysqli_fetch_assoc($class_result);
    
    if ($class) {
        $query = "
            SELECT u.id, u.full_name 
            FROM users u
            JOIN pelajar_profiles p ON u.id = p.user_id
            WHERE p.kelas = '{$class['nama_kelas']}'
            AND p.subkelas = '{$class['nama_subkelas']}'
            AND p.status = 'approved'
            ORDER BY u.full_name
        ";
        
        $result = mysqli_query($conn, $query);
        
        $students = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $students[] = [
                'id' => $row['id'],
                'full_name' => $row['full_name'],
                'kelas' => $class['nama_kelas'],
                'subkelas' => $class['nama_subkelas']
            ];
        }
        
        echo json_encode($students);
    }
}
?>