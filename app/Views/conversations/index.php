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
                                <?php if (!empty($conv['unread_count']) && $conv['unread_count'] > 0): ?>
                                    <span class="badge bg-danger rounded-pill ms-2 fs-6">
                                        <?= $conv['unread_count'] ?>
                                    </span>
                                <?php endif; ?>
                            </h5>
                            <small class="text-secondary">
                                <?= date('d/m/Y H:i', strtotime($conv['last_message_at'])) ?>
                            </small>
                        </div>
                        <div class="mt-2" id="tags-<?= $conv['id'] ?>">
                             <?php if (!empty($conv['sentiment'])): ?>
                                <?php
                                    $sColors = [
                                        'Neutro' => 'secondary',
                                        'Contente' => 'info',
                                        'Feliz' => 'success',
                                        'Raiva' => 'danger',
                                        'Frustração' => 'warning'
                                    ];
                                    $sCol = $sColors[$conv['sentiment']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $sCol ?> me-1"><?= htmlspecialchars($conv['sentiment']) ?></span>
                            <?php endif; ?>
                            
                            <?php if (!empty($conv['topic'])): ?>
                                <span class="badge bg-dark border border-secondary text-light"><?= htmlspecialchars($conv['topic']) ?></span>
                            <?php endif; ?>
                            
                            <!-- Manual Analysis Trigger -->
                            <button class="btn btn-sm btn-dark border-secondary ms-2 py-0 px-2 text-white-50 hover-white" 
                                    onclick="analyzeConversation(<?= $conv['id'] ?>, true, event)" 
                                    title="Refazer análise">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        // Auto-analyze ONLY if never analyzed
        <?php foreach ($conversations as $conv): ?>
            <?php 
                $lastAnalyzed = !empty($conv['last_analyzed_at']) ? 1 : 0;
            ?>
            if (!<?= $lastAnalyzed ?>) {
                analyzeConversation(<?= $conv['id'] ?>);
            }
        <?php endforeach; ?>
    });

    function analyzeConversation(id, force = false, event = null) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        const container = $(`#tags-${id}`);
        // Visual feedback for manual refresh
        const btn = event ? $(event.currentTarget).find('i') : null;
        if(btn) btn.addClass('spin-anim');
        
        $.ajax({
            url: '/conversations/analyze',
            method: 'GET',
            data: { id: id, force: force ? 1 : 0 },
            success: function(response) {
                if(btn) btn.removeClass('spin-anim');
                
                if (response.success) {
                    // Update UI
                    let html = '';
                    const sColors = {
                        'Neutro': 'secondary',
                        'Contente': 'info',
                        'Feliz': 'success',
                        'Raiva': 'danger',
                        'Frustração': 'warning'
                    };
                    const color = sColors[response.sentiment] || 'secondary';
                    
                    if (response.sentiment) {
                        html += `<span class="badge bg-${color} me-1">${response.sentiment}</span>`;
                    }
                    if (response.topic) {
                        html += `<span class="badge bg-dark border border-secondary text-light">${response.topic}</span>`;
                    }
                    
                    // Re-add button
                    html += `
                        <button class="btn btn-sm btn-dark border-secondary ms-2 py-0 px-2 text-white-50 hover-white" 
                                onclick="analyzeConversation(${id}, true, event)" 
                                title="Refazer análise">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>`;
                        
                    container.html(html);
                }
            },
            error: function() {
                 if(btn) btn.removeClass('spin-anim');
            }
        });
    }
</script>

<style>
    .hover-effect:hover {
        background-color: #343a40 !important;
    }
    .hover-white:hover {
        color: white !important;
        border-color: white !important;
    }
    @keyframes spin { 100% { transform: rotate(360deg); } }
    .spin-anim { animation: spin 1s linear infinite; }
</style>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
