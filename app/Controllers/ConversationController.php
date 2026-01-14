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

        // Get User Phone
        $conversation = $this->db->getConversationById($conversationId);
        $userPhone = $conversation['user_id']; 

        // Send to Python Interface (internal_send)
        // Python handles logging to DB now
        
        $url = "http://localhost:3000/whatsapp/internal_send";
        $data = [
            'to' => $userPhone,
            'message' => $content
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
             http_response_code(500);
             echo json_encode(['error' => 'Failed to send via Python interface', 'details' => $response]);
             exit;
        }

        echo json_encode(['success' => true, 'response' => json_decode($response, true)]);
        exit;
    }
}
