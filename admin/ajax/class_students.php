<?php
include '../includes/auth_check.php';
include '../../config/database.php';

$kelas = $_GET['kelas'] ?? '';
$subkelas = $_GET['subkelas'] ?? '';

if (!$kelas || !$subkelas) {
    header('Location: manage_kelas.php');
    exit();
}

// Get students in this class
$query = "
    SELECT u.id, u.full_name, u.email, u.username,
           p.asal_sekolah, p.jenjang, p.gender,
           p.mata_pelajaran, p.status
    FROM users u
    JOIN pelajar_profiles p ON u.id = p.user_id
    WHERE p.kelas = '$kelas' 
    AND p.subkelas = '$subkelas'
    ORDER BY u.full_name
";
$students = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Students - Admin Gensa Course</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen p-6">
        <div class="max-w-7xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            Students in <?php echo $kelas; ?> - <?php echo $subkelas; ?>
                        </h1>
                        <p class="text-gray-600">Total: <?php echo mysqli_num_rows($students); ?> students</p>
                    </div>
                    <div>
                        <button onclick="window.print()" 
                                class="px-4 py-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg mr-2">
                            <i class="fas fa-print mr-2"></i> Print
                        </button>
                        <button onclick="window.close()" 
                                class="px-4 py-2 bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-lg">
                            <i class="fas fa-times mr-2"></i> Close
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Student List -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">No</th>
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Student Name</th>
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Username/Email</th>
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">School</th>
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Subjects</th>
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Status</th>
                                <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php $no = 1; while ($student = mysqli_fetch_assoc($students)): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-3 px-4"><?php echo $no++; ?></td>
                                    <td class="py-3 px-4">
                                        <p class="font-medium"><?php echo $student['full_name']; ?></p>
                                        <p class="text-sm text-gray-500">
                                            <?php echo $student['jenjang']; ?> • <?php echo $student['gender']; ?>
                                        </p>
                                    </td>
                                    <td class="py-3 px-4">
                                        <p class="text-sm">@<?php echo $student['username']; ?></p>
                                        <p class="text-sm text-gray-500"><?php echo $student['email']; ?></p>
                                    </td>
                                    <td class="py-3 px-4">
                                        <p class="text-sm"><?php echo $student['asal_sekolah']; ?></p>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php 
                                        $subjects = explode(',', $student['mata_pelajaran']);
                                        foreach ($subjects as $subject): 
                                            $color = $subject == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
                                        ?>
                                            <span class="inline-block px-2 py-1 rounded text-xs font-medium <?php echo $color; ?> mr-1 mb-1">
                                                <?php echo ucfirst($subject); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium <?php 
                                            echo $student['status'] == 'approved' ? 'bg-green-100 text-green-800' : 
                                            ($student['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                        ?>">
                                            <?php echo ucfirst($student['status']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex space-x-1">
                                            <a href="approve_user.php#student_<?php echo $student['id']; ?>" 
                                               class="p-1 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded"
                                               title="View Details">
                                                <i class="fas fa-eye text-xs"></i>
                                            </a>
                                            <button onclick="sendMessage(<?php echo $student['id']; ?>)" 
                                                    class="p-1 bg-green-100 text-green-700 hover:bg-green-200 rounded"
                                                    title="Send Message">
                                                <i class="fas fa-envelope text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Summary -->
                <div class="p-4 bg-gray-50 border-t">
                    <div class="grid grid-cols-4 gap-4">
                        <div class="text-center">
                            <p class="text-2xl font-bold text-blue-600"><?php echo $no-1; ?></p>
                            <p class="text-sm text-gray-600">Total Students</p>
                        </div>
                        <?php
                        mysqli_data_seek($students, 0);
                        $approved = 0;
                        $pending = 0;
                        $arab = 0;
                        $english = 0;
                        
                        while ($student = mysqli_fetch_assoc($students)) {
                            if ($student['status'] == 'approved') $approved++;
                            if ($student['status'] == 'pending') $pending++;
                            
                            $subjects = explode(',', $student['mata_pelajaran']);
                            if (in_array('arab', $subjects)) $arab++;
                            if (in_array('inggris', $subjects)) $english++;
                        }
                        ?>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-green-600"><?php echo $approved; ?></p>
                            <p class="text-sm text-gray-600">Approved</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-yellow-600"><?php echo $arab; ?></p>
                            <p class="text-sm text-gray-600">Arabic Students</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-purple-600"><?php echo $english; ?></p>
                            <p class="text-sm text-gray-600">English Students</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Print Styles -->
            <style media="print">
            @page {
                size: A4 landscape;
                margin: 0.5cm;
            }
            body {
                font-size: 12px;
            }
            .no-print {
                display: none !important;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                padding: 4px;
                border: 1px solid #ddd;
            }
            th {
                background-color: #f3f4f6;
                font-weight: bold;
            }
            </style>
        </div>
    </div>
    
    <script>
    function sendMessage(studentId) {
        const message = prompt('Enter message for student:');
        if (message) {
            alert('Message sent to student (demo only)');
            // In real application, AJAX call to send message
        }
    }
    </script>
</body>
</html>