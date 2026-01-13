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

        // Handle Git Pull
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['git_pull'])) {
            $output = [];
            $returnVar = 0;
            // Execute git pull. 2>&1 redirects stderr to stdout to capture errors.
            // Ensure git is in PATH or use absolute path.
            exec('git pull 2>&1', $output, $returnVar);
            
            $gitOutput = implode("\n", $output);
            
            if ($returnVar === 0) {
                 $message = "Git Pull executado com sucesso.";
            } else {
                 $error = "Erro ao executar Git Pull.";
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
        $this->view('settings/index', [
            'users' => $users, 
            'message' => $message, 
            'error' => $error,
            'gitOutput' => $gitOutput
        ]);
    }
}
