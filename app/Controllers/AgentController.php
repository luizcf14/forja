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
        $status = isset($_POST['is_production']) ? 'production' : 'development';

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
            'knowledge_base' => $knowledgeBase,
            'status' => $status
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
        $status = isset($_POST['is_production']) ? 'production' : 'development';

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
            'behaviour' => $behaviour,
            'details' => $details,
            'status' => $status
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
    public function optimize()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $input = $_POST['text'] ?? '';
        if (empty($input)) {
            // Try reading raw input if not in POST (e.g. JSON body)
            $rawInput = json_decode(file_get_contents('php://input'), true);
            $input = $rawInput['text'] ?? '';
        }

        if (empty($input)) {
            echo json_encode(['error' => 'No text provided']);
            exit;
        }

        $pythonScript = __DIR__ . '/../../src/python/optimizer.py';
        if (!file_exists($pythonScript)) {
            echo json_encode(['error' => 'Optimizer script not found']);
            exit;
        }

        // Use a temporary file to pass input to avoid shell escaping issues with large text
        $tempInputFile = tempnam(sys_get_temp_dir(), 'opt_in_');
        // The python script expects JSON input with a "text" key if reading from file/stdin
        file_put_contents($tempInputFile, json_encode(['text' => $input]));

        // Execute Python script
        // We pipe the file content to stdin
        $command = "type " . escapeshellarg($tempInputFile) . " | python " . escapeshellarg($pythonScript);

        $output = shell_exec($command . " 2>&1");

        unlink($tempInputFile);

        // Try to parse output as JSON
        $responseData = json_decode($output, true);

        if ($responseData && json_last_error() === JSON_ERROR_NONE) {
            echo json_encode($responseData);
        } else {
            echo json_encode(['error' => 'Raw output: ' . $output]);
        }
        exit;
    }
}
