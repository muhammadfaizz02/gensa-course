<?php
include '../includes/auth_check.php';
include '../config/database.php';

// Date filters
$month = $_GET['month'] ?? date('Y-m');
$guru_id = $_GET['guru_id'] ?? '';

// Calculate payment (assuming 50,000 per teaching hour)
$rate_per_hour = 16000;

// Build query
$where_conditions = [];
if ($month) {
    $start_date = date('Y-m-01', strtotime($month));
    $end_date = date('Y-m-t', strtotime($month));
    $where_conditions[] = "k.tanggal BETWEEN '$start_date' AND '$end_date'";
}
if ($guru_id) {
    $where_conditions[] = "k.guru_id = '$guru_id'";
}
$where_clause = count($where_conditions) > 0 ? "WHERE " . implode(' AND ', $where_conditions) : "";

// Get attendance data for payment calculation
$query = "
    SELECT k.guru_id, u.full_name as guru_nama, 
           COUNT(k.id) as total_sessions,
           SUM(TIME_TO_SEC(TIMEDIFF(k.jam_selesai, k.jam_mulai))) / 3600 as total_hours,
           (SUM(TIME_TO_SEC(TIMEDIFF(k.jam_selesai, k.jam_mulai))) / 3600) * $rate_per_hour as total_payment
    FROM kehadiran_guru k
    JOIN users u ON k.guru_id = u.id
    $where_clause
    GROUP BY k.guru_id
    ORDER BY total_payment DESC
";
$payment_data = mysqli_query($conn, $query);

// Get teachers for filter
$teachers_query = "SELECT id, full_name FROM users WHERE role = 'guru' ORDER BY full_name";
$teachers = mysqli_query($conn, $teachers_query);

// Calculate totals - PERBAIKAN: tambahkan alias 'k'
$totals_query = "
    SELECT 
        COUNT(DISTINCT k.guru_id) as total_teachers,
        SUM(TIME_TO_SEC(TIMEDIFF(k.jam_selesai, k.jam_mulai))) / 3600 as total_hours,
        (SUM(TIME_TO_SEC(TIMEDIFF(k.jam_selesai, k.jam_mulai))) / 3600) * $rate_per_hour as total_payment_all
    FROM kehadiran_guru k
    $where_clause
