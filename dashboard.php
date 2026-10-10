<?php
session_start();
require_once 'db.php';

// 1. Login Check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// 2. Database එකෙන් Current User ගේ Fresh Data ලබා ගැනීම (Role එක හරියටම තහවුරු කිරීමට)
$logged_user_id = $_SESSION['user_id'];
$user_stmt = $conn->prepare("SELECT user_id, role, full_name, username FROM users WHERE user_id = ?");
$user_stmt->execute([$logged_user_id]);
$current_logged_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_logged_user) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Session Role සහ Full Name යාවත්කාලීන කිරීම
$user_role           = strtolower(trim($current_logged_user['role']));
$_SESSION['role']    = $user_role;
$_SESSION['username'] = $current_logged_user['username'];
$_SESSION['full_name'] = !empty($current_logged_user['full_name']) ? $current_logged_user['full_name'] : $current_logged_user['username'];

$active_page = 'dashboard';

// 3. Dashboard Statistics & Counts Fetching
$total_items       = 0;
$low_stock_count   = 0;
$total_suppliers   = 0;
$total_departments = 0;
$low_stock_items   = [];
$recent_grns       = [];
$backups           = [];

try {
    // Total Items Count
    $total_items = $conn->query("SELECT COUNT(*) FROM items")->fetchColumn();

    // Low Stock Alert Count (Current Stock <= Reorder Level)
    $low_stock_count = $conn->query("SELECT COUNT(*) FROM items WHERE opening_stock <= reorder_level")->fetchColumn();

    // Suppliers Count
    $total_suppliers = $conn->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();

    // Sections / Departments Count
    $total_departments = $conn->query("SELECT COUNT(*) FROM sections")->fetchColumn();

    // Low Stock Items List
    $stmt_low = $conn->query("SELECT item_code, item_name, opening_stock, reorder_level FROM items WHERE opening_stock <= reorder_level ORDER BY opening_stock ASC LIMIT 5");
    $low_stock_items = $stmt_low->fetchAll(PDO::FETCH_ASSOC);

    // Recent GRNs
    $stmt_grn = $conn->query("SELECT g.grn_no, s.supplier_name, g.created_at FROM grn g LEFT JOIN suppliers s ON g.supplier_id = s.supplier_id ORDER BY g.grn_id DESC LIMIT 5");
    if ($stmt_grn) {
        $recent_grns = $stmt_grn->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Exception handled gracefully
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OSIMS Store System - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .card-dash { border: none; border-radius: 8px; color: white; }
        .bg-items { background-color: #4361ee; }
        .bg-lowstock { background-color: #f72585; }
        .bg-suppliers { background-color: #4cc9f0; }
        .bg-departments { background-color: #7209b7; }
    </style>
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container-fluid px-4 mt-4 mb-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0">📊 Dashboard</h3>
            <p class="text-muted mb-0">
                සාදරයෙන් පිළිගනිමු, <strong><?= htmlspecialchars($_SESSION['full_name']); ?></strong> 
                (<code><?= strtoupper($user_role); ?></code>)
            </p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($user_role === 'admin'): ?>
                <a href="backup.php" class="btn btn-outline-primary fw-bold">💾 Create DB Backup</a>
            <?php endif; ?>
            <span class="btn btn-secondary disabled fw-bold">📅 <?= date('Y-m-d'); ?></span>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card card-dash bg-items p-3 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase fw-bold mb-1">TOTAL ITEMS</h6>
                        <h2 class="fw-bold mb-0"><?= $total_items; ?></h2>
                    </div>
                    <i class="bi bi-box-seam fs-1 opacity-50"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card card-dash bg-lowstock p-3 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase fw-bold mb-1">⚠️ LOW STOCK ALERT</h6>
                        <h2 class="fw-bold mb-0"><?= $low_stock_count; ?></h2>
                    </div>
                    <i class="bi bi-exclamation-triangle fs-1 opacity-50"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card card-dash bg-suppliers p-3 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase fw-bold mb-1">TRUCK / SUPPLIERS</h6>
                        <h2 class="fw-bold mb-0"><?= $total_suppliers; ?></h2>
                    </div>
                    <i class="bi bi-truck fs-1 opacity-50"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card card-dash bg-departments p-3 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase fw-bold mb-1">DEPARTMENTS / BRANCHES</h6>
                        <h2 class="fw-bold mb-0"><?= $total_departments; ?></h2>
                    </div>
                    <i class="bi bi-building fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Tables -->
    <div class="row g-4">
        <!-- Low Stock Table -->
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-danger text-white fw-bold">
                    ⚠️ Low Stock Items (අඩු තොග පවතින භාණ්ඩ)
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <th class="text-center">Current Stock</th>
                                <th class="text-center">Reorder Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($low_stock_items)): ?>
                                <?php foreach ($low_stock_items as $item): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($item['item_code']); ?></code></td>
                                        <td><strong><?= htmlspecialchars($item['item_name']); ?></strong></td>
                                        <td class="text-center">
                                            <span class="badge bg-danger fs-6"><?= $item['opening_stock']; ?> Nos</span>
                                        </td>
                                        <td class="text-center"><?= $item['reorder_level']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-3 text-muted">අඩු තොග වාර්තා නොමැත.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent GRN Receipts -->
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white fw-bold">
                    📥 Recent GRN Receipts (මෑතකදී ඇතුළත් කළ GRN)
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>GRN No</th>
                                <th>Supplier</th>
                                <th>Received Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_grns)): ?>
                                <?php foreach ($recent_grns as $grn): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($grn['grn_no']); ?></code></td>
                                        <td><?= htmlspecialchars($grn['supplier_name'] ?? 'N/A'); ?></td>
                                        <td><?= htmlspecialchars($grn['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">මෑතකදී කළ GRN සටහන් නොමැත.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>