<?php
// fix_db.php
require_once 'db.php';

echo "<h2>Fixing Database Schema...</h2>";

// --- 1. SUPPLIERS TABLE FIXES ---
$supplier_cols = [
    "supplier_code TEXT",
    "contact_person TEXT",
    "contact_no TEXT",
    "address TEXT"
];

foreach ($supplier_cols as $col) {
    try {
        $conn->exec("ALTER TABLE suppliers ADD COLUMN $col;");
        echo "<p style='color:green;'>✔️ Suppliers: Added column ($col)</p>";
    } catch (PDOException $e) {
        echo "<p style='color:gray;'>ℹ️ Suppliers: Column already exists or skipped.</p>";
    }
}

// --- 2. DEPARTMENTS TABLE FIXES ---
$dept_cols = [
    "department_code TEXT",
    "dept_code TEXT",
    "location TEXT",
    "responsible_officer TEXT"
];

foreach ($dept_cols as $col) {
    try {
        $conn->exec("ALTER TABLE departments ADD COLUMN $col;");
        echo "<p style='color:green;'>✔️ Departments: Added column ($col)</p>";
    } catch (PDOException $e) {
        echo "<p style='color:gray;'>ℹ️ Departments: Column already exists or skipped.</p>";
    }
}

echo "<hr><p style='font-size:18px;'><b>Fix Complete!</b></p>";
echo "<a href='javascript:history.back()' style='font-size:16px; font-weight:bold;'>⬅️ Go Back & Try Again</a>";
?>