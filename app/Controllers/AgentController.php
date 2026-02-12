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

        $agentId = $this->db->insertAgent([
            'user_id' => $_SESSION['user_id'] ?? null,
            'subject' => $subject,
            'type' => $type,
            'behaviour' => $behaviour,
            'details' => $details,
            'knowledge_base' => '', // Legacy, empty for new agents
            'status' => $status
        ]);

        if ($agentId) {
            $this->handleFileUploads($agentId);
            
            $this->db->logAction($_SESSION['user_id'] ?? 0, $_SESSION['user'], 'AGENT_CREATE', "Criou agente: $subject (ID: $agentId)");

            if ($this->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'redirect' => '/']);
                exit;
            }

            $_SESSION['success_message'] = "Agente criado com sucesso!";
            $this->redirect('/');
        } else {
            if ($this->isAjax()) {
                header('Content-Type: application/json');
                http_response_code(400); // Bad Request or Internal Server Error
                echo json_encode(['error' => 'Failed to create agent']);
                exit;
            }
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

        $data = [
            'subject' => $subject,
            'type' => $type,
            'behaviour' => $behaviour,
            'details' => $details,
            'status' => $status
        ];

        $this->db->updateAgent($id, $data);
        $this->handleFileUploads($id);

        $this->db->logAction($_SESSION['user_id'] ?? 0, $_SESSION['user'], 'AGENT_UPDATE', "Atualizou agente: $subject (ID: $id)");

        if ($this->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'redirect' => '/']);
            exit;
        }

        $_SESSION['success_message'] = "Agente atualizado com sucesso!";
        $this->redirect('/');
    }

    private function handleFileUploads($agentId)
    {
        if (isset($_FILES['knowledge_base'])) {
            $files = $_FILES['knowledge_base'];
            $uploadDir = __DIR__ . '/../../public/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Normalize files array
            $fileCount = is_array($files['name']) ? count($files['name']) : 1;
            
            for ($i = 0; $i < $fileCount; $i++) {
                $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
                if ($error === UPLOAD_ERR_OK) {
                    $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                    $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                    
                    $fileName = basename($name);
                    $fileName = time() . '_' . $i . '_' . $fileName; // Unique name
                    $targetPath = $uploadDir . $fileName;

                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $this->db->addAgentDocument($agentId, $fileName);
                        $this->db->logAction($_SESSION['user_id'] ?? 0, $_SESSION['user'], 'AGENT_UPLOAD', "Upload de arquivo para Agente $agentId: $fileName");
                    }
                }
            }
        }
    }

    public function deleteFile()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             header('Content-Type: application/json');
             echo json_encode(['error' => 'Method not allowed']);
             exit;
        }

        $docId = $_POST['id'] ?? null;
        if (!$docId) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'ID missing']);
            exit;
        }

        $doc = $this->db->getAgentDocumentById($docId);
        if (!$doc) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Document not found']);
            exit;
        }

        // Verify ownership (optional but good practice - here we assume session user matches agent owner or admin)
        // For simplicity in this iteration, we trust the session is valid as per constructor check.

        $uploadDir = __DIR__ . '/../../public/uploads/';
        $filePath = $uploadDir . $doc['filename'];

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        if ($this->db->deleteAgentDocument($docId)) {
            $this->db->logAction($_SESSION['user_id'] ?? 0, $_SESSION['user'], 'AGENT_FILE_DELETE', "Arquivo excluído: " . $doc['filename']);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Database deletion failed']);
        }
        exit;
    }

    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/');
        }

        $id = $_POST['id'] ?? null;
        if (!$id) {
            $this->redirect('/');
        }

        // Fetch agent and documents to delete files
        $agent = $this->db->getAgentById($id);
        if (!$agent) {
             $this->redirect('/');
        }

        // Delete documents from uploads
        $docs = $this->db->getAgentDocuments($id);
        $uploadDir = __DIR__ . '/../../public/uploads/';
        foreach ($docs as $doc) {
            $filePath = $uploadDir . $doc['filename'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // Delete legacy knowledge_base file if exists and not in docs
        if (!empty($agent['knowledge_base'])) {
             $filePath = $uploadDir . $agent['knowledge_base'];
             if (file_exists($filePath)) {
                unlink($filePath);
             }
        }

        if ($this->db->deleteAgent($id)) {
            $this->db->logAction($_SESSION['user_id'] ?? 0, $_SESSION['user'], 'AGENT_DELETE', "Excluiu agente: " . $agent['subject'] . " (ID: $id)");
            $_SESSION['success_message'] = "Agente excluído com sucesso!";
        } else {
             // Handle error
        }
        
        $this->redirect('/');
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
    private function isAjax() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
