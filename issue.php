<?php
session_start();
require_once 'db.php';

// User Login වී නැතිනම් Login පිටුවට යවන්න
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$active_page = 'issue';
$user_role   = $_SESSION['role'] ?? 'data_entry';
$message     = '';
$error       = '';

// SQLite සඳහා Column එකක් තිබේදැයි පරීක්ෂා කරන නිවැරදි Function එක
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

// 1. AUTO CREATE TABLES & AUTO FIX MISSING COLUMNS
try {
    // Issues Table
    $conn->exec("CREATE TABLE IF NOT EXISTS stock_issues (
        issue_id INTEGER PRIMARY KEY AUTOINCREMENT,
        issue_no TEXT UNIQUE,
        branch_name TEXT,
        issue_date TEXT,
        remarks TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Check issue_items Table - Drop and Recreate if 'qty' column is missing
    $issue_items_info = $conn->query("PRAGMA table_info(issue_items)")->fetchAll(PDO::FETCH_ASSOC);
    $issue_items_cols = array_column($issue_items_info, 'name');

    if (!empty($issue_items_cols) && !in_array('qty', $issue_items_cols)) {
        $conn->exec("DROP TABLE issue_items");
    }

    // Create issue_items table with required columns
    $conn->exec("CREATE TABLE IF NOT EXISTS issue_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        issue_id INTEGER,
        item_id INTEGER,
        qty INTEGER DEFAULT 0
    )");

    // Fix items table columns if missing
    if (!columnExists($conn, 'items', 'opening_stock')) {
        $conn->exec("ALTER TABLE items ADD COLUMN opening_stock INTEGER DEFAULT 0");
    }

    if (!columnExists($conn, 'items', 'unit_price')) {
        $conn->exec("ALTER TABLE items ADD COLUMN unit_price REAL DEFAULT 0");
    }

    if (!columnExists($conn, 'items', 'current_stock')) {
        $conn->exec("ALTER TABLE items ADD COLUMN current_stock INTEGER DEFAULT 0");
    }

} catch (PDOException $e) {
    $error = "Database Auto-Fix Error: " . $e->getMessage();
}

// 2. AUTO GENERATE ISSUE NUMBER (ISS-001, ISS-002...)
$next_issue_no = '';
try {
    $stmt_code = $conn->query("SELECT MAX(issue_id) FROM stock_issues");
    $max_id = $stmt_code->fetchColumn();
    $next_id = ($max_id ? $max_id + 1 : 1);
    $next_issue_no = 'ISS-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);
} catch (PDOException $e) {
    $next_issue_no = 'ISS-001';
}

// 3. SAVE ISSUE TRANSACTION & REDUCE STOCK
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $issue_no    = trim($_POST['issue_no']);
    $branch_name = trim($_POST['branch_name']);
    $issue_date  = $_POST['issue_date'];
    $remarks     = trim($_POST['remarks']);
    $item_id     = (int)$_POST['item_id'];
    $qty         = (int)$_POST['qty'];

    if (!empty($branch_name) && !empty($item_id) && $qty > 0) {
        try {
            // Check available stock
            $stmt_check = $conn->prepare("
                SELECT item_name, 
                (
                    COALESCE(opening_stock, 0) + 
                    COALESCE((SELECT SUM(gi.qty) FROM grn_items gi WHERE gi.item_id = items.item_id), 0) - 
                    COALESCE((SELECT SUM(ii.qty) FROM issue_items ii WHERE ii.item_id = items.item_id), 0)
                ) AS calculated_stock 
                FROM items WHERE item_id = ?
            ");
            $stmt_check->execute([$item_id]);
            $item_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

            $available_stock = $item_data['calculated_stock'] ?? 0;

            if ($item_data && $available_stock >= $qty) {
                $conn->beginTransaction();

                // Insert Main Issue Record
                $stmt = $conn->prepare("INSERT INTO stock_issues (issue_no, branch_name, issue_date, remarks) VALUES (?, ?, ?, ?)");
                $stmt->execute([$issue_no, $branch_name, $issue_date, $remarks]);
                $issue_id = $conn->lastInsertId();

                // Insert Issued Item
                $stmt_item = $conn->prepare("INSERT INTO issue_items (issue_id, item_id, qty) VALUES (?, ?, ?)");
                $stmt_item->execute([$issue_id, $item_id, $qty]);

                // Update current_stock column in items table as well
                $stmt_stock = $conn->prepare("UPDATE items SET current_stock = ? WHERE item_id = ?");
                $stmt_stock->execute([($available_stock - $qty), $item_id]);

                $conn->commit();
                header("Location: issue.php?msg=success");
                exit();
            } else {
                $error = "තොග ප්‍රමාණය ප්‍රමාණවත් නැත! දැනට පවතින ප්‍රමාණය: " . $available_stock;
            }
        } catch (PDOException $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $error = "දෝෂයක් සිදුවිය: " . $e->getMessage();
        }
    } else {
        $error = "කරුණාකර Branch/Section, Item සහ Quantity නිවැරදිව ඇතුලත් කරන්න.";
    }
}

// Departments, Items List & Recent Issues Fetch
$departments_list = [];
$items_list       = [];
$issues_list      = [];
try {
    // Fetch Departments for Dropdown
    $departments_list = $conn->query("SELECT * FROM departments ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch Available Items using Subquery to fix dropdown empty issue
    $items_query = "
        SELECT * FROM (
            SELECT item_id, item_code, item_name, 
            (
                COALESCE(opening_stock, 0) + 
                COALESCE((SELECT SUM(gi.qty) FROM grn_items gi WHERE gi.item_id = items.item_id), 0) - 
                COALESCE((SELECT SUM(ii.qty) FROM issue_items ii WHERE ii.item_id = items.item_id), 0)
            ) AS current_stock 
            FROM items
        ) t 
        WHERE current_stock > 0 
        ORDER BY item_name ASC
    ";
    $items_list = $conn->query($items_query)->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch Issued History
    $query = "SELECT s.*, i.item_name, ii.qty 
              FROM stock_issues s
              LEFT JOIN issue_items ii ON s.issue_id = ii.issue_id
              LEFT JOIN items i ON ii.item_id = i.item_id
              ORDER BY s.issue_id DESC";
    $issues_list = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OSIMS - Issue Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container-fluid px-4 mt-4">
    <div class="row">
        <!-- Add Stock Issue Form -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-warning text-dark fw-bold">
                    📤 Issue Goods to Branch / Section
                </div>
                <div class="card-body">
                    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
                        <div class="alert alert-success alert-dismissible fade show">භාණ්ඩ නිකුත් කිරීම සාර්ථකයි! තොගය යාවත්කාලීන විය.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show"><?= $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>

                    <form method="POST" action="issue.php">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Issue No (ස්වයංක්‍රීය අංකය)</label>
                            <input type="text" name="issue_no" class="form-control bg-light" value="<?= $next_issue_no; ?>" readonly required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Issue Date (නිකුත් කළ දිනය)</label>
                            <input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                        </div>

                        <!-- Section Dropdown -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Section / Branch (ශාඛාව / අංශය)</label>
                            <select name="branch_name" class="form-select" required>
                                <option value="">-- Select Section / Branch --</option>
                                <?php foreach ($departments_list as $dep): ?>
                                    <option value="<?= htmlspecialchars($dep['department_name']); ?>">
                                        <?= htmlspecialchars($dep['department_name']); ?> <?= !empty($dep['location']) ? ' (' . htmlspecialchars($dep['location']) . ')' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Item Name (භාණ්ඩය)</label>
                            <select name="item_id" class="form-select" required>
                                <option value="">-- Select Available Item --</option>
                                <?php foreach ($items_list as $item): ?>
                                    <option value="<?= $item['item_id']; ?>">
                                        <?= htmlspecialchars(($item['item_code'] ?? '') . ' - ' . $item['item_name']); ?> (Avail: <?= $item['current_stock']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Quantity (ප්‍රමාණය)</label>
                            <input type="number" name="qty" class="form-control" min="1" value="1" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Remarks (සටහන්)</label>
                            <textarea name="remarks" class="form-control" rows="2" placeholder="Requisition No, Approved by..."></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-warning fw-bold">Issue Item & Reduce Stock</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Issue History Table -->
        <div class="col-md-8 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white fw-bold">
                    📋 Recent Issued Records (නිකුත් කළ භාණ්ඩ ලැයිස්තුව)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Issue No</th>
                                    <th>Date</th>
                                    <th>Branch / Section</th>
                                    <th>Item</th>
                                    <th>Issued Qty</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($issues_list)): ?>
                                    <?php foreach ($issues_list as $row): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($row['issue_no']); ?></code></td>
                                            <td><?= htmlspecialchars($row['issue_date']); ?></td>
                                            <td><strong><?= htmlspecialchars($row['branch_name']); ?></strong></td>
                                            <td><?= htmlspecialchars($row['item_name'] ?? 'N/A'); ?></td>
                                            <td><span class="badge bg-danger fs-6">-<?= $row['qty'] ?? 0; ?></span></td>
                                            <td><small><?= htmlspecialchars($row['remarks'] ?? '-'); ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-3">දැනට නිකුත් කිරීමේ වාර්තා කිසිවක් ඇතුලත් කර නොමැත.</td>
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