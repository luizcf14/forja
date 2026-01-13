<?php ob_start(); ?>

<div class="row">
    <!-- User Management Section -->
    <div class="col-md-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-people"></i> Gerenciar Usuários</h5>
            </div>
            <div class="card-body">
                 <!-- Alerts -->
                 <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Create User Form -->
                <div class="mb-4 border-bottom pb-4">
                    <h6 class="fw-bold mb-3">Adicionar Novo Usuário</h6>
                    <form method="POST" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" name="username" class="form-control" placeholder="Usuário" required>
                        </div>
                        <div class="col-md-4">
                            <input type="password" name="password" class="form-control" placeholder="Senha" required>
                        </div>
                        <div class="col-md-2">
                            <select name="role" class="form-select">
                                <option value="user">Usuário</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" name="create_user" class="btn btn-success w-100">Adicionar</button>
                        </div>
                    </form>
                </div>

                <!-- Users List -->
                <h6 class="fw-bold mb-3">Usuários Existentes</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
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
                                        <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 'secondary' ?>">
                                            <?= ucfirst($user['role']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <?php if ($user['username'] !== 'admin' && $user['username'] !== $_SESSION['user']): ?>
                                            <a href="?delete=<?= $user['id'] ?>" class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Tem certeza que deseja excluir este usuário?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
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

    <!-- System Actions Section -->
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-gear"></i> Sistema</h5>
            </div>
            <div class="card-body">
                <h6 class="fw-bold">Atualização do Projeto</h6>
                <p class="text-muted small">Puxe as últimas alterações do repositório Git.</p>
                <form method="POST">
                    <button type="submit" name="git_pull" class="btn btn-dark w-100 mb-3">
                        <i class="bi bi-git"></i> Git Pull (Origin Main)
                    </button>
                </form>

                <?php if (isset($gitOutput) && !empty($gitOutput)): ?>
                    <div class="bg-light p-2 rounded border font-monospace small" style="max-height: 200px; overflow-y: auto;">
                        <pre class="mb-0"><?= htmlspecialchars($gitOutput) ?></pre>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
