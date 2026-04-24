<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

$host = "db";
$dbname = getenv("MARIADB_DATABASE");
$username = getenv("MARIADB_USER");
$password = getenv("MARIADB_APP_PASSWORD");

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* REMEMBER ME: якщо сесії нема, але є cookie */
if (!isset($_SESSION["user_id"]) && isset($_COOKIE["remember_token"])) {
    $cookieToken = $_COOKIE["remember_token"];

    $stmtAuto = $conn->prepare("SELECT id, username FROM users WHERE remember_token = ?");
    $stmtAuto->bind_param("s", $cookieToken);
    $stmtAuto->execute();
    $resultAuto = $stmtAuto->get_result();

    if ($resultAuto->num_rows === 1) {
        $userAuto = $resultAuto->fetch_assoc();
        $_SESSION["user_id"] = $userAuto["id"];
        $_SESSION["username"] = $userAuto["username"];
    }

    $stmtAuto->close();
}

/* ТІЛЬКИ ПІСЛЯ ЦЬОГО перевірка входу */
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];
$workplacesForSelect = [];
$stmtWp = $conn->prepare("SELECT id, name, color FROM workplaces WHERE user_id = ? ORDER BY name ASC");
$stmtWp->bind_param("i", $user_id);
$stmtWp->execute();
$wpResult = $stmtWp->get_result();

while ($wpRow = $wpResult->fetch_assoc()) {
    $workplacesForSelect[] = $wpRow;
}
$stmtWp->close();

$user_name = $_SESSION["username"] ?? "User";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];
$user_name = $_SESSION["username"] ?? "User";
$profile_image = null;

$stmtUser = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$userResult = $stmtUser->get_result();

if ($userResult && $userResult->num_rows === 1) {
    $userRow = $userResult->fetch_assoc();
    $profile_image = $userRow["profile_image"] ?? null;
}
$stmtUser->close();

function decimalToTime($value) {
    if ($value === null || $value === '') return null;

    $num = floatval($value);
    $hours = floor($num);
    $minutes = round(($num - $hours) * 60);

    if ($minutes >= 60) {
        $hours += 1;
        $minutes = 0;
    }

    return sprintf('%02d:%02d:00', $hours, $minutes);
}

function formatTimeForInput($time) {
    if (!$time) return '';
    return substr($time, 0, 5);
}

