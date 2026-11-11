<?php
$usuarioLogado = usuarioAtual();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($tituloPagina) ? escapar($tituloPagina) . ' | Validade' : 'Validade e Lotes' ?></title>
    <!-- Favicon gerado a partir do logo -->
    <link rel="icon" type="image/png" sizes="32x32" href="favicon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="favicon-192.png">
    <link rel="apple-touch-icon" href="favicon-192.png">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Estilo Dark Slate Customizado -->
    <link rel="stylesheet" href="estilo.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark mb-4 sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-box-seam" viewBox="0 0 16 16">
                    <path d="M8.186 1.113a.5.5 0 0 0-.372 0L1.846 3.5l2.404.961L10.404 2zm3.564 1.489L5.71 5.062 2.628 3.828l6.837-2.735zM1.4 4.872V12a.5.5 0 0 0 .28.45l6 3a.5.5 0 0 0 .44 0l6-3a.5.5 0 0 0 .28-.45V4.872L9 7.87v5.52l-2 1v-6.5zm13.2 0L9 7.87v6.5l2-1v-5.52l5.6-2.8z"/>
                </svg>
                <span>Gestão de <span>Validade</span></span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <?php if ($usuarioLogado !== null): ?>
                    <!-- Botão Meu Perfil adicionado -->
                    <a href="perfil.php" class="btn btn-outline-light btn-sm">Meu Perfil</a>
                    
                    <?php if (isset($usuarioLogado['perfil']) && $usuarioLogado['perfil'] === 'admin'): ?>
                        <a href="usuarios.php" class="btn btn-warning btn-sm">Gerenciar Usuários</a>
                    <?php endif; ?>
                    
                    <form method="post" action="logout.php" class="m-0">
                        <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm">Sair</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </nav>
<main class="container mb-5">