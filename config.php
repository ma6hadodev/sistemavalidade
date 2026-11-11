<?php

// Configuracao de conexao com o banco de dados.
//
// O ambiente e escolhido automaticamente pelo conexao.php:
//   - local    : projeto rodando no XAMPP (caminho contendo htdocs)
//   - producao : qualquer outro servidor, ou seja, a hospedagem
//
// Para forcar, defina a variavel de ambiente APP_ENV como "local" ou "producao".
// As variaveis DB_HOST, DB_NAME, DB_USER e DB_PASS tem prioridade sobre este arquivo.
//
// Protegido contra acesso direto: responde 404 e tambem esta bloqueado no .htaccess.

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(404);
    exit;
}

return [
    'local' => [
        'host' => 'localhost',
        'dbname' => 'sistema_validade',
        'user' => 'root',
        'pass' => '',
    ],
    'producao' => [
        // Preencha com o usuario e a senha criados no painel da hospedagem
        'host' => 'localhost',
        'dbname' => 'u837023575_sis_validade',
        'user' => '',
        'pass' => '',
    ],
];
