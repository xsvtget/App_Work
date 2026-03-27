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
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["action"]) && $_POST["action"] === "save_workplace") {
        $id = (int)($_POST["id"] ?? 0);
        $name = trim($_POST["name"] ?? "");
        $color = trim($_POST["color"] ?? "#3b82f6");
        $hourly_rate = (float)($_POST["hourly_rate"] ?? 0);
        $tax_percent = (float)($_POST["tax_percent"] ?? 0);
        $overtime_percent = (float)($_POST["overtime_percent"] ?? 40);
        $evening_addition = (float)($_POST["evening_addition"] ?? 0);
        $weekend_addition = (float)($_POST["weekend_addition"] ?? 0);
        $holiday_percent = (float)($_POST["holiday_percent"] ?? 100);
        $evening_start = $_POST["evening_start"] ?? "18:00";

        if ($name !== "") {
            if ($id > 0) {
                $stmt = $conn->prepare("
                    UPDATE workplaces
                    SET name = ?, color = ?, hourly_rate = ?, tax_percent = ?, overtime_percent = ?,
                        evening_addition = ?, weekend_addition = ?, holiday_percent = ?, evening_start = ?
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->bind_param(
                    "ssddddddsii",
                    $name,
                    $color,
                    $hourly_rate,
                    $tax_percent,
                    $overtime_percent,
                    $evening_addition,
                    $weekend_addition,
                    $holiday_percent,
                    $evening_start,
                    $id,
                    $user_id
                );
                $stmt->execute();
                $stmt->close();
                $message = "Workplace updated.";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO workplaces (
                        user_id, name, color, hourly_rate, tax_percent, overtime_percent,
                        evening_addition, weekend_addition, holiday_percent, evening_start
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "issdddddds",
                    $user_id,
                    $name,
                    $color,
                    $hourly_rate,
                    $tax_percent,
                    $overtime_percent,
                    $evening_addition,
                    $weekend_addition,
                    $holiday_percent,
                    $evening_start
                );
                $stmt->execute();
                $stmt->close();
                $message = "Workplace created.";
            }
        }
    }

    if (isset($_POST["action"]) && $_POST["action"] === "delete_workplace") {
        $id = (int)($_POST["id"] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM workplaces WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $stmt->close();
            $message = "Workplace deleted.";
        }
    }
}

