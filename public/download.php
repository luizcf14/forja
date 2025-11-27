<?php
require_once __DIR__ . '/../src/Database.php';

if (!isset($_GET['id'])) {
    die("Invalid Request");
}

$id = (int) $_GET['id'];
$db = new Database();
$agent = $db->getAgentById($id);

if (!$agent) {
    die("Agent not found");
}

// Construct the Agno-compatible configuration
$config = [
    "agent_name" => $agent['subject'],
    "type" => $agent['type'],
    "instructions" => $agent['behaviour'],
    "additional_details" => $agent['details'],
    "knowledge_base" => [
        "file" => $agent['knowledge_base'] ? "uploads/" . $agent['knowledge_base'] : null,
        "type" => "LanceDB"
    ],
    "generated_at" => date('c')
];

$jsonConfig = json_encode($config, JSON_PRETTY_PRINT);

if (isset($_GET['download'])) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="agent_' . $id . '_config.json"');
    echo $jsonConfig;
    exit;
} else {
    // Preview mode (optional)
    echo "<pre>" . htmlspecialchars($jsonConfig) . "</pre>";
}
