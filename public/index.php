<?php
session_start();
require_once __DIR__ . '/../src/Database.php';

$db = new Database();

// Auth Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $user = $db->getUserByUsername($username);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = $user['username'];
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
    } else {
        $error = "Invalid credentials";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

$agents = [];
if (isset($_SESSION['user'])) {
    $agents = $db->getAllAgents();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Forge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-robot"></i> Agent Forge
            </a>
            <?php if (isset($_SESSION['user'])): ?>
                <div class="d-flex align-items-center">
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <a href="users.php" class="btn btn-outline-primary btn-sm me-2">Gerenciar Usuários</a>
                    <?php endif; ?>
                    <span class="text-muted me-3 d-none d-md-block">Bem-vindo,
                        <?= htmlspecialchars($_SESSION['user']) ?></span>
                    <a href="?logout=1" class="btn btn-outline-danger btn-sm">Sair</a>
                </div>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container" style="margin-top: 100px;">
        <?php if (!isset($_SESSION['user'])): ?>
            <div class="row justify-content-center align-items-center" style="min-height: 80vh;">
                <div class="col-md-5">
                    <div class="text-center mb-5">
                        <h1 class="display-4 fw-bold text-gradient">Agent Forge</h1>
                        <p class="lead text-muted">Construa, Configure e Implante Agentes de IA em segundos.</p>
                    </div>
                    <div class="card shadow-lg">
                        <div class="card-body p-5">
                            <h3 class="text-center mb-4 fw-bold">Entrar</h3>
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger"><?= $error ?></div>
                            <?php endif; ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label text-muted">Usuário</label>
                                    <input type="text" name="username" class="form-control form-control-lg" value="admin">
                                </div>
                                <div class="mb-4">
                                    <label class="form-label text-muted">Senha</label>
                                    <input type="password" name="password" class="form-control form-control-lg"
                                        value="password">
                                </div>
                                <button type="submit" name="login" class="btn btn-primary w-100 btn-lg">Entrar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="hero-section">
                <h1 class="display-5 fw-bold mb-3">Sua Frota de Agentes</h1>
                <p class="text-muted mb-4">Gerencie e configure sua força de trabalho de IA.</p>
                <a href="create_agent.php" class="btn btn-primary btn-lg shadow-sm">
                    + Criar Novo Agente
                </a>
            </div>

            <div class="row g-4">
                <?php foreach ($agents as $agent): ?>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title fw-bold mb-0"><?= htmlspecialchars($agent['subject']) ?></h5>
                                    <span
                                        class="badge bg-light text-primary border"><?= htmlspecialchars($agent['type']) ?></span>
                                </div>
                                <p class="card-text text-muted small mb-4" style="min-height: 60px;">
                                    <?= htmlspecialchars(substr($agent['behaviour'], 0, 100)) ?>...
                                </p>
                                <a href="download.php?id=<?= $agent['id'] ?>" class="btn btn-outline-primary w-100">
                                    Baixar Configuração
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($agents)): ?>
                    <div class="col-12 text-center py-5">
                        <div class="text-muted">
                            <p class="mb-0">Nenhum agente criado ainda.</p>
                            <small>Clique no botão acima para começar.</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>