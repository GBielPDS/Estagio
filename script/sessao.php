<?php

session_start();

define('BASE_URL', '/git/ESTAGIO/');

function verificarSessao()
{
    if (!isset($_SESSION['id_usuario'])) {
        header('Location: ' . BASE_URL . 'pages/login.php');
        exit();
    }
}

function verificarTipo($tiposPermitidos)
{
    if (!isset($_SESSION['tipo'])) {
        header('Location: ' . BASE_URL . 'pages/login.php');
        exit();
    }

    if (!in_array($_SESSION['tipo'], $tiposPermitidos)) {
        http_response_code(403);
        die('Acesso negado.');
    }
}