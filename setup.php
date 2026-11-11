<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';

date_default_timezone_set('America/Sao_Paulo');

$erros = [];
$configuracaoPronta = false;
$nome = valorPost('nome');
$email = strtolower(valorPost('email'));

try {
    $totalUsuarios = (int) Conexao::obter()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    $configuracaoPronta = $totalUsuarios === 0;
} catch (PDOException | RuntimeException $e) {
    error_log('Falha ao verificar a configuração inicial: ' . $e->getMessage());
    $erros[] = 'Não foi possível acessar o banco de dados. Importe schema.sql e configure DB_USER e DB_PASSWORD no ambiente do Apache.';
}

if (!$configuracaoPronta && $erros === []) {
    header('Location: login.php', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $configuracaoPronta) {
    if (!tokenPostValido()) {
        $erros[] = 'A sessão expirou. Atualize a página e tente novamente.';
    }
    if (!textoDentroDoLimite($nome, 120)) {
        $erros[] = 'Informe seu nome (até 120 caracteres).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $erros[] = 'Informe um endereço de e-mail válido.';
    }
    $senha = senhaPost('senha');
    if (strlen($senha) < 12) {
        $erros[] = 'A senha deve ter pelo menos 12 caracteres.';
    }
    if ($senha !== senhaPost('confirmar_senha')) {
        $erros[] = 'A confirmação da senha não corresponde.';
    }

    if ($erros === []) {
        try {
            $comando = Conexao::obter()->prepare(
                'INSERT INTO usuarios (nome, email, senha, perfil, ativo)
                 SELECT :nome, :email, :senha, \'admin\', 1
                 WHERE NOT EXISTS (SELECT 1 FROM usuarios)'
            );
            $comando->execute([
                'nome' => $nome,
                'email' => $email,
                'senha' => password_hash($senha, PASSWORD_DEFAULT),
            ]);

            if ($comando->rowCount() === 1) {
                header('Location: login.php?configurado=1', true, 303);
                exit;
            }
            $erros[] = 'A configuração inicial já foi concluída. Acesse pela tela de login.';
        } catch (PDOException | RuntimeException $e) {
            error_log('Falha ao criar o administrador inicial: ' . $e->getMessage());
            $erros[] = 'Não foi possível criar o administrador. Verifique o banco de dados e tente novamente.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuração inicial | Validade e Lotes</title>
    <link rel="icon" type="image/png" sizes="32x32" href="favicon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="favicon-192.png">
    <link rel="apple-touch-icon" href="favicon-192.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
<main class="container py-5" style="max-width: 560px">
    <div class="card shadow-sm">
        <div class="card-body p-4 p-md-5">
            <h1 class="h3 mb-2">Configurar administrador</h1>
            <p class="text-body-secondary mb-4">Crie a primeira conta para proteger o acesso ao sistema.</p>
            <?php if ($erros !== []): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= escapar($erro) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>
            <?php if ($configuracaoPronta): ?>
                <form method="post" action="setup.php">
                    <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="nome">Nome</label>
                        <input class="form-control" id="nome" name="nome" maxlength="120" required
                               autocomplete="name" value="<?= escapar($nome) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">E-mail</label>
                        <input class="form-control" type="email" id="email" name="email" maxlength="190" required
                               autocomplete="email" value="<?= escapar($email) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="senha">Senha</label>
                        <input class="form-control" type="password" id="senha" name="senha" minlength="12"
                               autocomplete="new-password" required>
                        <div class="form-text">Use pelo menos 12 caracteres.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="confirmar_senha">Confirme a senha</label>
                        <input class="form-control" type="password" id="confirmar_senha" name="confirmar_senha"
                               minlength="12" autocomplete="new-password" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Criar administrador</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
