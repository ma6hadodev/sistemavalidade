<?php
declare(strict_types=1);

function escapar(string $texto): string {
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function tokenCsrf(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Proteção contra força bruta na tela de login
const LOGIN_MAX_TENTATIVAS = 5;
const LOGIN_BLOQUEIO_SEGUNDOS = 900;

function bloqueioLoginRestante(): int {
    $bloqueadoAte = (int) ($_SESSION['login_bloqueado_ate'] ?? 0);
    $restante = $bloqueadoAte - time();
    if ($restante <= 0) {
        unset($_SESSION['login_bloqueado_ate']);
        return 0;
    }
    return $restante;
}

function registrarFalhaLogin(): void {
    $tentativas = (int) ($_SESSION['login_tentativas'] ?? 0) + 1;
    if ($tentativas >= LOGIN_MAX_TENTATIVAS) {
        $_SESSION['login_tentativas'] = 0;
        $_SESSION['login_bloqueado_ate'] = time() + LOGIN_BLOQUEIO_SEGUNDOS;
        return;
    }
    $_SESSION['login_tentativas'] = $tentativas;
}

function limparTentativasLogin(): void {
    unset($_SESSION['login_tentativas'], $_SESSION['login_bloqueado_ate']);
}

function textoDentroDoLimite(string $texto, int $limite): bool {
    $tamanho = mb_strlen(trim($texto));
    return $tamanho > 0 && $tamanho <= $limite;
}

function dataValida(string $data): bool {
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $data);
    return $d && $d->format('Y-m-d') === $data;
}

function valorPost(string $campo, string $padrao = ''): string {
    $valor = $_POST[$campo] ?? $padrao;
    return is_string($valor) ? trim($valor) : $padrao;
}

function senhaPost(string $campo): string {
    $senha = $_POST[$campo] ?? '';
    return is_string($senha) ? $senha : '';
}

function obterCamposProduto(array $entrada, string $dataEntradaPadrao = ''): array {
    $campos = [
        'descricao' => '',
        'lote' => '',
        'categoria' => '',
        'temperatura_armazenamento' => '',
        'quantidade' => '',
        'data_entrada' => $dataEntradaPadrao,
        'data_validade' => '',
    ];

    foreach ($campos as $campo => $valorAtual) {
        $valor = $entrada[$campo] ?? $valorAtual;
        $campos[$campo] = is_string($valor) ? trim($valor) : '';
    }

    return $campos;
}

function validarProduto(array $valores, array $categorias): array {
    $erros = [];

    if (!textoDentroDoLimite($valores['descricao'], 255)) {
        $erros[] = 'Informe a descrição do produto (até 255 caracteres).';
    }
    if (!textoDentroDoLimite($valores['lote'], 100)) {
        $erros[] = 'Informe o lote (até 100 caracteres).';
    }
    if (!in_array($valores['categoria'], $categorias, true)) {
        $erros[] = 'Selecione uma categoria válida.';
    }
    if (!textoDentroDoLimite($valores['temperatura_armazenamento'], 100)) {
        $erros[] = 'Informe as condições de armazenamento (até 100 caracteres).';
    }

    $quantidade = filter_var($valores['quantidade'], FILTER_VALIDATE_INT);
    if ($quantidade === false || $quantidade < 1 || $quantidade > 4294967295) {
        $erros[] = 'A quantidade deve ser um número inteiro entre 1 e 4.294.967.295.';
    }
    if (!dataValida($valores['data_entrada'])) {
        $erros[] = 'Informe uma data de entrada válida.';
    }
    if (!dataValida($valores['data_validade'])) {
        $erros[] = 'Informe uma data de validade válida.';
    } elseif (dataValida($valores['data_entrada']) && $valores['data_validade'] < $valores['data_entrada']) {
        $erros[] = 'A validade não pode ser anterior à data de entrada.';
    }

    return [$erros, $quantidade];
}