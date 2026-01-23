<?php
require_once __DIR__ . '/../Core/Database.php';

class AuditController {
    private $db;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Ensure only admin can access
        if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
            header('Location: /');
            exit;
        }

        $this->db = new Database();
    }

    public function index() {
        $logs = $this->db->getAuditLogs(200); // Fetch last 200 logs
        require __DIR__ . '/../Views/audit/index.php';
    }
}
