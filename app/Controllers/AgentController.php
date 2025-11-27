<?php

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Core/Database.php';

class AgentController extends Controller
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

    public function create()
    {
        $this->view('agents/form', ['isEdit' => false]);
    }

    public function edit()
    {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $this->redirect('/');
        }

        $agent = $this->db->getAgentById($id);
        if (!$agent) {
            $this->redirect('/');
        }

        $this->view('agents/form', ['isEdit' => true, 'agent' => $agent]);
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/agents/create');
        }

        $subject = $_POST['subject'] ?? '';
        $type = $_POST['type'] ?? 'Fast';
        $behaviour = $_POST['behaviour'] ?? '';
        $details = $_POST['details'] ?? '';

        // Handle File Upload
        $knowledgeBase = '';
        if (isset($_FILES['knowledge_base']) && $_FILES['knowledge_base']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../public/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = basename($_FILES['knowledge_base']['name']);
            // Basic validation (ensure unique name to avoid overwrite issues in real app)
            $fileName = time() . '_' . $fileName;
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['knowledge_base']['tmp_name'], $targetPath)) {
                $knowledgeBase = $fileName;
            }
        }

        $agentId = $this->db->insertAgent([
            'user_id' => $_SESSION['user_id'] ?? null,
            'subject' => $subject,
            'type' => $type,
            'behaviour' => $behaviour,
            'details' => $details,
            'knowledge_base' => $knowledgeBase
        ]);

        if ($agentId) {
            if (isset($_POST['save_download'])) {
                $this->redirect("/agents/download?id=$agentId&download=1");
            } else {
                $_SESSION['success_message'] = "Agente criado com sucesso!";
                $this->redirect('/');
            }
        } else {
            // Handle error
            $this->view('agents/form', ['error' => 'Failed to create agent', 'isEdit' => false]);
        }
    }

    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/');
        }

        $id = $_POST['id'] ?? null;
        if (!$id) {
            $this->redirect('/');
        }

        $subject = $_POST['subject'] ?? '';
        $type = $_POST['type'] ?? 'Fast';
        $behaviour = $_POST['behaviour'] ?? '';
        $details = $_POST['details'] ?? '';

        // Handle File Upload
        $knowledgeBase = '';
        if (isset($_FILES['knowledge_base']) && $_FILES['knowledge_base']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../public/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = basename($_FILES['knowledge_base']['name']);
            $fileName = time() . '_' . $fileName;
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['knowledge_base']['tmp_name'], $targetPath)) {
                $knowledgeBase = $fileName;
            }
        }

        $data = [
            'subject' => $subject,
            'type' => $type,
            'behaviour' => $behaviour,
            'details' => $details
        ];

        if ($knowledgeBase) {
            $data['knowledge_base'] = $knowledgeBase;
        }

        $this->db->updateAgent($id, $data);

        if (isset($_POST['save_download'])) {
            $this->redirect("/agents/download?id=$id&download=1");
        } else {
            $_SESSION['success_message'] = "Agente atualizado com sucesso!";
            $this->redirect('/');
        }
    }

    public function download()
    {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            die("ID inválido.");
        }

        $agent = $this->db->getAgentById($id);

        if (!$agent) {
            die("Agente não encontrado.");
        }

        $agentData = [
            'name' => $agent['subject'],
            'type' => $agent['type'],
            'behaviour' => $agent['behaviour'],
            'details' => $agent['details'],
            'knowledge_base' => $agent['knowledge_base']
        ];

        $json = json_encode($agentData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = 'agent_' . $agent['id'] . '.json';

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $json;
        exit;
    }
}
