<?php ob_start(); ?>

<div class="container-fluid h-100">
    <div class="row h-100">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse" style="height: calc(100vh - 100px); overflow-y: auto;">
            <div class="position-sticky pt-3">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                    <span>Configuração do Agente</span>
                </h6>
                <div class="card m-3 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($agent['subject']) ?></h5>
                        <span class="badge bg-primary mb-2"><?= htmlspecialchars($agent['type']) ?></span>
                        <p class="card-text small text-muted">
                            <?= htmlspecialchars(substr($agent['behaviour'], 0, 100)) ?>...
                        </p>
                        <?php if ($agent['knowledge_base']): ?>
                            <div class="alert alert-info py-1 px-2 small">
                                <i class="bi bi-file-earmark-text"></i> KB Ativo
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-grid gap-2 mx-3">
                    <a href="/agents/edit?id=<?= $agent['id'] ?>" class="btn btn-outline-secondary btn-sm">Editar Configuração</a>
                </div>
            </div>
        </div>

        <!-- Chat Area -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 d-flex flex-column" style="height: calc(100vh - 100px);">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Testar Agente</h1>
            </div>

            <!-- Messages -->
            <div class="flex-grow-1 overflow-auto p-3" id="chat-messages" style="background-color: #f8f9fa; border-radius: 10px;">
                <div class="text-center text-muted mt-5">
                    <i class="bi bi-chat-dots display-4"></i>
                    <p class="mt-2">Inicie a conversa com seu agente.</p>
                </div>
            </div>

            <!-- Input -->
            <div class="p-3 bg-white border-top">
                <form id="chat-form" class="d-flex gap-2">
                    <input type="text" id="message-input" class="form-control form-control-lg" placeholder="Digite sua mensagem..." autocomplete="off">
                    <button type="submit" class="btn btn-primary btn-lg px-4">
                        <i class="bi bi-send"></i>
                    </button>
                </form>
            </div>
        </main>
    </div>
</div>

<script>
    $(document).ready(function() {
        const agentId = <?= $agent['id'] ?>;
        const sessionId = "<?= $session_id ?>";
        const chatMessages = $('#chat-messages');
        const chatForm = $('#chat-form');
        const messageInput = $('#message-input');
        let history = [];

        function appendMessage(role, text) {
            const isUser = role === 'user';
            const align = isUser ? 'end' : 'start';
            const bg = isUser ? 'primary text-white' : 'white border';
            
            const html = `
                <div class="d-flex justify-content-${align} mb-3">
                    <div class="card bg-${bg} shadow-sm" style="max-width: 75%;">
                        <div class="card-body p-3">
                            ${text.replace(/\n/g, '<br>')}
                        </div>
                    </div>
                </div>
            `;
            
            // Remove welcome message if exists
            if (chatMessages.find('.text-center').length) {
                chatMessages.empty();
            }

            chatMessages.append(html);
            chatMessages.scrollTop(chatMessages[0].scrollHeight);
        }

        function appendTyping() {
            const html = `
                <div class="d-flex justify-content-start mb-3" id="typing-indicator">
                    <div class="card bg-white border shadow-sm">
                        <div class="card-body p-3">
                            <div class="spinner-grow spinner-grow-sm text-secondary" role="status"></div>
                            <div class="spinner-grow spinner-grow-sm text-secondary" role="status"></div>
                            <div class="spinner-grow spinner-grow-sm text-secondary" role="status"></div>
                        </div>
                    </div>
                </div>
            `;
            chatMessages.append(html);
            chatMessages.scrollTop(chatMessages[0].scrollHeight);
        }

        function removeTyping() {
            $('#typing-indicator').remove();
        }

        chatForm.on('submit', function(e) {
            e.preventDefault();
            const message = messageInput.val().trim();
            if (!message) return;

            // Add User Message
            appendMessage('user', message);
            messageInput.val('');
            appendTyping();

            // Send to API
            $.ajax({
                url: '/api/chat',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    agent_id: agentId,
                    message: message,
                    session_id: sessionId,
                    history: history
                }),
                success: function(response) {
                    removeTyping();
                    if (response.error) {
                        appendMessage('system', 'Error: ' + response.error);
                    } else {
                        appendMessage('agent', response.response);
                        // Update history (simplified)
                        history.push({role: 'user', content: message});
                        history.push({role: 'model', content: response.response});
                    }
                },
                error: function(xhr) {
                    removeTyping();
                    appendMessage('system', 'Error: Failed to communicate with server.');
                }
            });
        });
    });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>