$editWorkplace = null;
if (isset($_GET["edit_id"])) {
    $edit_id = (int)$_GET["edit_id"];
    $stmt = $conn->prepare("SELECT * FROM workplaces WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param("ii", $edit_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $editWorkplace = $result->fetch_assoc();
    $stmt->close();
}

if (!$editWorkplace) {
    $editWorkplace = [
        "id" => "",
        "name" => "",
        "color" => "#3b82f6",
        "hourly_rate" => "0.00",
        "tax_percent" => "0.00",
        "overtime_percent" => "40.00",
        "evening_addition" => "0.00",
        "weekend_addition" => "0.00",
        "holiday_percent" => "100.00",
        "evening_start" => "18:00"
    ];
}

$stmt = $conn->prepare("SELECT * FROM workplaces WHERE user_id = ? ORDER BY name ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$workplaces = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary settings</title>
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
            max-width: 1100px;
            margin: 0 auto;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 16px;
            text-decoration: none;
            color: #14a39d;
            font-weight: 700;
        }

        .layout {
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 20px;
        }

        .card {
            background: #ffffff;
            border-radius: 22px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
            padding: 22px;
        }

        h1, h2 {
            margin-top: 0;
            color: #0f274f;
        }

        .message {
            padding: 12px 14px;
            border-radius: 12px;
            background: #ecfeff;
            color: #0f766e;
            margin-bottom: 16px;
            font-weight: 700;
        }

        .workplace-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .workplace-item {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 14px;
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: flex-start;
        }

        .workplace-left {
            min-width: 0;
        }

        .workplace-top {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .color-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .workplace-name {
            font-size: 18px;
            font-weight: 700;
            color: #0f274f;
        }

        .meta {
            color: #64748b;
            font-size: 14px;
            line-height: 1.7;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-link,
        .btn-danger,
        .form button {
            text-decoration: none;
            border: none;
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-link {
            background: #14b8a6;
            color: #fff;
        }

        .btn-danger {
            background: #ef4444;
            color: #fff;
        }

        .form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .form label {
            font-weight: 700;
            font-size: 14px;
            color: #334155;
        }

        .form input {
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #d1d5db;
            font-size: 14px;
        }

        .form button {
            background: #0f766e;
            color: white;
            margin-top: 6px;
        }

        .small {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }

        @media (max-width: 900px) {
            .layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <a href="dashboard.php" class="back-link">← Back to dashboard</a>

        <?php if ($message !== ""): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="layout">
            <div class="card">
                <h1>Your workplaces</h1>
                <p class="small">
                    Here you can create separate salary settings for each workplace:
                    hourly rate, tax, overtime, evening addition, weekend addition, holiday percent, and evening start time.
                </p>

                <div class="workplace-list">
                    <?php if ($workplaces->num_rows > 0): ?>
                        <?php while ($row = $workplaces->fetch_assoc()): ?>
                            <div class="workplace-item">
                                <div class="workplace-left">
                                    <div class="workplace-top">
                                        <div class="color-dot" style="background: <?php echo htmlspecialchars($row["color"]); ?>;"></div>
                                        <div class="workplace-name"><?php echo htmlspecialchars($row["name"]); ?></div>
                                    </div>

                                    <div class="meta">
                                        Timelønn: <?php echo number_format((float)$row["hourly_rate"], 2); ?><br>
                                        Skatt: <?php echo number_format((float)$row["tax_percent"], 2); ?>%<br>
                                        Overtid: <?php echo number_format((float)$row["overtime_percent"], 2); ?>%<br>
                                        Kveldstillegg: <?php echo number_format((float)$row["evening_addition"], 2); ?><br>
                                        Helgetillegg: <?php echo number_format((float)$row["weekend_addition"], 2); ?><br>
                                        Røddager: <?php echo number_format((float)$row["holiday_percent"], 2); ?>%<br>
                                        Evening starts at: <?php echo htmlspecialchars(substr($row["evening_start"], 0, 5)); ?>
                                    </div>
                                </div>

                                <div class="actions">
                                    <a class="btn-link" href="salary_settings.php?edit_id=<?php echo $row["id"]; ?>">Edit</a>

                                    <form method="POST" onsubmit="return confirm('Delete this workplace?');">
                                        <input type="hidden" name="action" value="delete_workplace">
                                        <input type="hidden" name="id" value="<?php echo $row["id"]; ?>">
                                        <button type="submit" class="btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="small">No workplaces yet. Create your first one on the right.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <h2><?php echo !empty($editWorkplace["id"]) ? "Edit workplace" : "Add workplace"; ?></h2>

                <form method="POST" class="form">
                    <input type="hidden" name="action" value="save_workplace">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editWorkplace["id"]); ?>">

                    <label>Workplace name</label>
                    <input type="text" name="name" required value="<?php echo htmlspecialchars($editWorkplace["name"]); ?>">

                    <label>Color</label>
                    <input type="color" name="color" value="<?php echo htmlspecialchars($editWorkplace["color"]); ?>">

                    <label>Timelønn</label>
                    <input type="number" step="0.01" name="hourly_rate" value="<?php echo htmlspecialchars($editWorkplace["hourly_rate"]); ?>">

                    <label>Skatt (%)</label>
                    <input type="number" step="0.01" name="tax_percent" value="<?php echo htmlspecialchars($editWorkplace["tax_percent"]); ?>">

                    <label>Overtid (%)</label>
                    <input type="number" step="0.01" name="overtime_percent" value="<?php echo htmlspecialchars($editWorkplace["overtime_percent"]); ?>">

                    <label>Kveldstillegg</label>
                    <input type="number" step="0.01" name="evening_addition" value="<?php echo htmlspecialchars($editWorkplace["evening_addition"]); ?>">

                    <label>Helgetillegg</label>
                    <input type="number" step="0.01" name="weekend_addition" value="<?php echo htmlspecialchars($editWorkplace["weekend_addition"]); ?>">

                    <label>Røddager (%)</label>
                    <input type="number" step="0.01" name="holiday_percent" value="<?php echo htmlspecialchars($editWorkplace["holiday_percent"]); ?>">

                    <label>Evening starts at</label>
                    <input type="time" name="evening_start" value="<?php echo htmlspecialchars(substr($editWorkplace["evening_start"], 0, 5)); ?>">

                    <button type="submit"><?php echo !empty($editWorkplace["id"]) ? "Save changes" : "Add workplace"; ?></button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>