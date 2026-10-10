<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$active_page = 'reports';

// Helper function to check column existence safely
function columnExists($conn, $table, $column) {
    $table_check = $conn->query("SHOW TABLES LIKE '$table'");
    if (!$table_check || $table_check->num_rows == 0) return false;
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($res && $res->num_rows > 0);
}

// 1. Auto-detect Unit column
$unit_select_sql = "'PCS' AS unit"; 
if (columnExists($conn, 'items', 'unit_of_measure')) {
    $unit_select_sql = "i.unit_of_measure AS unit";
} elseif (columnExists($conn, 'items', 'unit_name')) {
    $unit_select_sql = "i.unit_name AS unit";
} elseif (columnExists($conn, 'items', 'unit_type')) {
    $unit_select_sql = "i.unit_type AS unit";
} elseif (columnExists($conn, 'items', 'uom')) {
    $unit_select_sql = "i.uom AS unit";
} elseif (columnExists($conn, 'items', 'unit')) {
    $unit_select_sql = "i.unit AS unit";
}

// 2. Auto-detect Initial/Opening Stock Column in items table
$opening_stock_sql = "0";
if (columnExists($conn, 'items', 'opening_stock')) {
    $opening_stock_sql = "IFNULL(i.opening_stock, 0)";
} elseif (columnExists($conn, 'items', 'opening_balance')) {
    $opening_stock_sql = "IFNULL(i.opening_balance, 0)";
} elseif (columnExists($conn, 'items', 'initial_stock')) {
    $opening_stock_sql = "IFNULL(i.initial_stock, 0)";
} elseif (columnExists($conn, 'items', 'quantity')) {
    $opening_stock_sql = "IFNULL(i.quantity, 0)";
} elseif (columnExists($conn, 'items', 'qty')) {
    $opening_stock_sql = "IFNULL(i.qty, 0)";
}

// Get Filter Parameters
$report_type   = $_GET['report_type'] ?? 'section_issue';
$from_date     = $_GET['from_date'] ?? date('Y-m-01');
$to_date       = $_GET['to_date'] ?? date('Y-m-d');
$department_id = $_GET['department_id'] ?? '';
$supplier_id   = $_GET['supplier_id'] ?? '';
$item_id       = $_GET['item_id'] ?? '';

// Fetch Dropdown Data
$departments_list = $conn->query("SELECT * FROM departments ORDER BY department_name ASC");
$suppliers_list   = $conn->query("SELECT * FROM suppliers ORDER BY supplier_name ASC");
$items_list       = $conn->query("SELECT * FROM items ORDER BY item_name ASC");

// Report Queries Logic
$report_title = "";
$report_results = false;

