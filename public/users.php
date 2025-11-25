<?php
session_start();
require_once __DIR__ . '/../src/Database.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$db = new Database();
$message = '';
$error = '';

// Handle Create User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    if ($username && $password) {
        if ($db->createUser($username, $password, $role)) {
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
    if ($db->deleteUser($_GET['delete'])) {
        $message = "User deleted successfully.";
    } else {
        $error = "Could not delete user (Admin cannot be deleted).";
    }
}

// Fetch all users
$users = $db->getAllUsers();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Agent Forge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-robot"></i> Agent Forge
            </a>
            <div class="d-flex align-items-center">
                <a href="index.php" class="btn btn-outline-secondary btn-sm me-2">Voltar ao Painel</a>
                <a href="index.php?logout=1" class="btn btn-outline-danger btn-sm">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container" style="margin-top: 100px;">
        <div class="row">
            <div class="col-md-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Criar Novo Usuário</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?= $message ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Usuário</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Senha</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Função</label>
                                <select name="role" class="form-select">
                                    <option value="user">Usuário</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <button type="submit" name="create_user" class="btn btn-primary w-100">Criar Usuário</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Usuários Existentes</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Usuário</th>
                                        <th>Função</th>
                                        <th>Criado Em</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?= $user['id'] ?></td>
                                            <td>
                                                <span class="fw-bold"><?= htmlspecialchars($user['username']) ?></span>
                                                <?php if ($user['username'] === $_SESSION['user']): ?>
                                                    <span class="badge bg-info text-dark ms-2">Você</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 'secondary' ?>">
                                                    <?= ucfirst($user['role']) ?>
                                                </span>
                                            </td>
                                            <td><?= $user['created_at'] ?></td>
                                            <td>
                                                <?php if ($user['username'] !== 'admin' && $user['username'] !== $_SESSION['user']): ?>
                                                    <a href="?delete=<?= $user['id'] ?>" class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Tem certeza?')">Excluir</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>