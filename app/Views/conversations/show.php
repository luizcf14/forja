<?php ob_start(); ?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-lg border-secondary" style="height: 80vh; display: flex; flex-direction: column;">
            
            <!-- Header -->
            <div class="card-header bg-dark border-secondary d-flex align-items-center py-2 px-3 text-white">
                <a href="/conversations" class="btn btn-sm btn-outline-light me-3 rounded-circle" style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div class="d-flex align-items-center">
                    <div class="bg-secondary rounded-circle text-white d-flex justify-content-center align-items-center me-3" style="width: 40px; height: 40px;">
                        <i class="bi bi-person-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold"><?= htmlspecialchars($conversation['user_id']) ?></h6>
                        <small class="text-white-50">Conversa via WhatsApp</small>
                    </div>
                </div>
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
                    <input type="text" class="form-control border-0 bg-secondary text-white" placeholder="Mensagens apenas de leitura..." disabled>
                    <span class="input-group-text bg-secondary border-0 text-white-50"><i class="bi bi-mic"></i></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Scroll to bottom on load
    window.onload = function() {
        const chatBody = document.querySelector('.card-body');
        chatBody.scrollTop = chatBody.scrollHeight;
    }
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
