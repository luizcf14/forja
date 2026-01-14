<?php ob_start(); ?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-lg border-secondary" style="height: 80vh; display: flex; flex-direction: column;">
            
            <!-- Header -->
            <div class="card-header bg-dark border-secondary d-flex align-items-center py-2 px-3 text-white">
                <a href="/conversations" class="btn btn-sm btn-outline-light me-3 rounded-circle" style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div class="d-flex align-items-center flex-grow-1">
                    <div class="bg-secondary rounded-circle text-white d-flex justify-content-center align-items-center me-3" style="width: 40px; height: 40px;">
                        <i class="bi bi-person-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold"><?= htmlspecialchars($conversation['user_id']) ?></h6>
                        <small class="text-white-50">Conversa via WhatsApp</small>
                    </div>
                </div>
                <!-- AI Interruption Toggle -->
                <?php 
                    $aiStatus = $conversation['ai_status'] ?? 'active'; 
                    $isPaused = $aiStatus === 'paused';
                    $toggleIcon = $isPaused ? 'bi-play-fill' : 'bi-pause-fill';
                    $toggleText = $isPaused ? 'Resume AI' : 'Pause AI';
                    $btnClass = $isPaused ? 'btn-success' : 'btn-warning';
                ?>
                <button id="toggleAiBtn" class="btn btn-sm <?= $btnClass ?> d-flex align-items-center gap-2" onclick="toggleAi()">
                    <i class="bi <?= $toggleIcon ?>"></i> <span class="d-none d-md-inline"><?= $toggleText ?></span>
                </button>
            </div>

            <!-- Chat Area -->
            <!-- Using a dark background for chat to mimic WhatsApp Dark Mode -->
            <div class="card-body p-4" style="flex: 1; overflow-y: auto; background-color: #0b141a; background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png'); background-blend-mode: soft-light;">
                <div class="d-flex flex-column">
                    <?php if (empty($messages)): ?>
                        <div class="text-center my-4">
                            <span class="badge bg-dark border border-secondary text-light shadow-sm px-3 py-2 rounded-pill opacity-75">
                                Início da conversa
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($messages as $msg): ?>
                        <?php 
                            $isAgent = $msg['sender'] === 'agent';
                            $alignClass = $isAgent ? 'align-self-start' : 'align-self-end';
                            // WhatsApp Dark Mode Bubbles
                            // User (Green): #005c4b, text #e9edef
                            // Agent/Other (Gray): #202c33, text #e9edef
                            
                            $bubbleStyle = $isAgent 
                                ? "border-top-left-radius: 0; background-color: #202c33; color: #e9edef;" 
                                : "border-top-right-radius: 0; background-color: #005c4b; color: #e9edef;";
                        ?>
                        <div class="<?= $alignClass ?> mb-2" style="max-width: 75%;">
                            <div class="card shadow-sm border-0" style="<?= $bubbleStyle ?>; border-radius: 7.5px;">
                                <div class="card-body py-2 px-3">
                                    <div class="mb-1" style="white-space: pre-wrap; font-size: 14.2px; line-height: 19px;"><?= htmlspecialchars($msg['content']) ?></div>
                                    <div class="text-end" style="font-size: 11px; opacity: 0.7; margin-top: -4px;">
                                        <?= date('H:i', strtotime($msg['created_at'])) ?>
                                        <?php if (!$isAgent): ?>
                                            <i class="bi bi-check2-all ms-1 text-info"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Input Placeholder (Read Only) -->
            <div class="card-footer bg-dark border-secondary py-2 px-3">
                <div class="input-group">
                    <span class="input-group-text bg-secondary border-0 text-white-50"><i class="bi bi-emoji-smile"></i></span>
                    <input type="text" id="chatInput" class="form-control border-0 bg-secondary text-white" 
                           placeholder="<?= $isPaused ? 'Digite uma mensagem manual...' : 'Pause a IA para enviar mensagens manuais...' ?>" 
                           <?= $isPaused ? '' : 'disabled' ?> onkeypress="handleInputKey(event)">
                    <button id="sendBtn" class="btn btn-secondary border-0 text-white-50" onclick="sendMessage()" <?= $isPaused ? '' : 'disabled' ?>>
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const conversationId = <?= $conversation['id'] ?>;
    let lastMessageId = <?= !empty($messages) ? end($messages)['id'] : 0 ?>;
    let aiStatus = '<?= $aiStatus ?>'; // 'active' or 'paused'
    const chatBody = document.querySelector('.card-body');
    const toggleBtn = document.getElementById('toggleAiBtn');
    const chatInput = document.getElementById('chatInput');
    const sendBtn = document.getElementById('sendBtn');

    // Scroll to bottom on load
    window.onload = function() {
        scrollToBottom();
    }

    function scrollToBottom() {
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    function appendMessage(msg) {
        const isAgent = msg.sender === 'agent';
        const alignClass = isAgent ? 'align-self-start' : 'align-self-end';
        // Match PHP dark theme colors
        const bubbleStyle = isAgent 
            ? "border-top-left-radius: 0; background-color: #202c33; color: #e9edef;" 
            : "border-top-right-radius: 0; background-color: #005c4b; color: #e9edef;";
        
        const checkIcon = !isAgent ? '<i class="bi bi-check2-all ms-1 text-info"></i>' : '';
        const time = new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

        const html = `
            <div class="${alignClass} mb-2" style="max-width: 75%;">
                <div class="card shadow-sm border-0" style="${bubbleStyle}; border-radius: 7.5px;">
                    <div class="card-body py-2 px-3">
                        <div class="mb-1" style="white-space: pre-wrap; font-size: 14.2px; line-height: 19px;">${escapeHtml(msg.content)}</div>
                        <div class="text-end" style="font-size: 11px; opacity: 0.7; margin-top: -4px;">
                            ${time}
                            ${checkIcon}
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Check if user is near bottom before appending to auto-scroll
        const isNearBottom = chatBody.scrollHeight - chatBody.scrollTop - chatBody.clientHeight < 100;
        
        $('.d-flex.flex-column').append(html);
        
        if (isNearBottom) {
            scrollToBottom();
        }
    }

    function escapeHtml(text) {
        if (!text) return text;
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Poll for new messages every 3 seconds
    setInterval(function() {
        $.ajax({
            url: '/api/conversations/messages',
            data: { 
                conversation_id: conversationId, 
                last_id: lastMessageId 
            },
            success: function(messages) {
                if (messages && messages.length > 0) {
                    messages.forEach(function(msg) {
                        appendMessage(msg);
                        lastMessageId = msg.id;
                    });
                }
            }
        });
    }, 3000);

    function toggleAi() {
        // Toggle logic
        const newStatus = aiStatus === 'active' ? 'paused' : 'active';
        
        $.ajax({
            url: '/conversations/toggle-ai',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                conversation_id: conversationId,
                status: newStatus
            }),
            success: function(response) {
                if(response.success) {
                    aiStatus = newStatus;
                    updateUiState();
                }
            }
        });
    }

    function updateUiState() {
        const isPaused = aiStatus === 'paused';
        
        // Update Button
        toggleBtn.className = `btn btn-sm ${isPaused ? 'btn-success' : 'btn-warning'} d-flex align-items-center gap-2`;
        toggleBtn.innerHTML = `<i class="bi ${isPaused ? 'bi-play-fill' : 'bi-pause-fill'}"></i> <span class="d-none d-md-inline">${isPaused ? 'Resume AI' : 'Pause AI'}</span>`;
        
        // Update Input
        if (isPaused) {
            chatInput.removeAttribute('disabled');
            chatInput.placeholder = 'Digite uma mensagem manual...';
            sendBtn.removeAttribute('disabled');
        } else {
            chatInput.setAttribute('disabled', 'true');
            chatInput.placeholder = 'Pause a IA para enviar mensagens manuais...';
            sendBtn.setAttribute('disabled', 'true');
        }
    }

    function handleInputKey(event) {
        if (event.key === 'Enter') {
            sendMessage();
        }
    }

    function sendMessage() {
        const content = chatInput.value.trim();
        if (!content) return;

        // Optimistic update? Or wait for success? 
        // Let's wait for success to be sure.
        // But clear input immediately.
        chatInput.value = '';

        $.ajax({
            url: '/conversations/send-message',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                conversation_id: conversationId,
                content: content
            }),
            success: function(response) {
                if(response.success) {
                    // Message will appeal via polling or we can append manually here.
                    // Let's manually append to be instant.
                    // Wait, we don't have the message ID from backend, only success.
                    // We'll trust polling to pick it up in < 3s, or we can mock it.
                    // But Polling relies on ID > lastId.
                    // Let's rely on polling for simplicity to avoid duplicate ID issues.
                } else {
                   alert('Failed to send message');
                }
            }
        });
    }
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
