<?php

declare(strict_types=1);

require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/funcoes_logs.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respostaAcesso(405, 'Use o botão Sair no menu.');
}
verificarSessao();
try {
    if (isset($_SESSION['id_usuario'])) {
        $idUsuario = (int) $_SESSION['id_usuario'];
        $nomeUsuario = (string) ($_SESSION['nome'] ?? 'Desconhecido');

        registrarLog(
            $conn,
            'Logout',
            'Usuário ' . $nomeUsuario . ' saiu do sistema.',
            $idUsuario
        );
    }
} catch (Throwable $e) {
    error_log((string) $e);
}

encerrarSessao();

header('Location: ' . BASE_URL . 'pages/login.php');
exit;