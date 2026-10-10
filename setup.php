<?php
require_once 'db.php';

try {
    // Foreign Key constraints සක්‍රීය කිරීම
    $conn->exec("PRAGMA foreign_keys = ON;");

    // 1. Roles Table
    $conn->exec("CREATE TABLE IF NOT EXISTS roles (
        role_id INTEGER PRIMARY KEY AUTOINCREMENT,
        role_name TEXT UNIQUE NOT NULL
    )");

    // Default Roles එකතු කිරීම (Admin සහ Data Entry)
    $conn->exec("INSERT OR IGNORE INTO roles (role_id, role_name) VALUES 
        (1, 'admin'), 
        (2, 'data_entry')");

    // 2. Users Table (role_id එක සමඟ)
    $conn->exec("CREATE TABLE IF NOT EXISTS users (
        user_id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        full_name TEXT NOT NULL,
        role_id INTEGER DEFAULT 2,
        FOREIGN KEY (role_id) REFERENCES roles(role_id)
    )");

    // Default Admin User එකතු කිරීම (Username: admin | Password: admin123)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->exec("INSERT INTO users (username, password, full_name, role_id) 
                     VALUES ('admin', '$hash', 'System Admin', 1)");
    }

    // 3. Sections / Branches Table
    $conn->exec("CREATE TABLE IF NOT EXISTS sections (
        section_id INTEGER PRIMARY KEY AUTOINCREMENT,
        section_name TEXT NOT NULL
    )");

    // 4. Suppliers Table
    $conn->exec("CREATE TABLE IF NOT EXISTS suppliers (
        supplier_id INTEGER PRIMARY KEY AUTOINCREMENT,
        supplier_name TEXT NOT NULL,
        contact_no TEXT,
        address TEXT
    )");

    // 5. Items Table
    $conn->exec("CREATE TABLE IF NOT EXISTS items (
        item_id INTEGER PRIMARY KEY AUTOINCREMENT,
        item_code TEXT UNIQUE NOT NULL,
        item_name TEXT NOT NULL,
        category TEXT,
        unit TEXT,
        current_stock INTEGER DEFAULT 0,
        reorder_level INTEGER DEFAULT 10
    )");

    // 6. GRN Table
    $conn->exec("CREATE TABLE IF NOT EXISTS grn (
        grn_id INTEGER PRIMARY KEY AUTOINCREMENT,
        grn_no TEXT UNIQUE NOT NULL,
        supplier_id INTEGER,
        received_date TEXT NOT NULL,
        remarks TEXT,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id)
    )");

    // 7. GRN Items Table
    $conn->exec("CREATE TABLE IF NOT EXISTS grn_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        grn_id INTEGER,
        item_id INTEGER,
        quantity INTEGER NOT NULL,
        unit_price REAL DEFAULT 0,
        FOREIGN KEY (grn_id) REFERENCES grn(grn_id),
        FOREIGN KEY (item_id) REFERENCES items(item_id)
    )");

    // 8. Issue Table
    $conn->exec("CREATE TABLE IF NOT EXISTS issue (
        issue_id INTEGER PRIMARY KEY AUTOINCREMENT,
        issue_no TEXT UNIQUE NOT NULL,
        section_id INTEGER,
        issued_date TEXT NOT NULL,
        remarks TEXT,
        FOREIGN KEY (section_id) REFERENCES sections(section_id)
    )");

    // 9. Issue Items Table
    $conn->exec("CREATE TABLE IF NOT EXISTS issue_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        issue_id INTEGER,
        item_id INTEGER,
        quantity INTEGER NOT NULL,
        FOREIGN KEY (issue_id) REFERENCES issue(issue_id),
        FOREIGN KEY (item_id) REFERENCES items(item_id)
    )");

    echo "<div style='font-family: Arial; padding: 20px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 5px; max-width: 600px; margin: 50px auto;'>";
    echo "<h2>✅ SQLite Database & Tables (Roles සහිතව) සාර්ථකව නිර්මාණය විය!</h2>";
    echo "<p><b>Default Login Details:</b></p>";
    echo "<ul>";
    echo "<li><b>Username:</b> admin</li>";
    echo "<li><b>Password:</b> admin123</li>";
    echo "</ul>";
    echo "<a href='index.php' style='display: inline-block; padding: 10px 15px; background-color: #28a745; color: white; text-decoration: none; border-radius: 3px;'>Login පිටුවට යන්න</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<div style='font-family: Arial; padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; max-width: 600px; margin: 50px auto;'>";
    echo "<h2>❌ Error එකක් සිදුවිය:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>