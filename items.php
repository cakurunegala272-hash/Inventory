<?php
session_start();
require_once 'db.php';

// User Login වී නැතිනම් Login පිටුවට යවන්න
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Current User Role එක ලබා ගැනීම (Default: data_entry)
$user_role = strtolower($_SESSION['role'] ?? 'data_entry');

$active_page = 'items';
$message     = '';
$error       = '';

// Column පරික්ෂා කිරීමේ Function එක
function columnExists($conn, $table, $column) {
    try {
        $stmt = $conn->query("PRAGMA table_info($table)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            if ($col['name'] === $column) {
                return true;
            }
        }
    } catch (PDOException $e) {
        return false;
    }
    return false;
}

// Automatic Database Alterations
if (!columnExists($conn, 'items', 'opening_stock')) {
    $conn->exec("ALTER TABLE items ADD COLUMN opening_stock INTEGER DEFAULT 0");
}
if (!columnExists($conn, 'items', 'unit_price')) {
    $conn->exec("ALTER TABLE items ADD COLUMN unit_price REAL DEFAULT 0");
}
if (!columnExists($conn, 'items', 'reorder_level')) {
    $conn->exec("ALTER TABLE items ADD COLUMN reorder_level INTEGER DEFAULT 5");
}

// Item Code Auto-generator
function generateItemCode($conn) {
    try {
        $stmt = $conn->query("SELECT item_id FROM items ORDER BY item_id DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $next_id = $row ? ($row['item_id'] + 1) : 1;
        return 'ITM-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        return 'ITM-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    }
}

// SAVE & UPDATE SECURITY CHECK
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id       = $_POST['item_id'] ?? '';
    $item_code     = trim($_POST['item_code']);
    $item_name     = trim($_POST['item_name']);
    $opening_stock = (int)($_POST['opening_stock'] ?? 0);
    $unit_price    = (float)($_POST['unit_price'] ?? 0);
    $reorder_level = (int)($_POST['reorder_level'] ?? 5);

    if (empty($item_code) || empty($item_name)) {
        $error = "Item Code සහ Item Name අනිවාර්යයෙන්ම ඇතුළත් කරන්න.";
    } else {
        try {
            if (!empty($item_id)) {
                // UPDATE: Data Entry පරිශීලකයින්ට Block කිරීම (Admin ට පමණි)
                if ($user_role !== 'admin') {
                    $error = "🚫 දත්ත වෙනස් කිරීමට (Edit/Update) ඔබට අවසර නැත! (Admin පමණි)";
                } else {
                    $stmt = $conn->prepare("UPDATE items SET item_code = ?, item_name = ?, opening_stock = ?, unit_price = ?, reorder_level = ? WHERE item_id = ?");
                    $stmt->execute([$item_code, $item_name, $opening_stock, $unit_price, $reorder_level, $item_id]);
                    $message = "Item එක සාර්ථකව යාවත්කාලීන (Update) කරන ලදී!";
                }
            } else {
                // INSERT: සියලුම User ලාට නව දත්ත ඇතුළත් කළ හැක
                $checkStmt = $conn->prepare("SELECT COUNT(*) FROM items WHERE item_code = ?");
                $checkStmt->execute([$item_code]);
                if ($checkStmt->fetchColumn() > 0) {
                    $error = "මෙම Item Code එක මීට පෙර භාවිතා කර ඇත!";
                } else {
                    $stmt = $conn->prepare("INSERT INTO items (item_code, item_name, opening_stock, unit_price, reorder_level) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$item_code, $item_name, $opening_stock, $unit_price, $reorder_level]);
                    $message = "නව Item එක සාර්ථකව එකතු කරන ලදී!";
                }
            }
        } catch (PDOException $e) {
            $error = "දත්ත සුරැකීමේ දෝෂයක්: " . $e->getMessage();
        }
    }
}

// DELETE SECURITY CHECK (Data Entry පරිශීලකයින්ට Block කිරීම)
if (isset($_GET['delete'])) {
    if ($user_role !== 'admin') {
        $error = "🚫 දත්ත මකා දැමීමට (Delete) ඔබට අවසර නැත! (Admin පමණි)";
    } else {
        $delete_id = (int)$_GET['delete'];
        try {
            $stmt = $conn->prepare("DELETE FROM items WHERE item_id = ?");
            $stmt->execute([$delete_id]);
            header("Location: items.php?msg=deleted");
            exit();
        } catch (PDOException $e) {
            $error = "මකා දැමීමේදී දෝෂයක් ඇති විය: " . $e->getMessage();
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = "Item එක සාර්ථකව මකා දමන ලදී!";
}

// FETCH ITEMS LIST
$items = [];
try {
    $items = $conn->query("SELECT * FROM items ORDER BY item_id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "දත්ත ලබා ගැනීමේ දෝෂයක්: " . $e->getMessage();
}

// EDIT DATA FETCH SECURITY
$edit_item = null;
if (isset($_GET['edit'])) {
    if ($user_role !== 'admin') {
        $error = "🚫 Item එකක් සංස්කරණය කිරීමට ඔබට අවසර නොමැත!";
    } else {
        $edit_id = (int)$_GET['edit'];
        $stmt = $conn->prepare("SELECT * FROM items WHERE item_id = ?");
        $stmt->execute([$edit_id]);
        $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$default_item_code = $edit_item ? $edit_item['item_code'] : generateItemCode($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OSIMS - Items Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container mt-4 mb-5">
    
    <!-- Alerts -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row">
        <!-- Add Item Form -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white fw-bold">
                    <?= ($edit_item && $user_role === 'admin') ? '✏️ Edit Item' : '➕ Add New Item'; ?>
                </div>
                <div class="card-body">
                    <form action="items.php" method="POST">
                        <input type="hidden" name="item_id" value="<?= ($user_role === 'admin') ? ($edit_item['item_id'] ?? '') : ''; ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Item Code (කේතය)</label>
                            <input type="text" name="item_code" class="form-control bg-light" required 
                                   value="<?= htmlspecialchars($default_item_code); ?>" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Item Name (නම)</label>
                            <input type="text" name="item_name" class="form-control" required 
                                   value="<?= htmlspecialchars($edit_item['item_name'] ?? ''); ?>" placeholder="e.g. Pen / A4 Paper">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Opening Stock</label>
                            <input type="number" name="opening_stock" class="form-control" min="0" 
                                   value="<?= htmlspecialchars($edit_item['opening_stock'] ?? '0'); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Unit Price (Rs.)</label>
                            <input type="number" step="0.01" name="unit_price" class="form-control" min="0" 
                                   value="<?= htmlspecialchars($edit_item['unit_price'] ?? '0.00'); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Reorder Level</label>
                            <input type="number" name="reorder_level" class="form-control" min="1" 
                                   value="<?= htmlspecialchars($edit_item['reorder_level'] ?? '5'); ?>">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary fw-bold">
                                <?= ($edit_item && $user_role === 'admin') ? 'Update Item' : 'Save Item'; ?>
                            </button>
                            <?php if ($edit_item && $user_role === 'admin'): ?>
                                <a href="items.php" class="btn btn-outline-secondary">Cancel Edit</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Table View -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
                    <span>📦 Master Items List</span>
                    <span class="badge bg-light text-dark">Total: <?= count($items); ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-secondary">
                                <tr>
                                    <th>Code</th>
                                    <th>Item Name</th>
                                    <th class="text-center">Opening Stock</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-center">Reorder Level</th>
                                    <!-- Action Column එක පෙන්වන්නේ Admin හට පමණි -->
                                    <?php if ($user_role === 'admin'): ?>
                                        <th class="text-center">Action</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($items)): ?>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($item['item_code']); ?></code></td>
                                            <td><strong><?= htmlspecialchars($item['item_name']); ?></strong></td>
                                            <td class="text-center fw-bold text-primary"><?= (int)($item['opening_stock'] ?? 0); ?></td>
                                            <td class="text-end">Rs. <?= number_format((float)($item['unit_price'] ?? 0), 2); ?></td>
                                            <td class="text-center"><span class="badge bg-warning text-dark"><?= (int)($item['reorder_level'] ?? 5); ?></span></td>
                                            
                                            <!-- Edit සහ Delete Buttons සඟවා තිබීම -->
                                            <?php if ($user_role === 'admin'): ?>
                                                <td class="text-center">
                                                    <a href="items.php?edit=<?= $item['item_id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                    <a href="items.php?delete=<?= $item['item_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('ඔබට මෙම Item එක මකා දැමීමට අවශ්‍යද?');">Delete</a>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?= $user_role === 'admin' ? '6' : '5'; ?>" class="text-center py-4 text-muted">තවමත් කිසිදු Item එකක් ඇතුළත් කර නොමැත.</td>
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