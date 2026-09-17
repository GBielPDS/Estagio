<?php

declare(strict_types=1);

$host = "localhost";
$usuario = "root";
$senha = "";
$banco = "almoxarifado";

try {
    $conn = new mysqli($host, $usuario, $senha, $banco);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    die("Erro de conexão: " . $e->getMessage());
}
