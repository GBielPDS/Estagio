<?php

declare(strict_types=1);

$host = "localhost";
$usuario = "root";
$senha = "";
$banco = getenv("GESTSAUDE_DB") ?: "al";

try {
    $conn = new mysqli($host, $usuario, $senha, $banco);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log((string) $e);
    http_response_code(503);
    die('Não foi possível conectar ao banco. Contate o responsável pelo sistema.');
}
