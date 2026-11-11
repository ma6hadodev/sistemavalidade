<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';

$administrador = exigirAdmin();
$erros = [];
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!tokenPostValido()) {
        $erros[] = 'A sessão expirou. Atualize a página e tente novamente.';
    } elseif (valorPost('acao') === 'criar') {
        $nome = valorPost('nome');
        $email = strtolower(valorPost('email'));
        $senha = senhaPost('senha');
        $perfil = valorPost('perfil');

        if (!textoDentroDoLimite($nome, 120)) {
            $erros[] = 'Informe o nome (até 120 caracteres).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $erros[] = 'Informe um endereço de e-mail válido.';
        }
        if (strlen($senha) < 12) {
            $erros[] = 'A senha deve ter pelo menos 12 caracteres.';
        }
        if (!in_array($perfil, ['admin', 'usuario'], true)) {
            $erros[] = 'Selecione um perfil válido.';
        }

        if ($erros === []) {
            try {
                $comando = Conexao::obter()->prepare(
                    'INSERT INTO usuarios (nome, email, senha, perfil, ativo)
                     VALUES (:nome, :email, :senha, :perfil, 1)'
                );
                $comando->execute([
                    'nome' => $nome,
                    'email' => $email,
                    'senha' => password_hash($senha, PASSWORD_DEFAULT),
                    'perfil' => $perfil,
                ]);
                $sucesso = 'Usuário criado com sucesso.';
            } catch (PDOException $e) {
                error_log('Falha ao criar usuário: ' . $e->getMessage());
                $erros[] = $e->getCode() === '23000'
                    ? 'Já existe uma conta cadastrada com esse e-mail.'
                    : 'Não foi possível criar o usuário. Verifique a conexão com o banco de dados.';
            } catch (RuntimeException $e) {
                error_log('Falha ao criar usuário: ' . $e->getMessage());
                $erros[] = 'Não foi possível criar o usuário. Verifique a configuração do banco de dados.';
            }
        }
    } elseif (valorPost('acao') === 'atualizar') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $perfil = valorPost('perfil');
        $ativo = valorPost('ativo');

        if ($id === false || $id === null || !in_array($perfil, ['admin', 'usuario'], true) || !in_array($ativo, ['0', '1'], true)) {
            $erros[] = 'Os dados da conta são inválidos.';
        } elseif ((int) $id === $administrador['id'] && ($perfil !== 'admin' || $ativo !== '1')) {
            $erros[] = 'Você não pode desativar nem remover seu próprio perfil de administrador.';
        } else {
            $pdo = null;
            try {
                $pdo = Conexao::obter();
                $pdo->beginTransaction();

                $consultaUsuario = $pdo->prepare(
                    'SELECT id, perfil, ativo FROM usuarios WHERE id = :id FOR UPDATE'
                );
                $consultaUsuario->execute(['id' => $id]);
                $usuarioAlvo = $consultaUsuario->fetch();
                if ($usuarioAlvo === false) {
                    $erros[] = 'A conta selecionada não foi encontrada.';
                } else {
                    $administradoresAtivos = $pdo->query(
                        'SELECT id FROM usuarios WHERE perfil = \'admin\' AND ativo = 1 FOR UPDATE'
                    )->fetchAll();
                    $vaiRemoverAdminAtivo = $usuarioAlvo['perfil'] === 'admin'
                        && (int) $usuarioAlvo['ativo'] === 1
                        && ($perfil !== 'admin' || $ativo !== '1');
                    if ($vaiRemoverAdminAtivo && count($administradoresAtivos) <= 1) {
                        $erros[] = 'O sistema precisa manter pelo menos um administrador ativo.';
                    } else {
                        $atualizacao = $pdo->prepare(
                            'UPDATE usuarios SET perfil = :perfil, ativo = :ativo WHERE id = :id'
                        );
                        $atualizacao->execute([
                            'perfil' => $perfil,
                            'ativo' => (int) $ativo,
                            'id' => $id,
                        ]);
                        $sucesso = 'Permissões da conta atualizadas.';
                    }
                }

                if ($erros === []) {
                    $pdo->commit();
                } else {
                    $pdo->rollBack();
                }
            } catch (PDOException | RuntimeException $e) {
                if ($pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Falha ao atualizar usuário: ' . $e->getMessage());
                $erros[] = 'Não foi possível atualizar a conta. Verifique o banco de dados e tente novamente.';
            }
        }
    } else {
        $erros[] = 'Ação inválida.';
    }
}

