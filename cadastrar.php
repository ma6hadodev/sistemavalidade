<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';

$usuario = exigirLogin();
date_default_timezone_set('America/Sao_Paulo');

$categorias = ['Frios', 'Congelados', 'Secos']; // Mesmas categorias do painel
$hoje = new DateTimeImmutable('today');

// Se for um novo cadastro, já preenche a data de entrada com o dia de hoje
$valores = $_SERVER['REQUEST_METHOD'] === 'POST' 
    ? obterCamposProduto($_POST) 
    : obterCamposProduto([], $hoje->format('Y-m-d'));
    
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!tokenPostValido()) {
        $erros[] = 'A sessão do formulário expirou. Atualize a página e tente novamente.';
    }
    
    // Valida os dados enviados usando as funções que já temos
    [$errosProduto, $quantidadeValidada] = validarProduto($valores, $categorias);
    $erros = array_merge($erros, $errosProduto);

    // Se não houver erros, insere no banco de dados
    if ($erros === []) {
        try {
          $comando = Conexao::obter()->prepare(
    'INSERT INTO produtos_lotes 
     (usuario_id, descricao, lote, categoria, temperatura_armazenamento, quantidade, data_entrada, data_validade)
     VALUES (:usuario_id, :descricao, :lote, :categoria, :temperatura, :quantidade, :data_entrada, :data_validade)'
);

$comando->execute([
    'usuario_id' => $usuario['id'], // Associa ao ID do usuário logado
    'descricao' => $valores['descricao'],
    'lote' => $valores['lote'],
    'categoria' => $valores['categoria'],
    'temperatura' => $valores['temperatura_armazenamento'],
    'quantidade' => $quantidadeValidada,
    'data_entrada' => $valores['data_entrada'],
    'data_validade' => $valores['data_validade'],
]);

            // Redireciona para o painel com mensagem de sucesso
            header('Location: index.php?sucesso=cadastrado', true, 303);
            exit;
        } catch (PDOException | RuntimeException $e) {
            error_log('Falha ao cadastrar lote: ' . $e->getMessage());
            $erros[] = 'Não foi possível salvar o lote. Verifique a conexão com o banco de dados e tente novamente.';
        }
    }
}

$tituloPagina = 'Cadastrar lote';
require __DIR__ . '/topo.php';
?>
<div class="mb-4">
    <h1 class="h2 mb-1">Cadastrar lote</h1>
    <p class="text-body-secondary mb-0">Adicione um novo produto ao controle de validade.</p>
</div>

<?php if ($erros !== []): ?>
    <div class="alert alert-danger" role="alert">
        <p class="fw-semibold mb-2">Revise os dados informados:</p>
        <ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= escapar($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php
// Configura as variáveis que o arquivo formulario.php precisa para renderizar os campos
$acaoFormulario = 'cadastrar.php';
$rotuloBotao = 'Cadastrar produto';

// Carrega o arquivo visual do formulário 
require __DIR__ . '/formulario.php';
require __DIR__ . '/rodape.php';