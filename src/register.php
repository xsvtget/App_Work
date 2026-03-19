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

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $pass = trim($_POST["password"]);

    if (!empty($user) && !empty($email) && !empty($pass)) {
        $hashedPassword = password_hash($pass, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $user, $email, $hashedPassword);

        if ($stmt->execute()) {
            header("Location: login.php");
            exit();
        } else {
            $message = "User or email already exists.";
        }

        $stmt->close();
    } else {
        $message = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | App Work</title>

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

        .register-card {
            width: 100%;
            max-width: 440px;
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

        .register-btn {
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
            .register-card {
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
            .register-btn {
                height: 50px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="register-card">
        <a href="welcome.php" class="top-link">← Back</a>

        <div class="logo-badge">AW</div>

        <h2>Create account</h2>
        <p class="subtitle">Register to start using your work planner.</p>

        <?php if (!empty($message)): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Choose a username" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Create a password" required>
            </div>

            <button type="submit" class="register-btn">Register</button>
        </form>

        <div class="bottom-text">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</body>
</html>