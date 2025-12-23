<?php

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Core/Database.php';

class TestController extends Controller
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
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $this->redirect('/');
        }

        $agent = $this->db->getAgentById($id);
        if (!$agent) {
            $this->redirect('/');
        }

        $sessionId = uniqid('sess_', true);
        $this->view('agents/test', ['agent' => $agent, 'session_id' => $sessionId]);
    }

    public function chat()
    {
        set_time_limit(300); // Increase execution time to 5 minutes
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $agentId = $input['agent_id'] ?? null;
        $message = $input['message'] ?? '';
        $sessionId = $input['session_id'] ?? null;
        $history = $input['history'] ?? [];

        if (!$agentId || !$message || !$sessionId) {
            echo json_encode(['error' => 'Missing agent_id, message, or session_id']);
            exit;
        }

        $agent = $this->db->getAgentById($agentId);
        if (!$agent) {
            echo json_encode(['error' => 'Agent not found']);
            exit;
        }

        // Prepare arguments for Python script

        $pythonScript = __DIR__ . '/../../src/python/chat_agent.py';


        // Ensure the script exists
        if (!file_exists($pythonScript)) {
            echo json_encode(['error' => 'Python script not found']);
            exit;
        }

        // We'll pass data via a temporary JSON file to avoid command line argument issues
        $tempInputFile = tempnam(sys_get_temp_dir(), 'agent_chat_in_');
        $tempOutputFile = tempnam(sys_get_temp_dir(), 'agent_chat_out_');

        // Prepare knowledge base files list
        $kbFiles = [];
        if (!empty($agent['knowledge_base'])) {
            $kbPath = realpath(__DIR__ . '/../../public/uploads/' . $agent['knowledge_base']);
            if ($kbPath && file_exists($kbPath)) {
                $kbFiles[] = $kbPath;
            }
        }

        $inputData = [
            'agent' => $agent,
            'message' => $message,
            'session_id' => $sessionId,
            'history' => $history,
            'knowledge_base_files' => $kbFiles
        ];
        file_put_contents($tempInputFile, json_encode($inputData));

        // Execute Python script
        // Pass input file and output file paths
        $command = "python " . escapeshellarg($pythonScript) . " " . escapeshellarg($tempInputFile) . " " . escapeshellarg($tempOutputFile);

        $output = shell_exec($command . " 2>&1"); // Capture stderr too for debugging

        // Read output from file
        if (file_exists($tempOutputFile)) {
            $jsonOutput = file_get_contents($tempOutputFile);
            $responseData = json_decode($jsonOutput, true);
            unlink($tempOutputFile); // Cleanup output file
        } else {
            $responseData = null;
        }

        // Cleanup input file
        unlink($tempInputFile);

        if ($responseData && json_last_error() === JSON_ERROR_NONE) {
            echo json_encode($responseData);
        } else {
            // If not JSON, it might be an error message from Python or shell
            echo json_encode(['error' => 'Raw output: ' . $output . ' | JSON Error: ' . json_last_error_msg()]);
        }
        exit;
    }
}