";
$totals = mysqli_fetch_assoc(mysqli_query($conn, $totals_query));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Payment Report - Admin Gensa Course</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Mobile Menu Toggle -->
    <button id="mobileMenuBtn" class="lg:hidden fixed top-4 left-4 z-50 p-2 bg-yellow-500 text-white rounded-lg shadow-lg">
        <i class="fas fa-bars text-xl"></i>
    </button>
    
    <!-- Sidebar Overlay -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden lg:hidden"></div>
    
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Main Content Wrapper -->
    <div class="lg:ml-64 pt-14 lg:pt-0 min-h-screen min-w-0">
        <!-- Top Bar -->
        <div class="bg-white shadow-sm p-4 border-b">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Teacher Payment Report</h1>
                    <p class="text-gray-600">Calculate teacher payments based on teaching hours</p>
                </div>
                <div>
                    <a href="dashboard.php" class="inline-flex w-full sm:w-auto justify-center items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Content -->
        <div class="p-4 sm:p-6">
            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Filter Report</h2>
                
                <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                        <input type="month" name="month" value="<?php echo $month; ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Teacher</label>
                        <select name="guru_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <option value="">All Teachers</option>
                            <?php 
                            mysqli_data_seek($teachers, 0);
                            while ($teacher = mysqli_fetch_assoc($teachers)): 
                            ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php echo $guru_id == $teacher['id'] ? 'selected' : ''; ?>>
                                    <?php echo $teacher['full_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" 
                                class="w-full py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg">
                            <i class="fas fa-calculator mr-2"></i> Calculate
                        </button>
                    </div>
                </form>
                
                <!-- Payment Rate -->
                <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Payment Rate</p>
                            <p class="text-lg font-bold text-blue-600">Rp <?php echo number_format($rate_per_hour, 0, ',', '.'); ?> / hour</p>
                        </div>
                        <div class="sm:text-right">
                            <p class="text-sm text-gray-600">Period</p>
                            <p class="font-medium"><?php echo date('F Y', strtotime($month)); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Summary -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Payment Summary</h2>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="p-4 bg-blue-50 rounded-lg">
                        <p class="text-sm text-gray-600">Total Teachers</p>
                        <p class="text-2xl font-bold text-blue-600"><?php echo $totals['total_teachers'] ?? 0; ?></p>
                    </div>
                    
                    <div class="p-4 bg-green-50 rounded-lg">
                        <p class="text-sm text-gray-600">Total Hours</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo number_format($totals['total_hours'] ?? 0, 1); ?>h</p>
                    </div>
                    
                    <div class="p-4 bg-yellow-50 rounded-lg">
                        <p class="text-sm text-gray-600">Total Payment</p>
                        <p class="text-2xl font-bold text-yellow-600">Rp <?php echo number_format($totals['total_payment_all'] ?? 0, 0, ',', '.'); ?></p>
                    </div>
                    
                    <div class="p-4 bg-purple-50 rounded-lg">
                        <p class="text-sm text-gray-600">Average/Teacher</p>
                        <p class="text-2xl font-bold text-purple-600">
                            Rp <?php 
                            $avg = ($totals['total_teachers'] ?? 0) > 0 ? ($totals['total_payment_all'] ?? 0) / $totals['total_teachers'] : 0;
                            echo number_format($avg, 0, ',', '.');
                            ?>
                        </p>
                    </div>
                </div>
                
                <!-- Export Options -->
                <div class="flex flex-wrap gap-3">
                    <button onclick="exportToExcel()" 
                            class="px-4 py-2 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg">
                        <i class="fas fa-file-excel mr-2"></i> Export Excel
                    </button>
                    <button onclick="exportToPDF()" 
                            class="px-4 py-2 bg-red-100 text-red-700 hover:bg-red-200 rounded-lg">
                        <i class="fas fa-file-pdf mr-2"></i> Export PDF
                    </button>
                    <button onclick="printReport()" 
                            class="px-4 py-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                    <button onclick="generatePayroll()" 
                            class="px-4 py-2 bg-yellow-100 text-yellow-700 hover:bg-yellow-200 rounded-lg">
                        <i class="fas fa-money-bill-wave mr-2"></i> Generate Payroll
                    </button>
                </div>
            </div>
            
            <!-- Payment Details -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Payment Details by Teacher</h2>
                
                <?php if (mysqli_num_rows($payment_data) > 0): ?>
                    <!-- PERUBAHAN: Tabel Responsive -->
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px]">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Teacher</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Sessions</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Total Hours</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Hours/Session</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Payment</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Status</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php 
                                mysqli_data_seek($payment_data, 0);
                                while ($payment = mysqli_fetch_assoc($payment_data)): 
                                    $hours_per_session = $payment['total_sessions'] > 0 ? 
                                        $payment['total_hours'] / $payment['total_sessions'] : 0;
                                    $payment_status = 'pending';
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-3 px-4">
                                            <p class="font-medium"><?php echo $payment['guru_nama']; ?></p>
                                            <p class="text-sm text-gray-500">ID: <?php echo $payment['guru_id']; ?></p>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">
                                                <?php echo $payment['total_sessions']; ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <p class="font-medium"><?php echo number_format($payment['total_hours'], 1); ?>h</p>
                                        </td>
                                        <td class="py-3 px-4">
                                            <?php echo number_format($hours_per_session, 1); ?>h
                                        </td>
                                        <td class="py-3 px-4">
                                            <p class="text-lg font-bold text-yellow-600">
                                                Rp <?php echo number_format($payment['total_payment'], 0, ',', '.'); ?>
                                            </p>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium <?php 
                                                echo $payment_status == 'paid' ? 'bg-green-100 text-green-800' : 
                                                ($payment_status == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                            ?>">
                                                <?php echo ucfirst($payment_status); ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex space-x-1">
                                                <button onclick="viewTeacherDetails(<?php echo $payment['guru_id']; ?>)" 
                                                        class="p-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button onclick="markAsPaid(<?php echo $payment['guru_id']; ?>)" 
                                                        class="p-2 bg-green-100 text-green-700 hover:bg-green-200 rounded">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button onclick="downloadSlip(<?php echo $payment['guru_id']; ?>)" 
                                                        class="p-2 bg-yellow-100 text-yellow-700 hover:bg-yellow-200 rounded">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Payment Summary by Status -->
                    <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="font-medium text-gray-800 mb-3">Payment Status Summary</h3>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-center p-3 bg-yellow-50 rounded">
                                <p class="text-2xl font-bold text-yellow-600"><?php echo mysqli_num_rows($payment_data); ?></p>
                                <p class="text-sm text-gray-600">Pending Payment</p>
                            </div>
                            <div class="text-center p-3 bg-green-50 rounded">
                                <p class="text-2xl font-bold text-green-600">0</p>
                                <p class="text-sm text-gray-600">Paid</p>
                            </div>
                            <div class="text-center p-3 bg-red-50 rounded">
                                <p class="text-2xl font-bold text-red-600">0</p>
                                <p class="text-sm text-gray-600">Overdue</p>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-money-bill-wave text-5xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-700 mb-2">No Payment Data Found</h3>
                        <p class="text-gray-500">No attendance records found for the selected period</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Payment Schedule -->
            <div class="mt-6 bg-white rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Payment Schedule</h2>
                
                <div class="space-y-4">
                    <div class="p-4 bg-blue-50 rounded-lg">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div>
                                <h3 class="font-semibold text-blue-800">Next Payment Date</h3>
                                <p class="text-sm text-blue-700">5th of every month</p>
                            </div>
                            <div class="sm:text-right">
                                <p class="text-sm text-gray-600">Upcoming</p>
                                <p class="font-bold text-blue-800">
                                    <?php echo date('d F Y', strtotime('first day of next month +4 days')); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="p-4 bg-green-50 rounded-lg">
                            <h3 class="font-semibold text-green-800 mb-2">Payment Methods</h3>
                            <ul class="space-y-1 text-sm text-green-700">
                                <li><i class="fas fa-university mr-2"></i> Bank Transfer (Default)</li>
                                <li><i class="fas fa-money-bill-wave mr-2"></i> Cash</li>
                                <li><i class="fas fa-mobile-alt mr-2"></i> E-Wallet</li>
                            </ul>
                        </div>
                        
                        <div class="p-4 bg-purple-50 rounded-lg">
                            <h3 class="font-semibold text-purple-800 mb-2">Payment Notes</h3>
                            <ul class="space-y-1 text-sm text-purple-700">
                                <li><i class="fas fa-info-circle mr-2"></i> Payments processed on 5th monthly</li>
                                <li><i class="fas fa-clock mr-2"></i> 2-3 business days processing time</li>
                                <li><i class="fas fa-file-invoice mr-2"></i> Tax invoice provided</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    if (mobileMenuBtn && sidebar && sidebarOverlay) {
        mobileMenuBtn.addEventListener('click', function() {
            sidebar.classList.remove('-translate-x-full');
            sidebarOverlay.classList.remove('hidden');
        });
        
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
        });
        
        // Close button inside sidebar
        const closeSidebar = document.getElementById('closeSidebar');
        if (closeSidebar) {
            closeSidebar.addEventListener('click', function() {
                sidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            });
        }
    }
    
    function exportToExcel() {
        const params = new URLSearchParams(window.location.search);
        window.open(`ajax/export_payment_excel.php?${params.toString()}`, '_blank');
    }
    
    function exportToPDF() {
        const params = new URLSearchParams(window.location.search);
        window.open(`ajax/export_payment_pdf.php?${params.toString()}`, '_blank');
    }
    
    function printReport() {
        window.print();
    }
    
    function generatePayroll() {
        if (confirm('Generate payroll for all teachers? This will create payment records.')) {
            const params = new URLSearchParams(window.location.search);
            window.open(`ajax/generate_payroll.php?${params.toString()}`, '_blank');
        }
    }
    
    function viewTeacherDetails(teacherId) {
        window.open(`teacher_details.php?id=${teacherId}`, '_blank');
    }
    
    function markAsPaid(teacherId) {
        if (confirm('Mark all payments for this teacher as paid?')) {
            fetch(`ajax/mark_as_paid.php?teacher_id=${teacherId}&month=<?php echo $month; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Payment marked as paid!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
        }
    }
    
    function downloadSlip(teacherId) {
        window.open(`ajax/download_payment_slip.php?teacher_id=${teacherId}&month=<?php echo $month; ?>`, '_blank');
    }
    
    window.addEventListener('beforeprint', () => {
        document.querySelectorAll('.no-print').forEach(el => {
            el.classList.add('hidden');
        });
    });
    
    window.addEventListener('afterprint', () => {
        document.querySelectorAll('.no-print').forEach(el => {
            el.classList.remove('hidden');
        });
    });
    </script>
</body>
</html>