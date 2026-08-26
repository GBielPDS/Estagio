<?php

require_once '../script/sessao.php';
require_once '../script/conexao.php';
require_once '../script/funcoes_usuarios.php';

$modoAdministrador = isset($_GET['admin']) && $_GET['admin'] === '1';

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
            $mensagem = 'Cadastro realizado com sucesso.';
            $tipo_mensagem = 'sucesso';
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

<body>

    <h1><?= $modoAdministrador ? 'Cadastrar usuário' : 'Cadastro' ?></h1>

    <?php if ($mensagem !== ''): ?>

        <p>
            <?php echo $mensagem; ?>
        </p>

    <?php endif; ?>

    <form method="POST" action="">

        <label for="nome">Nome:</label>
        <br>
        <input type="text" id="nome" name="nome">

        <br><br>

        <label for="email">Email:</label>
        <br>
        <input type="email" id="email" name="email">

        <br><br>

        <label for="senha">Senha:</label>
        <br>
        <input type="password" id="senha" name="senha">

        <?php if ($modoAdministrador): ?>
            <br><br>

            <label for="tipo">Tipo de usuário:</label>
            <br>
            <select id="tipo" name="tipo">
                <option value="Usuario" <?= $tipo === 'Usuario' ? 'selected' : '' ?>>Usuario</option>
                <option value="Suporte" <?= $tipo === 'Suporte' ? 'selected' : '' ?>>Suporte</option>
                <option value="Administrador" <?= $tipo === 'Administrador' ? 'selected' : '' ?>>Administrador</option>
            </select>
        <?php endif; ?>

        <br><br>

        <button type="submit">Cadastrar</button>

    </form>

    <br>

    <a href="login.php">Já possui uma conta? Fazer login</a>

</body>

</html>