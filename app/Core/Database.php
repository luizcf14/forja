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
