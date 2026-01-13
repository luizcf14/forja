<?php

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Core/Database.php';

class SettingsController extends Controller
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
            $this->redirect('/');
        }
    }

    public function index()
    {
        $message = '';
        $error = '';
        $gitOutput = '';

        // Handle Create User
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'user';

            if ($username && $password) {
                if ($this->db->createUser($username, $password, $role)) {
                    $message = "Usuário criado com sucesso.";
                } else {
                    $error = "Usuário já existe.";
                }
            } else {
                $error = "Usuário e senha são obrigatórios.";
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['git_pull'])) {
            $output = [];
            $returnVar = 0;
            // Execute git pull. 2>&1 redirects stderr to stdout to capture errors.
            // Ensure execution in project root
            $projectRoot = realpath(__DIR__ . '/../../');
            // Modified command to include cd and full output capture including sterr
            $command = "cd " . escapeshellarg($projectRoot) . " && git pull origin main 2>&1";
             
            exec($command, $output, $returnVar);
            
            $gitOutput = implode("\n", $output);
            
            if ($returnVar === 0) {
                 $message = "Git Pull executado com sucesso.";
            } else {
                 $error = "Erro ao executar Git Pull.";
            }
        }

        // Handle Service Control (Start/Stop)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['start_service'])) {
                $pid = $this->startParenteService();
                if ($pid) {
                    $message = "Serviço iniciado com sucesso. PID: $pid";
                } else {
                    $error = "Falha ao iniciar o serviço.";
                }
            } elseif (isset($_POST['stop_service'])) {
                if ($this->stopParenteService()) {
                    $message = "Serviço parado com sucesso.";
                } else {
                    $error = "Falha ao parar o serviço ou serviço não estava rodando.";
                }
            }
        }

        // Handle Delete User (logic mostly for GET if we keep the link style, 
        // but better to move to POST for security. Keeping GET for compatibility with previous view style for now)
        if (isset($_GET['delete'])) {
            if ($this->db->deleteUser($_GET['delete'])) {
                $message = "Usuário excluído com sucesso.";
            } else {
                $error = "Não foi possível excluir o usuário (Admin não pode ser excluído).";
            }
        }

        $users = $this->db->getAllUsers();
        $servicePid = $this->db->getSetting('parente_pid');
        $isServiceRunning = $servicePid && $this->isProcessRunning($servicePid);
        
        // If stored PID is dead, clear it
        if ($servicePid && !$isServiceRunning) {
            $this->db->setSetting('parente_pid', '');
            $servicePid = null;
        }

        $this->view('settings/index', [
            'users' => $users, 
            'message' => $message, 
            'error' => $error,
            'gitOutput' => $gitOutput,
            'servicePid' => $servicePid,
            'isServiceRunning' => $isServiceRunning
        ]);
    }

    private function startParenteService()
    {
        $projectRoot = realpath(__DIR__ . '/../../');
        $scriptPath = $projectRoot . '/src/python/parente.py';
        
        if (PHP_OS_FAMILY === 'Windows') {
            $command = "powershell -Command \"Start-Process python -ArgumentList '$scriptPath' -PassThru -NoNewWindow | Select-Object -ExpandProperty Id\"";
            $output = shell_exec($command);
            $pid = trim($output);
        } else {
            // Linux/Unix implementation
            // Use nohup to run in background, redirect output to /dev/null (or a log file if preferred)
            // echo $! prints the PID of the last background command
            $command = "nohup python3 " . escapeshellarg($scriptPath) . " > /dev/null 2>&1 & echo $!";
            $output = [];
            exec($command, $output);
            $pid = isset($output[0]) ? trim($output[0]) : false;
        }
        
        if (is_numeric($pid) && $pid > 0) {
            $this->db->setSetting('parente_pid', $pid);
            return $pid;
        }
        
        return false;
    }

    private function stopParenteService()
    {
        $pid = $this->db->getSetting('parente_pid');
        if (!$pid) return false;

        if (PHP_OS_FAMILY === 'Windows') {
            exec("taskkill /F /PID $pid", $output, $returnVar);
            // 0=Success, 128=No process found
            if ($returnVar === 0 || $returnVar === 128) {
                $this->db->setSetting('parente_pid', '');
                return true;
            }
        } else {
            // Linux implementation
            // Check if running first to avoid error? Or just kill.
            exec("kill " . $pid, $output, $returnVar);
            // 0 = success
            if ($returnVar === 0) {
                $this->db->setSetting('parente_pid', '');
                return true;
            }
        }
        
        return false;
    }

    private function isProcessRunning($pid)
    {
        if (!$pid) return false;
        
        if (PHP_OS_FAMILY === 'Windows') {
            $output = [];
            exec("tasklist /FI \"PID eq $pid\"", $output);
            foreach ($output as $line) {
                if (strpos($line, (string)$pid) !== false) {
                    return true;
                }
            }
        } else {
            // Linux implementation
            // ps -p PID returns header line and process line if exists
            $output = [];
            exec("ps -p " . $pid, $output);
            return count($output) > 1;
        }
        
        return false;
    }
}
