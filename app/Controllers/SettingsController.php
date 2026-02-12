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

        // Handle Change Password
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $error = "Todos os campos de senha são obrigatórios.";
            } elseif ($newPassword !== $confirmPassword) {
                $error = "A nova senha e a confirmação não coincidem.";
            } else {
                // Verify current password
                $user = $this->db->getUserById($_SESSION['user_id']);
                if ($user && password_verify($currentPassword, $user['password'])) {
                    // Update password
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    if ($this->db->updateUserPassword($_SESSION['user_id'], $newHash)) {
                        $message = "Senha alterada com sucesso.";
                        $this->db->logAction($_SESSION['user_id'], $_SESSION['user'], 'PASSWORD_CHANGE', "Alterou a própria senha.");
                    } else {
                        $error = "Erro ao atualizar a senha no banco de dados.";
                    }
                } else {
                    $error = "Senha atual incorreta.";
                    $this->db->logAction($_SESSION['user_id'], $_SESSION['user'], 'PASSWORD_CHANGE_FAILED', "Tentativa falha de alterar senha (senha atual incorreta).");
                }
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
            } elseif (isset($_POST['restart_service'])) {
                $this->stopParenteService(); // Try to stop, ignore result (start fresh)
                sleep(1); // Give it a moment to release resources
                $pid = $this->startParenteService();
                if ($pid) {
                    $message = "Serviço reiniciado com sucesso. Novo PID: $pid";
                } else {
                    $error = "Falha ao reiniciar o serviço.";
                }
            }
        }


        // Handle Delete User Request
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_request'])) {
            $requestId = $_POST['request_id'] ?? null;
            if ($requestId) {
                if ($this->db->deleteUserRequest($requestId)) {
                    $message = "Pedido excluído com sucesso.";
                } else {
                    $error = "Erro ao excluir pedido.";
                }
            }
        }

        if (isset($gitOutput) && !empty($gitOutput)) {
             // Keep git output logic... or cleaner: 
        }

        $users = $this->db->getAllUsers();
        $servicePid = $this->db->getSetting('parente_pid');
        $isServiceRunning = $servicePid && $this->isProcessRunning($servicePid);
        
        // If stored PID is dead, clear it
        if ($servicePid && !$isServiceRunning) {
            $this->db->setSetting('parente_pid', '');
            $servicePid = null;
        }

        $serviceLogs = $this->getServiceLogs();
        $userRequests = $this->db->getUserRequests();

        $this->view('settings/index', [
            'users' => $users, 
            'message' => $message, 
            'error' => $error,
            'gitOutput' => $gitOutput,
            'servicePid' => $servicePid,
            'isServiceRunning' => $isServiceRunning,
            'serviceLogs' => $serviceLogs,
            'userRequests' => $userRequests
        ]);
    }

    private function getServiceLogs($lines = 50)
    {
        $logFile = realpath(__DIR__ . '/../../') . '/storage/logs/parente.log';
        if (!file_exists($logFile)) {
            return "Arquivo de log não encontrado ou vazio.";
        }

        // Simple tail implementation
        $data = file($logFile);
        if (!$data) return "Log vazio.";
        
        $lines = array_slice($data, -$lines);
        return implode("", $lines);
    }

    private function startParenteService()
    {
        $projectRoot = realpath(__DIR__ . '/../../');
        $scriptPath = $projectRoot . '/src/python/main.py';
        
        // Ensure log directory exists
        $logDir = $projectRoot . '/storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/parente.log';
        
        if (PHP_OS_FAMILY === 'Windows') {
            // PowerShell: -RedirectStandardOutput and -RedirectStandardError
            // Note: Start-Process with redirection works well.
            $command = "powershell -Command \"Start-Process python -ArgumentList '$scriptPath' -RedirectStandardOutput '$logFile' -RedirectStandardError '$logFile' -PassThru -NoNewWindow | Select-Object -ExpandProperty Id\"";
            $output = shell_exec($command);
            $pid = trim($output);
        } else {
            // Linux/Unix implementation
            // Redirect both stdout and stderr to log file
            $command = "nohup python3 " . escapeshellarg($scriptPath) . " >> " . escapeshellarg($logFile) . " 2>&1 & echo $!";
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
