<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';

$usuario = exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !tokenPostValido()) {
    http_response_code(400);
    exit('Solicitação inválida ou sessão expirada.');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    http_response_code(400);
    exit('Identificador de lote inválido.');
}

try {
    $comando = Conexao::obter()->prepare('DELETE FROM produtos_lotes WHERE id = :id AND usuario_id = :usuario_id');
    $comando->execute([
        'id' => $id,
        'usuario_id' => $usuario['id']
    ]);
    if ($comando->rowCount() !== 1) {
        http_response_code(404);
        exit('Lote não encontrado.');
    }
} catch (PDOException | RuntimeException $e) {
    error_log('Falha ao excluir lote: ' . $e->getMessage());
    http_response_code(500);
    exit('Não foi possível excluir o lote. Tente novamente.');
}

header('Location: index.php?sucesso=excluido', true, 303);
exit;