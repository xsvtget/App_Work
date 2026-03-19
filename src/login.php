<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
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

if (!isset($_SESSION["from_welcome"])) {
    header("Location: welcome.php");
    exit();
}

$host = "db";
$dbname = getenv("MARIADB_DATABASE");
$username = getenv("MARIADB_USER");
$password = getenv("MARIADB_APP_PASSWORD");

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = trim($_POST["username"]);
    $pass = trim($_POST["password"]);

    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        if (password_verify($pass, $row["password"])) {
            $_SESSION["user_id"] = $row["id"];
            $_SESSION["username"] = $row["username"];
            unset($_SESSION["from_welcome"]);

            $rememberToken = bin2hex(random_bytes(32));

            $stmtRemember = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            $stmtRemember->bind_param("si", $rememberToken, $row["id"]);
            $stmtRemember->execute();
            $stmtRemember->close();

            setcookie(
                "remember_token",
                $rememberToken,
                time() + (86400 * 30),
                "/",
                "",
                false,
                true
            );

            header("Location: dashboard.php");
            exit();
        } else {
            $message = "Wrong password.";
        }
    } else {
        $message = "User not found.";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | App Work</title>

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

        .login-card {
            width: 100%;
            max-width: 420px;
            background: rgba(255,255,255,0.90);
            backdrop-filter: blur(12px);
            border-radius: 26px;
            padding: 30px 22px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.10);
            border: 1px solid rgba(255,255,255,0.7);
        }

        .top-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: #0f766e;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 18px;
        }

        .logo-badge {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: linear-gradient(160deg, #0f274f 0%, #18b7b0 140%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 18px;
        }

        h2 {
            margin: 0;
            font-size: 32px;
            color: #0f274f;
        }

        .subtitle {
            margin: 10px 0 24px;
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
        }

        .message {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 14px;
            background: #fff1f2;
            color: #be123c;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid #fecdd3;
        }

        .form-group {
            margin-bottom: 14px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 700;
            color: #334155;
        }

        input {
            width: 100%;
            height: 54px;
            border-radius: 15px;
            border: 1px solid #dbe4ee;
            background: #ffffff;
            padding: 0 16px;
            font-size: 15px;
            outline: none;
            transition: 0.2s ease;
        }

        input:focus {
            border-color: #18b7b0;
            box-shadow: 0 0 0 4px rgba(24, 183, 176, 0.12);
        }

        .login-btn {
            width: 100%;
            height: 54px;
            margin-top: 8px;
            border: none;
            border-radius: 15px;
            background: linear-gradient(135deg, #18b7b0 0%, #0f8f88 100%);
            color: #ffffff;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 12px 24px rgba(24, 183, 176, 0.24);
        }

        .bottom-text {
            margin-top: 18px;
            text-align: center;
            font-size: 14px;
            color: #64748b;
        }

        .bottom-text a {
            color: #0f766e;
            text-decoration: none;
            font-weight: 700;
        }

        @media (max-width: 600px) {
            .login-card {
                padding: 24px 18px;
                border-radius: 22px;
            }

            .logo-badge {
                width: 50px;
                height: 50px;
                font-size: 21px;
                border-radius: 14px;
            }

            h2 {
                font-size: 28px;
            }

            input,
            .login-btn {
                height: 50px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <a href="welcome.php" class="top-link">← Back</a>

        <div class="logo-badge">AW</div>

        <h2>Login</h2>
        <p class="subtitle">Sign in to continue to your work planner.</p>

        <?php if (!empty($message)): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter your username" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="login-btn">Login</button>
        </form>

        <div class="bottom-text">
            No account yet? <a href="register.php">Register here</a>
        </div>
    </div>
</body>
</html>