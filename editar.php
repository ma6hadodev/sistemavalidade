<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';

$usuario = exigirLogin();
date_default_timezone_set('America/Sao_Paulo');

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    http_response_code(400);
    exit('Identificador de lote inválido.');
}

try {
    $consulta = Conexao::obter()->prepare(
        'SELECT id, descricao, lote, categoria, temperatura_armazenamento,
                quantidade, data_entrada, data_validade
         FROM produtos_lotes WHERE id = :id AND usuario_id = :usuario_id'
    );
    $consulta->execute([
        'id' => $id,
        'usuario_id' => $usuario['id'],
    ]);
    $produto = $consulta->fetch();
} catch (PDOException | RuntimeException $e) {
    error_log('Falha ao carregar lote para edição: ' . $e->getMessage());
    http_response_code(500);
    exit('Não foi possível carregar o lote. Tente novamente.');
}
if ($produto === false) {
    http_response_code(404);
    exit('Lote não encontrado.');
}

$categorias = ['Frios', 'Congelados', 'Secos'];
$valores = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? obterCamposProduto($_POST)
    : obterCamposProduto($produto);
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!tokenPostValido()) {
        $erros[] = 'A sessão do formulário expirou. Atualize a página e tente novamente.';
    }
    [$errosProduto, $quantidadeValidada] = validarProduto($valores, $categorias);
    $erros = array_merge($erros, $errosProduto);

    if ($erros === []) {
        try {
           $comando = Conexao::obter()->prepare(
                'UPDATE produtos_lotes
                 SET descricao = :descricao, lote = :lote, categoria = :categoria,
                     temperatura_armazenamento = :temperatura, quantidade = :quantidade,
                     data_entrada = :data_entrada, data_validade = :data_validade
                 WHERE id = :id AND usuario_id = :usuario_id'
            );
            $comando->execute([
                'descricao' => $valores['descricao'],
                'lote' => $valores['lote'],
                'categoria' => $valores['categoria'],
                'temperatura' => $valores['temperatura_armazenamento'],
                'quantidade' => $quantidadeValidada,
                'data_entrada' => $valores['data_entrada'],
                'data_validade' => $valores['data_validade'],
                'id' => $id,
                'usuario_id' => $usuario['id']
            ]);

            header('Location: index.php?sucesso=editado', true, 303);
            exit;
        } catch (PDOException | RuntimeException $e) {
            error_log('Falha ao editar lote: ' . $e->getMessage());
            $erros[] = 'Não foi possível atualizar o lote. Verifique a conexão com o banco de dados e tente novamente.';
        }
    }
}

$tituloPagina = 'Editar lote';
require __DIR__ . '/topo.php';
?>
<div class="mb-4">
    <h1 class="h2 mb-1">Editar lote</h1>
    <p class="text-body-secondary mb-0">Atualize as informações do produto.</p>
</div>
<?php if ($erros !== []): ?>
    <div class="alert alert-danger" role="alert">
        <p class="fw-semibold mb-2">Revise os dados informados:</p>
        <ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= escapar($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>
<?php
$acaoFormulario = 'editar.php?id=' . rawurlencode((string) $id);
$rotuloBotao = 'Salvar alterações';
require __DIR__ . '/formulario.php';
require __DIR__ . '/rodape.php';
