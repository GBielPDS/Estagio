<?php

require_once '../script/sessao.php';
require_once '../script/conexao.php';
require_once '../script/funcoes_usuarios.php';

$modoAdministrador = ($_GET['admin'] ?? $_POST['admin'] ?? '') === '1';

if ($modoAdministrador) {
    verificarSessao();
    verificarTipo(['Administrador']);
}

$mensagem = '';
$tipo_mensagem = '';
$tipo = 'Usuario';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $tipo = $modoAdministrador ? ($_POST['tipo'] ?? 'Usuario') : 'Usuario';

    if ($nome === '' || $email === '' || $senha === '') {

        $mensagem = 'Preencha todos os campos.';
        $tipo_mensagem = 'erro';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $mensagem = 'Digite um email válido.';
        $tipo_mensagem = 'erro';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = 'Digite um email válido.';
        $tipo_mensagem = 'erro';
    } elseif (!in_array($tipo, ['Administrador', 'Suporte', 'Usuario'], true)) {
        $mensagem = 'Tipo de usuário inválido.';
        $tipo_mensagem = 'erro';
    } else {
        $resultado = cadastrarUsuario($conn, $nome, $email, $senha, $tipo);

        if ($resultado['sucesso']) {
            $_SESSION['mensagem_cadastro'] = [
                'texto' => 'Cadastro realizado com sucesso.',
                'tipo' => 'sucesso'
            ];

            if ($modoAdministrador) {
                header('Location: ' . BASE_URL . 'pages/usuarios.php');
            } else {
                header('Location: ' . BASE_URL . 'pages/login.php');
            }

            exit;
        } else {
            $mensagem = $resultado['mensagem'];
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
    <title>Cadastro</title>
</head>

<body class="pagina-login">

    <div class="login-container">

        <div class="logo">
            <img src="../gestsaude-logo.svg" alt="Logo GestSaúde">
        </div>

        <h2 class="titulo-formulario"><?= $modoAdministrador ? 'Cadastrar usuário' : 'Cadastro' ?></h2>

        <?php if ($mensagem !== ''): ?>
            <div class="mensagem-formulario mensagem-<?= $tipo_mensagem === 'sucesso' ? 'sucesso' : 'erro' ?>">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="formulario-acesso">

            <?php if ($modoAdministrador): ?>
                <input type="hidden" name="admin" value="1">
            <?php endif; ?>

            <div class="grupamento">
                <label for="nome">Nome</label>
                <input type="text" id="nome" name="nome" placeholder="Seu nome completo" required>
            </div>

            <div class="grupamento">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" placeholder="usuario@gmail.com" required>
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
            <a href="login.php">Já possui uma conta? Fazer login</a>
        </div>

    </div>

</body>

</html>