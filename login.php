<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';

date_default_timezone_set('America/Sao_Paulo');

if (usuarioAtual() !== null) {
    header('Location: index.php', true, 303);
    exit;
}

$erro = '';
$email = strtolower(valorPost('email'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bloqueioRestante = bloqueioLoginRestante();
    if ($bloqueioRestante > 0) {
        $erro = 'Muitas tentativas de acesso. Tente novamente em '
            . (int) ceil($bloqueioRestante / 60) . ' minuto(s).';
    } elseif (!tokenPostValido()) {
        $erro = 'A sessão expirou. Atualize a página e tente novamente.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        registrarFalhaLogin();
        $erro = 'E-mail ou senha inválidos.';
    } else {
        try {
            $comando = Conexao::obter()->prepare(
                'SELECT id, nome, email, senha, perfil, ativo
                 FROM usuarios
                 WHERE email = :email
                 LIMIT 1'
            );
            $comando->execute(['email' => $email]);
            $usuario = $comando->fetch();

            if ($usuario !== false && (int) $usuario['ativo'] === 1 && password_verify(senhaPost('senha'), $usuario['senha'])) {
                limparTentativasLogin();
                session_regenerate_id(true);
                $_SESSION['usuario'] = [
                    'id' => (int) $usuario['id'],
                    'nome' => $usuario['nome'],
                    'email' => $usuario['email'],
                    'perfil' => $usuario['perfil'],
                ];
                unset($_SESSION['csrf_token']);
                tokenCsrf();
                header('Location: index.php', true, 303);
                exit;
            }

            registrarFalhaLogin();
            $erro = 'E-mail ou senha inválidos.';
        } catch (PDOException | RuntimeException $e) {
            error_log('Falha ao autenticar usuário: ' . $e->getMessage());
            $erro = 'Não foi possível acessar o sistema. Confira se a configuração inicial foi concluída.';
        }
    }
}
$tituloPagina = 'Entrar';
require __DIR__ . '/topo.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6 col-xl-5">
        <section class="card shadow-sm">
            <div class="card-header py-3">
                <h1 class="h4 mb-0">Entrar no sistema</h1>
            </div>
            <div class="card-body p-4">
                <p class="text-body-secondary mb-4">Acesse para acompanhar a validade dos seus produtos.</p>

                <?php if (isset($_GET['configurado'])): ?>
                    <div class="alert alert-success" role="status">Administrador criado. Agora você já pode entrar.</div>
                <?php endif; ?>
                <?php if ($erro !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?= escapar($erro) ?></div>
                <?php endif; ?>

                <form method="post" action="login.php">
                    <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="email">E-mail</label>
                        <input class="form-control" type="email" id="email" name="email" autocomplete="username"
                               maxlength="190" required autofocus value="<?= escapar($email) ?>">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="senha">Senha</label>
                        <input class="form-control" type="password" id="senha" name="senha"
                               autocomplete="current-password" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Entrar</button>
                </form>
            </div>
        </section>
    </div>
</div>

<?php require __DIR__ . '/rodape.php'; ?>
