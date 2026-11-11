<?php
declare(strict_types=1);

require_once __DIR__ . '/funcoes.php';
require_once __DIR__ . '/conexao.php';

ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function usuarioAtual(): ?array
{
    return isset($_SESSION['usuario']) && is_array($_SESSION['usuario'])
        ? $_SESSION['usuario']
        : null;
}

function exigirLogin(): array
{
    $usuario = usuarioAtual();
    if ($usuario === null) {
        header('Location: login.php', true, 303);
        exit;
    }

    try {
        $consulta = Conexao::obter()->prepare(
            'SELECT id, nome, email, perfil, ativo FROM usuarios WHERE id = :id LIMIT 1'
        );
        $consulta->execute(['id' => $usuario['id']]);
        $contaAtual = $consulta->fetch();
    } catch (PDOException | RuntimeException $e) {
        error_log('Falha ao validar a sessão do usuário: ' . $e->getMessage());
        http_response_code(503);
        exit('Não foi possível validar seu acesso. Tente novamente em instantes.');
    }

    if ($contaAtual === false || (int) $contaAtual['ativo'] !== 1) {
        unset($_SESSION['usuario']);
        session_regenerate_id(true);
        header('Location: login.php', true, 303);
        exit;
    }

    $_SESSION['usuario'] = [
        'id' => (int) $contaAtual['id'],
        'nome' => $contaAtual['nome'],
        'email' => $contaAtual['email'],
        'perfil' => $contaAtual['perfil'],
    ];

    return $_SESSION['usuario'];
}

function exigirAdmin(): array
{
    $usuario = exigirLogin();
    if ($usuario['perfil'] !== 'admin') {
        http_response_code(403);
        exit('Acesso permitido somente para administradores.');
    }

    return $usuario;
}

function tokenPostValido(): bool
{
    $enviado = $_POST['csrf_token'] ?? '';
    return is_string($enviado)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $enviado);
}