<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/git/ESTAGIO/');
}

function verificarSessao(): void
{
    if (!isset($_SESSION['id_usuario'])) {
        header('Location: ' . BASE_URL . 'pages/login.php');
        exit();
    }
}

function verificarTipo(array $tiposPermitidos): void
{
    if (!isset($_SESSION['tipo'])) {
        header('Location: ' . BASE_URL . 'pages/login.php');
        exit();
    }

    if (!in_array($_SESSION['tipo'], $tiposPermitidos, true)) {
        http_response_code(403);
        die('Acesso negado.');
    }
}