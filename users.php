<?php
session_start();
require_once 'db.php';

// 1. Session Authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// 2. Database එකෙන්ම Logged User ගේ Role එක Fresh විදියට Fetch කිරීම (Session Error වැළැක්වීමට)
$logged_user_id = $_SESSION['user_id'];
$user_stmt = $conn->prepare("SELECT user_id, role, full_name, username FROM users WHERE user_id = ?");
$user_stmt->execute([$logged_user_id]);
$current_logged_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_logged_user) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Session Data Update කිරීම
$current_user_role = strtolower(trim($current_logged_user['role']));
$_SESSION['role']   = $current_user_role;

// 3. Strict Security Check: Data Entry User කෙනෙකු නම් Dashboard එකට හරවා යැවීම
if ($current_user_role !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$active_page = 'users';
$message     = '';
$error       = '';

// Add New User Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username  = trim($_POST['username'] ?? '');
    $password  = trim($_POST['password'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role      = strtolower(trim($_POST['role'] ?? 'data_entry'));

    if (empty($username) || empty($password)) {
        $error = "Username සහ Password අනිවාර්යයෙන්ම ඇතුළත් කරන්න!";
    } else {
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE LOWER(username) = LOWER(?)");
            $stmt->execute([$username]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = "මෙම Username එක මීට පෙර භාවිතා කර ඇත!";
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $insert = $conn->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
                $insert->execute([$username, $hashed_password, $full_name, $role]);
                $message = "නව පරිශීලකයා සාර්ථකව එකතු කරන ලදී!";
            }
        } catch (PDOException $e) {
            $error = "දෝෂයක් සිදුවිය: " . $e->getMessage();
        }
    }
}

// Delete User Logic
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    if ($delete_id == $logged_user_id) {
        $error = "ඔබ දැනට Log වී සිටින ගිණුම මකා දැමිය නොහැක!";
    } else {
        try {
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$delete_id]);
            header("Location: users.php?msg=deleted");
            exit();
        } catch (PDOException $e) {
            $error = "මකා දැමීමේදී දෝෂයක් සිදුවිය: " . $e->getMessage();
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = "පරිශීලක ගිණුම සාර්ථකව මකා දමන ලදී!";
}

// Fetch All Users
$users = [];
try {
    $users = $conn->query("SELECT user_id, username, full_name, role FROM users ORDER BY user_id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "දත්ත ලබා ගැනීමේ දෝෂයක්: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OSIMS - User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container mt-4 mb-5">
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Add User Form -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white fw-bold">
                    👤➕ Add New User
                </div>
                <div class="card-body">
                    <form action="users.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Full Name (සම්පූර්ණ නම)</label>
                            <input type="text" name="full_name" class="form-control" placeholder="e.g. Nimal Silva" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Username (පරිශීලක නමය)</label>
                            <input type="text" name="username" class="form-control" placeholder="e.g. nimal" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Password (මුරපදය)</label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">User Role (බලතල)</label>
                            <select name="role" class="form-select">
                                <option value="data_entry">Data Entry Operator</option>
                                <option value="admin">System Admin</option>
                            </select>
                        </div>
                        <button type="submit" name="add_user" class="btn btn-primary w-100 fw-bold">Save User Account</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Registered Users Table -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
                    <span>👥 Registered System Users</span>
                    <span class="badge bg-light text-dark">Total: <?= count($users); ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-secondary">
                                <tr>
                                    <th>#ID</th>
                                    <th>Full Name</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($users)): ?>
                                    <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td><?= $u['user_id']; ?></td>
                                            <td><strong><?= htmlspecialchars($u['full_name']); ?></strong></td>
                                            <td><code><?= htmlspecialchars($u['username']); ?></code></td>
                                            <td>
                                                <span class="badge <?= strtolower($u['role']) === 'admin' ? 'bg-danger' : 'bg-info text-dark'; ?>">
                                                    <?= strtoupper($u['role']); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <!-- Logged-in User ID එක සසඳා නිවැරදි Badge/Button පෙන්වීම -->
                                                <?php if ((int)$u['user_id'] === (int)$logged_user_id): ?>
                                                    <span class="badge bg-secondary">Current User</span>
                                                <?php else: ?>
                                                    <a href="users.php?delete=<?= $u['user_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('මෙම පරිශීලක ගිණුම මකා දැමීමට අවශ්‍යද?');">Delete Account</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">පරිශීලකයින් කිසිවෙකු නැත.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>