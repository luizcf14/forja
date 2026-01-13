<?php
require_once __DIR__ . '/app/Core/Database.php';

$db = new Database();
echo "Conversations:\n";
print_r($db->getConversations());
echo "\nMessages:\n";
// Get first conversation ID if exists
$conversations = $db->getConversations();
if (!empty($conversations)) {
    print_r($db->getMessages($conversations[0]['id']));
} else {
    echo "No conversations found.\n";
}
