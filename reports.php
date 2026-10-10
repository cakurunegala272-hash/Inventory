<?php
session_start();
require_once 'db.php';

// User Login වී නැතිනම් Login පිටුවට යවන්න
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$active_page = 'reports';
$user_role   = $_SESSION['role'] ?? 'data_entry';
$report_type = $_GET['type'] ?? 'stock_balance';

// Filters Input Values
$as_of_date  = $_GET['as_of_date'] ?? date('Y-m-d');
$start_date  = $_GET['start_date'] ?? '';
$end_date    = $_GET['end_date'] ?? '';
$branch_name = $_GET['branch_name'] ?? '';
$supplier_id = $_GET['supplier_id'] ?? '';

// SQLite සඳහා Table එකක් තිබේදැයි බලන Function එක
function tableExists($conn, $table) {
    try {
        $result = $conn->query("SELECT 1 FROM $table LIMIT 1");
        return $result !== false;
    } catch (Exception $e) {
        return false;
    }
}

// SQLite සඳහා Column එකක් තිබේදැයි පරීක්ෂා කරන Function එක
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

// Ensure required columns exist in items table
if (!columnExists($conn, 'items', 'opening_stock')) {
    $conn->exec("ALTER TABLE items ADD COLUMN opening_stock INTEGER DEFAULT 0");
}
if (!columnExists($conn, 'items', 'unit_price')) {
    $conn->exec("ALTER TABLE items ADD COLUMN unit_price REAL DEFAULT 0");
}

