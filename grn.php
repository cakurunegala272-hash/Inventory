<?php
session_start();
require_once 'db.php';

// User Login වී නැතිනම් Login පිටුවට යවන්න
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$active_page = 'grn';
$user_role   = $_SESSION['role'] ?? 'data_entry';
$message     = '';
$error       = '';

// 1. AUTO CREATE TABLES & AUTO FIX MISSING COLUMNS IN ALL TABLES
try {
    // Goods Receipts Table
    $conn->exec("CREATE TABLE IF NOT EXISTS goods_receipts (
        grn_id INTEGER PRIMARY KEY AUTOINCREMENT,
        grn_no TEXT UNIQUE,
        supplier_id INTEGER,
        received_date TEXT,
        remarks TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Fix items Table - Add unit_price column if missing
    $items_info = $conn->query("PRAGMA table_info(items)")->fetchAll(PDO::FETCH_ASSOC);
    $items_cols = array_column($items_info, 'name');
    if (!in_array('unit_price', $items_cols)) {
        $conn->exec("ALTER TABLE items ADD COLUMN unit_price REAL DEFAULT 0");
    }

    // Fix grn_items Table - Drop & Recreate if columns are missing
    $grn_items_info = $conn->query("PRAGMA table_info(grn_items)")->fetchAll(PDO::FETCH_ASSOC);
    $grn_items_cols = array_column($grn_items_info, 'name');
    if (empty($grn_items_cols) || !in_array('qty', $grn_items_cols) || !in_array('unit_price', $grn_items_cols)) {
        $conn->exec("DROP TABLE IF EXISTS grn_items");
        $conn->exec("CREATE TABLE grn_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            grn_id INTEGER,
            item_id INTEGER,
            qty INTEGER DEFAULT 0,
            unit_price REAL DEFAULT 0
        )");
    }

} catch (PDOException $e) {
    $error = "Database Structure Update Error: " . $e->getMessage();
}

// 2. AUTO GENERATE GRN NUMBER (GRN-001, GRN-002...)
$next_grn_no = '';
try {
    $stmt_code = $conn->query("SELECT MAX(grn_id) FROM goods_receipts");
    $max_id = $stmt_code->fetchColumn();
    $next_id = ($max_id ? $max_id + 1 : 1);
    $next_grn_no = 'GRN-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);
} catch (PDOException $e) {
    $next_grn_no = 'GRN-001';
}

// 3. SAVE GRN TRANSACTION & UPDATE STOCK
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $grn_no        = trim($_POST['grn_no']);
    $supplier_id   = (int)$_POST['supplier_id'];
    $received_date = $_POST['received_date'];
    $remarks       = trim($_POST['remarks']);
    $item_id       = (int)$_POST['item_id'];
    $qty           = (int)$_POST['qty'];
    $unit_price    = (float)$_POST['unit_price'];

    if (!empty($supplier_id) && !empty($item_id) && $qty > 0) {
        try {
            $conn->beginTransaction();

            // Insert Main GRN Entry
            $stmt = $conn->prepare("INSERT INTO goods_receipts (grn_no, supplier_id, received_date, remarks) VALUES (?, ?, ?, ?)");
            $stmt->execute([$grn_no, $supplier_id, $received_date, $remarks]);
            $grn_id = $conn->lastInsertId();

            // Insert Item Detail
            $stmt_item = $conn->prepare("INSERT INTO grn_items (grn_id, item_id, qty, unit_price) VALUES (?, ?, ?, ?)");
            $stmt_item->execute([$grn_id, $item_id, $qty, $unit_price]);

            // Update Stock and Unit Price in items table
            $stmt_stock = $conn->prepare("UPDATE items SET current_stock = current_stock + ?, unit_price = ? WHERE item_id = ?");
            $stmt_stock->execute([$qty, $unit_price, $item_id]);

            $conn->commit();
            header("Location: grn.php?msg=success");
            exit();
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "දෝෂයක් සිදුවිය: " . $e->getMessage();
        }
    } else {
        $error = "කරුණාකර Supplier, Item සහ Quantity නිවැරදිව තෝරන්න.";
    }
}

