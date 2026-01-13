<?php ob_start(); ?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-lg">
            <div class="card-header">
                <h4 class="mb-0 fw-bold"><?= $isEdit ? 'Editar Agente' : 'Criar Novo Agente' ?></h4>
            </div>
            <div class="card-body p-4">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST" action="<?= $isEdit ? '/agents/update' : '/agents/store' ?>" enctype="multipart/form-data" id="agentForm">
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="id" value="<?= $agent['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Assunto</label>
                        <input type="text" name="subject" class="form-control form-control-lg" required
                            placeholder="ex: Bot de Suporte ao Cliente" value="<?= $isEdit ? htmlspecialchars($agent['subject']) : '' ?>">
                        <div class="form-text">Qual é o objetivo principal deste agente?</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Tipo de Agente</label>
                        <select name="type" class="form-select form-select-lg">
                            <option value="Fast" <?= ($isEdit && $agent['type'] === 'Fast') ? 'selected' : '' ?>>Rápido (Baixa Latência)</option>
                            <option value="Slow" <?= ($isEdit && $agent['type'] === 'Slow') ? 'selected' : '' ?>>Lento (Alto Raciocínio)</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_production" name="is_production" value="1" 
                                <?= ($isEdit && isset($agent['status']) && $agent['status'] === 'production') ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold" for="is_production">Habilitar para a Produção</label>
                        </div>
                        <div class="form-text">Ative para indicar que este agente está pronto para uso em produção.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Base de Conhecimento</label>
                        <input type="file" name="knowledge_base[]" class="form-control" accept=".pdf,.html" multiple>
                        <div class="form-text">Envie um ou mais documentos PDF ou HTML para contexto.</div>
                        
                        <?php 
                        // Fetch existing documents if in edit mode
                        if ($isEdit) {
                            $db = new Database(); // Or pass from controller
                            $docs = $db->getAgentDocuments($agent['id']);
                            if (!empty($docs)) {
                                echo '<div class="mt-2"><span class="text-muted">Arquivos atuais:</span><ul class="list-unstyled" id="fileList">';
                                foreach ($docs as $doc) {
                                    echo '<li class="d-flex align-items-center mb-1" id="doc-' . $doc['id'] . '">';
                                    echo '<a href="/uploads/' . htmlspecialchars($doc['filename']) . '" target="_blank" class="me-2">' . htmlspecialchars($doc['filename']) . '</a>';
                                    echo '<button type="button" class="btn btn-sm btn-outline-danger border-0 py-0 px-1 delete-file-btn" data-id="' . $doc['id'] . '" title="Remover arquivo"><i class="bi bi-x-lg"></i></button>';
                                    echo '</li>';
                                }
                                echo '</ul></div>';
                            } elseif (!empty($agent['knowledge_base'])) {
                                // Legacy check
                                echo '<div class="mt-2"><span class="text-muted">Arquivo atual (Legacy):</span> <a href="/uploads/' . htmlspecialchars($agent['knowledge_base']) . '" target="_blank">' . htmlspecialchars($agent['knowledge_base']) . '</a></div>';
                            }
                        }
                        ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Comportamento do Agente</label>
                        <div class="position-relative">
                            <textarea name="behaviour" id="behaviour" class="form-control" rows="5"
                                placeholder="Descreva como o agente deve se comportar..."><?= $isEdit ? htmlspecialchars($agent['behaviour']) : '' ?></textarea>
                            <button type="button"
                                class="btn btn-sm btn-light position-absolute bottom-0 end-0 m-2 border"
                                id="optimizeBtn">
                                ✨ Otimizar com IA
                            </button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Detalhes Adicionais</label>
                        <textarea name="details" class="form-control" rows="3"
                            placeholder="Quaisquer instruções ou restrições específicas..."><?= $isEdit ? htmlspecialchars($agent['details']) : '' ?></textarea>
                    </div>

                </form>

                <div class="d-flex justify-content-between align-items-center pt-4 border-top mt-4">
                    <!-- Left Side: Delete Button -->
                    <div>
                        <?php if ($isEdit): ?>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <i class="bi bi-trash"></i> Excluir Agente
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Right Side: Save Buttons -->
                    <div class="d-flex gap-2">
                        <button type="submit" name="save_agent" form="agentForm" class="btn btn-secondary btn-lg">
                            <i class="bi bi-save"></i> Salvar
                        </button>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<?php if ($isEdit): ?>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background-color: #27272a; border: 1px solid #3f3f46; color: #ffffff;">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title text-danger" id="deleteModalLabel">Excluir Agente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="fw-bold mb-2">Tem certeza que deseja excluir o agente "<?= htmlspecialchars($agent['subject']) ?>"?</p>
                <p class="text-secondary small mb-0">Esta ação é irreversível. Todos os documentos associados também serão removidos permanentemente.</p>
            </div>
            <div class="modal-footer border-top border-secondary">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="/agents/delete" method="POST">
                    <input type="hidden" name="id" value="<?= $agent['id'] ?>">
                    <button type="submit" class="btn btn-danger">Sim, Excluir</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        $('#optimizeBtn').click(function () {
            const behaviour = $('#behaviour').val();
            if (!behaviour) {
                alert('Por favor, insira algum texto de comportamento primeiro.');
                return;
            }

            const btn = $(this);
            const originalText = btn.text();
            btn.prop('disabled', true).text('Otimizando...');

            $.ajax({
                url: '/api/optimize', // Need to handle this route
                method: 'POST',
                data: { text: behaviour },
                success: function (response) {
                    try {
                        // If response is already an object, use it directly
                        const res = typeof response === 'object' ? response : JSON.parse(response);
                        if (res.optimized_text) {
                            $('#behaviour').val(res.optimized_text);
                        } else {
                            alert('Falha na otimização: ' + (res.error || 'Erro desconhecido'));
                        }
                    } catch (e) {
                        alert('Resposta inválida do servidor');
                    }
                },
                error: function () {
                    alert('Erro ao conectar ao serviço de otimização');
                },
                complete: function () {
                    btn.prop('disabled', false).text(originalText);
                }
            });
        });

        // File Deletion Handler
        $('.delete-file-btn').click(function() {
            if (!confirm('Tem certeza que deseja remover este arquivo?')) {
                return;
            }

            const btn = $(this);
            const docId = btn.data('id');
            const listItem = $('#doc-' + docId);

            btn.prop('disabled', true);

            $.ajax({
                url: '/agents/delete-file',
                method: 'POST',
                data: { id: docId },
                success: function(response) {
                    // Try parse if string
                    const res = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (res.success) {
                        listItem.fadeOut(300, function() {
                            $(this).remove();
                            // If list is empty, maybe remove the header too?
                            if ($('#fileList li').length === 0) {
                                $('#fileList').parent().remove();
                            }
                        });
                    } else {
                        alert('Erro ao excluir: ' + (res.error || 'Erro desconhecido'));
                        btn.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Erro de conexão ao tentar excluir o arquivo.');
                    btn.prop('disabled', false);
                }
            });
        });
    });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
