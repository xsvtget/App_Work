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

        header("Location: dashboard.php");
        exit();
    }

    $stmtAuto->close();
}

$_SESSION["from_welcome"] = true;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome | App Work</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0f172a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="App Work">
    <link rel="apple-touch-icon" href="/logo.png">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(32, 189, 183, 0.18), transparent 35%),
                radial-gradient(circle at bottom right, rgba(15, 118, 110, 0.12), transparent 30%),
                linear-gradient(135deg, #f3f6fb 0%, #eef7f7 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
            color: #0f172a;
        }

        .welcome-card {
            width: 100%;
            max-width: 460px;
            background: linear-gradient(160deg, #0f274f 0%, #123b63 40%, #18b7b0 140%);
            border-radius: 28px;
            padding: 28px 24px;
            color: white;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.14);
            position: relative;
            overflow: hidden;
        }

        .welcome-card::before,
        .welcome-card::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
        }

        .welcome-card::before {
            width: 180px;
            height: 180px;
            top: -40px;
            right: -60px;
        }

        .welcome-card::after {
            width: 140px;
            height: 140px;
            bottom: 20px;
            left: -40px;
        }

        .content {
            position: relative;
            z-index: 1;
        }

        .logo-badge {
            width: 62px;
            height: 62px;
            border-radius: 18px;
            background: rgba(255,255,255,0.14);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 22px;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.10);
        }

        h1 {
            margin: 0 0 14px;
            font-size: 36px;
            line-height: 1.1;
            font-weight: 800;
        }

        .subtitle {
            margin: 0 0 24px;
            font-size: 15px;
            line-height: 1.7;
            color: rgba(255,255,255,0.88);
        }

        .feature-list {
            display: grid;
            gap: 12px;
            margin-bottom: 26px;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 14px;
            border-radius: 16px;
            background: rgba(255,255,255,0.08);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.08);
        }

        .feature-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #7ef3ea;
            flex-shrink: 0;
        }

        .feature span {
            font-size: 15px;
            line-height: 1.35;
        }

        .actions {
            display: grid;
            gap: 12px;
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 54px;
            border-radius: 16px;
            text-decoration: none;
            font-weight: 800;
            font-size: 16px;
            transition: 0.2s ease;
        }

        .btn-login {
            background: #ffffff;
            color: #0f274f;
        }

        .btn-register {
            background: rgba(255,255,255,0.12);
            color: #ffffff;
            border: 1px solid rgba(255,255,255,0.18);
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        @media (max-width: 600px) {
            .welcome-card {
                border-radius: 22px;
                padding: 24px 18px;
            }

            .logo-badge {
                width: 54px;
                height: 54px;
                font-size: 22px;
                border-radius: 16px;
            }

            h1 {
                font-size: 28px;
            }

            .subtitle,
            .feature span {
                font-size: 14px;
            }

            .btn {
                height: 50px;
                border-radius: 14px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="welcome-card">
        <div class="content">
            <div class="logo-badge">AW</div>

            <h1>Plan your work smarter and faster.</h1>
            <p class="subtitle">
                Keep your shifts, working hours, and schedule in one clean place.
                Fast, simple, and perfect for mobile.
            </p>

            <div class="feature-list">
                <div class="feature">
                    <div class="feature-dot"></div>
                    <span>Track shifts in a clean calendar view</span>
                </div>
                <div class="feature">
                    <div class="feature-dot"></div>
                    <span>Manage hours and workplaces easily</span>
                </div>
                <div class="feature">
                    <div class="feature-dot"></div>
                    <span>Designed for both phone and computer</span>
                </div>
            </div>

            <div class="actions">
                <a href="login.php" class="btn btn-login">Login</a>
                <a href="register.php" class="btn btn-register">Register</a>
            </div>
        </div>
    </div>
</body>
</html>