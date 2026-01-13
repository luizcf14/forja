<?php ob_start(); ?>

<div class="card shadow-lg bg-dark text-light border-secondary">
    <div class="card-header border-secondary bg-transparent d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-white"><i class="bi bi-chat-dots-fill text-primary"></i> Conversas do Parente</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($conversations)): ?>
            <div class="p-5 text-center text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                <p>Nenhuma conversa registrada ainda.</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($conversations as $conv): ?>
                    <a href="/conversations/show?id=<?= $conv['id'] ?>" class="list-group-item list-group-item-action bg-dark text-light border-secondary p-3 hover-effect">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1 fw-bold text-white">
                                <i class="bi bi-person-circle text-primary me-2"></i>
                                <?= htmlspecialchars($conv['user_id']) ?>
                            </h5>
                            <small class="text-secondary">
                                <?= date('d/m/Y H:i', strtotime($conv['last_message_at'])) ?>
                            </small>
                        </div>
                        <small class="text-white-50">
                            Clique para ver o histórico completo.
                        </small>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .hover-effect:hover {
        background-color: #343a40 !important;
    }
</style>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