// Fetch Master Data for Filter Dropdowns
$departments_list = [];
$suppliers_list   = [];
try {
    if (tableExists($conn, 'departments')) {
        $departments_list = $conn->query("SELECT * FROM departments ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
    if (tableExists($conn, 'suppliers')) {
        $suppliers_list   = $conn->query("SELECT * FROM suppliers ORDER BY supplier_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {}

// DATA FETCHING BASED ON REPORT TYPE & FILTERS
$report_data = [];
$error_msg   = '';

try {
    if ($report_type === 'stock_balance') {
        // Dynamic Stock Balance calculated up to selected 'as_of_date'
        $sql = "SELECT 
                    i.item_id,
                    i.item_code,
                    i.item_name,
                    COALESCE(i.unit_price, 0) AS unit_price,
                    COALESCE(i.reorder_level, 5) AS reorder_level,
                    COALESCE(i.opening_stock, 0) AS open_qty,
                    (SELECT COALESCE(SUM(gi.qty), 0) 
                     FROM grn_items gi 
                     JOIN goods_receipts g ON gi.grn_id = g.grn_id 
                     WHERE gi.item_id = i.item_id AND DATE(g.received_date) <= DATE(?)) AS total_grn,
                    (SELECT COALESCE(SUM(ii.qty), 0) 
                     FROM issue_items ii 
                     JOIN stock_issues s ON ii.issue_id = s.issue_id 
                     WHERE ii.item_id = i.item_id AND DATE(s.issue_date) <= DATE(?)) AS total_issued
                FROM items i 
                ORDER BY i.item_name ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$as_of_date, $as_of_date]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($report_type === 'grn_report') {
        $params = [];
        $where_clauses = [];

        if (!empty($start_date) && !empty($end_date)) {
            $where_clauses[] = "DATE(g.received_date) BETWEEN DATE(?) AND DATE(?)";
            $params[] = $start_date;
            $params[] = $end_date;
        } elseif (!empty($start_date)) {
            $where_clauses[] = "DATE(g.received_date) >= DATE(?)";
            $params[] = $start_date;
        } elseif (!empty($end_date)) {
            $where_clauses[] = "DATE(g.received_date) <= DATE(?)";
            $params[] = $end_date;
        }

        if (!empty($supplier_id)) {
            $where_clauses[] = "g.supplier_id = ?";
            $params[] = $supplier_id;
        }

        $sql = "SELECT g.grn_no, g.received_date, g.remarks, 
                       COALESCE(s.supplier_name, 'N/A') AS supplier_name, 
                       i.item_code, i.item_name, gi.qty, gi.unit_price 
                FROM goods_receipts g
                LEFT JOIN suppliers s ON g.supplier_id = s.supplier_id
                LEFT JOIN grn_items gi ON g.grn_id = gi.grn_id
                LEFT JOIN items i ON gi.item_id = i.item_id";

        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $sql .= " ORDER BY g.grn_id DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($report_type === 'issue_report') {
        $params = [];
        $where_clauses = [];

        if (!empty($start_date) && !empty($end_date)) {
            $where_clauses[] = "DATE(s.issue_date) BETWEEN DATE(?) AND DATE(?)";
            $params[] = $start_date;
            $params[] = $end_date;
        } elseif (!empty($start_date)) {
            $where_clauses[] = "DATE(s.issue_date) >= DATE(?)";
            $params[] = $start_date;
        } elseif (!empty($end_date)) {
            $where_clauses[] = "DATE(s.issue_date) <= DATE(?)";
            $params[] = $end_date;
        }

        if (!empty($branch_name)) {
            $where_clauses[] = "s.branch_name = ?";
            $params[] = $branch_name;
        }

        $sql = "SELECT s.issue_no, s.issue_date, s.branch_name, s.remarks, i.item_code, i.item_name, ii.qty 
                FROM stock_issues s
                LEFT JOIN issue_items ii ON s.issue_id = ii.issue_id
                LEFT JOIN items i ON ii.item_id = i.item_id";

        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $sql .= " ORDER BY s.issue_id DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error_msg = "Database Query Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OSIMS - Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background-color: #fff !important; }
            .card { border: none !important; box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-light">

<div class="no-print">
    <?php include 'navbar.php'; ?>
</div>

<div class="container-fluid px-4 mt-4">
    <div class="card shadow-sm border-0 mb-4 no-print">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="btn-group">
                <a href="reports.php?type=stock_balance" class="btn btn-outline-primary <?= $report_type == 'stock_balance' ? 'active fw-bold' : ''; ?>">📦 Stock Balance (All Items)</a>
                <a href="reports.php?type=grn_report" class="btn btn-outline-success <?= $report_type == 'grn_report' ? 'active fw-bold' : ''; ?>">📥 GRN / Receipts Report</a>
                <a href="reports.php?type=issue_report" class="btn btn-outline-warning text-dark <?= $report_type == 'issue_report' ? 'active fw-bold' : ''; ?>">📤 Stock Issue Report</a>
            </div>
            <button onclick="window.print()" class="btn btn-secondary fw-bold">🖨️ Print / Save PDF</button>
        </div>

        <!-- Stock Balance Date Filter -->
        <?php if ($report_type === 'stock_balance'): ?>
            <div class="card-footer bg-white border-top py-3">
                <form method="GET" action="reports.php" class="row g-3 align-items-end">
                    <input type="hidden" name="type" value="stock_balance">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">As at Date (දිනට අදාළව)</label>
                        <input type="date" name="as_of_date" class="form-control" value="<?= htmlspecialchars($as_of_date); ?>">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary fw-bold w-100">Filter Date</button>
                        <a href="reports.php?type=stock_balance" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- GRN Filter Form -->
        <?php if ($report_type === 'grn_report'): ?>
            <div class="card-footer bg-white border-top py-3">
                <form method="GET" action="reports.php" class="row g-3 align-items-end">
                    <input type="hidden" name="type" value="grn_report">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Supplier</label>
                        <select name="supplier_id" class="form-select">
                            <option value="">-- All Suppliers --</option>
                            <?php foreach ($suppliers_list as $sup): ?>
                                <option value="<?= $sup['supplier_id']; ?>" <?= $supplier_id == $sup['supplier_id'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($sup['supplier_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-success fw-bold w-100">Filter</button>
                        <a href="reports.php?type=grn_report" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Issue Report Filter Form -->
        <?php if ($report_type === 'issue_report'): ?>
            <div class="card-footer bg-white border-top py-3">
                <form method="GET" action="reports.php" class="row g-3 align-items-end">
                    <input type="hidden" name="type" value="issue_report">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Branch / Section</label>
                        <select name="branch_name" class="form-select">
                            <option value="">-- All Branches / Sections --</option>
                            <?php foreach ($departments_list as $dep): ?>
                                <option value="<?= htmlspecialchars($dep['department_name']); ?>" <?= $branch_name == $dep['department_name'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($dep['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-warning text-dark fw-bold w-100">Filter</button>
                        <a href="reports.php?type=issue_report" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="card shadow-sm border-0 mb-5">
        <div class="card-body p-4">
            
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger mb-4"><?= $error_msg; ?></div>
            <?php endif; ?>

            <div class="text-center mb-4 border-bottom pb-3">
                <h2 class="fw-bold mb-1">OFFICE STORES INVENTORY MANAGEMENT SYSTEM</h2>
                <h4 class="text-primary text-uppercase fw-semibold">
                    <?php 
                        if ($report_type == 'stock_balance') echo "All Items Stock Balance Statement (As at " . htmlspecialchars($as_of_date) . ")";
                        elseif ($report_type == 'grn_report') echo "Goods Received Note (GRN) Summary Report";
                        elseif ($report_type == 'issue_report') echo "Stock Issue Summary Report";
                    ?>
                </h4>
                <p class="text-muted mb-0">Generated Date: <?= date('Y-m-d H:i:s'); ?></p>
            </div>

            <div class="table-responsive">
                <!-- 1. STOCK BALANCE TABLE -->
                <?php if ($report_type == 'stock_balance'): ?>
                    <table class="table table-bordered table-striped align-middle text-center">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Item Code</th>
                                <th class="text-start">Item Name</th>
                                <th class="table-secondary text-dark">Opening Stock</th>
                                <th class="table-success text-dark">GRN Received (+)</th>
                                <th class="table-danger text-dark">Issued (-)</th>
                                <th class="table-primary text-dark">Closing Stock</th>
                                <th>Unit Price</th>
                                <th>Stock Value (Rs.)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $grand_total = 0;
                            if (!empty($report_data)): 
                                foreach ($report_data as $index => $row): 
                                    $open_stock   = (int)($row['open_qty'] ?? 0);
                                    $grn_rec      = (int)($row['total_grn'] ?? 0);
                                    $issued_qty   = (int)($row['total_issued'] ?? 0);
                                    $calc_stock   = ($open_stock + $grn_rec) - $issued_qty;
                                    $unit_price   = (float)($row['unit_price'] ?? 0);
                                    $stock_val    = $calc_stock * $unit_price;
                                    $grand_total += $stock_val;
                            ?>
                                <tr>
                                    <td><?= $index + 1; ?></td>
                                    <td><code><?= htmlspecialchars($row['item_code'] ?? '-'); ?></code></td>
                                    <td class="text-start"><strong><?= htmlspecialchars($row['item_name']); ?></strong></td>
                                    <td class="fw-bold text-dark table-secondary"><?= $open_stock; ?></td>
                                    <td class="text-success fw-bold">+<?= $grn_rec; ?></td>
                                    <td class="text-danger fw-bold">-<?= $issued_qty; ?></td>
                                    <td>
                                        <span class="badge <?= ($calc_stock <= 0) ? 'bg-secondary' : (($calc_stock <= ($row['reorder_level'] ?? 5)) ? 'bg-danger' : 'bg-primary'); ?> fs-6">
                                            <?= $calc_stock; ?>
                                        </span>
                                    </td>
                                    <td>Rs. <?= number_format($unit_price, 2); ?></td>
                                    <td class="fw-bold">Rs. <?= number_format($stock_val, 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-dark fw-bold">
                                    <td colspan="8" class="text-end">Total Estimated Stock Value:</td>
                                    <td>Rs. <?= number_format($grand_total, 2); ?></td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">තවමත් කිසිදු Item එකක් ඇතුළත් කර නොමැත.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                <!-- 2. GRN / RECEIPTS REPORT TABLE -->
                <?php elseif ($report_type == 'grn_report'): ?>
                    <table class="table table-bordered table-striped align-middle text-center">
                        <thead class="table-success">
                            <tr>
                                <th>#</th>
                                <th>GRN No</th>
                                <th>Received Date</th>
                                <th>Supplier Name</th>
                                <th>Item Code</th>
                                <th class="text-start">Item Name</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Total Amount (Rs.)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $grn_grand_total = 0;
                            if (!empty($report_data)): 
                                foreach ($report_data as $index => $row): 
                                    $qty        = (int)($row['qty'] ?? 0);
                                    $unit_p     = (float)($row['unit_price'] ?? 0);
                                    $tot_amt    = $qty * $unit_p;
                                    $grn_grand_total += $tot_amt;
                            ?>
                                <tr>
                                    <td><?= $index + 1; ?></td>
                                    <td><code><?= htmlspecialchars($row['grn_no'] ?? '-'); ?></code></td>
                                    <td><?= htmlspecialchars($row['received_date'] ?? '-'); ?></td>
                                    <td><?= htmlspecialchars($row['supplier_name'] ?? '-'); ?></td>
                                    <td><code><?= htmlspecialchars($row['item_code'] ?? '-'); ?></code></td>
                                    <td class="text-start"><strong><?= htmlspecialchars($row['item_name'] ?? '-'); ?></strong></td>
                                    <td class="fw-bold text-success">+<?= $qty; ?></td>
                                    <td>Rs. <?= number_format($unit_p, 2); ?></td>
                                    <td class="fw-bold">Rs. <?= number_format($tot_amt, 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-dark fw-bold">
                                    <td colspan="8" class="text-end">Total Received Value:</td>
                                    <td>Rs. <?= number_format($grn_grand_total, 2); ?></td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">තෝරාගත් Filter අනුව කිසිදු GRN වාර්තාවක් හමු නොවීය.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                <!-- 3. STOCK ISSUE REPORT TABLE -->
                <?php elseif ($report_type == 'issue_report'): ?>
                    <table class="table table-bordered table-striped align-middle text-center">
                        <thead class="table-warning">
                            <tr>
                                <th>#</th>
                                <th>Issue No</th>
                                <th>Issue Date</th>
                                <th>Branch / Section</th>
                                <th>Item Code</th>
                                <th class="text-start">Item Name</th>
                                <th>Issued Qty</th>
                                <th class="text-start">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_issued_qty = 0;
                            if (!empty($report_data)): 
                                foreach ($report_data as $index => $row): 
                                    $iss_qty = (int)($row['qty'] ?? 0);
                                    $total_issued_qty += $iss_qty;
                            ?>
                                <tr>
                                    <td><?= $index + 1; ?></td>
                                    <td><code><?= htmlspecialchars($row['issue_no'] ?? '-'); ?></code></td>
                                    <td><?= htmlspecialchars($row['issue_date'] ?? '-'); ?></td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($row['branch_name'] ?? '-'); ?></span></td>
                                    <td><code><?= htmlspecialchars($row['item_code'] ?? '-'); ?></code></td>
                                    <td class="text-start"><strong><?= htmlspecialchars($row['item_name'] ?? '-'); ?></strong></td>
                                    <td class="fw-bold text-danger">-<?= $iss_qty; ?></td>
                                    <td class="text-start"><small><?= htmlspecialchars($row['remarks'] ?? '-'); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-dark fw-bold">
                                    <td colspan="6" class="text-end">Total Issued Quantity:</td>
                                    <td class="text-danger">-<?= $total_issued_qty; ?></td>
                                    <td></td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">තෝරාගත් Filter අනුව කිසිදු Stock Issue වාර්තාවක් හමු නොවීය.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>