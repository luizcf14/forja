<?php

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Core/Database.php';

class UserController extends Controller
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

        // Handle Create User
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'user';

            if ($username && $password) {
                if ($this->db->createUser($username, $password, $role)) {
                    $message = "User created successfully.";
                } else {
                    $error = "Username already exists.";
                }
            } else {
                $error = "Username and password are required.";
            }
        }

        // Handle Delete User
        if (isset($_GET['delete'])) {
            if ($this->db->deleteUser($_GET['delete'])) {
                $message = "User deleted successfully.";
            } else {
                $error = "Could not delete user (Admin cannot be deleted).";
            }
        }

        $users = $this->db->getAllUsers();
        $this->view('users/index', ['users' => $users, 'message' => $message, 'error' => $error]);
    }
}
