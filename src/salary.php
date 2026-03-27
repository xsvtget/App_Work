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

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];

$month = isset($_GET["month"]) ? (int)$_GET["month"] : (int)date("n");
$year = isset($_GET["year"]) ? (int)$_GET["year"] : (int)date("Y");
$workplace_id = isset($_GET["workplace_id"]) ? (int)$_GET["workplace_id"] : 0;

function hoursBetween($start, $end) {
    if (!$start || !$end) return 0;
    $startTs = strtotime($start);
    $endTs = strtotime($end);
    if ($endTs <= $startTs) return 0;
    return round(($endTs - $startTs) / 3600, 2);
}

function eveningHours($start, $end, $eveningStart) {
    if (!$start || !$end || !$eveningStart) return 0;

    $startTs = strtotime($start);
    $endTs = strtotime($end);
    $eveningTs = strtotime($eveningStart);

    if ($endTs <= $startTs) return 0;
    if ($endTs <= $eveningTs) return 0;

    $actualStart = max($startTs, $eveningTs);
    return round(($endTs - $actualStart) / 3600, 2);
}

$workplaces = [];
$stmtWp = $conn->prepare("SELECT * FROM workplaces WHERE user_id = ? ORDER BY name ASC");
$stmtWp->bind_param("i", $user_id);
$stmtWp->execute();
$wpResult = $stmtWp->get_result();

while ($row = $wpResult->fetch_assoc()) {
    $workplaces[] = $row;
}
$stmtWp->close();

$currentWorkplace = null;
foreach ($workplaces as $wp) {
    if ((int)$wp["id"] === $workplace_id) {
        $currentWorkplace = $wp;
        break;
    }
}

$summary = [
    "total_hours" => 0,
    "base_pay" => 0,
    "evening_hours" => 0,
    "evening_pay" => 0,
    "weekend_hours" => 0,
    "weekend_pay" => 0,
    "holiday_hours" => 0,
    "holiday_pay" => 0,
    "gross" => 0,
    "tax" => 0,
    "net" => 0
];

$shifts = [];

