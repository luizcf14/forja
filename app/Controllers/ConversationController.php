<?php

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Core/Database.php';

class ConversationController extends Controller
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user'])) {
            $this->redirect('/');
        }
    }

    public function index()
    {
        $conversations = $this->db->getConversations();
        $this->view('conversations/index', ['conversations' => $conversations]);
    }

    public function show()
    {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $this->redirect('/conversations');
        }

        $conversation = $this->db->getConversationById($id);
        if (!$conversation) {
            $this->redirect('/conversations');
        }

        $messages = $this->db->getMessages($id);
        
        $this->view('conversations/show', [
            'conversation' => $conversation, 
            'messages' => $messages
        ]);
    }

    public function apiMessages()
    {
        header('Content-Type: application/json');
        
        $conversationId = $_GET['conversation_id'] ?? null;
        $lastId = $_GET['last_id'] ?? 0;

        if (!$conversationId) {
            echo json_encode([]);
            exit;
        }

        $messages = $this->db->getMessagesAfter($conversationId, $lastId);
        
        // Also return the current AI Status
        $aiStatus = $this->db->getConversationAiStatus($conversationId);
        
        // Enhance messages/response if needed, or better, make a separate endpoint for status polling
        // For simplicity, let's just return messages here.
        // Ideally we should wrap this in a { messages: [], meta: { ai_status: ... } } but that breaks existing frontend.
        // We will make a separate endpoint for status toggling which returns status.
        
        echo json_encode($messages);
        exit;
    }

    public function toggleAi()
    {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        $conversationId = $input['conversation_id'] ?? null;
        $status = $input['status'] ?? null; // 'active' or 'paused'

        if (!$conversationId || !$status) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing parameters']);
            exit;
        }

        $success = $this->db->setConversationAiStatus($conversationId, $status);
        echo json_encode(['success' => $success, 'status' => $status]);
        exit;
    }

    public function sendMessage()
    {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        $conversationId = $input['conversation_id'] ?? null;
        $content = $input['content'] ?? null;

        if (!$conversationId || !$content) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing parameters']);
            exit;
        }
        
        // 1. Save to Database (as 'agent' sender, or maybe a new type 'manual_agent'?)
        // The requester asked for "manual response". It is essentially the agent (system) speaking but manually triggered.
        // Let's stick to 'agent' so it shows up on the Right side.
        
        // Need to add insertMessage method or use raw query.
        // There is no insertMessage in Database.php yet?
        // Checking Database.php... wait, LoggingTeam inserts into 'messages'.
        // Database.php does NOT have insertMessage. I need to add it or do raw query.
        // I'll do raw insert here for now or add method to Database.php. Better add to Database.php
        
        // ... Wait, let me check Database.php again. It has insertAgent, createUser... no insertMessage.
        // I will add insertMessage to Database.php in next tool call, or do it raw here.
        // Let's do raw here for speed, or better: add to Database.php for consistency. 
        // I will add insertMessage to ConversationController via DB instance.
        
        // Actually, let's assume I'll add `insertMessage` to Database.php.
        $this->db->insertMessage($conversationId, 'agent', $content);

        // 2. Send to WhatsApp
        $conversation = $this->db->getConversationById($conversationId);
        $userPhone = $conversation['user_id']; // user_id in conversations table is the phone number
        
        $result = $this->sendWhatsAppMessage($userPhone, $content);

        echo json_encode(['success' => true, 'whatsapp_response' => $result]);
        exit;
    }

    private function sendWhatsAppMessage($to, $message)
    {
        $token = $_ENV['WHATSAPP_ACCESS_TOKEN'] ?? getenv('WHATSAPP_ACCESS_TOKEN');
        $phoneId = $_ENV['WHATSAPP_PHONE_NUMBER_ID'] ?? getenv('WHATSAPP_PHONE_NUMBER_ID');
        
        // Fallback for loading .env if $_ENV is empty
        if (!$token || !$phoneId) {
             // Basic .env parser for this context
             if (file_exists(__DIR__ . '/../../.env')) {
                 $lines = file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                 foreach ($lines as $line) {
                     if (strpos(trim($line), '#') === 0) continue;
                     list($name, $value) = explode('=', $line, 2);
                     if (trim($name) == 'WHATSAPP_ACCESS_TOKEN') $token = trim($value);
                     if (trim($name) == 'WHATSAPP_PHONE_NUMBER_ID') $phoneId = trim($value);
                 }
             }
        }

        if (!$token || !$phoneId) {
            return ['error' => 'Missing WhatsApp credentials'];
        }

        $url = "https://graph.facebook.com/v21.0/{$phoneId}/messages";
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'text' => ['body' => $message]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}
