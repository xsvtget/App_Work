<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$month = isset($_GET["month"]) ? (int)$_GET["month"] : (int)date("n");
$year = isset($_GET["year"]) ? (int)$_GET["year"] : (int)date("Y");

if ($month < 1 || $month > 12) {
    $month = (int)date("n");
}

if ($year < 2000 || $year > 2100) {
    $year = (int)date("Y");
}

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

$filename = "template_" . $year . "_" . str_pad($month, 2, "0", STR_PAD_LEFT) . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen("php://output", "w");

fputcsv($output, ["Date", "Start", "End"]);

for ($day = 1; $day <= $daysInMonth; $day++) {
    $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
    fputcsv($output, [$date, "", ""]);
}

fclose($output);
exit();
?>