<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';

$usuario = exigirLogin();
date_default_timezone_set('America/Sao_Paulo');

$categorias = ['Frios', 'Congelados', 'Secos'];
$hoje = new DateTimeImmutable('today');
$limite = $hoje->modify('+15 days');
$busca = trim(is_string($_GET['busca'] ?? null) ? $_GET['busca'] : '');
$categoria = is_string($_GET['categoria'] ?? null) ? $_GET['categoria'] : '';
$situacao = is_string($_GET['situacao'] ?? null) ? $_GET['situacao'] : 'todos';
$situacoes = ['todos', 'vencidos', 'proximos', 'em_dia'];
$categoria = in_array($categoria, $categorias, true) ? $categoria : '';
$situacao = in_array($situacao, $situacoes, true) ? $situacao : 'todos';
$pagina = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$pagina = $pagina === false ? 1 : $pagina;
$porPagina = 25;

$produtos = [];
$contagens = ['total' => 0, 'vencidos' => 0, 'proximos' => 0, 'em_dia' => 0];
$totalFiltrado = 0;
$totalPaginas = 1;
$erroBanco = false;

try {
    $pdo = Conexao::obter();
    $resumo = $pdo->prepare(
        'SELECT COUNT(*) AS total,
                SUM(data_validade < :hoje_vencido) AS vencidos,
                SUM(data_validade >= :hoje_proximo AND data_validade <= :limite_proximo) AS proximos,
                SUM(data_validade > :limite_em_dia) AS em_dia
         FROM produtos_lotes
         WHERE usuario_id = :usuario_id'
    );
    $resumo->execute([
        'usuario_id' => $usuario['id'],
        'hoje_vencido' => $hoje->format('Y-m-d'),
        'hoje_proximo' => $hoje->format('Y-m-d'),
        'limite_proximo' => $limite->format('Y-m-d'),
        'limite_em_dia' => $limite->format('Y-m-d'),
    ]);
    $dadosResumo = $resumo->fetch();
    foreach ($contagens as $chave => $valor) {
        $contagens[$chave] = (int) ($dadosResumo[$chave] ?? 0);
    }

    $condicoes = [];
    $parametros = [];

    $condicoes[] = 'usuario_id = :usuario_id';
    $parametros['usuario_id'] = $usuario['id'];

    if ($busca !== '') {
        $condicoes[] = '(descricao LIKE :busca_descricao OR lote LIKE :busca_lote)';
        $parametros['busca_descricao'] = '%' . $busca . '%';
        $parametros['busca_lote'] = '%' . $busca . '%';
    }
    if ($categoria !== '') {
        $condicoes[] = 'categoria = :categoria';
        $parametros['categoria'] = $categoria;
    }
    if ($situacao === 'vencidos') {
        $condicoes[] = 'data_validade < :status_hoje';
        $parametros['status_hoje'] = $hoje->format('Y-m-d');
    } elseif ($situacao === 'proximos') {
        $condicoes[] = 'data_validade >= :status_hoje AND data_validade <= :status_limite';
        $parametros['status_hoje'] = $hoje->format('Y-m-d');
        $parametros['status_limite'] = $limite->format('Y-m-d');
    } elseif ($situacao === 'em_dia') {
        $condicoes[] = 'data_validade > :status_limite';
        $parametros['status_limite'] = $limite->format('Y-m-d');
    }

    $where = '';
    if ($condicoes !== []) {
        $where = ' WHERE ' . implode(' AND ', $condicoes);
    }

    $consultaTotal = $pdo->prepare('SELECT COUNT(*) FROM produtos_lotes' . $where);
    $consultaTotal->execute($parametros);
    $totalFiltrado = (int) $consultaTotal->fetchColumn();
    $totalPaginas = max(1, (int) ceil($totalFiltrado / $porPagina));
    $pagina = min($pagina, $totalPaginas);

    $sql = 'SELECT id, descricao, lote, categoria, temperatura_armazenamento,
                   quantidade, data_entrada, data_validade
            FROM produtos_lotes' . $where . '
            ORDER BY data_validade ASC, descricao ASC
            LIMIT :limite OFFSET :deslocamento';
    $consulta = $pdo->prepare($sql);
    foreach ($parametros as $nome => $valor) {
        $consulta->bindValue(':' . $nome, $valor, PDO::PARAM_STR);
    }
    $consulta->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $consulta->bindValue(':deslocamento', ($pagina - 1) * $porPagina, PDO::PARAM_INT);
    $consulta->execute();
    $produtos = $consulta->fetchAll();
} catch (PDOException | RuntimeException $e) {
    error_log('Falha ao carregar o painel de validade: ' . $e->getMessage());
    $erroBanco = true;
}

