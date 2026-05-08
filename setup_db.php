<?php

$dbFile = __DIR__ . '/database.sqlite';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Setting up database at $dbFile...\n";

// Create Users Table
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'user',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
echo "Table 'users' checked/created.\n";

// Create Agents Table
$pdo->exec("CREATE TABLE IF NOT EXISTS agents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    subject TEXT NOT NULL,
    type TEXT NOT NULL,
    behaviour TEXT NOT NULL,
    details TEXT,
    knowledge_base TEXT, -- Legacy column, kept for backward compatibility
    status TEXT DEFAULT 'development',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)");
echo "Table 'agents' checked/created.\n";

// Create Agent Documents Table
$pdo->exec("CREATE TABLE IF NOT EXISTS agent_documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    agent_id INTEGER NOT NULL,
    filename TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
)");
echo "Table 'agent_documents' checked/created.\n";

// Create Communication Evaluations Table
$pdo->exec("CREATE TABLE IF NOT EXISTS communication_evaluations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_identifier TEXT NOT NULL,
    trigger_message TEXT NOT NULL,
    last_messages TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
echo "Table 'communication_evaluations' checked/created.\n";

// Create Conversations Table (com colunas de consentimento LGPD)
$pdo->exec("CREATE TABLE IF NOT EXISTS conversations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id TEXT UNIQUE NOT NULL,
    ai_status TEXT DEFAULT 'active',
    last_message_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    lgpd_consent_status TEXT DEFAULT 'pending',
    lgpd_consent_at DATETIME,
    lgpd_awaiting_response INTEGER DEFAULT 0
)");
echo "Table 'conversations' checked/created.\n";

// Create Messages Table
$pdo->exec("CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id INTEGER NOT NULL,
    sender TEXT NOT NULL,
    content TEXT,
    media_type TEXT,
    media_url TEXT,
    is_read INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
)");
echo "Table 'messages' checked/created.\n";

// Create Settings Table (Key-Value Store)
$pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
    key TEXT PRIMARY KEY,
    value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
echo "Table 'system_settings' checked/created.\n";

// Seed Admin User
$adminUser = 'luizcf14';
$adminPass = 'qazx74123';
$adminRole = 'admin';

$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$adminUser]);
$user = $stmt->fetch();

if (!$user) {
    $hashedPass = password_hash($adminPass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->execute([$adminUser, $hashedPass, $adminRole]);
    echo "Admin user '$adminUser' created.\n";
} else {
    echo "Admin user '$adminUser' already exists. Updating password...\n";
    $hashedPass = password_hash($adminPass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ?, role = ? WHERE username = ?");
    $stmt->execute([$hashedPass, $adminRole, $adminUser]);
    echo "Admin user '$adminUser' password updated.\n";
}

echo "Database setup complete!\n";
