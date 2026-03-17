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
    <link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#0f172a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="App Work">
<link rel="apple-touch-icon" href="/logo.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#20bdb7">
<link rel="apple-touch-icon" href="icon-192.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Planner">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Login</h2>

        <?php if (!empty($message)): ?>
            <p><?php echo $message; ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="text" name="username" placeholder="Username" required><br><br>
            <input type="password" name="password" placeholder="Password" required><br><br>
            <button type="submit">Login</button>
        </form>

        <p>No account yet? <a href="register.php">Register here</a></p>
    </div>
    
<!--<script>
  if ("serviceWorker" in navigator) {
    window.addEventListener("load", function () {
      navigator.serviceWorker.register("/sw.js")
        .then(function (registration) {
          console.log("Service Worker registered:", registration.scope);
        })
        .catch(function (error) {
          console.log("Service Worker registration failed:", error);
        });
    });
  }
</script>-->
</body>
</html>