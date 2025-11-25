<?php
try {
    $db = new PDO('sqlite::memory:');
    echo "SQLite is working!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
