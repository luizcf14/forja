<?php
require_once __DIR__ . '/app/Core/Database.php';

$dbFile = __DIR__ . '/database.sqlite';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Migrating database...\n";

try {
    // Check if column exists
    $stmt = $pdo->query("PRAGMA table_info(agents)");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);

    if (!in_array('status', $columns)) {
        echo "Adding 'status' column to 'agents' table...\n";
        $pdo->exec("ALTER TABLE agents ADD COLUMN status TEXT DEFAULT 'development'");
        echo "Column 'status' added successfully.\n";
    } else {
        echo "Column 'status' already exists.\n";
    }

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}

echo "Migration complete.\n";
