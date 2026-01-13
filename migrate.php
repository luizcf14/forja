<?php
require_once __DIR__ . '/app/Core/Database.php';

echo "Starting Database Migration...\n";

try {
    $db = new Database();
    echo "Database tables checked/created successfully.\n";
    
    // Optional: Add specific migration logic here if needed in the future
    // For now, Database::__construct() -> initDb() handles table creation (IF NOT EXISTS).
    
    echo "Migration completed.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
