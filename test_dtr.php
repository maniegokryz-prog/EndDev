<?php
require 'db_connection.php';
require 'attendancerep/dtr_utils.php';

$schedule = [
    0 => [ // 0 = Monday in dtr_utils.php terminology for some reason, let's verify.
        ['start' => '08:00', 'end' => '17:00']
    ],
    1 => [ // 0 = Monday in dtr_utils.php terminology for some reason, let's verify.
        ['start' => '08:00', 'end' => '17:00']
    ]
];

$dateStr = '2026-03-09'; // 2026-03-09 is a Monday. $phpDow = 1. $dbDow = 0.
$timeInStr = '08:16:00';
$timeOutStr = '17:00:00';
$employeeRole = 'staff';

$mins = calculateActualHoursWithClamping($timeInStr, $timeOutStr, $schedule, $dateStr, $employeeRole);
echo "Result minutes: " . $mins . "<br>";
$h = floor($mins / 60);
$m = $mins % 60;
echo "Hours: " . $h . " Mins: " . $m;
?>
