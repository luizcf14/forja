<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forja do Parente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="bi bi-robot"></i> Forja do Parente
            </a>
            <?php if (isset($_SESSION['user'])): ?>
                <div class="d-flex align-items-center">
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <a href="/users" class="btn btn-outline-primary btn-sm me-2">Gerenciar Usuários</a>
                    <?php endif; ?>
                    <span class="text-muted me-3 d-none d-md-block">Bem-vindo,
                        <?= htmlspecialchars($_SESSION['user']) ?></span>
                    <a href="/?logout=1" class="btn btn-outline-danger btn-sm">Sair</a>
                </div>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container" style="margin-top: 100px;">
        <?= $content ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>