<?php
if (!isset($active_page)) {
    $active_page = '';
}
$user_role = strtolower($_SESSION['role'] ?? 'data_entry');
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm py-2 no-print">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold me-4" href="dashboard.php">🏢 OSIMS Store System</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto gap-2">
                <li class="nav-item">
                    <a class="btn <?= $active_page == 'dashboard' ? 'btn-primary active fw-bold shadow' : 'btn-outline-light'; ?> text-start" href="dashboard.php">📊 Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="btn <?= $active_page == 'items' ? 'btn-primary active fw-bold shadow' : 'btn-outline-light'; ?> text-start" href="items.php">📦 Manage Items</a>
                </li>
                <li class="nav-item">
                    <a class="btn <?= $active_page == 'suppliers' ? 'btn-primary active fw-bold shadow' : 'btn-outline-light'; ?> text-start" href="suppliers.php">🚚 Suppliers</a>
                </li>
                <li class="nav-item">
                    <a class="btn <?= $active_page == 'sections' ? 'btn-primary active fw-bold shadow' : 'btn-outline-light'; ?> text-start" href="sections.php">🏛️ Sections / Branches</a>
                </li>
                <li class="nav-item">
                    <a class="btn <?= $active_page == 'grn' ? 'btn-primary active fw-bold shadow' : 'btn-outline-light'; ?> text-start" href="grn.php">📥 GRN</a>
                </li>
                <li class="nav-item">
                    <a class="btn <?= $active_page == 'issue' ? 'btn-primary active fw-bold shadow' : 'btn-outline-light'; ?> text-start" href="issue.php">📤 Issue</a>
                </li>
                <li class="nav-item">
                    <a class="btn <?= $active_page == 'reports' ? 'btn-primary active fw-bold shadow' : 'btn-outline-light'; ?> text-start" href="reports.php">📑 Reports</a>
                </li>

                <!-- Admin ට පමණක් පෙනෙන සේ සීමා කිරීම -->
                <?php if ($user_role === 'admin'): ?>
                <li class="nav-item">
                    <a class="btn <?= $active_page == 'users' ? 'btn-primary active fw-bold shadow' : 'btn-outline-light'; ?> text-start" href="users.php">👥 User Management</a>
                </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-secondary fs-6 px-3 py-2">
                    👤 <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User'); ?> (<?= strtoupper($user_role); ?>)
                </span>
                <a href="logout.php" class="btn btn-danger btn-sm fw-bold px-3 py-2">Logout</a>
            </div>
        </div>
    </div>
</nav>