function calculateHours($start, $end) {
    if (!$start || !$end) return 0;
    $startTs = strtotime($start);
    $endTs = strtotime($end);
    if ($endTs <= $startTs) return 0;
    return round(($endTs - $startTs) / 3600, 2);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["action"]) && $_POST["action"] === "delete_selected_days") {
        $selected_days = $_POST["selected_days"] ?? [];
        $month_redirect = (int)($_POST["month"] ?? date("n"));
        $year_redirect = (int)($_POST["year"] ?? date("Y"));

        if (!empty($selected_days) && is_array($selected_days)) {
            $stmt = $conn->prepare("DELETE FROM work_shifts WHERE user_id = ? AND work_date = ?");

            foreach ($selected_days as $day) {
                $work_date = trim($day);

                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $work_date)) {
                    $stmt->bind_param("is", $user_id, $work_date);
                    $stmt->execute();
                }
            }

            $stmt->close();
        }

        header("Location: dashboard.php?month=" . $month_redirect . "&year=" . $year_redirect);
        exit();
    }
    if (isset($_POST["action"]) && $_POST["action"] === "delete_day_shifts") {
        $work_date = $_POST["work_date"] ?? '';
        $month_redirect = (int)($_POST["month"] ?? date("n"));
        $year_redirect = (int)($_POST["year"] ?? date("Y"));

        if (!empty($work_date)) {
            $stmt = $conn->prepare("DELETE FROM work_shifts WHERE user_id = ? AND work_date = ?");
            $stmt->bind_param("is", $user_id, $work_date);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: dashboard.php?month=" . $month_redirect . "&year=" . $year_redirect . "&selected_date=" . urlencode($work_date));
        exit();
    }
    if (isset($_POST["action"]) && $_POST["action"] === "save_shift") {
        $id = !empty($_POST["id"]) ? (int)$_POST["id"] : 0;
        $entry_type = $_POST["entry_type"] ?? 'shift';
        $workplace_id = !empty($_POST["workplace_id"]) ? (int)$_POST["workplace_id"] : null;
        $work_date = $_POST["work_date"] ?? '';
        $start_time = !empty($_POST["start_time"]) ? $_POST["start_time"] . ':00' : null;
        $end_time = !empty($_POST["end_time"]) ? $_POST["end_time"] . ':00' : null;
        $workplace = trim($_POST["workplace"] ?? 'Sabi Madla');
        $color = trim($_POST["color"] ?? '#3b82f6');
        $note = trim($_POST["note"] ?? '');

        if ($entry_type === 'shift' && empty($workplace_id)) {
            header("Location: dashboard.php?month=" . $month . "&year=" . $year . "&selected_date=" . urlencode($work_date));
            exit();
        }

        if ($entry_type === 'plan') {
            $workplace_id = null;
            $workplace = $note !== '' ? $note : 'Plan';
        }

        if ($id > 0) {
           $stmt = $conn->prepare("
                UPDATE work_shifts
                SET entry_type = ?, workplace_id = ?, work_date = ?, start_time = ?, end_time = ?, workplace = ?, color = ?, note = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->bind_param(
                "sissssssii",
                $entry_type,
                $workplace_id,
                $work_date,
                $start_time,
                $end_time,
                $workplace,
                $color,
                $note,
                $id,
                $user_id
            );
            $stmt->execute();
            $stmt->close();
        } 
        else {
            $stmt = $conn->prepare("
                INSERT INTO work_shifts (user_id, entry_type, workplace_id, work_date, start_time, end_time, workplace, color, note)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "isissssss",
                $user_id,
                $entry_type,
                $workplace_id,
                $work_date,
                $start_time,
                $end_time,
                $workplace,
                $color,
                $note
            );
            $stmt->execute();
            $stmt->close();
        }

        header("Location: dashboard.php?selected_date=" . urlencode($work_date));
        exit();
    }

    if (isset($_POST["action"]) && $_POST["action"] === "delete_shift") {
        $id = (int)($_POST["id"] ?? 0);
        $selected_date = $_POST["selected_date"] ?? date("Y-m-d");

        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM work_shifts WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: dashboard.php?selected_date=" . urlencode($selected_date));
        exit();
    }

    if (isset($_POST["action"]) && $_POST["action"] === "import_excel") {
        $json = $_POST["excel_data"] ?? '[]';
        $import_workplace_id = !empty($_POST["workplace_id"]) ? (int)$_POST["workplace_id"] : 0;
        $defaultColor = trim($_POST["import_color"] ?? '#3b82f6');
        $rows = json_decode($json, true);

        if ($import_workplace_id <= 0) {
            header("Location: dashboard.php?month=" . $month . "&year=" . $year . "&selected_date=" . urlencode($selectedDate));
            exit();
        }

        $stmtWpImport = $conn->prepare("SELECT name, color FROM workplaces WHERE id = ? AND user_id = ? LIMIT 1");
        $stmtWpImport->bind_param("ii", $import_workplace_id, $user_id);
        $stmtWpImport->execute();
        $wpImportResult = $stmtWpImport->get_result();
        $importWorkplace = $wpImportResult->fetch_assoc();
        $stmtWpImport->close();

        if (!$importWorkplace) {
            header("Location: dashboard.php?month=" . $month . "&year=" . $year . "&selected_date=" . urlencode($selectedDate));
            exit();
        }

        $defaultWorkplace = $importWorkplace["name"];
        $defaultColor = $importWorkplace["color"] ?: $defaultColor;

        if (is_array($rows)) {
            $stmt = $conn->prepare("
                INSERT INTO work_shifts (user_id, entry_type, workplace_id, work_date, start_time, end_time, workplace, color, note)
                VALUES (?, 'shift', ?, ?, ?, ?, ?, ?, NULL)
            ");

            foreach ($rows as $row) {
                $work_date = $row["work_date"] ?? null;
                $start_time = isset($row["start_decimal"]) ? decimalToTime($row["start_decimal"]) : null;
                $end_time = isset($row["end_decimal"]) ? decimalToTime($row["end_decimal"]) : null;

                if ($work_date && $start_time && $end_time) {
                    $stmt->bind_param(
                        "iisssss",
                        $user_id,
                        $import_workplace_id,
                        $work_date,
                        $start_time,
                        $end_time,
                        $defaultWorkplace,
                        $defaultColor
                    );
                    $stmt->execute();
                }
            }

            $stmt->close();
        }

        header("Location: dashboard.php");
        exit();
    }
}

$month = isset($_GET["month"]) ? (int)$_GET["month"] : (int)date("n");
$year = isset($_GET["year"]) ? (int)$_GET["year"] : (int)date("Y");

if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = (int)date("t", $firstDay);
$firstWeekDay = (int)date("N", $firstDay);

$monthNames = [
    1 => "January", 2 => "February", 3 => "March", 4 => "April",
    5 => "May", 6 => "June", 7 => "July", 8 => "August",
    9 => "September", 10 => "October", 11 => "November", 12 => "December"
];

$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$selectedDate = $_GET["selected_date"] ?? date("Y-m-d");

$stmt = $conn->prepare("
    SELECT id, entry_type, workplace_id, work_date, start_time, end_time, workplace, color, note
    FROM work_shifts
    WHERE user_id = ? AND MONTH(work_date) = ? AND YEAR(work_date) = ?
    ORDER BY work_date, start_time
");
$stmt->bind_param("iii", $user_id, $month, $year);
$stmt->execute();
$result = $stmt->get_result();

$calendarShifts = [];
$monthlyHours = 0;
$workplaceTotals = [];
$workplaceColors = [];

while ($row = $result->fetch_assoc()) {
    $hours = calculateHours($row["start_time"], $row["end_time"]);
    $row["hours"] = $hours;
    $calendarShifts[$row["work_date"]][] = $row;

    // Count only real work shifts in monthly total and workplace totals
    if ($row["entry_type"] === "shift") {
        $monthlyHours += $hours;

        if (!isset($workplaceTotals[$row["workplace"]])) {
            $workplaceTotals[$row["workplace"]] = 0;
        }

        $workplaceTotals[$row["workplace"]] += $hours;

        if (!isset($workplaceColors[$row["workplace"]])) {
            $workplaceColors[$row["workplace"]] = $row["color"];
        }
    }
}
$stmt->close();

$stmt = $conn->prepare("
    SELECT id, entry_type, workplace_id, work_date, start_time, end_time, workplace, color, note
    FROM work_shifts
    WHERE user_id = ? AND work_date = ?
    ORDER BY start_time
");
$stmt->bind_param("is", $user_id, $selectedDate);
$stmt->execute();
$selectedResult = $stmt->get_result();

$selectedShifts = [];
$dayTotal = 0;
while ($row = $selectedResult->fetch_assoc()) {
    $row["hours"] = calculateHours($row["start_time"], $row["end_time"]);

    // Count only real work shifts in selected day total
    if ($row["entry_type"] === "shift") {
        $dayTotal += $row["hours"];
    }

    $selectedShifts[] = $row;
}
$stmt->close();

$editShift = null;
if (isset($_GET["edit_id"])) {
    $edit_id = (int)$_GET["edit_id"];
    $stmt = $conn->prepare("
        SELECT id, entry_type, workplace_id, work_date, start_time, end_time, workplace, color, note
        FROM work_shifts
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $edit_id, $user_id);
    $stmt->execute();
    $editResult = $stmt->get_result();
    $editShift = $editResult->fetch_assoc();
    $stmt->close();
}

if (!$editShift) {
    $editShift = [
        "id" => "",
        "entry_type" => "shift",
        "workplace_id" => "",
        "work_date" => $selectedDate,
        "start_time" => "",
        "end_time" => "",
        "workplace" => "",
        "color" => "#3b82f6",
        "note" => ""
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Calendar</title>
    <meta name="format-detection" content="telephone=no, date=no, email=no, address=no">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0f172a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="App Work">
    <link rel="apple-touch-icon" href="/logo.png">
    <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #1f2937;
        }

        a[x-apple-data-detectors],
        a[x-apple-data-detectors-type="date"],
        a[x-apple-data-detectors-type="address"],
        a[x-apple-data-detectors-type="calendar-event"] {
            color: inherit !important;
            text-decoration: none !important;
            font-size: inherit !important;
            font-family: inherit !important;
            font-weight: inherit !important;
            line-height: inherit !important;
        }

        .top-bar {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            padding: 16px 18px;
            margin-bottom: 14px;
            box-sizing: border-box;
            position: relative;
        }

        .user-profile-box {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .profile-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ffffff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.10);
            background: #e9eef3;
        }

        .fallback-avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 800;
            color: #0d8a8a;
            background: #dff6f6;
        }

        .user-profile-text {
            display: flex;
            flex-direction: column;
        }

        .hello-user {
            font-size: 16px;
            font-weight: 700;
            color: #12304a;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }

        .profile-btn,
        .logout-btn,
        .menu-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            padding: 10px 16px;
            border-radius: 14px;
            transition: 0.2s ease;
            border: none;
        }

        .profile-btn {
            background: #18b7b0;
            box-shadow: 0 6px 14px rgba(24, 183, 176, 0.22);
        }

        .profile-btn:hover {
            background: #14a39d;
            transform: translateY(-1px);
        }

        .logout-btn {
            background: #e53935;
            box-shadow: 0 6px 14px rgba(229, 57, 53, 0.22);
        }

        .logout-btn:hover {
            background: #d32f2f;
            transform: translateY(-1px);
        }

        .menu-toggle {
            display: none;
            width: 42px;
            height: 42px;
            border: none;
            border-radius: 12px;
            background: #ffffff;
            color: #12304a;
            font-size: 20px;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            cursor: pointer;
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1002;
        }

        .mobile-only {
            display: none;
        }

        .desktop-only {
            display: block;
        }

        .menu-overlay {
            display: none;
        }

        .menu-modal {
            display: none;
        }

        .app {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 18px;
            padding: 16px;
        }

        .main,
        .sidebar {
            min-width: 0;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .month-nav {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .month-nav a {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #ffffff;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0f766e;
            font-size: 24px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            flex-shrink: 0;
        }

        .month-nav h1 {
            margin: 0;
            font-size: 28px;
            color: #0f274f;
        }

        .summary {
            background: #ffffff;
            border-radius: 18px;
            padding: 12px 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            min-width: 190px;
        }

        .summary .label {
            font-size: 14px;
            color: #64748b;
        }

        .summary strong {
            display: block;
            font-size: 20px;
            color: #0f766e;
            margin-top: 2px;
        }

        .calendar-box,
        .card,
        details.card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.06);
        }

        .calendar-box {
            padding: 14px;
        }

        .weekdays,
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 8px;
        }

        .weekdays {
            margin-bottom: 8px;
        }

        .weekdays div {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            color: #14b8a6;
            padding: 6px 0;
        }

        .day {
            display: block;
            height: 96px;
            min-height: 96px;
            max-height: 96px;
            background: #f8fafc;
            border-radius: 14px;
            padding: 8px;
            text-decoration: none;
            color: #111827;
            box-shadow: inset 0 0 0 1px #e5e7eb;
            overflow: hidden;
            position: relative;
        }

        .day.empty {
            background: transparent;
            box-shadow: none;
            pointer-events: none;
        }

        .day.selected {
            background: #ecfeff;
            box-shadow: inset 0 0 0 2px #14b8a6;
        }

        .day-number {
            font-weight: 700;
            font-size: 14px;
            display: inline-block;
            margin-bottom: 6px;
        }

        .day-content {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .mini-shift {
            width: 100%;
            height: 8px;
            border-radius: 999px;
        }

        .mini-more {
            font-size: 10px;
            color: #64748b;
            font-weight: 600;
            line-height: 1.1;
        }

        .desktop-time {
            display: none;
            font-size: 11px;
            color: #334155;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .panel-day {
            margin-top: 14px;
            padding: 18px;
        }

        .panel-day h3 {
            margin: 0 0 12px 0;
            font-size: 18px;
            color: #0f274f;
        }

        .shift-card {
            border-radius: 16px;
            padding: 12px;
            margin-bottom: 10px;
            background: #f8fafc;
            border-left: 8px solid #3b82f6;
        }

        .shift-card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
        }

        .shift-card h4 {
            margin: 0 0 4px 0;
            font-size: 18px;
        }

        .shift-meta {
            font-size: 14px;
            color: #475569;
        }

        .shift-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .action-link,
        .action-btn {
            text-decoration: none;
            border: none;
            border-radius: 10px;
            padding: 9px 12px;
            font-size: 14px;
            cursor: pointer;
            background: #e2e8f0;
            color: #111827;
        }

        .action-btn.delete {
            background: #fee2e2;
            color: #b91c1c;
        }

        details.card {
            margin-top: 14px;
            overflow: hidden;
        }

        details.card summary {
            list-style: none;
            cursor: pointer;
            padding: 16px 18px;
            font-size: 18px;
            font-weight: 700;
            color: #0f274f;
        }

        details.card summary::-webkit-details-marker {
            display: none;
        }

        details.card .inside {
            padding: 0 18px 18px 18px;
        }

        .form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .form label {
            font-size: 14px;
            font-weight: 600;
            color: #334155;
        }

        .form input,
        .form button {
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #d1d5db;
            font-size: 14px;
        }

        .form button {
            border: none;
            background: #14b8a6;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }

        .import-btn {
            background: #0ea5e9 !important;
        }

        .totals-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .totals-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            border-radius: 12px;
            background: #f8fafc;
        }

        .totals-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .totals-left span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .small {
            font-size: 13px;
            color: #64748b;
        }

        @media (min-width: 1100px) {
            .desktop-time {
                display: block;
            }

            .day {
                height: 112px;
                min-height: 112px;
                max-height: 112px;
            }
        }

        @media (max-width: 980px) {
            .app {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .top-bar {
                padding: 12px 14px;
                margin-bottom: 0px;
                min-height: 60px;
                padding-right: 68px;
            }

            .profile-avatar {
                width: 42px;
                height: 42px;
            }

            .fallback-avatar {
                font-size: 18px;
            }

            .hello-user {
                font-size: 14px;
            }

            .desktop-actions {
                display: none;
            }

            .mobile-only {
                display: block;
            }

            .menu-toggle {
                width: 38px;
                height: 38px;
                border-radius: 10px;
                font-size: 18px;
                right: 14px;
            }

            .menu-overlay {
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, 0.45);
                z-index: 1000;
                display: none;
            }

            .menu-overlay.open {
                display: block;
            }

            .menu-modal {
                position: fixed;
                top: 70px;
                right: 16px;
                width: 150px;
                background: #ffffff;
                border-radius: 22px;
                box-shadow: 0 18px 40px rgba(0,0,0,0.18);
                padding: 10px;
                z-index: 1001;
                display: none;
                flex-direction: column;
                gap: 10px;
            }

            .menu-modal.open {
                display: flex;
            }

            .menu-link {
                width: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                text-decoration: none;
                font-weight: 700;
                font-size: 12px;
                padding: 14px 16px;
                border-radius: 14px;
                color: #fff;
            }

            .menu-link.profile-link {
                background: #18b7b0;
                box-shadow: 0 6px 14px rgba(24, 183, 176, 0.22);
            }

            .menu-link.logout-link {
                background: #e53935;
                box-shadow: 0 6px 14px rgba(229, 57, 53, 0.22);
            }

            .app {
                padding: 10px;
                gap: 12px;
                grid-template-columns: 1fr;
            }

            .month-nav h1 {
                font-size: 22px;
            }

            .summary {
                width: 100%;
                min-width: 0;
            }

            .calendar-box {
                padding: 10px;
            }

            .weekdays,
            .calendar-grid {
                gap: 6px;
            }

            .weekdays div {
                font-size: 11px;
            }

            .day {
                height: 58px;
                min-height: 58px;
                max-height: 58px;
                padding: 5px;
                border-radius: 12px;
            }

            .day-number {
                font-size: 12px;
                margin-bottom: 2px;
            }

            .mini-shift {
                height: 5px;
                margin-top: 2px;
            }

            .mini-more {
                font-size: 9px;
                margin-top: 2px;
            }

            .panel-day {
                padding: 14px;
            }

            details.card summary {
                padding: 14px;
                font-size: 17px;
            }

            details.card .inside {
                padding: 0 14px 14px 14px;
            }

            .form input,
            .form select,
            .form button {
                font-size: 16px;
            }

            .shift-card {
                padding: 10px;
            }

            .shift-card h4 {
                font-size: 16px;
            }

            .shift-meta {
                font-size: 13px;
            }
        }

        .template-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            text-decoration: none;
            border: none;
            border-radius: 12px;
            padding: 12px;
            font-size: 14px;
            font-weight: 700;
            background: #2563eb;
            color: #ffffff;
            box-shadow: 0 6px 14px rgba(37, 99, 235, 0.20);
            transition: 0.2s ease;
        }

        .template-btn:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .delete-big-btn {
            width: 100%;
            border: none;
            border-radius: 12px;
            padding: 12px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            background: #f59e0b;
            color: #ffffff;
            box-shadow: 0 6px 14px rgba(245, 158, 11, 0.22);
            transition: 0.2s ease;
        }

        .delete-big-btn:hover {
            transform: translateY(-1px);
            background: #d97706;
        }

        .delete-big-btn.danger {
            background: #ef4444;
            box-shadow: 0 6px 14px rgba(239, 68, 68, 0.22);
        }

        .delete-big-btn.danger:hover {
            background: #dc2626;
        }

        #planFields,
        #workShiftFields {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .day-checkbox {
            display: none;
        }

        .selectable-day.multi-selected {
            outline: 3px solid #14b8a6;
            background: rgba(20, 184, 166, 0.15);
        }
    </style>
