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

                <!-- Change Password Form -->
                <div class="mb-4 border-bottom pb-4">
                    <h6 class="fw-bold mb-3">Alterar Sua Senha</h6>
                    <form method="POST" class="row g-3">
                        <div class="col-md-4">
                            <input type="password" name="current_password" class="form-control" placeholder="Senha Atual" required>
                        </div>
                        <div class="col-md-3">
                            <input type="password" name="new_password" class="form-control" placeholder="Nova Senha" required>
                        </div>
                         <div class="col-md-3">
                            <input type="password" name="confirm_password" class="form-control" placeholder="Confirmar" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" name="change_password" class="btn btn-warning w-100">Alterar</button>
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

        <!-- User Requests Section -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-info text-dark">
                <h5 class="mb-0"><i class="bi bi-lightbulb"></i> Pedidos dos Usuários</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Usuário</th>
                                <th>Pedido</th>
                                <th>Importância</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($userRequests)): ?>
                                <?php foreach ($userRequests as $request): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($request['user_identifier'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($request['request_text'] ?? '') ?></td>
                                        <td>
                                            <?php 
                                            $imp = $request['importance'] ?? 'normal';
                                            $badgeClass = 'primary';
                                            if ($imp === 'high') $badgeClass = 'danger';
                                            if ($imp === 'low') $badgeClass = 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $badgeClass ?>">
                                                <?= ucfirst($imp) ?>
                                            </span>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($request['created_at'] ?? 'now')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Nenhum pedido registrado.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- System Actions Section -->
    <div class="col-md-4">
        <!-- Communication Tools -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-megaphone"></i> Comunicação</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Ferramentas de envio de mensagens.</p>
                <a href="/notifications" class="btn btn-warning w-100 mb-2">
                    <i class="bi bi-broadcast"></i> Notificação em Massa
                </a>
            </div>
        </div>

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
                    <!-- Display Git Output -->
                    <?php if (!empty($gitOutput)): ?>
                        <div class="bg-black text-success p-2 rounded small font-monospace overflow-auto" style="max-height: 200px;">
                            <pre class="m-0"><?= htmlspecialchars($gitOutput) ?></pre>
                        </div>
                    <?php endif; ?>
                </form>

                <h6 class="fw-bold">Auditoria e Segurança</h6>
                <a href="/audit" class="btn btn-outline-light w-100 mb-3 text-start">
                    <i class="bi bi-shield-check"></i> Logs de Auditoria
                </a>

                <hr class="my-4">

                <h6 class="fw-bold">Serviço Parente IA</h6>
                <div class="d-flex align-items-center justify-content-between mb-3 p-3 border rounded <?= $isServiceRunning ? 'border-success bg-secondary bg-opacity-25' : 'border-secondary bg-dark' ?>">
                    <div class="<?= $isServiceRunning ? 'text-white' : 'text-light' ?>">
                        <strong>Status:</strong> 
                        <span class="badge <?= $isServiceRunning ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $isServiceRunning ? 'EXECUTANDO' : 'PARADO' ?>
                        </span>
                        <?php if ($isServiceRunning): ?>
                            <div class="small text-white-50 mt-1">PID: <?= $servicePid ?></div>
                        <?php endif; ?>
                    </div>
                    <form method="POST">
                        <?php if ($isServiceRunning): ?>
                            <button type="submit" name="stop_service" class="btn btn-danger btn-sm me-2">
                                <i class="bi bi-stop-circle"></i> Parar
                            </button>
                            <button type="submit" name="restart_service" class="btn btn-warning btn-sm">
                                <i class="bi bi-arrow-clockwise"></i> Reiniciar
                            </button>
                        <?php else: ?>
                            <button type="submit" name="start_service" class="btn btn-success btn-sm">
                                <i class="bi bi-play-circle"></i> Iniciar
                            </button>
                        <?php endif; ?>
                    </form>
                </div>

                <hr class="my-4">

                <h6 class="fw-bold">Logs de Execução</h6>
                <div class="bg-black text-white p-2 rounded border border-secondary font-monospace small" style="max-height: 300px; overflow-y: auto;">
                    <pre class="m-0" id="serviceLogs"><?= htmlspecialchars($serviceLogs ?? 'Nenhum log disponível.') ?></pre>
                </div>
                <div class="text-end mt-1">
                    <a href="" class="btn btn-link btn-sm text-decoration-none text-info"><i class="bi bi-arrow-clockwise"></i> Atualizar Logs</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
