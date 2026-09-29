<?php

declare(strict_types=1);
require_once __DIR__ . '/configuracao.php';

$host = configuracaoAmbiente('GESTSAUDE_DB_HOST', 'localhost');
$usuario = configuracaoAmbiente('GESTSAUDE_DB_USER', 'root');
$senha = configuracaoAmbiente('GESTSAUDE_DB_PASSWORD');
$banco = configuracaoAmbiente('GESTSAUDE_DB', 'almoxarifado');
$porta = (int) configuracaoAmbiente('GESTSAUDE_DB_PORT', '3306');

try {
    $conn = new mysqli($host, $usuario, $senha, $banco, $porta);
    $conn->set_charset('utf8mb4');
    configurarHorarioBanco($conn);
} catch (mysqli_sql_exception $e) {
    error_log((string) $e);
    http_response_code(503);
    die('Não foi possível conectar ao banco. Contate o responsável pelo sistema.');
}