</head>
<body>

<div class="top-bar">
    <div class="user-profile-box">
        <?php if (!empty($profile_image) && file_exists(__DIR__ . '/' . $profile_image)): ?>
            <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" class="profile-avatar">
        <?php else: ?>
            <div class="profile-avatar fallback-avatar">
                <?php echo strtoupper(substr($user_name, 0, 1)); ?>
            </div>
        <?php endif; ?>

        <div class="user-profile-text">
            <span class="hello-user"><?php echo htmlspecialchars($user_name); ?></span>
        </div>
    </div>

    <div class="top-actions desktop-actions">
        <a href="salary_settings.php" class="profile-btn">Salary settings</a>
        <a href="salary.php" class="profile-btn">Salary</a>
        <a href="edit_profile.php" class="profile-btn">Profile</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <button class="menu-toggle mobile-only" id="menuToggle" type="button">☰</button>
</div>

<div class="menu-overlay mobile-only" id="menuOverlay"></div>

<div class="menu-modal mobile-only" id="menuModal">
    <a href="salary_settings.php" class="profile-btn">Salary settings</a>
    <a href="salary.php" class="profile-btn">Salary</a>
    <a href="edit_profile.php" class="menu-link profile-link">Profile</a>
    <a href="logout.php" class="menu-link logout-link">Logout</a>
