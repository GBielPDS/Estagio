<?php

require_once 'sessao.php';
require_once 'conexao.php';
require_once 'funcoes_logs.php';

if (isset($_SESSION['id_usuario'])) {
    registrarLog(
        $conn,
        'Logout',
        'Usuário ' . ($_SESSION['nome'] ?? 'Desconhecido') . ' saiu do sistema.',
        $_SESSION['id_usuario']
    );
}

$_SESSION = [];
session_destroy();

header('Location: ' . BASE_URL . 'pages/login.php');
exit;