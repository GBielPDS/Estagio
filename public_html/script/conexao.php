<?php

declare(strict_types=1);

require_once __DIR__ . '/configuracao.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;

$diretoriosPossiveisEnv = [
    dirname(__DIR__, 2) . '/ARQUIVOS_SENSIVEIS_AQUI',
    dirname(__DIR__),
    dirname(__DIR__, 2),
];
foreach ($diretoriosPossiveisEnv as $dirEnv) {
    if (file_exists($dirEnv . '/.env')) {
        $dotenv = Dotenv::createImmutable($dirEnv);
        $dotenv->safeLoad();
        break;
    }
}

$host = $_ENV['GESTSAUDE_DB_HOST'] ?? 'localhost';
$usuario = $_ENV['GESTSAUDE_DB_USER'] ?? '';
$senha = $_ENV['GESTSAUDE_DB_PASSWORD'] ?? '';
$banco = $_ENV['GESTSAUDE_DB_NAME'] ?? '';

try {
    $conn = new mysqli(
        $host,
        $usuario,
        $senha,
        $banco
    );

    $conn->set_charset('utf8mb4');
    configurarHorarioBanco($conn);

} catch (mysqli_sql_exception $e) {

    error_log((string) $e);

    http_response_code(503);

    die(
        'Não foi possível conectar ao banco. '
        . 'Contate o responsável pelo sistema.'
    );
}