</div>

<div class="app">
    <div class="main">
        <div class="topbar">
            <div class="month-nav">
                <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>&selected_date=<?php echo urlencode($selectedDate); ?>">&#10094;</a>
                <h1><?php echo $monthNames[$month] . " " . $year; ?></h1>
                <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>&selected_date=<?php echo urlencode($selectedDate); ?>">&#10095;</a>
            </div>

            <div class="summary">
                <div class="label">Monthly total</div>
                <strong><?php echo number_format($monthlyHours, 2); ?> h</strong>
            </div>
        </div>

        <div class="calendar-box">
            <div class="weekdays">
                <div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div>
                <div>Fri</div><div>Sat</div><div>Sun</div>
            </div>

            <div class="calendar-grid">
                
                
                <?php
                for ($i = 1; $i < $firstWeekDay; $i++) {
                    echo '<div class="day empty"></div>';
                }

                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $dateString = sprintf("%04d-%02d-%02d", $year, $month, $day);
                    $selectedClass = ($selectedDate === $dateString) ? ' selected' : '';

                    echo '<a class="day selectable-day ' . $selectedClass . '" href="?month=' . $month . '&year=' . $year . '&selected_date=' . $dateString . '">';
                    echo '<input type="checkbox" form="multiDeleteForm" name="selected_days[]" value="' . $dateString . '" class="day-checkbox">';
                    echo '<div class="day-number">' . $day . '</div>';
                    echo '<div class="day-content">';

                    if (isset($calendarShifts[$dateString])) {
                        $count = 0;
                        foreach ($calendarShifts[$dateString] as $shift) {
                            if ($count >= 2) break;

                            echo '<div class="mini-shift" style="background:' . htmlspecialchars($shift["color"]) . ';"></div>';
                            echo '<div class="desktop-time">' .
                                htmlspecialchars(formatTimeForInput($shift["start_time"])) .
                                '-' .
                                htmlspecialchars(formatTimeForInput($shift["end_time"])) .
                                '</div>';
                            $count++;
                        }

                        if (count($calendarShifts[$dateString]) > 2) {
                            echo '<div class="mini-more">+' . (count($calendarShifts[$dateString]) - 2) . '</div>';
                        }
                    }

                    echo '</div>';
                    echo '</a>';
                    
                }
                ?>
                

            </div>
        </div>

        <div class="card panel-day">
            <h3><?php echo htmlspecialchars($selectedDate); ?> — <?php echo number_format($dayTotal, 2); ?> h</h3>

            <?php if (count($selectedShifts) > 0): ?>
                <?php foreach ($selectedShifts as $shift): ?>
                    <div class="shift-card" style="border-left-color: <?php echo htmlspecialchars($shift["color"]); ?>;">
                        <div class="shift-card-top">
                            <div>
                                <h4><?php echo htmlspecialchars($shift["workplace"]); ?></h4>
                                <div class="shift-meta">
                                    <?php echo htmlspecialchars(formatTimeForInput($shift["start_time"])); ?>
                                    -
                                    <?php echo htmlspecialchars(formatTimeForInput($shift["end_time"])); ?>
                                    ·
                                    <?php echo number_format($shift["hours"], 2); ?> h
                                </div>
                            </div>
                        </div>

                        <div class="shift-actions">
                            <a class="action-link" href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>&selected_date=<?php echo urlencode($selectedDate); ?>&edit_id=<?php echo $shift["id"]; ?>#editShift">Edit</a>

                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="action" value="delete_shift">
                                <input type="hidden" name="id" value="<?php echo $shift["id"]; ?>">
                                <input type="hidden" name="selected_date" value="<?php echo htmlspecialchars($selectedDate); ?>">
                                <button class="action-btn delete" type="submit" onclick="return confirm('Delete this shift?')">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="small">No shifts for this day yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="sidebar desktop-only">
        <details class="card">
            <summary>Import Excel</summary>
            <div class="inside">
                <p class="small">Upload file and choose job + color.</p>

                <a href="import_help.php" class="template-btn">Template / Example</a>
                <a href="download_template.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="template-btn" style="margin-top:10px;">
                    Download template
                </a>

                <?php if (empty($workplacesForSelect)): ?>
                    <p class="small" style="color:#b91c1c;">Create a workplace in Salary settings first.</p>
                <?php endif; ?>
                <form method="POST" id="excelImportForm" class="form" style="margin-top: 12px;">
                    <input type="hidden" name="action" value="import_excel">
                    <input type="hidden" name="excel_data" id="excel_data">

                    <label>Choose workplace</label>
                    <select name="workplace_id" id="importWorkplaceSelect" required onchange="syncImportWorkplaceData()">
                        <option value="">Choose workplace</option>
                        <?php foreach ($workplacesForSelect as $wp): ?>
                            <option
                                value="<?php echo $wp["id"]; ?>"
                                data-color="<?php echo htmlspecialchars($wp["color"]); ?>"
                            >
                                <?php echo htmlspecialchars($wp["name"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label>Color</label>
                    <input type="color" name="import_color" value="#3b82f6">

                    <label>Excel file</label>
                    <input type="file" id="excelFile" accept=".xlsx,.xls" required>

                    <button type="submit" class="import-btn" <?php echo empty($workplacesForSelect) ? 'disabled' : ''; ?>>
                        Import Excel
                    </button>
                </form>
            </div>
        </details>

        <details class="card" open id="editShift">
            <summary><?php echo !empty($editShift["id"]) ? "Edit shift" : "Add shift"; ?></summary>
            <div class="inside">
                <form method="POST" class="form">
                    <input type="hidden" name="action" value="save_shift">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editShift["id"]); ?>">

                    <label>Date</label>
                    <input type="date" name="work_date" value="<?php echo htmlspecialchars($editShift["work_date"]); ?>" required>

                    <label>From</label>
                    <input type="time" name="start_time" value="<?php echo htmlspecialchars(formatTimeForInput($editShift["start_time"])); ?>">

                    <label>To</label>
                    <input type="time" name="end_time" value="<?php echo htmlspecialchars(formatTimeForInput($editShift["end_time"])); ?>">

                    <label>Type</label>
                    <select name="entry_type" id="entryType" onchange="toggleEntryTypeFields()">
                        <option value="shift" <?php echo (($editShift["entry_type"] ?? "shift") === "shift") ? "selected" : ""; ?>>Work shift</option>
                        <option value="plan" <?php echo (($editShift["entry_type"] ?? "") === "plan") ? "selected" : ""; ?>>Plan</option>
                    </select>
                    <div id="workShiftFields">
                        <label>Choose workplace</label>
                        <select name="workplace_id" id="workplaceSelect" onchange="syncWorkplaceData()">
                            <option value="">Choose workplace</option>
                            <?php foreach ($workplacesForSelect as $wp): ?>
                                <option
                                    value="<?php echo $wp["id"]; ?>"
                                    data-name="<?php echo htmlspecialchars($wp["name"]); ?>"
                                    data-color="<?php echo htmlspecialchars($wp["color"]); ?>"
                                    <?php echo ((string)($editShift["workplace_id"] ?? "") === (string)$wp["id"]) ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($wp["name"]); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label>Workplace</label>
                        <input type="text" name="workplace" value="<?php echo htmlspecialchars($editShift["workplace"]); ?>" required>
                    </div>
                    
                    <div id="planFields">
                        <label>Comment / plan note</label>
                        <input type="text" name="note" value="<?php echo htmlspecialchars($editShift["note"] ?? ""); ?>" placeholder="Doctor, school, trip, meeting...">
                    </div>

                    <label>Color</label>
                    <input type="color" name="color" value="<?php echo htmlspecialchars($editShift["color"]); ?>">

                    <button type="submit"><?php echo !empty($editShift["id"]) ? "Save changes" : "Add shift"; ?></button>
                </form>
            </div>
        </details>

        <details class="card">
            <summary>Hours by workplace</summary>
            <div class="inside">
                <div class="totals-list">
                    <?php if (!empty($workplaceTotals)): ?>
                        <?php foreach ($workplaceTotals as $place => $hours): ?>
                            <div class="totals-item">
                                <div class="totals-left">
                                    <div class="dot" style="background: <?php echo htmlspecialchars($workplaceColors[$place] ?? '#3b82f6'); ?>;"></div>
                                    <span><?php echo htmlspecialchars($place); ?></span>
                                </div>
                                <strong><?php echo number_format($hours, 2); ?> h</strong>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="small">No saved shifts yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </details>
        <details class="card">
            <summary>Delete shifts</summary>
            <div class="inside">
                <form method="POST" id="multiDeleteForm">
                    <input type="hidden" name="action" value="delete_selected_days">
                    <input type="hidden" name="month" value="<?= $month ?>">
                    <input type="hidden" name="year" value="<?= $year ?>">

                    <button type="submit" class="delete-selected-btn">
                        Delete selected days
                    </button>
                </form>

                <form method="POST" onsubmit="return confirm('Delete ALL shifts for this month? This cannot be undone.');" class="form" style="margin-top: 12px;">
                    <input type="hidden" name="action" value="delete_month_shifts">
                    <input type="hidden" name="month" value="<?php echo $month; ?>">
                    <input type="hidden" name="year" value="<?php echo $year; ?>">
                    <button type="submit" class="delete-big-btn danger">Delete whole month</button>
                </form>
            </div>
    </details>
    </div>
</div>

<script>
const menuToggle = document.getElementById('menuToggle');
const menuModal = document.getElementById('menuModal');
const menuOverlay = document.getElementById('menuOverlay');

if (menuToggle && menuModal && menuOverlay) {
    menuModal.classList.remove('open');
    menuOverlay.classList.remove('open');

    menuToggle.addEventListener('click', function (e) {
        e.stopPropagation();
        menuModal.classList.toggle('open');
        menuOverlay.classList.toggle('open');
    });

    menuOverlay.addEventListener('click', function () {
        menuModal.classList.remove('open');
        menuOverlay.classList.remove('open');
    });
}

const excelImportForm = document.getElementById('excelImportForm');

if (excelImportForm) {
    excelImportForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const fileInput = document.getElementById('excelFile');
        const file = fileInput.files[0];

        if (!file) {
            alert('Choose an Excel file first.');
            return;
        }

        const data = await file.arrayBuffer();
        const workbook = XLSX.read(data, { type: 'array' });
        const sheet = workbook.Sheets[workbook.SheetNames[0]];
        const rows = XLSX.utils.sheet_to_json(sheet, { header: 1, raw: true });

        const imported = [];

        for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            const excelDate = row[0];
            const start = row[1];
            const end = row[2];

            if (!excelDate || start == null || end == null) continue;

            let jsDate;

            if (typeof excelDate === 'number') {
                const parsed = XLSX.SSF.parse_date_code(excelDate);
                jsDate = new Date(parsed.y, parsed.m - 1, parsed.d);
            } else {
                jsDate = new Date(excelDate);
            }

            if (isNaN(jsDate.getTime())) continue;

            const work_date =
                jsDate.getFullYear() + '-' +
                String(jsDate.getMonth() + 1).padStart(2, '0') + '-' +
                String(jsDate.getDate()).padStart(2, '0');

            imported.push({
                work_date: work_date,
                start_decimal: start,
                end_decimal: end
            });
        }

        document.getElementById('excel_data').value = JSON.stringify(imported);
        e.target.submit();
    });
}
</script>
<script>
function syncWorkplaceData() {
    const select = document.getElementById('workplaceSelect');
    if (!select) return;

    const selected = select.options[select.selectedIndex];
    const name = selected.getAttribute('data-name');
    const color = selected.getAttribute('data-color');

    const workplaceInput = document.querySelector('input[name="workplace"]');
    const colorInput = document.querySelector('input[name="color"]');

    if (name && workplaceInput) {
        workplaceInput.value = name;
    }

    if (color && colorInput) {
        colorInput.value = color;
    }
}

