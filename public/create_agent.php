<?php
session_start();
require_once __DIR__ . '/../src/Database.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_agent']) || isset($_POST['save_download']))) {
    $subject = $_POST['subject'] ?? '';
    $type = $_POST['type'] ?? 'Fast';
    $behaviour = $_POST['behaviour'] ?? '';
    $details = $_POST['details'] ?? '';

    // Handle File Upload
    $knowledgeBase = '';
    if (isset($_FILES['knowledge_base']) && $_FILES['knowledge_base']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = basename($_FILES['knowledge_base']['name']);
        $targetPath = $uploadDir . $fileName;

        // Basic validation (ensure unique name to avoid overwrite issues in real app)
        $fileName = time() . '_' . $fileName;
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['knowledge_base']['tmp_name'], $targetPath)) {
            $knowledgeBase = $fileName;
        }
    }

    $db = new Database();
    $agentId = $db->insertAgent([
        'user_id' => $_SESSION['user_id'] ?? null,
        'subject' => $subject,
        'type' => $type,
        'behaviour' => $behaviour,
        'details' => $details,
        'knowledge_base' => $knowledgeBase
    ]);

    if ($agentId) {
        if (isset($_POST['save_download'])) {
            // Redirect to download page
            header("Location: download.php?id=$agentId&download=1");
            exit;
        } else { // save_agent was pressed
            $_SESSION['success_message'] = "Agente criado com sucesso!";
            header("Location: index.php"); // Redirect to dashboard or agent list
            exit;
        }
    } else {
        $message = "Failed to create agent.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Novo Agente - Agent Forge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-robot"></i> Agent Forge
            </a>
            <div class="d-flex align-items-center">
                <a href="index.php" class="btn btn-outline-secondary btn-sm">Voltar ao Painel</a>
            </div>
        </div>
    </nav>

    <div class="container" style="margin-top: 100px;">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-lg">
                    <div class="card-header">
                        <h4 class="mb-0 fw-bold">Criar Novo Agente</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($message): ?>
                            <div class="alert alert-danger"><?= $message ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" id="agentForm">
                            <div class="mb-4">
                                <label class="form-label fw-bold">Assunto</label>
                                <input type="text" name="subject" class="form-control form-control-lg" required
                                    placeholder="ex: Bot de Suporte ao Cliente">
                                <div class="form-text">Qual é o objetivo principal deste agente?</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Tipo de Agente</label>
                                <select name="type" class="form-select form-select-lg">
                                    <option value="Fast">Rápido (Baixa Latência)</option>
                                    <option value="Slow">Lento (Alto Raciocínio)</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Base de Conhecimento</label>
                                <input type="file" name="knowledge_base" class="form-control" accept=".pdf,.html">
                                <div class="form-text">Envie documentos PDF ou HTML para contexto.</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Comportamento do Agente</label>
                                <div class="position-relative">
                                    <textarea name="behaviour" id="behaviour" class="form-control" rows="5"
                                        placeholder="Descreva como o agente deve se comportar..."></textarea>
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
                                    placeholder="Quaisquer instruções ou restrições específicas..."></textarea>
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
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                    url: 'api/optimize.php',
                    method: 'POST',
                    data: { text: behaviour },
                    success: function (response) {
                        try {
                            const res = JSON.parse(response);
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
</body>

</html>