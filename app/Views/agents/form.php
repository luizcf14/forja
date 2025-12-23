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
                        <input type="file" name="knowledge_base" class="form-control" accept=".pdf,.html">
                        <div class="form-text">Envie documentos PDF ou HTML para contexto.</div>
                        <?php if ($isEdit && !empty($agent['knowledge_base'])): ?>
                            <div class="mt-2">
                                <span class="text-muted">Arquivo atual:</span>
                                <a href="/uploads/<?= htmlspecialchars($agent['knowledge_base']) ?>" target="_blank">
                                    <?= htmlspecialchars($agent['knowledge_base']) ?>
                                </a>
                            </div>
                        <?php endif; ?>
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

                    <div class="d-grid gap-2 pt-3 d-md-flex justify-content-md-end">
                        <button type="submit" name="save_agent" class="btn btn-secondary btn-lg me-md-2">
                            <i class="bi bi-save"></i> Salvar Agente
                        </button>
                        <button type="submit" name="save_download" class="btn btn-primary btn-lg">
                            <i class="bi bi-download"></i> Salvar e Baixar
                        </button>
                    </div>
                </form>
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
    });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
