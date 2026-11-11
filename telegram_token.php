<?php
/**
 * Token do bot do Telegram — arquivo de segredo.
 *
 * Proteções aplicadas:
 *  - Acesso direto pelo navegador responde 404 (independente de Apache ou Nginx).
 *  - Também está bloqueado por regra no .htaccess.
 *  - Ao publicar, envie este arquivo junto: nada precisa ser reconfigurado.
 *
 * Alternativa: apague este arquivo e defina a variável de ambiente
 * TELEGRAM_BOT_TOKEN (SetEnv no .htaccess ou painel da hospedagem).
 *
 * Recomendado: revogar o token atual no @BotFather e colar o novo abaixo.
 */

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(404);
    exit;
}

return 'cole seu token aqui';
