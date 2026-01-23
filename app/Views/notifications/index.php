<?php ob_start(); ?>

<div class="card shadow-sm">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="bi bi-broadcast"></i> Notificação em Massa</h5>
    </div>
    <div class="card-body">
        
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="message" class="form-label fw-bold">Mensagem</label>
                <textarea class="form-control" id="message" name="message" rows="4" placeholder="Digite sua mensagem curta aqui..." required></textarea>
                <div class="form-text">Esta mensagem será enviada para todos os destinatários selecionados via WhatsApp.</div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Destinatários</label>
                <div class="d-flex mb-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="selectAll">Selecionar Todos</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAll">Desmarcar Todos</button>
                </div>
                
                <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                    <?php if (empty($users)): ?>
                        <p class="text-muted text-center my-2">Nenhum usuário encontrado na base de conversas.</p>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <div class="form-check">
                                <input class="form-check-input user-checkbox" type="checkbox" name="recipients[]" value="<?= htmlspecialchars($user) ?>" id="user_<?= md5($user) ?>">
                                <label class="form-check-label font-monospace" for="user_<?= md5($user) ?>">
                                    <?= htmlspecialchars($user) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="/settings" class="btn btn-outline-secondary me-md-2">Voltar</a>
                <button type="submit" class="btn btn-primary" onclick="return confirm('Tem certeza que deseja enviar esta mensagem para os usuários selecionados?')">
                    <i class="bi bi-send"></i> Enviar Notificação
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#selectAll').click(function() {
            $('.user-checkbox').prop('checked', true);
        });
        $('#deselectAll').click(function() {
            $('.user-checkbox').prop('checked', false);
        });
    });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