$tituloPagina = 'Painel';
require __DIR__ . '/topo.php';
?>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Painel de validade</h1>
        <p class="text-body-secondary mb-0">Olá, <?= escapar($usuario['nome']) ?>. Acompanhe os lotes cadastrados.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-primary" href="cadastrar.php">Cadastrar lote</a>
        <?php if ($usuario['perfil'] === 'admin'): ?>
            <a class="btn btn-warning" href="usuarios.php">Gerenciar usuários</a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['sucesso']) && $_GET['sucesso'] === 'cadastrado'): ?>
    <div class="alert alert-success" role="status">Lote cadastrado com sucesso.</div>
<?php elseif (isset($_GET['sucesso']) && $_GET['sucesso'] === 'editado'): ?>
    <div class="alert alert-success" role="status">Lote atualizado com sucesso.</div>
<?php elseif (isset($_GET['sucesso']) && $_GET['sucesso'] === 'excluido'): ?>
    <div class="alert alert-success" role="status">Lote excluído com sucesso.</div>
<?php endif; ?>

<?php if ($erroBanco): ?>
    <div class="alert alert-danger" role="alert">
        Não foi possível carregar o painel. Verifique a conexão com o banco de dados e tente novamente.
    </div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card h-100 shadow-sm"><div class="card-body">
                <div class="small text-body-secondary">Total de lotes</div>
                <div class="display-6 fw-semibold"><?= escapar((string) $contagens['total']) ?></div>
            </div></div>
        </div>
        <div class="col-6 col-xl-3">
            <a class="card h-100 text-decoration-none text-danger shadow-sm" href="?situacao=vencidos">
                <div class="card-body"><div class="small">Vencidos</div>
                    <div class="display-6 fw-semibold"><?= escapar((string) $contagens['vencidos']) ?></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-xl-3">
            <a class="card h-100 text-decoration-none text-dark shadow-sm" href="?situacao=proximos">
                <div class="card-body"><div class="small">Vencem em até 15 dias</div>
                    <div class="display-6 fw-semibold"><?= escapar((string) $contagens['proximos']) ?></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-xl-3">
            <a class="card h-100 text-decoration-none text-success shadow-sm" href="?situacao=em_dia">
                <div class="card-body"><div class="small">Vencem depois de 15 dias</div>
                    <div class="display-6 fw-semibold"><?= escapar((string) $contagens['em_dia']) ?></div>
                </div>
            </a>
        </div>
    </div>

    <section class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-0">Lotes por vencimento</h2>
        </div>
        <div class="card-body border-bottom">
            <form method="get" action="index.php" class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label" for="busca">Produto ou lote</label>
                    <input class="form-control" id="busca" name="busca" value="<?= escapar($busca) ?>"
                           placeholder="Digite para buscar">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="categoria">Categoria</label>
                    <select class="form-select" id="categoria" name="categoria">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $opcao): ?>
                            <option value="<?= escapar($opcao) ?>" <?= $categoria === $opcao ? 'selected' : '' ?>>
                                <?= escapar($opcao) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="situacao">Situação</label>
                    <select class="form-select" id="situacao" name="situacao">
                        <option value="todos" <?= $situacao === 'todos' ? 'selected' : '' ?>>Todas</option>
                        <option value="vencidos" <?= $situacao === 'vencidos' ? 'selected' : '' ?>>Vencidos</option>
                        <option value="proximos" <?= $situacao === 'proximos' ? 'selected' : '' ?>>Vencem em até 15 dias</option>
                        <option value="em_dia" <?= $situacao === 'em_dia' ? 'selected' : '' ?>>Vencem depois de 15 dias</option>
                    </select>
                </div>
                <div class="col-12 col-md-1 d-grid">
                    <button class="btn btn-outline-primary" type="submit">Filtrar</button>
                </div>
            </form>
        </div>
        <div class="table-responsive">
           <table class="table align-middle mb-0">
                <thead>
                <tr>
                    <th scope="col" style="min-width: 200px;">Produto / Lote</th>
                    <th scope="col">Categoria</th>
                    <th scope="col" class="text-center">Qtd.</th>
                    <th scope="col">Validade & Status</th>
                    <th scope="col" class="text-end">Ações</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($produtos === []): ?>
                    <tr><td colspan="5" class="text-center text-body-secondary py-4">Nenhum lote encontrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($produtos as $produto): ?>
                        <?php
                        $validade = new DateTimeImmutable($produto['data_validade']);
                        $diasRestantes = (int) $hoje->diff($validade)->format('%r%a');
                        $classeLinha = $diasRestantes < 0 ? 'table-danger' : ($diasRestantes <= 15 ? 'table-warning' : '');
                        ?>
                        <tr class="<?= escapar($classeLinha) ?>">
                            <td>
                                <div class="fw-semibold text-truncate" style="max-width: 250px;" title="<?= escapar($produto['descricao']) ?>">
                                    <?= escapar($produto['descricao']) ?>
                                </div>
                                <div class="small text-body-secondary">
                                    Lote: <span class="text-body fw-medium"><?= escapar($produto['lote']) ?></span>
                                    <span class="mx-1">•</span>
                                    <?= escapar($produto['temperatura_armazenamento']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-body border border-secondary-subtle">
                                    <?= escapar($produto['categoria']) ?>
                                </span>
                            </td>
                            <td class="text-center fw-semibold">
                                <?= escapar((string) $produto['quantidade']) ?>
                            </td>
                            <td>
                                <div class="fw-medium small mb-1">
                                    Validade: <?= escapar($validade->format('d/m/Y')) ?>
                                </div>
                                <div>
                                    <?php if ($diasRestantes < 0): ?>
                                        <span class="badge text-bg-danger">Vencido há <?= escapar((string) abs($diasRestantes)) ?> dia(s)</span>
                                    <?php elseif ($diasRestantes === 0): ?>
                                        <span class="badge text-bg-warning">Vence hoje</span>
                                    <?php elseif ($diasRestantes <= 15): ?>
                                        <span class="badge text-bg-warning"><?= escapar((string) $diasRestantes) ?> dia(s)</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-success"><?= escapar((string) $diasRestantes) ?> dia(s)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary me-1" href="editar.php?id=<?= escapar((string) $produto['id']) ?>">
                                    Editar
                                </a>
                                <form class="d-inline" method="post" action="excluir.php"
                                      onsubmit="return confirm('Deseja realmente excluir este lote?');">
                                    <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
                                    <input type="hidden" name="id" value="<?= escapar((string) $produto['id']) ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Excluir</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPaginas > 1): ?>
            <?php
            $filtrosPaginacao = ['busca' => $busca, 'categoria' => $categoria, 'situacao' => $situacao];
            ?>
            <div class="card-footer d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                <span class="small text-body-secondary">
                    Página <?= escapar((string) $pagina) ?> de <?= escapar((string) $totalPaginas) ?>
                    (<?= escapar((string) $totalFiltrado) ?> lotes)
                </span>
                <nav aria-label="Paginação dos lotes">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= escapar(http_build_query($filtrosPaginacao + ['pagina' => max(1, $pagina - 1)])) ?>"
                               aria-label="Página anterior">Anterior</a>
                        </li>
                        <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= escapar(http_build_query($filtrosPaginacao + ['pagina' => min($totalPaginas, $pagina + 1)])) ?>"
                               aria-label="Próxima página">Próxima</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </section>
    <p class="small text-body-secondary mt-3 mb-0">
        Vermelho: vencido · Amarelo: vence hoje ou nos próximos 15 dias · Verde: vence depois de 15 dias.
    </p>
<?php endif; ?>
<?php require __DIR__ . '/rodape.php'; ?>