if ($currentWorkplace) {
    $stmt = $conn->prepare("
        SELECT ws.*
        FROM work_shifts ws
        WHERE ws.user_id = ?
          AND ws.workplace_id = ?
          AND MONTH(ws.work_date) = ?
          AND YEAR(ws.work_date) = ?
        ORDER BY ws.work_date, ws.start_time
    ");
    $stmt->bind_param("iiii", $user_id, $workplace_id, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $hours = hoursBetween($row["start_time"], $row["end_time"]);
        $weekday = (int)date("N", strtotime($row["work_date"]));
        $isWeekend = ($weekday >= 6);

        $stmtHoliday = $conn->prepare("SELECT id FROM holidays WHERE holiday_date = ? LIMIT 1");
        $stmtHoliday->bind_param("s", $row["work_date"]);
        $stmtHoliday->execute();
        $holidayResult = $stmtHoliday->get_result();
        $isHoliday = $holidayResult->num_rows > 0;
        $stmtHoliday->close();

        $basePay = $hours * (float)$currentWorkplace["hourly_rate"];
        $eveningH = 0;
        $eveningPay = 0;
        $weekendPay = 0;
        $holidayPay = 0;

        if ($isHoliday) {
            $holidayPay = $hours * ((float)$currentWorkplace["hourly_rate"] * ((float)$currentWorkplace["holiday_percent"] / 100));
            $summary["holiday_hours"] += $hours;
        } elseif ($isWeekend) {
            $weekendPay = $hours * (float)$currentWorkplace["weekend_addition"];
            $summary["weekend_hours"] += $hours;
        } else {
            $eveningH = eveningHours($row["start_time"], $row["end_time"], $currentWorkplace["evening_start"]);
            $eveningPay = $eveningH * (float)$currentWorkplace["evening_addition"];
            $summary["evening_hours"] += $eveningH;
        }

        $grossLine = $basePay + $eveningPay + $weekendPay + $holidayPay;

        $row["hours"] = $hours;
        $row["is_weekend"] = $isWeekend;
        $row["is_holiday"] = $isHoliday;
        $row["base_pay"] = $basePay;
        $row["evening_hours"] = $eveningH;
        $row["evening_pay"] = $eveningPay;
        $row["weekend_pay"] = $weekendPay;
        $row["holiday_pay"] = $holidayPay;
        $row["gross"] = $grossLine;

        $shifts[] = $row;

        $summary["total_hours"] += $hours;
        $summary["base_pay"] += $basePay;
        $summary["evening_pay"] += $eveningPay;
        $summary["weekend_pay"] += $weekendPay;
        $summary["holiday_pay"] += $holidayPay;
    }
    $stmt->close();

    $summary["gross"] = $summary["base_pay"] + $summary["evening_pay"] + $summary["weekend_pay"] + $summary["holiday_pay"];
    $summary["tax"] = $summary["gross"] * ((float)$currentWorkplace["tax_percent"] / 100);
    $summary["net"] = $summary["gross"] - $summary["tax"];
}

$monthNames = [
    1 => "January", 2 => "February", 3 => "March", 4 => "April",
    5 => "May", 6 => "June", 7 => "July", 8 => "August",
    9 => "September", 10 => "October", 11 => "November", 12 => "December"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #1f2937;
            padding: 20px;
        }
        .page {
            max-width: 1200px;
            margin: 0 auto;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 16px;
            text-decoration: none;
            color: #14a39d;
            font-weight: 700;
        }
        .card {
            background: #fff;
            border-radius: 22px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
            padding: 22px;
            margin-bottom: 18px;
        }
        h1, h2 {
            margin-top: 0;
            color: #0f274f;
        }
        .filters {
            display: grid;
            grid-template-columns: 1fr 180px 180px auto;
            gap: 12px;
            align-items: end;
        }
        .filters label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .filters select, .filters button {
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #d1d5db;
            font-size: 14px;
        }
        .filters button {
            border: none;
            background: #14b8a6;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }
        .summary-item {
            background: #f8fafc;
            border-radius: 16px;
            padding: 14px;
        }
        .summary-item .label {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 6px;
        }
        .summary-item .value {
            font-size: 24px;
            font-weight: 700;
            color: #0f274f;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }
        th, td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            font-size: 14px;
        }
        th {
            color: #0f274f;
            background: #f8fafc;
        }
        .small {
            color: #64748b;
            line-height: 1.6;
        }
        @media (max-width: 900px) {
            .filters {
                grid-template-columns: 1fr;
            }
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <a href="dashboard.php" class="back-link">← Back to dashboard</a>

    <div class="card">
        <h1>Salary</h1>
        <form method="GET" class="filters">
            <div>
                <label>Workplace</label>
                <select name="workplace_id" required>
                    <option value="">Choose workplace</option>
                    <?php foreach ($workplaces as $wp): ?>
                        <option value="<?php echo $wp["id"]; ?>" <?php echo ($workplace_id === (int)$wp["id"]) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($wp["name"]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Month</label>
                <select name="month">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo ($month === $m) ? 'selected' : ''; ?>>
                            <?php echo $monthNames[$m]; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label>Year</label>
                <select name="year">
                    <?php for ($y = date("Y") + 1; $y >= 2024; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($year === $y) ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <button type="submit">Show salary</button>
            </div>
        </form>
    </div>

    <?php if ($currentWorkplace): ?>
        <div class="card">
            <h2><?php echo htmlspecialchars($currentWorkplace["name"]); ?> — <?php echo $monthNames[$month] . " " . $year; ?></h2>

            <div class="summary-grid">
                <div class="summary-item">
                    <div class="label">Total hours</div>
                    <div class="value"><?php echo number_format($summary["total_hours"], 2); ?> h</div>
                </div>
                <div class="summary-item">
                    <div class="label">Gross</div>
                    <div class="value"><?php echo number_format($summary["gross"], 2); ?></div>
                </div>
                <div class="summary-item">
                    <div class="label">Net</div>
                    <div class="value"><?php echo number_format($summary["net"], 2); ?></div>
                </div>
            </div>

            <table>
                <tr>
                    <th>Date</th>
                    <th>Hours</th>
                    <th>Base</th>
                    <th>Evening</th>
                    <th>Weekend</th>
                    <th>Holiday</th>
                    <th>Gross line</th>
                </tr>
                <?php if (!empty($shifts)): ?>
                    <?php foreach ($shifts as $shift): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($shift["work_date"]); ?></td>
                            <td><?php echo number_format($shift["hours"], 2); ?></td>
                            <td><?php echo number_format($shift["base_pay"], 2); ?></td>
                            <td><?php echo number_format($shift["evening_pay"], 2); ?></td>
                            <td><?php echo number_format($shift["weekend_pay"], 2); ?></td>
                            <td><?php echo number_format($shift["holiday_pay"], 2); ?></td>
                            <td><?php echo number_format($shift["gross"], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">No shifts found for this workplace and month.</td>
                    </tr>
                <?php endif; ?>
            </table>

            <div style="margin-top:16px;" class="small">
                Base pay: <?php echo number_format($summary["base_pay"], 2); ?><br>
                Evening pay: <?php echo number_format($summary["evening_pay"], 2); ?><br>
                Weekend pay: <?php echo number_format($summary["weekend_pay"], 2); ?><br>
                Holiday pay: <?php echo number_format($summary["holiday_pay"], 2); ?><br>
                Tax: <?php echo number_format($summary["tax"], 2); ?><br>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <p class="small">Choose a workplace, month, and year to see salary.</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>