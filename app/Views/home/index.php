<?php ob_start(); ?>

<?php if (!isset($_SESSION['user'])): ?>
    <div class="row justify-content-center align-items-center" style="min-height: 80vh;">
        <div class="col-md-5">
            <div class="text-center mb-5">
                <h1 class="display-4 fw-bold text-gradient">Forja do Parente</h1>
                <p class="lead text-muted">Construa, Configure e Implante Agentes de IA em segundos.</p>
            </div>
            <div class="card shadow-lg">
                <div class="card-body p-5">
                    <h3 class="text-center mb-4 fw-bold">Entrar</h3>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    <form method="POST" action="/">
                        <div class="mb-3">
                            <label class="form-label text-muted">Usuário</label>
                            <input type="text" name="username" class="form-control form-control-lg" value="admin">
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-muted">Senha</label>
                            <input type="password" name="password" class="form-control form-control-lg" value="password">
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
        <a href="/agents/create" class="btn btn-primary btn-lg shadow-sm">
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
                            <span class="badge bg-light text-primary border"><?= htmlspecialchars($agent['type']) ?></span>
                        </div>
                        <p class="card-text text-muted small mb-4" style="min-height: 60px;">
                            <?= htmlspecialchars(substr($agent['behaviour'], 0, 100)) ?>...
                        </p>
                        <div class="d-flex gap-2">
                            <a href="/agents/test?id=<?= $agent['id'] ?>" class="btn btn-outline-success w-50">
                                <i class="bi bi-chat-dots"></i> Testar
                            </a>
                            <a href="/agents/edit?id=<?= $agent['id'] ?>" class="btn btn-outline-secondary w-50">
                                Editar
                            </a>
                            <a href="/agents/download?id=<?= $agent['id'] ?>" class="btn btn-outline-primary w-50">
                                Baixar
                            </a>
                        </div>
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

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>