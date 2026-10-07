<?php

declare(strict_types=1);
require_once __DIR__ . '/configuracao.php';

$host = "localhost";
$usuario = "u821266728_SanitasSacra";
$senha = "q6h+y@qZBy3>";
$banco = getenv("GESTSAUDE_DB") ?: "u821266728_schema";

try {
    $conn = new mysqli($host, $usuario, $senha, $banco);
    $conn->set_charset('utf8mb4');
    configurarHorarioBanco($conn);
} catch (mysqli_sql_exception $e) {
    error_log((string) $e);
    http_response_code(503);
    die('Não foi possível conectar ao banco. Contate o responsável pelo sistema.');
}
