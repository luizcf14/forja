<?php
require_once __DIR__ . '/src/Database.php';

$jsonFile = __DIR__ . '/database.json';
if (!file_exists($jsonFile)) {
    die("No database.json found to migrate.");
}

$data = json_decode(file_get_contents($jsonFile), true);
$db = new Database();

echo "Migrating Users...\n";
if (isset($data['users'])) {
    foreach ($data['users'] as $user) {
        // Check if user exists (admin is seeded by constructor)
        $existing = $db->getUserByUsername($user['username']);
        if (!$existing) {
            // We can't easily preserve IDs with AUTOINCREMENT unless we force it, 
            // but for this simple app, re-creating is fine. 
            // Password hash is already hashed, so we need a raw insert or modify createUser to accept hash.
            // Let's use raw insert to preserve everything including ID and Hash.

            // Actually, Database class doesn't expose raw PDO. 
            // Let's just use createUser for simplicity, but we need to handle the password.
            // If we use createUser, it will re-hash the hash. Bad.

            // Let's modify Database.php to allow raw access OR just do raw PDO here.
            // Since Database.php is already refactored, I can't access $pdo (private).
            // I will use a separate PDO connection here for migration to be safe and precise.
        }
    }
}

// Re-connect raw for precise migration
try {
    $pdo = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Migration DB connection failed: " . $e->getMessage());
}

// Migrate Users
if (isset($data['users'])) {
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (id, username, password, role, created_at) VALUES (?, ?, ?, ?, ?)");
    foreach ($data['users'] as $user) {
        $stmt->execute([
            $user['id'],
            $user['username'],
            $user['password'], // Already hashed
            $user['role'],
            $user['created_at']
        ]);
        echo "Migrated user: {$user['username']}\n";
    }
}

// Migrate Agents
echo "Migrating Agents...\n";
if (isset($data['agents'])) {
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO agents (id, user_id, subject, type, behaviour, details, knowledge_base, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($data['agents'] as $agent) {
        $stmt->execute([
            $agent['id'],
            $agent['user_id'] ?? 1, // Default to admin (id 1) if missing
            $agent['subject'],
            $agent['type'],
            $agent['behaviour'],
            $agent['details'],
            $agent['knowledge_base'],
            $agent['created_at']
        ]);
        echo "Migrated agent: {$agent['subject']}\n";
    }
}

echo "Migration Complete!\n";