$usuarios = [];
try {
    $usuarios = Conexao::obter()->query(
        'SELECT id, nome, email, perfil, ativo, criado_em
         FROM usuarios
         ORDER BY nome ASC'
    )->fetchAll();
} catch (PDOException | RuntimeException $e) {
    error_log('Falha ao carregar usuários: ' . $e->getMessage());
    $erros[] = 'Não foi possível carregar a lista de usuários.';
}

$tituloPagina = 'Usuários';
require __DIR__ . '/topo.php';
?>
<div class="mb-4">
    <h1 class="h2 mb-1">Gerenciar usuários</h1>
    <p class="text-body-secondary mb-0">Crie contas e controle o perfil e o acesso de cada pessoa.</p>
</div>
<?php if ($sucesso !== ''): ?><div class="alert alert-success" role="status"><?= escapar($sucesso) ?></div><?php endif; ?>
<?php if ($erros !== []): ?>
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= escapar($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<section class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3"><h2 class="h5 mb-0">Adicionar usuário</h2></div>
    <div class="card-body">
        <form method="post" action="usuarios.php" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
            <input type="hidden" name="acao" value="criar">
            <div class="col-12 col-md-4">
                <label class="form-label" for="nome">Nome</label>
                <input class="form-control" id="nome" name="nome" maxlength="120" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="email">E-mail</label>
                <input class="form-control" type="email" id="email" name="email" maxlength="190" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="senha">Senha inicial</label>
                <input class="form-control" type="password" id="senha" name="senha" minlength="12"
                       autocomplete="new-password" required>
                <div class="form-text">Mínimo de 12 caracteres.</div>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="perfil_novo">Perfil</label>
                <select class="form-select" id="perfil_novo" name="perfil">
                    <option value="usuario">Usuário</option>
                    <option value="admin">Administrador</option>
                </select>
            </div>
            <div class="col-12 d-flex align-items-end">
                <button class="btn btn-primary" type="submit">Criar conta</button>
            </div>
        </form>
    </div>
</section>

<section class="card shadow-sm">
    <div class="card-header bg-white py-3"><h2 class="h5 mb-0">Contas cadastradas</h2></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
            <tr><th>Nome</th><th>E-mail</th><th>Desde</th><th>Perfil e acesso</th><th></th></tr>
            </thead>
            <tbody>
            <?php if ($usuarios === []): ?>
                <tr><td colspan="5" class="text-center text-body-secondary py-4">Nenhuma conta cadastrada.</td></tr>
            <?php else: ?>
                <?php foreach ($usuarios as $conta): ?>
                    <tr>
                        <td><?= escapar($conta['nome']) ?></td>
                        <td><?= escapar($conta['email']) ?></td>
                        <td><?= escapar((new DateTimeImmutable($conta['criado_em']))->format('d/m/Y')) ?></td>
                        <td>
                            <form method="post" action="usuarios.php" class="d-flex flex-column flex-md-row gap-2">
                                <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
                                <input type="hidden" name="acao" value="atualizar">
                                <input type="hidden" name="id" value="<?= escapar((string) $conta['id']) ?>">
                                <select class="form-select form-select-sm" name="perfil" aria-label="Perfil">
                                    <option value="usuario" <?= $conta['perfil'] === 'usuario' ? 'selected' : '' ?>>Usuário</option>
                                    <option value="admin" <?= $conta['perfil'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                                </select>
                                <select class="form-select form-select-sm" name="ativo" aria-label="Acesso">
                                    <option value="1" <?= (int) $conta['ativo'] === 1 ? 'selected' : '' ?>>Ativo</option>
                                    <option value="0" <?= (int) $conta['ativo'] === 0 ? 'selected' : '' ?>>Inativo</option>
                                </select>
                                <button class="btn btn-sm btn-outline-primary text-nowrap" type="submit">Salvar</button>
                            </form>
                        </td>
                        <td><?php if ((int) $conta['id'] === $administrador['id']): ?>
                            <span class="badge text-bg-secondary">Sua conta</span>
                        <?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/rodape.php'; ?>