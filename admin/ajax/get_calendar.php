<?php
include '../../config/database.php';

$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d', strtotime('+7 days'));

// Generate week days
$start = new DateTime($start_date);
$end = new DateTime($end_date);
$interval = new DateInterval('P1D');
$period = new DatePeriod($start, $interval, $end);

// Get schedules for the period
$query = "
    SELECT j.*, u.full_name as guru_nama, mk.nama_kelas, ms.nama_subkelas
    FROM jadwal j
    LEFT JOIN users u ON j.guru_id = u.id
    LEFT JOIN master_kelas mk ON j.kelas_id = mk.id
    LEFT JOIN master_subkelas ms ON j.subkelas_id = ms.id
    WHERE j.tanggal BETWEEN '$start_date' AND '$end_date'
    ORDER BY j.tanggal, j.jam_mulai
";
$schedules_result = mysqli_query($conn, $query);
$schedules = [];
while ($row = mysqli_fetch_assoc($schedules_result)) {
    $schedules[$row['tanggal']][] = $row;
}

// Generate calendar
?>
<div class="min-w-full">
    <table class="w-full">
        <thead>
            <tr class="bg-gray-50">
                <?php foreach ($period as $date): ?>
                    <th class="py-3 px-4 text-center text-sm font-medium text-gray-700">
                        <div class="font-semibold"><?php echo $date->format('D'); ?></div>
                        <div class="text-lg <?php echo $date->format('Y-m-d') == date('Y-m-d') ? 'text-yellow-600 font-bold' : 'text-gray-800'; ?>">
                            <?php echo $date->format('d'); ?>
                        </div>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
                <?php foreach ($period as $date): 
                    $date_str = $date->format('Y-m-d');
                ?>
                    <td class="align-top border border-gray-200 p-2 min-h-[150px]">
                        <?php if (isset($schedules[$date_str])): ?>
                            <div class="space-y-2">
                                <?php foreach ($schedules[$date_str] as $schedule): 
                                    $status_color = $schedule['status'] == 'scheduled' ? 'border-yellow-300 bg-yellow-50' : 
                                                  ($schedule['status'] == 'completed' ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50');
                                ?>
                                    <div class="border-l-4 border-yellow-500 bg-yellow-50 p-2 rounded-r text-xs hover:bg-yellow-100 cursor-pointer" 
                                         onclick="viewSchedule(<?php echo $schedule['id']; ?>)"
                                         title="<?php echo $schedule['guru_nama']; ?> - <?php echo $schedule['nama_kelas']; ?>">
                                        <div class="font-medium truncate">
                                            <?php echo date('H:i', strtotime($schedule['jam_mulai'])); ?> 
                                            <?php echo $schedule['nama_kelas']; ?>
                                        </div>
                                        <div class="text-gray-600 truncate">
                                            <?php echo $schedule['guru_nama']; ?>
                                        </div>
                                        <div class="flex justify-between items-center mt-1">
                                            <span class="px-1 py-0.5 <?php 
                                                echo $schedule['bahasa'] == 'arab' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
                                            ?> rounded text-xs">
                                                <?php echo substr($schedule['bahasa'], 0, 1); ?>
                                            </span>
                                            <span class="text-gray-500">
                                                <?php echo $schedule['status'] == 'scheduled' ? '●' : 
                                                      ($schedule['status'] == 'completed' ? '✓' : '✗'); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-gray-400 py-8 text-sm">
                                No schedule
                            </div>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        </tbody>
    </table>
</div>