if ($report_type == 'section_issue') {
    // Report 1: Section / Branch Issue Report
    $report_title = "SECTION / BRANCH ISSUE REPORT";
    $where = "WHERE iv.issue_date BETWEEN '$from_date' AND '$to_date'";
    
    if (!empty($department_id)) {
        $where .= " AND iv.department_id = '$department_id'";
    }
    if (!empty($item_id)) {
        $where .= " AND ivi.item_id = '$item_id'";
    }

    $sql = "SELECT iv.issue_number AS ref_no, iv.issue_date AS trans_date, 
                   d.department_name AS party_name, i.item_code, i.item_name, 
                   $unit_select_sql, ivi.issued_qty AS qty, 0 AS unit_price, 0 AS total_price
            FROM issue_voucher_items ivi
            JOIN issue_vouchers iv ON ivi.issue_id = iv.issue_id
            LEFT JOIN departments d ON iv.department_id = d.department_id
            LEFT JOIN items i ON ivi.item_id = i.item_id
            $where
            ORDER BY iv.issue_date DESC, iv.issue_id DESC";

    $report_results = $conn->query($sql);

} elseif ($report_type == 'monthly_balance') {
    // Report 2: Monthly Stock Balance Report (Initial + Pre-GRN - Pre-Issue = True Opening)
    $report_title = "MONTHLY STOCK BALANCE REPORT";
    $where_item = !empty($item_id) ? "WHERE i.item_id = '$item_id'" : "";

    $sql = "SELECT i.item_code, i.item_name, $unit_select_sql, i.reorder_level,
                   (
                       $opening_stock_sql
                       +
                       IFNULL((SELECT SUM(gri.received_qty) 
                               FROM goods_receipt_items gri 
                               JOIN goods_receipts gr ON gri.grn_id = gr.grn_id 
                               WHERE gri.item_id = i.item_id AND gr.grn_date < '$from_date'), 0)
                       -
                       IFNULL((SELECT SUM(ivi.issued_qty) 
                               FROM issue_voucher_items ivi 
                               JOIN issue_vouchers iv ON ivi.issue_id = iv.issue_id 
                               WHERE ivi.item_id = i.item_id AND iv.issue_date < '$from_date'), 0)
                   ) AS opening_balance,

                   IFNULL((SELECT SUM(gri.received_qty) 
                           FROM goods_receipt_items gri 
                           JOIN goods_receipts gr ON gri.grn_id = gr.grn_id 
                           WHERE gri.item_id = i.item_id AND gr.grn_date BETWEEN '$from_date' AND '$to_date'), 0) AS month_grn,

                   IFNULL((SELECT SUM(ivi.issued_qty) 
                           FROM issue_voucher_items ivi 
                           JOIN issue_vouchers iv ON ivi.issue_id = iv.issue_id 
                           WHERE ivi.item_id = i.item_id AND iv.issue_date BETWEEN '$from_date' AND '$to_date'), 0) AS month_issued

            FROM items i
            $where_item
            ORDER BY i.item_name ASC";

    $report_results = $conn->query($sql);

} elseif ($report_type == 'grn_report') {
    // Report 3: Supplier GRN Report
    $report_title = "SUPPLIER RECEIVED GOODS REPORT (GRN)";
    $where = "WHERE gr.grn_date BETWEEN '$from_date' AND '$to_date'";
    
    if (!empty($supplier_id)) {
        $where .= " AND gr.supplier_id = '$supplier_id'";
    }
    if (!empty($item_id)) {
        $where .= " AND gri.item_id = '$item_id'";
    }

    $sql = "SELECT gr.grn_number AS ref_no, gr.grn_date AS trans_date, 
                   s.supplier_name AS party_name, i.item_code, i.item_name, 
                   $unit_select_sql, gri.received_qty AS qty, 
                   IFNULL(gri.unit_price, 0) AS unit_price, 
                   (gri.received_qty * IFNULL(gri.unit_price, 0)) AS total_price
            FROM goods_receipt_items gri
            JOIN goods_receipts gr ON gri.grn_id = gr.grn_id
            LEFT JOIN suppliers s ON gr.supplier_id = s.supplier_id
            LEFT JOIN items i ON gri.item_id = i.item_id
            $where
            ORDER BY gr.grn_date DESC, gr.grn_id DESC";

    $report_results = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OSIMS - System Reports</title>
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

<!-- Navbar Section -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm py-2 no-print">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold me-4" href="dashboard.php">🏢 OSIMS Store System</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto gap-2">
                <li class="nav-item"><a class="btn btn-outline-light text-start" href="dashboard.php">📊 Dashboard</a></li>
                <li class="nav-item"><a class="btn btn-outline-light text-start" href="items.php">📦 Manage Items</a></li>
                <li class="nav-item"><a class="btn btn-outline-light text-start" href="suppliers.php">🚚 Suppliers</a></li>
                <li class="nav-item"><a class="btn btn-outline-light text-start" href="sections.php">🏛️ Sections / Branches</a></li>
                <li class="nav-item"><a class="btn btn-outline-light text-start" href="grn.php">📥 GRN</a></li>
                <li class="nav-item"><a class="btn btn-outline-light text-start" href="issue.php">📤 Issue</a></li>
                <li class="nav-item"><a class="btn btn-primary active fw-bold shadow text-start" href="reports.php">📑 Reports</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-secondary fs-6 px-3 py-2">👤 <?= $_SESSION['full_name'] ?? 'System Admin'; ?></span>
                <a href="logout.php" class="btn btn-danger btn-sm fw-bold px-3 py-2">Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 mt-4">

    <!-- Filter Form Section -->
    <div class="card shadow-sm mb-4 no-print">
        <div class="card-header bg-dark text-white fw-bold">📑 Generate & Filter Reports</div>
        <div class="card-body">
            <form method="GET" action="reports.php" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Report Type</label>
                    <select name="report_type" class="form-select fw-bold text-primary" onchange="this.form.submit()">
                        <option value="section_issue" <?= $report_type == 'section_issue' ? 'selected' : ''; ?>>📤 Section / Branch Issue Report</option>
                        <option value="monthly_balance" <?= $report_type == 'monthly_balance' ? 'selected' : ''; ?>>📊 Monthly Stock Balance Report</option>
                        <option value="grn_report" <?= $report_type == 'grn_report' ? 'selected' : ''; ?>>📥 Supplier GRN Report</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label fw-semibold">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?= $from_date; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?= $to_date; ?>">
                </div>

                <?php if ($report_type == 'section_issue'): ?>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Filter Section / Branch</label>
                    <select name="department_id" class="form-select">
                        <option value="">-- All Sections --</option>
                        <?php if ($departments_list): while($dept = $departments_list->fetch_assoc()): ?>
                            <option value="<?= $dept['department_id']; ?>" <?= $department_id == $dept['department_id'] ? 'selected' : ''; ?>>
                                <?= $dept['department_name']; ?>
                            </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if ($report_type == 'grn_report'): ?>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Filter Supplier</label>
                    <select name="supplier_id" class="form-select">
                        <option value="">-- All Suppliers --</option>
                        <?php if ($suppliers_list): while($sup = $suppliers_list->fetch_assoc()): ?>
                            <option value="<?= $sup['supplier_id']; ?>" <?= $supplier_id == $sup['supplier_id'] ? 'selected' : ''; ?>>
                                <?= $sup['supplier_name']; ?>
                            </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="col-md-2">
                    <label class="form-label fw-semibold">Filter Item</label>
                    <select name="item_id" class="form-select">
                        <option value="">-- All Items --</option>
                        <?php if ($items_list): while($itm = $items_list->fetch_assoc()): ?>
                            <option value="<?= $itm['item_id']; ?>" <?= $item_id == $itm['item_id'] ? 'selected' : ''; ?>>
                                <?= $itm['item_code'] . ' - ' . $itm['item_name']; ?>
                            </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>

                <div class="col-md-12 d-flex justify-content-end gap-2 mt-3">
                    <button type="submit" class="btn btn-primary fw-bold px-4">🔍 Filter Data</button>
                    <button type="button" onclick="window.print();" class="btn btn-success fw-bold px-4">🖨️ Print / Export to PDF</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Print Header -->
    <div class="text-center my-4">
        <h2 class="fw-bold mb-1">OSIMS STORE MANAGEMENT SYSTEM</h2>
        <h5 class="text-uppercase fw-bold text-secondary"><?= $report_title; ?></h5>
        <p class="mb-0 text-muted">Period From: <u><?= $from_date; ?></u> To: <u><?= $to_date; ?></u></p>
        <small class="text-muted">Generated Date: <?= date('Y-m-d H:i:s'); ?></small>
    </div>

    <!-- Data Tables Area -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-body p-0">
            
            <?php if ($report_type == 'section_issue'): ?>
            <!-- Table 1: Section Issue -->
            <table class="table table-striped table-bordered align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Issue Voucher No</th>
                        <th>Date</th>
                        <th>Section / Branch Name</th>
                        <th>Item Code</th>
                        <th>Item Description</th>
                        <th>Unit</th>
                        <th class="text-end">Issued Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($report_results && $report_results->num_rows > 0): 
                        while($row = $report_results->fetch_assoc()): ?>
                        <tr>
                            <td><b><?= $row['ref_no']; ?></b></td>
                            <td><?= $row['trans_date']; ?></td>
                            <td><b><?= $row['party_name'] ?? 'N/A'; ?></b></td>
                            <td><?= $row['item_code']; ?></td>
                            <td><?= $row['item_name']; ?></td>
                            <td><?= $row['unit'] ?? 'PCS'; ?></td>
                            <td class="text-end fw-bold text-danger"><?= number_format($row['qty'], 0); ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">තෝරාගත් දත්ත සඳහා කිසිදු සටහනක් නොමැත.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php elseif ($report_type == 'monthly_balance'): ?>
            <!-- Table 2: Monthly Stock Balance -->
            <table class="table table-striped table-bordered align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Item Code</th>
                        <th>Item Description</th>
                        <th>Unit</th>
                        <th class="text-end">Opening Balance</th>
                        <th class="text-end">GRN (Received)</th>
                        <th class="text-end">Issued Qty</th>
                        <th class="text-end">Closing Balance</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($report_results && $report_results->num_rows > 0): 
                        while($row = $report_results->fetch_assoc()):
                            $opening_balance = $row['opening_balance'];
                            $month_grn      = $row['month_grn'];
                            $month_issued   = $row['month_issued'];
                            $closing_balance = $opening_balance + $month_grn - $month_issued;
                    ?>
                        <tr>
                            <td><b><?= $row['item_code']; ?></b></td>
                            <td><?= $row['item_name']; ?></td>
                            <td><?= $row['unit'] ?? 'PCS'; ?></td>
                            <td class="text-end fw-bold text-dark bg-light"><?= number_format($opening_balance, 0); ?></td>
                            <td class="text-end text-success fw-semibold">+<?= number_format($month_grn, 0); ?></td>
                            <td class="text-end text-danger fw-semibold">-<?= number_format($month_issued, 0); ?></td>
                            <td class="text-end fw-bold fs-6 <?= $closing_balance <= $row['reorder_level'] ? 'text-danger' : 'text-primary'; ?>">
                                <?= number_format($closing_balance, 0); ?>
                            </td>
                            <td class="text-center">
                                <?php if ($closing_balance <= $row['reorder_level']): ?>
                                    <span class="badge bg-danger">Reorder Required</span>
                                <?php else: ?>
                                    <span class="badge bg-success">In Stock</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">දත්ත හමු නොවීය.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php elseif ($report_type == 'grn_report'): ?>
            <!-- Table 3: GRN Report -->
            <table class="table table-striped table-bordered align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>GRN No</th>
                        <th>Date</th>
                        <th>Supplier Name</th>
                        <th>Item Code</th>
                        <th>Item Description</th>
                        <th>Unit</th>
                        <th class="text-end">Received Qty</th>
                        <th class="text-end">Unit Price (LKR)</th>
                        <th class="text-end">Total Amount (LKR)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grand_total = 0;
                    if ($report_results && $report_results->num_rows > 0): 
                        while($row = $report_results->fetch_assoc()):
                            $grand_total += $row['total_price'];
                    ?>
                        <tr>
                            <td><b><?= $row['ref_no']; ?></b></td>
                            <td><?= $row['trans_date']; ?></td>
                            <td><?= $row['party_name'] ?? 'N/A'; ?></td>
                            <td><?= $row['item_code']; ?></td>
                            <td><?= $row['item_name']; ?></td>
                            <td><?= $row['unit'] ?? 'PCS'; ?></td>
                            <td class="text-end fw-bold text-success"><?= number_format($row['qty'], 0); ?></td>
                            <td class="text-end"><?= number_format($row['unit_price'], 2); ?></td>
                            <td class="text-end fw-bold"><?= number_format($row['total_price'], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    <tr class="table-secondary fw-bold fs-6">
                        <td colspan="8" class="text-end">Grand Total Value:</td>
                        <td class="text-end">LKR <?= number_format($grand_total, 2); ?></td>
                    </tr>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">තෝරාගත් දත්ත සඳහා කිසිදු සටහනක් නොමැත.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php endif; ?>

        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>