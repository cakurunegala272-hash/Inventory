<?php
// Database Connection File (db.php)

$db_file = __DIR__ . '/osims.db'; // SQLite database ගොනුව පිහිටන තැන

try {
    $conn = new PDO("sqlite:" . $db_file);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Helper Function to check column existence
    if (!function_exists('dbColumnExists')) {
        function dbColumnExists($conn, $table, $column) {
            try {
                $stmt = $conn->query("PRAGMA table_info($table)");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($columns as $col) {
                    if ($col['name'] === $column) return true;
                }
            } catch (PDOException $e) {}
            return false;
        }
    }

    // 1. Roles Table
    $conn->exec("CREATE TABLE IF NOT EXISTS roles (
        role_id INTEGER PRIMARY KEY AUTOINCREMENT,
        role_name TEXT UNIQUE,
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Users Table
    $conn->exec("CREATE TABLE IF NOT EXISTS users (
        user_id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password TEXT,
        role TEXT DEFAULT 'data_entry',
        role_id INTEGER DEFAULT 1,
        full_name TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    if (!dbColumnExists($conn, 'users', 'role_id')) {
        $conn->exec("ALTER TABLE users ADD COLUMN role_id INTEGER DEFAULT 1");
    }
    if (!dbColumnExists($conn, 'users', 'full_name')) {
        $conn->exec("ALTER TABLE users ADD COLUMN full_name TEXT");
    }

    // Default Admin User
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
        $insert = $conn->prepare("INSERT INTO users (username, password, role, role_id, full_name) VALUES ('admin', ?, 'admin', 1, 'System Administrator')");
        $insert->execute([$hashed_password]);
    }

    // 3. Departments / Branches Table
    $conn->exec("CREATE TABLE IF NOT EXISTS departments (
        department_id INTEGER PRIMARY KEY AUTOINCREMENT,
        department_name TEXT UNIQUE,
        location TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 4. Sections Table
    $conn->exec("CREATE TABLE IF NOT EXISTS sections (
        section_id INTEGER PRIMARY KEY AUTOINCREMENT,
        section_name TEXT UNIQUE,
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 5. Suppliers Table
    $conn->exec("CREATE TABLE IF NOT EXISTS suppliers (
        supplier_id INTEGER PRIMARY KEY AUTOINCREMENT,
        supplier_name TEXT UNIQUE,
        contact_person TEXT,
        phone TEXT,
        email TEXT,
        address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 6. Items Table
    $conn->exec("CREATE TABLE IF NOT EXISTS items (
        item_id INTEGER PRIMARY KEY AUTOINCREMENT,
        item_code TEXT UNIQUE,
        item_name TEXT,
        unit TEXT DEFAULT 'Nos',
        opening_stock INTEGER DEFAULT 0,
        unit_price REAL DEFAULT 0,
        current_stock INTEGER DEFAULT 0,
        reorder_level INTEGER DEFAULT 5,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    if (!dbColumnExists($conn, 'items', 'unit')) {
        $conn->exec("ALTER TABLE items ADD COLUMN unit TEXT DEFAULT 'Nos'");
    }
    if (!dbColumnExists($conn, 'items', 'reorder_level')) {
        $conn->exec("ALTER TABLE items ADD COLUMN reorder_level INTEGER DEFAULT 5");
    }

    // 7. GRN Tables
    $conn->exec("CREATE TABLE IF NOT EXISTS grn (
        grn_id INTEGER PRIMARY KEY AUTOINCREMENT,
        grn_no TEXT UNIQUE,
        supplier_id INTEGER DEFAULT 0,
        supplier_name TEXT,
        grn_date TEXT,
        received_date TEXT,
        remarks TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    if (!dbColumnExists($conn, 'grn', 'supplier_id')) {
        $conn->exec("ALTER TABLE grn ADD COLUMN supplier_id INTEGER DEFAULT 0");
    }
    if (!dbColumnExists($conn, 'grn', 'received_date')) {
        $conn->exec("ALTER TABLE grn ADD COLUMN received_date TEXT");
    }

    $conn->exec("CREATE TABLE IF NOT EXISTS grn_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        grn_id INTEGER,
        item_id INTEGER,
        qty INTEGER DEFAULT 0,
        unit_price REAL DEFAULT 0
    )");

    // 8. Stock Issues Tables
    $conn->exec("CREATE TABLE IF NOT EXISTS stock_issues (
        issue_id INTEGER PRIMARY KEY AUTOINCREMENT,
        issue_no TEXT UNIQUE,
        branch_name TEXT,
        issue_date TEXT,
        remarks TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $conn->exec("CREATE TABLE IF NOT EXISTS issue_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        issue_id INTEGER,
        item_id INTEGER,
        qty INTEGER DEFAULT 0
    )");

} catch (PDOException $e) {
    die("Database Connection & Setup Error: " . $e->getMessage());
}
?>