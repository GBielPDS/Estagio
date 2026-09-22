<?php

declare(strict_types=1);

require_once '../script/sessao.php';
require_once '../script/conexao.php';
require_once '../script/funcoes_usuarios.php';
require_once '../script/funcoes_logs.php';

$modoAdministrador = (($_GET['admin'] ?? $_POST['admin'] ?? '') === '1');
$modoRecuperar = (($_GET['modo'] ?? $_POST['modo'] ?? '') === 'recuperar');

if ($modoAdministrador) {
    verificarSessao();
    verificarTipo(['Administrador']);
}

$mensagem = '';
$tipo_mensagem = '';
$tipo = 'Usuario';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($modoRecuperar) {
        $email = trim((string) ($_POST['email'] ?? ''));
        $senha = (string) ($_POST['senha'] ?? '');

        if ($email === '' || $senha === '') {
            $mensagem = 'Preencha todos os campos.';
            $tipo_mensagem = 'erro';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensagem = 'Digite um email válido.';
            $tipo_mensagem = 'erro';
        } else {
            $resultado = recuperarSenhaUsuario($conn, $email, $senha);

            if ($resultado['sucesso']) {
                $acaoLog = !empty($resultado['reativado']) ? 'Reativação de usuário' : 'Recuperação de senha';
                $descricaoLog = !empty($resultado['reativado'])
                    ? 'Usuário ' . (string) $resultado['nome'] . ' (ID ' . (int) $resultado['id'] . ') reativou sua conta e redefiniu a senha.'
                    : 'Usuário ' . (string) $resultado['nome'] . ' (ID ' . (int) $resultado['id'] . ') redefiniu sua senha de acesso.';

                registrarLog($conn, $acaoLog, $descricaoLog, (int) $resultado['id']);

                $_SESSION['mensagem_cadastro'] = [
                    'texto' => (string) $resultado['mensagem'],
                    'tipo' => 'sucesso'
                ];

                header('Location: ' . BASE_URL . 'pages/login.php');
                exit;
            }

            $mensagem = (string) $resultado['mensagem'];
            $tipo_mensagem = 'erro';
        }
    } else {
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $senha = (string) ($_POST['senha'] ?? '');
        $tipo = $modoAdministrador ? (string) ($_POST['tipo'] ?? 'Usuario') : 'Usuario';

        if ($nome === '' || $email === '' || $senha === '') {
            $mensagem = 'Preencha todos os campos.';
            $tipo_mensagem = 'erro';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensagem = 'Digite um email válido.';
            $tipo_mensagem = 'erro';
        } elseif (!in_array($tipo, ['Administrador', 'Suporte', 'Usuario'], true)) {
            $mensagem = 'Tipo de usuário inválido.';
            $tipo_mensagem = 'erro';
        } else {
            $resultado = cadastrarUsuario($conn, $nome, $email, $senha, $tipo, true);

            if ($resultado['sucesso']) {
                $foiReativado = !empty($resultado['reativado']);

                if ($modoAdministrador && isset($_SESSION['id_usuario'])) {
                    $acao = $foiReativado ? 'Reativação de usuário' : 'Cadastro de usuário';
                    $descricao = $foiReativado
                        ? 'Usuário ' . $nome . ' (' . $tipo . ') foi reativado com nova senha pelo administrador.'
                        : 'Usuário ' . $nome . ' (' . $tipo . ') cadastrado.';

                    registrarLog(
                        $conn,
                        $acao,
                        $descricao,
                        (int) $_SESSION['id_usuario']
                    );
                } elseif (!empty($resultado['id'])) {
                    $acao = $foiReativado ? 'Reativação de usuário' : 'Cadastro de usuário';
                    $descricao = $foiReativado
                        ? 'Usuário ' . $nome . ' reativou sua conta no primeiro acesso com nova senha.'
                        : 'Novo usuário ' . $nome . ' realizou primeiro acesso.';

                    registrarLog(
                        $conn,
                        $acao,
                        $descricao,
                        (int) $resultado['id']
                    );
                }

                $textoSucesso = $foiReativado
                    ? 'Conta reativada com sucesso! A nova senha de acesso foi configurada.'
                    : 'Cadastro realizado com sucesso.';

                $_SESSION['mensagem_cadastro'] = [
                    'texto' => $textoSucesso,
                    'tipo' => 'sucesso'
                ];

                if ($modoAdministrador) {
                    header('Location: ' . BASE_URL . 'pages/usuarios.php');
                } else {
                    header('Location: ' . BASE_URL . 'pages/login.php');
                }

                exit;
            }

            $mensagem = (string) $resultado['mensagem'];
            $tipo_mensagem = 'erro';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style.css">
    <title><?= $modoRecuperar ? 'Recuperar Acesso' : ($modoAdministrador ? 'Cadastrar Usuário' : 'Cadastro') ?></title>
</head>

<body class="pagina-login">

    <div class="login-container">

        <div class="logo">
            <img src="../gestsaude-logo.svg" alt="Logo GestSaúde">
        </div>

        <h2 class="titulo-formulario">
            <?php
            if ($modoRecuperar) {
                echo 'Recuperar / Reativar Acesso';
            } elseif ($modoAdministrador) {
                echo 'Cadastrar usuário';
            } else {
                echo 'Cadastro';
            }
            ?>
        </h2>

        <?php if ($mensagem !== ''): ?>
            <div class="mensagem-formulario mensagem-<?= $tipo_mensagem === 'sucesso' ? 'sucesso' : 'erro' ?>">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($modoRecuperar): ?>
            <form method="POST" action="<?= htmlspecialchars((string) $_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" class="formulario-acesso">
                <input type="hidden" name="modo" value="recuperar">

                <div class="grupamento">
                    <label for="email">E-mail da sua conta</label>
                    <input type="email" id="email" name="email" placeholder="usuario@gmail.com" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="grupamento">
                    <label for="senha">Nova Senha</label>
                    <input type="password" id="senha" name="senha" placeholder="Digite sua nova senha" required>
                </div>

                <button type="submit" class="button-submit">Salvar nova senha e reativar</button>
            </form>

            <div class="footer-links">
                <a href="cadastrar_usuario.php">Primeiro Acesso</a>
                <span style="margin: 0 8px; color: var(--texto-suave);">|</span>
                <a href="login.php">Fazer login</a>
            </div>

        <?php else: ?>
            <form method="POST" action="<?= htmlspecialchars((string) $_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" class="formulario-acesso">

                <?php if ($modoAdministrador): ?>
                    <input type="hidden" name="admin" value="1">
                <?php endif; ?>

                <div class="grupamento">
                    <label for="nome">Nome</label>
                    <input type="text" id="nome" name="nome" placeholder="Seu nome completo" value="<?= htmlspecialchars($_POST['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="grupamento">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" placeholder="usuario@gmail.com" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="grupamento">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" placeholder="Digite sua senha" required>
                </div>

                <?php if ($modoAdministrador): ?>
                    <div class="grupamento">
                        <label for="tipo">Tipo de usuário</label>
                        <select id="tipo" name="tipo" class="campo-select">
                            <option value="Usuario" <?= $tipo === 'Usuario' ? 'selected' : '' ?>>Usuario</option>
                            <option value="Suporte" <?= $tipo === 'Suporte' ? 'selected' : '' ?>>Suporte</option>
                            <option value="Administrador" <?= $tipo === 'Administrador' ? 'selected' : '' ?>>Administrador</option>
                        </select>
                    </div>
                <?php endif; ?>

                <button type="submit" class="button-submit">Cadastrar</button>

            </form>

            <div class="footer-links">
                <?php if ($modoAdministrador): ?>
                    <a href="usuarios.php">Voltar para a lista de usuários</a>
                <?php else: ?>
                    <a href="login.php">Já possui uma conta? Fazer login</a>
                    <span style="margin: 0 8px; color: var(--texto-suave);">|</span>
                    <a href="cadastrar_usuario.php?modo=recuperar">Recuperar senha / Reativar conta</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

</body>

</html>