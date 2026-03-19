<?php
session_start();

$host = "db";
$dbname = getenv("MARIADB_DATABASE");
$username = getenv("MARIADB_USER");
$password = getenv("MARIADB_APP_PASSWORD");

$conn = new mysqli($host, $username, $password, $dbname);

if (isset($_SESSION["user_id"])) {
    $user_id = (int)$_SESSION["user_id"];
    $stmt = $conn->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

setcookie("remember_token", "", time() - 3600, "/");

session_unset();
session_destroy();

header("Location: welcome.php");
exit();
?>