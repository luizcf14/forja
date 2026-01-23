<?php
require_once __DIR__ . '/../Core/Database.php';

class NotificationController {
    private $db;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Ensure only admin can access
        if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
            header('Location: /');
            exit;
        }

        $this->db = new Database();
    }

    public function index() {
        $message = null;
        $error = null;
        $sendCount = 0;
        $failCount = 0;

        // Fetch users from conversations
        // user_id is unique in conversations table
        $conversations = $this->db->getConversations();
        // Extract just the IDs
        $users = [];
        foreach ($conversations as $conv) {
             $users[] = $conv['user_id'];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $text = $_POST['message'] ?? '';
            $selectedUsers = $_POST['recipients'] ?? [];
            
            if (empty($text)) {
                $error = "A mensagem não pode estar vazia.";
            } elseif (empty($selectedUsers)) {
                $error = "Selecione pelo menos um destinatário.";
            } else {
                // Send messages
                foreach ($selectedUsers as $recipient) {
                    if ($this->sendInternalMessage($recipient, $text)) {
                        $sendCount++;
                    } else {
                        $failCount++;
                    }
                }
                
                if ($failCount === 0) {
                    $message = "Mensagem enviada com sucesso para $sendCount usuários.";
                    $logDetails = json_encode([
                        'type' => 'mass_text',
                        'recipient_count' => $sendCount,
                        'content' => $text,
                        'description' => "Enviou mensagem em massa para $sendCount usuários"
                    ], JSON_UNESCAPED_UNICODE);
                    $this->db->logAction($_SESSION['user_id'] ?? 0, $_SESSION['user'], 'NOTIFICATION_SEND', $logDetails);
                } else {
                    $error = "Enviado para $sendCount usuários. Falha em $failCount envios.";
                    $logDetails = json_encode([
                        'type' => 'mass_text',
                        'recipient_count' => $sendCount,
                        'fail_count' => $failCount,
                        'content' => $text,
                        'description' => "Tentativa de envio em massa (Sucesso: $sendCount, Falha: $failCount)"
                    ], JSON_UNESCAPED_UNICODE);
                    $this->db->logAction($_SESSION['user_id'] ?? 0, $_SESSION['user'], 'NOTIFICATION_SEND', $logDetails);
                }
            }
        }

        require __DIR__ . '/../Views/notifications/index.php';
    }

    private function sendInternalMessage($to, $message) {
        $url = 'http://localhost:3000/whatsapp/internal_send';
        $data = [
            'to' => $to,
            'message' => $message
        ];

        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($data),
                'timeout' => 5 // 5 seconds timeout
            ]
        ];

        $context  = stream_context_create($options);
        try {
            $result = @file_get_contents($url, false, $context);
            if ($result === FALSE) {
                // Log error if needed
                return false;
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
