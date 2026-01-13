<?php
require_once __DIR__ . '/app/Core/Database.php';

$db = new Database();

// Use Reflection or raw PDO since logMessage is not in Database class or we didn't add it there?
// We added tables but maybe not a logMessage method in Database.php?
// Implementation plan said "Add methods... logMessage".
// Let's check Database.php content again or just use raw PDO here.
// I think I only added getConversations, getMessages. Python does logging directly.
// So I will use raw PDO here.

try {
    $pdo = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $userId = "5511988887777";
    
    // Create/Update Conversation
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO conversations (user_id) VALUES (?)");
    $stmt->execute([$userId]);
    
    $stmt = $pdo->prepare("SELECT id FROM conversations WHERE user_id = ?");
    $stmt->execute([$userId]);
    $convId = $stmt->fetchColumn();
    
    // Insert Messages
    $messages = [
        ['user', 'Olá, tudo bem?'],
        ['agent', 'Olá! Sou o Parente, seu assistente. Como posso ajudar?'],
        ['user', 'Queria saber sobre o projeto.'],
        ['agent', 'O projeto Conexão Povos da Floresta visa...']
    ];
    
    foreach ($messages as $msg) {
        $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender, content) VALUES (?, ?, ?)");
        $stmt->execute([$convId, $msg[0], $msg[1]]);
    }
    
    echo "Seed successful.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
