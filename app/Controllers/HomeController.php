<?php

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Core/Database.php';

class HomeController extends Controller
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function index()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Auth Logic
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            $user = $this->db->getUserByUsername($username);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user'] = $user['username'];
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $this->redirect('/');
            } else {
                $error = "Invalid credentials";
                $this->view('home/index', ['error' => $error]);
                return;
            }
        }

        if (isset($_GET['logout'])) {
            session_destroy();
            $this->redirect('/');
        }

        $agents = [];
        if (isset($_SESSION['user'])) {
            $agents = $this->db->getAllAgents();
        }

        $this->view('home/index', ['agents' => $agents]);
    }
}
