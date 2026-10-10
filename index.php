<?php
session_start();
require_once 'db.php';

// පරිශීලකයා දැනටමත් Log වී ඇත්නම් Dashboard එකට යවන්න
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        try {
            // Users සහ Roles tables JOIN කර Data ලබාගැනීම
            $stmt = $conn->prepare("SELECT u.user_id, u.username, u.password, u.full_name, r.role_name 
                                    FROM users u 
                                    JOIN roles r ON u.role_id = r.role_id 
                                    WHERE u.username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Password එක තහවුරු කරගැනීම
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['role']      = $user['role_name']; // admin හෝ data_entry

                header("Location: dashboard.php");
                exit();
            } else {
                $error = "ඇතුලත් කළ පරිශීලක නමය හෝ මුරපදය වැරදිය!";
            }
        } catch (PDOException $e) {
            $error = "පද්ධතියේ දෝෂයක් සිදුවිය: " . $e->getMessage();
        }
    } else {
        $error = "කරුණාකර පරිශීලක නමය සහ මුරපදය ඇතුලත් කරන්න.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OSIMS - System Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
        }
        .login-card {
            max-width: 400px;
            margin: 80px auto;
            border-radius: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card shadow-lg border-0 login-card">
        <div class="card-header bg-primary text-white text-center py-3 rounded-top">
            <h4 class="mb-0 fw-bold">🏢 OSIMS Store System</h4>
            <small>කරුණාකර පද්ධතියට ප්‍රවේශ වන්න</small>
        </div>
        <div class="card-body p-4">

            <?php if ($error != ''): ?>
                <div class="alert alert-danger alert-dismissible fade show text-center" role="alert">
                    <?= $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="index.php">
                <div class="mb-3">
                    <label for="username" class="form-label fw-bold">Username (පරිශීලක නමය)</label>
                    <input type="text" name="username" id="username" class="form-control" placeholder="admin" required autofocus>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label fw-bold">Password (මුරපදය)</label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
                </div>

                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-primary btn-lg fw-bold">Login</button>
                </div>
            </form>
        </div>
        <div class="card-footer text-center text-muted py-3">
            <small>Office Stores Information Management System</small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.bundle.min.js"></script>
</body>
</html>