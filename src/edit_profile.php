<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
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

$user_id = (int)$_SESSION["user_id"];
$message = "";
$error = "";

$stmt = $conn->prepare("SELECT username, email, profile_image FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows !== 1) {
    die("User not found.");
}

$user = $result->fetch_assoc();
$stmt->close();

$currentUsername = $user["username"];
$currentEmail = $user["email"];
$currentImage = $user["profile_image"] ?? null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newUsername = trim($_POST["username"] ?? "");
    $newEmail = trim($_POST["email"] ?? "");
    $newPassword = trim($_POST["new_password"] ?? "");
    $confirmPassword = trim($_POST["confirm_password"] ?? "");

    if ($newUsername === "" || $newEmail === "") {
        $error = "Username and email are required.";
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "Enter a valid email.";
    } elseif ($newPassword !== "" && $newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        $checkStmt = $conn->prepare("
            SELECT id FROM users
            WHERE (username = ? OR email = ?) AND id != ?
            LIMIT 1
        ");
        $checkStmt->bind_param("ssi", $newUsername, $newEmail, $user_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult && $checkResult->num_rows > 0) {
            $error = "Username or email is already used by another account.";
        }
        $checkStmt->close();
    }

    $uploadedPath = $currentImage;

    if ($error === "" && isset($_FILES["profile_image"]) && $_FILES["profile_image"]["error"] !== 4) {
        if (!is_dir(__DIR__ . "/uploads")) {
            mkdir(__DIR__ . "/uploads", 0777, true);
        }

        $file = $_FILES["profile_image"];

        if ($file["error"] === 0) {
            $allowed = ["jpg", "jpeg", "png", "webp"];
            $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $error = "Only JPG, JPEG, PNG, WEBP allowed.";
            } else {
                $newName = "profile_" . $user_id . "_" . time() . "." . $ext;
                $relativePath = "uploads/" . $newName;
                $fullPath = __DIR__ . "/" . $relativePath;

                if (move_uploaded_file($file["tmp_name"], $fullPath)) {
                    $uploadedPath = $relativePath;
                } else {
                    $error = "Failed to upload photo.";
                }
            }
        } else {
            $error = "Upload error.";
        }
    }

    if ($error === "") {
        if ($newPassword !== "") {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            $updateStmt = $conn->prepare("
                UPDATE users
                SET username = ?, email = ?, password = ?, profile_image = ?
                WHERE id = ?
            ");
            $updateStmt->bind_param("ssssi", $newUsername, $newEmail, $hashedPassword, $uploadedPath, $user_id);
        } else {
            $updateStmt = $conn->prepare("
                UPDATE users
                SET username = ?, email = ?, profile_image = ?
                WHERE id = ?
            ");
            $updateStmt->bind_param("sssi", $newUsername, $newEmail, $uploadedPath, $user_id);
        }

        if ($updateStmt->execute()) {
            $_SESSION["username"] = $newUsername;
            $message = "Profile updated successfully.";

            $currentUsername = $newUsername;
            $currentEmail = $newEmail;
            $currentImage = $uploadedPath;
        } else {
            $error = "Failed to update profile.";
        }

        $updateStmt->close();
    }
}

$conn->close();
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit profile</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #eef3f7;
        }

        .profile-page {
            max-width: 620px;
            margin: 30px auto;
            background: #fff;
            padding: 24px;
            border-radius: 28px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.08);
        }

        .profile-back {
            display: inline-block;
            margin-bottom: 16px;
            text-decoration: none;
            color: #18b7b0;
            font-weight: 700;
        }

        .profile-page h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 24px;
        }

        .profile-preview {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            object-fit: cover;
            display: block;
            margin: 0 auto 18px;
            background: #e8eef4;
        }

        .fallback-preview {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            font-weight: 800;
            color: #0d8a8a;
        }

        .message-success,
        .message-error {
            padding: 12px 14px;
            border-radius: 14px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .message-success {
            background: #e6fffb;
            color: #0f766e;
        }

        .message-error {
            background: #fee2e2;
            color: #b91c1c;
        }

        .profile-form {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .profile-form label {
            font-weight: 700;
            color: #243b53;
            margin-bottom: 6px;
            display: block;
        }

        .profile-form input {
            width: 100%;
            padding: 14px;
            border-radius: 14px;
            border: 1px solid #d3dce6;
            font-size: 15px;
            box-sizing: border-box;
        }

        .profile-form button {
            width: 100%;
            border: none;
            border-radius: 16px;
            padding: 14px;
            background: #20bdb7;
            color: white;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
        }

        .profile-form button:hover {
            background: #17aaa4;
        }

        .section-title {
            margin-top: 10px;
            margin-bottom: 2px;
            font-size: 18px;
            font-weight: 800;
            color: #0f274f;
        }

        .section-note {
            margin-top: 0;
            margin-bottom: 6px;
            color: #64748b;
            font-size: 14px;
        }

        @media (max-width: 700px) {
            .profile-page {
                margin: 14px;
                padding: 18px;
                border-radius: 22px;
            }

            .profile-preview {
                width: 90px;
                height: 90px;
            }

            .fallback-preview {
                font-size: 34px;
            }

            .profile-form input,
            .profile-form button {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="profile-page">
        <a href="dashboard.php" class="profile-back">← Back to dashboard</a>
        <h2>Edit profile</h2>

        <?php if ($message !== ""): ?>
            <div class="message-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="message-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($currentImage) && file_exists(__DIR__ . '/' . $currentImage)): ?>
            <img src="<?php echo htmlspecialchars($currentImage); ?>" alt="Profile" class="profile-preview">
        <?php else: ?>
            <div class="profile-preview fallback-preview">
                <?php echo strtoupper(substr($currentUsername, 0, 1)); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="profile-form">
            <div>
                <label for="profile_image">Profile photo</label>
                <input type="file" name="profile_image" id="profile_image" accept=".jpg,.jpeg,.png,.webp">
            </div>

            <div>
                <label for="username">Username</label>
                <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($currentUsername); ?>" required>
            </div>

            <div>
                <label for="email">Email</label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($currentEmail); ?>" required>
            </div>

            <div class="section-title">Change password</div>
            <p class="section-note">Leave empty if you do not want to change it.</p>

            <div>
                <label for="new_password">New password</label>
                <input type="password" name="new_password" id="new_password" placeholder="New password">
            </div>

            <div>
                <label for="confirm_password">Confirm new password</label>
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password">
            </div>

            <button type="submit">Save profile</button>
        </form>
    </div>
    <script>
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
</script>
</body>
</html>