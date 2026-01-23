<?php ob_start(); ?>

<div class="card shadow-sm">
    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-shield-check"></i> Logs de Auditoria do Sistema</h5>
        <a href="/settings" class="btn btn-sm btn-light text-dark">Voltar</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover small">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Data/Hora</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Detalhes</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Nenhum log registrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= $log['id'] ?></td>
                                <td><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                                <td>
                                    <?php if ($log['username']): ?>
                                        <strong><?= htmlspecialchars($log['username']) ?></strong>
                                        <div class="text-muted" style="font-size: 0.8em;">ID: <?= $log['user_id'] ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">Sistema</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        $badges = [
                                            'LOGIN' => 'success',
                                            'LOGOUT' => 'secondary',
                                            'AGENT_CREATE' => 'primary',
                                            'AGENT_UPDATE' => 'info',
                                            'AGENT_DELETE' => 'danger',
                                            'NOTIFICATION_SEND' => 'warning',
                                            'AI_STATUS_CHANGE' => 'primary',
                                            'MANUAL_MESSAGE_SEND' => 'success',
                                            'MANUAL_AUDIO_SEND' => 'success',
                                            'AGENT_UPLOAD' => 'info',
                                            'AGENT_FILE_DELETE' => 'danger',
                                            'SYSTEM' => 'dark'
                                        ];
                                        $color = $badges[$log['action']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $color ?>"><?= htmlspecialchars($log['action']) ?></span>
                                </td>
                                <td style="max-width: 400px;">
                                    <?php 
                                        $rawDetails = $log['details'] ?? '';
                                        $jsonData = null;
                                        $isJson = false;

                                        // Try to decode as JSON
                                        $decoded = json_decode($rawDetails, true);
                                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                            $isJson = true;
                                            $jsonData = $decoded;
                                            $displayText = $jsonData['description'] ?? 'Detalhes';
                                        } else {
                                            // Legacy/Text log
                                            $displayText = $rawDetails;
                                            $jsonData = ['description' => $rawDetails];
                                        }
                                    ?>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-truncate me-2" title="<?= htmlspecialchars($displayText) ?>">
                                            <?= htmlspecialchars(substr($displayText, 0, 80)) . (strlen($displayText) > 80 ? '...' : '') ?>
                                        </span>
                                        
                                        <button class="btn btn-sm btn-outline-info view-details ps-2 pe-2 pt-0 pb-0" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#logModal"
                                                data-json='<?= htmlspecialchars(json_encode($jsonData), ENT_QUOTES, 'UTF-8') ?>'>
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($log['ip_address']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="logModal" tabindex="-1" aria-labelledby="logModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
      <div class="modal-header" style="border-bottom-color: var(--border-color);">
        <h5 class="modal-title" id="logModalLabel" style="color: var(--primary-color);">Detalhes do Log</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="modalContent"></div>
      </div>
      <div class="modal-footer" style="border-top-color: var(--border-color);">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<script>
    $(document).ready(function() {
        $('.view-details').click(function() {
            var data = $(this).data('json');
            var contentHtml = '';
            
            if (data.description) {
                contentHtml += '<p><strong>Descrição:</strong> ' + data.description + '</p>';
            }
            if (data.recipient) {
                contentHtml += '<p><strong>Destinatário:</strong> ' + data.recipient + '</p>';
            }
            if (data.content) {
                contentHtml += '<div class="mb-3"><strong>Conteúdo:</strong><div class="p-2 border rounded" style="background-color: var(--bg-color); border-color: var(--border-color) !important;">' + data.content + '</div></div>';
            }
            if (data.audio_url) {
                contentHtml += '<div class="mb-3"><strong>Áudio:</strong><br><audio controls src="' + data.audio_url + '" class="w-100 mt-2"></audio></div>';
            }
            
            // Show other fields if needed, or raw JSON for debugging
            // For now, these specialized fields cover the request.
            
            $('#modalContent').html(contentHtml);
        });
    });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
