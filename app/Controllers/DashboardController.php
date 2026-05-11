<?php

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Core/Database.php';

class DashboardController extends Controller
{
    private $db;
    private $pdo;

    public function __construct()
    {
        $this->db = new Database();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user'])) {
            $this->redirect('/');
        }
        // Get direct PDO access for custom queries
        $this->pdo = $this->db->getPdo();
    }

    public function index()
    {
        // ── 1. Novos contatos por dia (últimos 30 dias) ──────────────────────────
        $stmt = $this->pdo->query("
            SELECT date(last_message_at) AS day,
                   COUNT(*) AS total
            FROM conversations
            WHERE last_message_at >= date('now', '-30 days')
            GROUP BY day
            ORDER BY day ASC
        ");
        $rawContacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fill all 30 days
        $contactsByDay = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-$i days"));
            $contactsByDay[$day] = 0;
        }
        foreach ($rawContacts as $row) {
            if (isset($contactsByDay[$row['day']])) {
                $contactsByDay[$row['day']] = (int)$row['total'];
            }
        }

        // ── 2. Novas mensagens por dia (últimos 7 dias) ──────────────────────────
        $stmt = $this->pdo->query("
            SELECT date(created_at) AS day,
                   COUNT(*) AS total
            FROM messages
            WHERE created_at >= date('now', '-7 days')
            GROUP BY day
            ORDER BY day ASC
        ");
        $rawMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $messagesByDay = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-$i days"));
            $messagesByDay[$day] = 0;
        }
        foreach ($rawMessages as $row) {
            if (isset($messagesByDay[$row['day']])) {
                $messagesByDay[$row['day']] = (int)$row['total'];
            }
        }

        // ── 3. Ranking de pessoas que mais se comunicaram ─────────────────────────
        $stmt = $this->pdo->query("
            SELECT c.user_id,
                   COUNT(m.id) AS msg_count
            FROM messages m
            JOIN conversations c ON c.id = m.conversation_id
            GROUP BY c.user_id
            ORDER BY msg_count DESC
            LIMIT 10
        ");
        $ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── 4. Contador semanal de tipo de mensagem ───────────────────────────────
        $stmt = $this->pdo->query("
            SELECT
                CASE
                    WHEN media_type = 'audio' THEN 'audio'
                    WHEN media_type = 'video' THEN 'video'
                    WHEN media_type IS NULL OR media_type = '' THEN 'texto'
                    ELSE 'texto'
                END AS tipo,
                COUNT(*) AS total
            FROM messages
            WHERE created_at >= date('now', '-7 days')
              AND sender = 'user'
            GROUP BY tipo
        ");
        $rawMediaTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $mediaTypes = ['texto' => 0, 'audio' => 0, 'video' => 0];
        foreach ($rawMediaTypes as $row) {
            $mediaTypes[$row['tipo']] = (int)$row['total'];
        }

        // ── 5. Totais gerais ────────────────────────────────────────────────────
        $totalContacts = (int)$this->pdo->query("SELECT COUNT(*) FROM conversations")->fetchColumn();
        $totalMessages = (int)$this->pdo->query("SELECT COUNT(*) FROM messages WHERE sender = 'user'")->fetchColumn();
        $activeToday   = (int)$this->pdo->query("SELECT COUNT(DISTINCT conversation_id) FROM messages WHERE date(created_at) = date('now')")->fetchColumn();

        $this->view('dashboard/index', [
            'contactsByDay' => $contactsByDay,
            'messagesByDay' => $messagesByDay,
            'ranking'       => $ranking,
            'mediaTypes'    => $mediaTypes,
            'totalContacts' => $totalContacts,
            'totalMessages' => $totalMessages,
            'activeToday'   => $activeToday,
        ]);
    }

    public function apiStats()
    {
        header('Content-Type: application/json');

        $type = $_GET['type'] ?? 'all';

        if ($type === 'contacts_monthly') {
            $stmt = $this->pdo->query("
                SELECT date(last_message_at) AS day, COUNT(*) AS total
                FROM conversations
                WHERE last_message_at >= date('now', '-30 days')
                GROUP BY day ORDER BY day ASC
            ");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($type === 'messages_weekly') {
            $stmt = $this->pdo->query("
                SELECT date(created_at) AS day, COUNT(*) AS total
                FROM messages WHERE created_at >= date('now', '-7 days')
                GROUP BY day ORDER BY day ASC
            ");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } else {
            echo json_encode(['error' => 'Unknown type']);
        }
        exit;
    }
}
