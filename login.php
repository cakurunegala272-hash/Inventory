<?php
session_start();
require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "කරුණාකර Username සහ Password ඇතුළත් කරන්න!";
    } else {
        try {
            // Username එක පහසුවෙන් පරීක්ෂා කිරීමට Case-insensitive Query එකක් භාවිතය
            $stmt = $conn->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Password verify කිරීම (Plain text password සහ Hashed password දෙකම Check කිරීම)
                $password_matched = false;

                if (password_verify($password, $user['password'])) {
                    $password_matched = true;
                } elseif ($password === $user['password']) { // පැරණි Plain Text Passwords තිබේ නම්
                    $password_matched = true;
                }

                if ($password_matched) {
                    // Database එකේ ඇති නිවැරදි Role එක Session එකට Save කිරීම
                    $_SESSION['user_id']   = $user['user_id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['full_name'] = !empty($user['full_name']) ? $user['full_name'] : $user['username'];
                    $_SESSION['role']      = strtolower(trim($user['role'])); // Database Role (admin / data_entry)

                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "ඇතුළත් කළ මුරපදය (Password) වැරදිය!";
                }
            } else {
                $error = "එවැනි පරිශීලක නාමයක් (Username) පද්ධතියේ නොමැත!";
            }
        } catch (PDOException $e) {
            $error = "ලොග් වීමේ දෝෂයක්: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OSIMS Store System - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            border-radius: 12px;
        }
    </style>
</head>
<body>

<div class="card shadow-lg border-0 login-card">
    <div class="card-body p-4">
        <div class="text-center mb-4">
            <h3 class="fw-bold text-primary">🏢 OSIMS Store System</h3>
            <p class="text-muted small">කරුණාකර පද්ධතියට Log වන්න</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 border-0 text-center text-small">
                <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="mb-3">
                <label class="form-label fw-bold">Username</label>
                <input type="text" name="username" class="form-control" placeholder="e.g. kamal" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-bold py-2 mt-2">Login</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>