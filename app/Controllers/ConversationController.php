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
        echo json_encode($messages);
        exit;
    }
}