// Dropdowns සඳහා Suppliers සහ Items ලබාගැනීම
$suppliers_list = [];
$items_list     = [];
try {
    $suppliers_list = $conn->query("SELECT * FROM suppliers ORDER BY supplier_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $items_list     = $conn->query("SELECT * FROM items ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// සියලුම GRN ලැයිස්තුව ලබාගැනීම
$grn_list = [];
try {
    $query = "SELECT g.*, s.supplier_name, i.item_name, gi.qty, gi.unit_price 
              FROM goods_receipts g
              LEFT JOIN suppliers s ON g.supplier_id = s.supplier_id
              LEFT JOIN grn_items gi ON g.grn_id = gi.grn_id
              LEFT JOIN items i ON gi.item_id = i.item_id
              ORDER BY g.grn_id DESC";
    $grn_list = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OSIMS - Goods Received Note (GRN)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container-fluid px-4 mt-4">
    <div class="row">
        <!-- Add GRN Form -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white fw-bold">
                    📥 New Goods Received Note (GRN)
                </div>
                <div class="card-body">
                    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
                        <div class="alert alert-success alert-dismissible fade show">GRN එක සාර්ථකව සුරැකි අතර තොගය හා මිල ගණන් යාවත්කාලීන විය!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show"><?= $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>

                    <form method="POST" action="grn.php">
                        <div class="mb-3">
                            <label class="form-label fw-bold">GRN No (ස්වයංක්‍රීය අංකය)</label>
                            <input type="text" name="grn_no" class="form-control bg-light" value="<?= $next_grn_no; ?>" readonly required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Received Date (ලැබුණු දිනය)</label>
                            <input type="date" name="received_date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Supplier (සැපයුම්කරු)</label>
                            <select name="supplier_id" class="form-select" required>
                                <option value="">-- Select Supplier --</option>
                                <?php foreach ($suppliers_list as $sup): ?>
                                    <option value="<?= $sup['supplier_id']; ?>"><?= htmlspecialchars($sup['supplier_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Item Name (භාණ්ඩය)</label>
                            <select name="item_id" class="form-select" required>
                                <option value="">-- Select Item --</option>
                                <?php foreach ($items_list as $item): ?>
                                    <option value="<?= $item['item_id']; ?>"><?= htmlspecialchars(($item['item_code'] ?? '') . ' - ' . $item['item_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Quantity (ප්‍රමාණය)</label>
                                <input type="number" name="qty" id="qty" class="form-control" min="1" value="1" oninput="calculateTotal()" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Unit Price (Rs.)</label>
                                <input type="number" step="0.01" name="unit_price" id="unit_price" class="form-control" placeholder="0.00" oninput="calculateTotal()" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Total Amount (Rs.)</label>
                            <input type="text" id="total_amount" class="form-control bg-light text-primary fw-bold" readonly value="0.00">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Remarks (සටහන්)</label>
                            <textarea name="remarks" class="form-control" rows="2" placeholder="PO Number, Invoice No..."></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary fw-bold">Save GRN & Add Stock</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- GRN History Table -->
        <div class="col-md-8 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white fw-bold">
                    📋 Recent GRN Records (ලැබුණු භාණ්ඩ වාර්තා)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>GRN No</th>
                                    <th>Date</th>
                                    <th>Supplier</th>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Unit Price</th>
                                    <th>Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($grn_list)): ?>
                                    <?php foreach ($grn_list as $row): 
                                        $total = ($row['qty'] ?? 0) * ($row['unit_price'] ?? 0);
                                    ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($row['grn_no']); ?></code></td>
                                            <td><?= htmlspecialchars($row['received_date']); ?></td>
                                            <td><?= htmlspecialchars($row['supplier_name'] ?? 'N/A'); ?></td>
                                            <td><strong><?= htmlspecialchars($row['item_name'] ?? 'N/A'); ?></strong></td>
                                            <td><span class="badge bg-success fs-6">+<?= $row['qty'] ?? 0; ?></span></td>
                                            <td>Rs. <?= number_format($row['unit_price'] ?? 0, 2); ?></td>
                                            <td><strong>Rs. <?= number_format($total, 2); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-3">දැනට GRN වාර්තා කිසිවක් ඇතුලත් කර නොමැත.</td>
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

<script>
function calculateTotal() {
    let qty = parseFloat(document.getElementById('qty').value) || 0;
    let price = parseFloat(document.getElementById('unit_price').value) || 0;
    let total = qty * price;
    document.getElementById('total_amount').value = total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>