function toggleEntryTypeFields() {
    const entryType = document.getElementById('entryType');
    const workShiftFields = document.getElementById('workShiftFields');
    const planFields = document.getElementById('planFields');
    const workplaceSelect = document.getElementById('workplaceSelect');
    const workplaceInput = document.querySelector('input[name="workplace"]');

    if (!entryType) return;

    if (entryType.value === 'plan') {
        if (workShiftFields) workShiftFields.style.display = 'none';
        if (planFields) planFields.style.display = 'flex';
        if (workplaceSelect) workplaceSelect.required = false;
        if (workplaceInput) workplaceInput.required = false;
    } else {
        if (workShiftFields) workShiftFields.style.display = 'flex';
        if (planFields) planFields.style.display = 'none';
        if (workplaceSelect) workplaceSelect.required = true;
        if (workplaceInput) workplaceInput.required = true;
        syncWorkplaceData();
    }
}

window.addEventListener('load', function () {
    toggleEntryTypeFields();
    syncWorkplaceData();
});
</script>

<script>
function syncImportWorkplaceData() {
    const select = document.getElementById('importWorkplaceSelect');
    if (!select) return;

    const selected = select.options[select.selectedIndex];
    const color = selected.getAttribute('data-color');

    const colorInput = document.querySelector('input[name="import_color"]');

    if (color && colorInput) {
        colorInput.value = color;
    }
}

window.addEventListener('load', syncImportWorkplaceData);
</script>
<script>
document.querySelectorAll(".selectable-day").forEach(day => {
    day.addEventListener("click", function (e) {
        e.preventDefault();

        const checkbox = this.querySelector(".day-checkbox");
        if (!checkbox) return;

        checkbox.checked = !checkbox.checked;
        this.classList.toggle("multi-selected", checkbox.checked);
    });

    day.addEventListener("dblclick", function () {
        window.location.href = this.href;
    });
});
</script>
</body>
</html>