<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';

$usuario = exigirLogin();
$pdo = Conexao::obter();

$mensagemSucesso = '';
$mensagemErro = '';

// Processa a atualização do perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!tokenPostValido()) {
        $mensagemErro = 'Sessão inválida. Tente novamente.';
    } else {
        $nome = valorPost('nome');
        $telegramChatId = valorPost('telegram_chat_id');
        $telegramChatId = $telegramChatId === '' ? null : $telegramChatId;

        if (!textoDentroDoLimite($nome, 120)) {
            $mensagemErro = 'Informe o nome (até 120 caracteres).';
        } elseif ($telegramChatId !== null && preg_match('/^-?\d{5,20}$/', $telegramChatId) !== 1) {
            $mensagemErro = 'O Telegram Chat ID deve conter apenas números (até 20 dígitos).';
        } else {
            try {
                $stmt = $pdo->prepare('
                    UPDATE usuarios 
                    SET nome = :nome, telegram_chat_id = :telegram_chat_id 
                    WHERE id = :id
                ');
                $stmt->execute([
                    'nome' => $nome,
                    'telegram_chat_id' => $telegramChatId,
                    'id' => $usuario['id'],
                ]);

                // Atualiza os dados da sessão atual
                $_SESSION['usuario']['nome'] = $nome;
                $usuario['nome'] = $nome;
                $usuario['telegram_chat_id'] = $telegramChatId;

                $mensagemSucesso = 'Configurações salvas com sucesso!';
            } catch (PDOException $e) {
                error_log('Erro ao atualizar perfil: ' . $e->getMessage());
                $mensagemErro = 'Não foi possível salvar as alterações.';
            }
        }
    }
} else {
    // Busca dados atualizados do banco
    $stmt = $pdo->prepare('SELECT nome, email, telegram_chat_id FROM usuarios WHERE id = :id');
    $stmt->execute(['id' => $usuario['id']]);
    $dadosBanco = $stmt->fetch();
    if ($dadosBanco) {
        $usuario['nome'] = $dadosBanco['nome'];
        $usuario['email'] = $dadosBanco['email'];
        $usuario['telegram_chat_id'] = $dadosBanco['telegram_chat_id'];
    }
}

$tituloPagina = 'Configurações do Perfil';
require __DIR__ . '/topo.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2 mb-0">Configurações da Conta</h1>
            <a href="index.php" class="btn btn-outline-secondary">Voltar ao Painel</a>
        </div>

        <?php if ($mensagemSucesso !== ''): ?>
            <div class="alert alert-success" role="alert"><?= escapar($mensagemSucesso) ?></div>
        <?php endif; ?>

        <?php if ($mensagemErro !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= escapar($mensagemErro) ?></div>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h2 class="h5 mb-0">Dados Pessoais</h2>
            </div>
            <div class="card-body">
                <form method="post" action="perfil.php">
                    <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">

                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome Completo</label>
                        <input type="text" class="form-control" id="nome" name="nome" 
                               value="<?= escapar($usuario['nome']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" 
                               value="<?= escapar($usuario['email'] ?? '') ?>" disabled>
                        <div class="form-text">O e-mail não pode ser alterado por aqui.</div>
                    </div>

                    <hr class="my-4">

                    <h3 class="h5 mb-3">Notificações via Telegram</h3>

                    <div class="mb-3">
                        <label for="telegram_chat_id" class="form-label">Telegram Chat ID</label>
                        <input type="text" class="form-control" id="telegram_chat_id" name="telegram_chat_id" 
                               value="<?= escapar((string)($usuario['telegram_chat_id'] ?? '')) ?>" 
                               placeholder="Ex: 8577031745">
                        <div class="form-text">
                            Informe o seu ID numérico para receber alertas diários de vencimento diretamente no aplicativo.
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Card de Instruções de Ajuda -->
        <div class="card border-info shadow-sm">
            <div class="card-body">
                <h4 class="h6 text-info mb-2">💡 Como descobrir o seu Chat ID do Telegram?</h4>
                <ol class="small text-body-secondary mb-0 ps-3">
                    <li class="mb-1">
                        Abra o Telegram e inicie uma conversa com o robô 
                        <a href="https://t.me/userinfobot" target="_blank" class="text-info fw-semibold">@userinfobot</a>.
                    </li>
                    <li class="mb-1">
                        Envie qualquer mensagem para ele e copie o número exibido no campo <b>Id</b>.
                    </li>
                    <li class="mb-1">
                        Inicie a conversa com o bot de alertas do sistema e clique em <b>START / INICIAR</b>.
                    </li>
                    <li>
                        Cole o número copiado no campo acima e clique em <b>Salvar Alterações</b>.
                    </li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/rodape.php'; ?>