<?php
session_start();
require_once 'db.php';

// User Login වී නැතිනම් Login පිටුවට යවන්න
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$active_page = 'sections';
$user_role   = $_SESSION['role'] ?? 'data_entry';
$message     = '';
$error       = '';

// 1. CREATE TABLE IF NOT EXISTS (Table එක නොමැති නම් ස්වයංක්‍රීයව සාදයි)
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS departments (
        department_id INTEGER PRIMARY KEY AUTOINCREMENT,
        department_code TEXT UNIQUE,
        department_name TEXT NOT NULL,
        location TEXT
    )");
} catch (PDOException $e) {
    $error = "Table Creation Error: " . $e->getMessage();
}

// 2. AUTO GENERATE DEPARTMENT CODE (DEP-001, DEP-002...)
$next_dep_code = '';
try {
    $stmt_code = $conn->query("SELECT MAX(department_id) FROM departments");
    $max_id = $stmt_code->fetchColumn();
    $next_id = ($max_id ? $max_id + 1 : 1);
    $next_dep_code = 'DEP-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);
} catch (PDOException $e) {
    $next_dep_code = 'DEP-001';
}

// 3. DELETE DEPARTMENT (Admin හට පමණක් අවසර ඇත)
if (isset($_GET['delete'])) {
    if ($user_role === 'admin') {
        $delete_id = $_GET['delete'];
        try {
            $stmt = $conn->prepare("DELETE FROM departments WHERE department_id = ?");
            $stmt->execute([$delete_id]);
            header("Location: sections.php?msg=deleted");
            exit();
        } catch (PDOException $e) {
            $error = "අංශය ඉවත් කිරීමට නොහැක: " . $e->getMessage();
        }
    } else {
        $error = "ඔබට අංශ ඉවත් කිරීමට (Delete) බලතල නොමැත!";
    }
}

// 4. ADD / UPDATE DEPARTMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $department_code = trim($_POST['department_code']);
    $department_name = trim($_POST['department_name']);
    $location        = trim($_POST['location']);
    $department_id   = $_POST['department_id'] ?? '';

    if (!empty($department_name)) {
        try {
            if (!empty($department_id)) {
                // Update
                $stmt = $conn->prepare("UPDATE departments SET department_code = ?, department_name = ?, location = ? WHERE department_id = ?");
                $stmt->execute([$department_code, $department_name, $location, $department_id]);
            } else {
                // Insert New
                $stmt = $conn->prepare("INSERT INTO departments (department_code, department_name, location) VALUES (?, ?, ?)");
                $stmt->execute([$department_code, $department_name, $location]);
            }
            header("Location: sections.php?msg=success");
            exit();
        } catch (PDOException $e) {
            $error = "දෝෂයක් සිදුවිය: " . $e->getMessage();
        }
    } else {
        $error = "කරුණාකර අංශයේ නම ඇතුලත් කරන්න.";
    }
}

// EDIT සඳහා Select කර ඇත්නම්
$edit_dep = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM departments WHERE department_id = ?");
    $stmt->execute([$edit_id]);
    $edit_dep = $stmt->fetch(PDO::FETCH_ASSOC);
}

// සියලුම Departments ලැයිස්තුව ලබාගැනීම
$departments_list = [];
try {
    $departments_list = $conn->query("SELECT * FROM departments ORDER BY department_id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments_list = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OSIMS - Manage Sections/Branches</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container-fluid px-4 mt-4">
    <div class="row">
        <!-- Add / Edit Form -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white fw-bold">
                    <?= $edit_dep ? '✏️ Edit Section / Branch' : '➕ Add New Section / Branch'; ?>
                </div>
                <div class="card-body">
                    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
                        <div class="alert alert-success alert-dismissible fade show">දත්ත සාර්ථකව සුරකින ලදී!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show"><?= $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>

                    <form method="POST" action="sections.php">
                        <input type="hidden" name="department_id" value="<?= $edit_dep['department_id'] ?? ''; ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Section Code (ස්වයංක්‍රීය අංකය)</label>
                            <input type="text" name="department_code" class="form-control bg-light" value="<?= htmlspecialchars($edit_dep['department_code'] ?? $next_dep_code); ?>" readonly required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Section / Branch Name (අංශයේ / ශාඛාවේ නම)</label>
                            <input type="text" name="department_name" class="form-control" placeholder="Accounts, HR, IT..." value="<?= htmlspecialchars($edit_dep['department_name'] ?? ''); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Location / Office (ස්ථානය)</label>
                            <input type="text" name="location" class="form-control" placeholder="Main Office, Branch 01..." value="<?= htmlspecialchars($edit_dep['location'] ?? ''); ?>">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary fw-bold"><?= $edit_dep ? 'Update Section' : 'Save Section'; ?></button>
                            <?php if ($edit_dep): ?>
                                <a href="sections.php" class="btn btn-secondary">Cancel Edit</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Table List -->
        <div class="col-md-8 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white fw-bold">
                    🏢 Sections / Branches List (අංශ හා ශාඛා ලැයිස්තුව)
                </div>
                <div class="card-body p-0">
                    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
                        <div class="alert alert-warning m-3">අංශය ඉවත් කරන ලදී!</div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Code</th>
                                    <th>Section / Branch Name</th>
                                    <th>Location</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($departments_list)): ?>
                                    <?php foreach ($departments_list as $row): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($row['department_code']); ?></code></td>
                                            <td><strong><?= htmlspecialchars($row['department_name']); ?></strong></td>
                                            <td><?= htmlspecialchars($row['location'] ?? '-'); ?></td>
                                            <td>
                                                <a href="sections.php?edit=<?= $row['department_id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                                
                                                <?php if ($user_role === 'admin'): ?>
                                                    <a href="sections.php?delete=<?= $row['department_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('මෙම අංශය ඉවත් කිරීමට තහවුරු කරන්න?');">Delete</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">දැනට අංශ/ශාඛා කිසිවක් ඇතුලත් කර නොමැත.</td>
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