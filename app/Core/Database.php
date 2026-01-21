<?php

class Database
{
    private $pdo;

    public function __construct()
    {
        try {
            $dbFile = __DIR__ . '/../../database.sqlite';
            $this->pdo = new PDO('sqlite:' . $dbFile);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->initDb();
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    private function initDb()
    {
        // Create Users Table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT DEFAULT 'user',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Create Agents Table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS agents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            subject TEXT,
            type TEXT,
            behaviour TEXT,
            details TEXT,
            knowledge_base TEXT,
            status TEXT DEFAULT 'development',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )");

        // Create Agent Documents Table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS agent_documents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            agent_id INTEGER NOT NULL,
            filename TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
        )");

        // Seed Admin if not exists
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $this->createUser('admin', 'password', 'admin');
        }

        // Create Conversations Table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS conversations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id TEXT UNIQUE NOT NULL,
            last_message_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            ai_status TEXT DEFAULT 'active'
        )");

        // Create Messages Table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            conversation_id INTEGER NOT NULL,
            sender TEXT NOT NULL, -- 'user' or 'agent'
            content TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
        )");

        // Create Settings Table (Key-Value Store)
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
            key TEXT PRIMARY KEY,
            value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    // --- SYSTEM SETTINGS ---

    public function getSetting($key)
    {
        $stmt = $this->pdo->prepare("SELECT value FROM system_settings WHERE key = ?");
        $stmt->execute([$key]);
        return $stmt->fetchColumn();
    }

    public function setSetting($key, $value)
    {
        $sql = "INSERT INTO system_settings (key, value, updated_at) VALUES (:key, :value, CURRENT_TIMESTAMP)
                ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':key' => $key, ':value' => $value]);
    }

    // --- CONVERSATIONS ---

    public function getConversations()
    {
        $stmt = $this->pdo->query("SELECT * FROM conversations ORDER BY last_message_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getConversationById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM conversations WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getConversationByUserId($userId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM conversations WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getMessages($conversationId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
        $stmt->execute([$conversationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMessagesAfter($conversationId, $lastId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM messages WHERE conversation_id = ? AND id > ? ORDER BY created_at ASC");
        $stmt->execute([$conversationId, $lastId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getConversationAiStatus($conversationId)
    {
        $stmt = $this->pdo->prepare("SELECT ai_status FROM conversations WHERE id = ?");
        $stmt->execute([$conversationId]);
        return $stmt->fetchColumn();
    }

    public function setConversationAiStatus($conversationId, $status)
    {
        $stmt = $this->pdo->prepare("UPDATE conversations SET ai_status = ? WHERE id = ?");
        return $stmt->execute([$status, $conversationId]);
    }



    public function countUnreadMessages($conversationId)
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM messages WHERE conversation_id = ? AND is_read = 0 AND sender = 'user'");
        $stmt->execute([$conversationId]);
        return $stmt->fetchColumn();
    }

    public function markMessagesAsRead($conversationId)
    {
        $stmt = $this->pdo->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender = 'user'");
        return $stmt->execute([$conversationId]);
    }

    public function insertMessage($conversationId, $sender, $content, $mediaType = null, $mediaUrl = null)
    {
        // Agent messages are read by default, user messages are unread
        $isRead = ($sender !== 'user') ? 1 : 0;
        
        $stmt = $this->pdo->prepare("INSERT INTO messages (conversation_id, sender, content, is_read, media_type, media_url) VALUES (:conversation_id, :sender, :content, :is_read, :media_type, :media_url)");
        return $stmt->execute([
            ':conversation_id' => $conversationId,
            ':sender' => $sender,
            ':content' => $content,
            ':is_read' => $isRead,
            ':media_type' => $mediaType,
            ':media_url' => $mediaUrl
        ]);
    }

    // --- AGENT DOCUMENTS ---

    public function addAgentDocument($agentId, $filename)
    {
        $stmt = $this->pdo->prepare("INSERT INTO agent_documents (agent_id, filename, created_at) VALUES (?, ?, ?)");
        return $stmt->execute([$agentId, $filename, date('Y-m-d H:i:s')]);
    }

    public function getAgentDocuments($agentId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM agent_documents WHERE agent_id = ? ORDER BY id ASC");
        $stmt->execute([$agentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAgentDocumentById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM agent_documents WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteAgentDocument($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM agent_documents WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // --- AGENTS ---

    public function getAllAgents()
    {
        $stmt = $this->pdo->query("SELECT * FROM agents ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAgentById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM agents WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function insertAgent($data)
    {
        $sql = "INSERT INTO agents (user_id, subject, type, behaviour, details, knowledge_base, status, created_at) 
                VALUES (:user_id, :subject, :type, :behaviour, :details, :knowledge_base, :status, :created_at)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $data['user_id'] ?? null,
            ':subject' => $data['subject'] ?? '',
            ':type' => $data['type'] ?? '',
            ':behaviour' => $data['behaviour'] ?? '',
            ':details' => $data['details'] ?? '',
            ':knowledge_base' => $data['knowledge_base'] ?? '',
            ':status' => $data['status'] ?? 'development',
            ':created_at' => date('Y-m-d H:i:s')
        ]);

        return $this->pdo->lastInsertId();
    }

    public function updateAgent($id, $data)
    {
        $sql = "UPDATE agents SET 
                subject = :subject, 
                type = :type, 
                behaviour = :behaviour, 
                details = :details,
                status = :status";

        // Only update knowledge_base if a new file was uploaded
        if (!empty($data['knowledge_base'])) {
            $sql .= ", knowledge_base = :knowledge_base";
        }

        $sql .= " WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);

        $params = [
            ':id' => $id,
            ':subject' => $data['subject'] ?? '',
            ':type' => $data['type'] ?? '',
            ':behaviour' => $data['behaviour'] ?? '',
            ':details' => $data['details'] ?? '',
            ':status' => $data['status'] ?? 'development'
        ];

        if (!empty($data['knowledge_base'])) {
            $params[':knowledge_base'] = $data['knowledge_base'];
        }

        return $stmt->execute($params);
    }

    public function deleteAgent($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM agents WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // --- USERS ---

    public function getAllUsers()
    {
        $stmt = $this->pdo->query("SELECT * FROM users ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getUserByUsername($username)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createUser($username, $password, $role = 'user')
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO users (username, password, role, created_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $username,
                password_hash($password, PASSWORD_DEFAULT),
                $role,
                date('Y-m-d H:i:s')
            ]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            return false; // Likely username duplicate
        }
    }

    public function deleteUser($id)
    {
        // Prevent deleting admin (id 1 or username admin)
        $user = $this->getUserById($id);
        if (!$user || $user['username'] === 'admin')
            return false;

        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
