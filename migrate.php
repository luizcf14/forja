<?php
// migrate.php

require_once __DIR__ . '/app/Core/Database.php';

$dbFile = __DIR__ . '/database.sqlite';
$migrationsDir = __DIR__ . '/database/migrations';

echo "Starting Database Migration System...\n";

try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Ensure migrations table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        migration TEXT UNIQUE NOT NULL,
        executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Get executed migrations
    $executed = $pdo->query("SELECT migration FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
    
    // 3. Scan for migration files
    $files = glob($migrationsDir . '/*.sql');
    natsort($files); // Sort naturally (001, 002, ...)

    foreach ($files as $file) {
        $filename = basename($file);
        
        if (in_array($filename, $executed)) {
            // echo "Skipping $filename (already executed)\n";
            continue;
        }

        echo "Migrating: $filename...\n";
        
        // Read SQL
        $sql = file_get_contents($file);
        
        try {
            // Execute SQL
            // Split by semicolon if multiple statements? 
            // SQLite exec() supports multiple statements in one string usually.
            $pdo->exec($sql);
            
            // Record migration
            $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
            $stmt->execute([$filename]);
            
            echo "Migrated: $filename\n";
            
        } catch (PDOException $e) {
            // Handle specific cases (like duplicate column) if we want to be robust for existing dbs
            // For now, fail hard so we know.
            // SPECIAL CASE: 002_add_ai_status might fail if 001 didn't run effectively but column exists logic...
            // Actually, if 001 ran and created tables, and 002 tries to add column, it works.
            // If the user already ran the OLD migrate.php, the column exists but 'migrations' table is empty.
            // So 002 will try to run and FAIL.
            
            // DETECTION: If error is "duplicate column name: ai_status"
            if (strpos($e->getMessage(), 'duplicate column') !== false) {
                 echo "Warning: Column already exists. Marking $filename as executed.\n";
                 $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
                 $stmt->execute([$filename]);
                 continue;
            }
            
            echo "Error executing $filename: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    echo "Migration completed successfully.\n";

} catch (Exception $e) {
    echo "Migration system error: " . $e->getMessage() . "\n";
    exit(1);
}
