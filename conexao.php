<?php
declare(strict_types=1);

class Conexao {
    private static ?PDO $instancia = null;

    public static function obter(): PDO {
        if (self::$instancia === null) {
            $config = self::configuracao();

            // Evita erro genérico quando o config.php ainda não foi preenchido
            // (a senha pode ser vazia no XAMPP, por isso só o usuário é obrigatório)
            if ($config['user'] === '') {
                http_response_code(500);
                exit('Configuração do banco incompleta. Preencha o usuário e a senha do banco em config.php.');
            }

            try {
                self::$instancia = new PDO(
                    'mysql:host=' . $config['host'] . ';dbname=' . $config['dbname'] . ';charset=utf8mb4',
                    $config['user'],
                    $config['pass'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        // Prepared statements reais: impede a montagem da query no cliente
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            } catch (PDOException $e) {
                // Detalhe fica no log do servidor; o navegador nunca vê credenciais
                error_log('Erro de conexão (ambiente ' . self::ambiente() . '): ' . $e->getMessage());
                exit('Falha ao conectar com o banco de dados.');
            }
        }
        return self::$instancia;
    }

    /**
     * Ambiente atual: 'local' (XAMPP) ou 'producao' (hospedagem).
     */
    public static function ambiente(): string
    {
        $forcado = getenv('APP_ENV');
        if (is_string($forcado) && in_array($forcado, ['local', 'producao'], true)) {
            return $forcado;
        }

        // XAMPP no Windows: C:\xampp\htdocs\sistemadevalidade
        $caminhoWindows = preg_match('/^[A-Za-z]:[\\\\\/]/', __DIR__) === 1;
        return $caminhoWindows && stripos(__DIR__, 'htdocs') !== false ? 'local' : 'producao';
    }

    /**
     * Monta a configuração final: config.php + variáveis de ambiente.
     */
    private static function configuracao(): array
    {
        $padroes = [
            'local' => [
                'host' => 'localhost',
                'dbname' => 'sistema_validade',
                'user' => 'root',
                'pass' => '',
            ],
            'producao' => [
                'host' => 'localhost',
                'dbname' => 'u837023575_sis_validade',
                'user' => '',
                'pass' => '',
            ],
        ];

        $arquivo = __DIR__ . '/config.php';
        if (is_readable($arquivo)) {
            $dados = require $arquivo;
            if (is_array($dados)) {
                foreach (['local', 'producao'] as $ambiente) {
                    if (isset($dados[$ambiente]) && is_array($dados[$ambiente])) {
                        $padroes[$ambiente] = array_merge($padroes[$ambiente], $dados[$ambiente]);
                    }
                }
            }
        }

        $config = $padroes[self::ambiente()];

        // Variáveis de ambiente têm prioridade (SetEnv no Apache, Docker, etc.)
        $mapa = [
            'DB_HOST' => 'host',
            'DB_NAME' => 'dbname',
            'DB_USER' => 'user',
            'DB_PASS' => 'pass',
        ];
        foreach ($mapa as $variavel => $chave) {
            $valor = getenv($variavel);
            if (is_string($valor) && $valor !== '') {
                $config[$chave] = $valor;
            }
        }

        return $config;
    }
}
