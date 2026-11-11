<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';

// Cron de hospedagem costuma rodar em UTC; fixa o fuso para os cálculos de data.
date_default_timezone_set('America/Sao_Paulo');

/**
 * Envio diário dos alertas de validade via Telegram (rotina agendada).
 *
 * Execução permitida somente por:
 *  1. Linha de comando / cron (PHP_SAPI === 'cli') — forma recomendada.
 *  2. Requisição HTTP com ?chave=VALOR igual ao CRON_SECRET do ambiente,
 *     para hospedagens que só oferecem agendamento por URL.
 *
 * O acesso livre pelo navegador foi removido: sem CRON_SECRET configurado,
 * qualquer requisição HTTP recebe 403. Nada é enviado por engano.
 *
 * O token do bot não fica escrito aqui: use a variável de ambiente
 * TELEGRAM_BOT_TOKEN ou o arquivo telegram_token.php.
 */

$executandoViaCli = PHP_SAPI === 'cli';

function execucaoAutorizada(): bool
{
    // 1. Cron via linha de comando (nunca acessível pela web).
    if (PHP_SAPI === 'cli') {
        return true;
    }

    // 2. HTTP: somente com a chave correta, para cron por URL.
    $segredo = getenv('CRON_SECRET');
    $segredo = is_string($segredo) ? trim($segredo) : '';

    if ($segredo === '') {
        return false;
    }

    $recebido = $_GET['chave'] ?? '';
    return is_string($recebido) && hash_equals($segredo, $recebido);
}

if (!execucaoAutorizada()) {
    http_response_code(403);
    exit('Acesso negado.');
}

function obterTokenTelegram(): string
{
    $token = getenv('TELEGRAM_BOT_TOKEN');
    if (is_string($token) && trim($token) !== '') {
        return trim($token);
    }

    $arquivo = __DIR__ . '/telegram_token.php';
    if (is_readable($arquivo)) {
        $valor = require $arquivo;
        if (is_string($valor) && trim($valor) !== '') {
            return trim($valor);
        }
    }

    return '';
}

function enviarTelegram(string $token, string $chatId, string $texto): bool
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.telegram.org/bot' . $token . '/sendMessage');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'chat_id' => $chatId,
        'text' => $texto,
        'parse_mode' => 'HTML',
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Validação do certificado TLS ativa: impede interceptação do token
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erroCurl = curl_errno($ch);
    curl_close($ch);

    if ($erroCurl !== 0) {
        error_log('Falha de cURL ao enviar mensagem ao Telegram (código ' . $erroCurl . ').');
        return false;
    }

    return $httpCode === 200;
}

$tokenTelegram = obterTokenTelegram();
if ($tokenTelegram === '') {
    error_log('Token do Telegram não configurado (defina TELEGRAM_BOT_TOKEN).');

    if ($executandoViaCli) {
        fwrite(STDERR, "Token do Telegram não configurado.\n");
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Token do Telegram não configurado.\n";
        echo "Crie o arquivo telegram_token.php com: <?php return 'SEU_TOKEN';\n";
    }
    exit(1);
}

$enviados = 0;
$falhas = 0;

try {
    $pdo = Conexao::obter();
    $hoje = (new DateTimeImmutable('today'))->format('Y-m-d');
    $limite = (new DateTimeImmutable('+15 days'))->format('Y-m-d');

    $stmtUsuarios = $pdo->prepare(
        "SELECT id, nome, telegram_chat_id
         FROM usuarios
         WHERE telegram_chat_id IS NOT NULL AND telegram_chat_id <> ''"
    );
    $stmtUsuarios->execute();
    $usuarios = $stmtUsuarios->fetchAll();

    $stmtLotes = $pdo->prepare(
        'SELECT descricao, lote, data_validade
         FROM produtos_lotes
         WHERE usuario_id = :usuario_id AND data_validade <= :limite
         ORDER BY data_validade ASC'
    );

    foreach ($usuarios as $usuario) {
        $chatId = trim((string) $usuario['telegram_chat_id']);
        if ($chatId === '') {
            continue;
        }

        $stmtLotes->execute([
            'usuario_id' => (int) $usuario['id'],
            'limite' => $limite,
        ]);
        $lotes = $stmtLotes->fetchAll();

        if ($lotes === []) {
            continue;
        }

        $mensagem = "<b>📢 Relatório Diário de Validade - MachBen</b>\n";
        $mensagem .= 'Olá, ' . htmlspecialchars((string) $usuario['nome'], ENT_QUOTES, 'UTF-8')
            . "! Confira os lotes que exigem atenção:\n\n";

        foreach ($lotes as $lote) {
            $dataValidade = (new DateTimeImmutable($lote['data_validade']))->format('d/m/Y');
            $status = $lote['data_validade'] < $hoje ? '🔴 VENCIDO' : '⚠️ Vence em breve';
            $mensagem .= '• <b>' . htmlspecialchars((string) $lote['descricao'], ENT_QUOTES, 'UTF-8')
                . '</b> (Lote: ' . htmlspecialchars((string) $lote['lote'], ENT_QUOTES, 'UTF-8') . ")\n";
            $mensagem .= "  Validade: {$dataValidade} - {$status}\n\n";
        }

        if (enviarTelegram($tokenTelegram, $chatId, $mensagem)) {
            $enviados++;
        } else {
            $falhas++;
        }
    }

    if (!$executandoViaCli) {
        header('Content-Type: text/plain; charset=utf-8');
    }

    echo 'Execução: ' . (new DateTimeImmutable('now'))->format('d/m/Y H:i') . "\n";
    echo 'Usuários com lotes a notificar: ' . count($usuarios) . "\n";
    echo "Mensagens enviadas: {$enviados}\n";
    echo "Falhas de envio: {$falhas}\n";

    // Sai com erro no cron quando algum envio falhar (aparece no log/e-mail do cron)
    if ($executandoViaCli && $falhas > 0) {
        exit(1);
    }
} catch (Throwable $e) {
    error_log('Falha ao processar notificações do Telegram: ' . $e->getMessage());

    if ($executandoViaCli) {
        fwrite(STDERR, "Falha ao processar notificações.\n");
        exit(1);
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Falha ao processar as notificações. Verifique o log do Apache/PHP.\n";
}
