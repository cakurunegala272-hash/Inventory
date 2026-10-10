<?php
session_start();
require_once 'db.php';

// User Login වී නැතිනම් Login පිටුවට යවන්න
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$active_page = 'suppliers';
$user_role   = $_SESSION['role'] ?? 'data_entry';
$message     = '';
$error       = '';

// 1. AUTO GENERATE SUPPLIER CODE (NEW SUPPLIER සඳහා පමණි)
$next_supplier_code = '';
try {
    $stmt_code = $conn->query("SELECT MAX(supplier_id) FROM suppliers");
    $max_id = $stmt_code->fetchColumn();
    $next_id = ($max_id ? $max_id + 1 : 1);
    $next_supplier_code = 'SUP-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);
} catch (PDOException $e) {
    $next_supplier_code = 'SUP-001';
}

// 2. DELETE SUPPLIER (Admin හට පමණක් අවසර ඇත)
if (isset($_GET['delete'])) {
    if ($user_role === 'admin') {
        $delete_id = $_GET['delete'];
        try {
            $stmt = $conn->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
            $stmt->execute([$delete_id]);
            header("Location: suppliers.php?msg=deleted");
            exit();
        } catch (PDOException $e) {
            $error = "සැපයුම්කරු ඉවත් කිරීමට නොහැක: " . $e->getMessage();
        }
    } else {
        $error = "ඔබට සැපයුම්කරුවන් ඉවත් කිරීමට (Delete) බලතල නොමැත!";
    }
}

// 3. ADD / UPDATE SUPPLIER
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_code   = trim($_POST['supplier_code']);
    $supplier_name   = trim($_POST['supplier_name']);
    $contact_person  = trim($_POST['contact_person']);
    $contact_no      = trim($_POST['contact_no']);
    $address         = trim($_POST['address']);
    $supplier_id     = $_POST['supplier_id'] ?? '';

    if (!empty($supplier_name)) {
        try {
            if (!empty($supplier_id)) {
                // Update Supplier
                $stmt = $conn->prepare("UPDATE suppliers SET supplier_name = ?, contact_person = ?, contact_no = ?, address = ? WHERE supplier_id = ?");
                $stmt->execute([$supplier_name, $contact_person, $contact_no, $address, $supplier_id]);
            } else {
                // Insert New Supplier
                $stmt = $conn->prepare("INSERT INTO suppliers (supplier_code, supplier_name, contact_person, contact_no, address) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$supplier_code, $supplier_name, $contact_person, $contact_no, $address]);
            }
            header("Location: suppliers.php?msg=success");
            exit();
        } catch (PDOException $e) {
            $error = "දෝෂයක් සිදුවිය: " . $e->getMessage();
        }
    } else {
        $error = "කරුණාකර සැපයුම්කරුගේ නම ඇතුලත් කරන්න.";
    }
}

// EDIT සඳහා Supplier එකක් Select කර ඇත්නම්
$edit_supplier = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM suppliers WHERE supplier_id = ?");
    $stmt->execute([$edit_id]);
    $edit_supplier = $stmt->fetch(PDO::FETCH_ASSOC);
}

// සියලුම Suppliers ලැයිස්තුව ලබාගැනීම
$suppliers_list = [];
try {
    $suppliers_list = $conn->query("SELECT * FROM suppliers ORDER BY supplier_id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "දත්ත ලබාගැනීමේ දෝෂයක්: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OSIMS - Manage Suppliers</title>
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
                    <?= $edit_supplier ? '✏️ Edit Supplier' : '➕ Add New Supplier'; ?>
                </div>
                <div class="card-body">
                    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
                        <div class="alert alert-success alert-dismissible fade show">දත්ත සාර්ථකව සුරකින ලදී!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>

                    <form method="POST" action="suppliers.php">
                        <input type="hidden" name="supplier_id" value="<?= $edit_supplier['supplier_id'] ?? ''; ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Supplier Code (ස්වයංක්‍රීය අංකය)</label>
                            <input type="text" name="supplier_code" class="form-control bg-light" value="<?= $edit_supplier ? htmlspecialchars($edit_supplier['supplier_code'] ?? 'SUP-' . str_pad($edit_supplier['supplier_id'], 3, '0', STR_PAD_LEFT)) : $next_supplier_code; ?>" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Supplier Name (සැපයුම්කරුගේ නම)</label>
                            <input type="text" name="supplier_name" class="form-control" value="<?= htmlspecialchars($edit_supplier['supplier_name'] ?? ''); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Contact Person (සම්බන්ධ කරගත හැකි පුද්ගලයා)</label>
                            <input type="text" name="contact_person" class="form-control" value="<?= htmlspecialchars($edit_supplier['contact_person'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Contact No (දුරකථන අංකය)</label>
                            <input type="text" name="contact_no" class="form-control" value="<?= htmlspecialchars($edit_supplier['contact_no'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Address (ලිපිනය)</label>
                            <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($edit_supplier['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary fw-bold"><?= $edit_supplier ? 'Update Supplier' : 'Save Supplier'; ?></button>
                            <?php if ($edit_supplier): ?>
                                <a href="suppliers.php" class="btn btn-secondary">Cancel Edit</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Suppliers Table -->
        <div class="col-md-8 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white fw-bold">
                    🚚 Suppliers List (සැපයුම්කරුවන්ගේ ලැයිස්තුව)
                </div>
                <div class="card-body p-0">
                    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
                        <div class="alert alert-warning m-3">සැපයුම්කරු ඉවත් කරන ලදී!</div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Supplier Code</th>
                                    <th>Supplier Name</th>
                                    <th>Contact Person</th>
                                    <th>Contact No</th>
                                    <th>Address</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($suppliers_list)): ?>
                                    <?php foreach ($suppliers_list as $row): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($row['supplier_code'] ?? 'SUP-' . str_pad($row['supplier_id'], 3, '0', STR_PAD_LEFT)); ?></code></td>
                                            <td><strong><?= htmlspecialchars($row['supplier_name']); ?></strong></td>
                                            <td><?= htmlspecialchars($row['contact_person'] ?? '-'); ?></td>
                                            <td><?= htmlspecialchars($row['contact_no'] ?? '-'); ?></td>
                                            <td><?= htmlspecialchars($row['address'] ?? '-'); ?></td>
                                            <td>
                                                <a href="suppliers.php?edit=<?= $row['supplier_id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                                
                                                <?php if ($user_role === 'admin'): ?>
                                                    <a href="suppliers.php?delete=<?= $row['supplier_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('මෙම සැපයුම්කරු ඉවත් කිරීමට තහවුරු කරන්න?');">Delete</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-3">දැනට සැපයුම්කරුවන් කිසිවක් ඇතුලත් කර නොමැත.</td>
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