<?php

require_once '../script/sessao.php';
require "../script/conexao.php";
require "../script/funcoes_usuarios.php";
require "../script/sidebar.php";

verificarSessao();

verificarTipo(['Administrador']);

if (!isset($_GET['id'])) {
    die("Usuário não informado.");
}

$id = $_GET['id'];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = $_POST['senha'];
    $tipo = $_POST['tipo'];

    if (atualizarUsuario($conn, $id, $nome, $email, $senha, $tipo)) {

        header("Location: usuarios.php");
        exit;

    } else {
        echo "Erro ao atualizar usuário.";
    }
}

$usuario = buscarUsuarioPorId($conn, $id);

if (!$usuario) {
    die("Usuário não encontrado.");
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style.css">
    <title>Document</title>
</head>

<body>

    <?php sidebar('usuarios'); ?>

    <main class="conteudo">

        <h1>Editar Usuário</h1>

        <form method="POST">

            <label>Nome:</label>
            <input
                type="text"
                name="nome"
                value="<?= htmlspecialchars($usuario['nome']) ?>"
                required
            >

            <br><br>

            <label>Email:</label>
            <input
                type="email"
                name="email"
                value="<?= htmlspecialchars($usuario['email']) ?>"
                required
            >

            <br><br>

            <label>Nova senha:</label>
            <input
                type="password"
                name="senha"
                placeholder="Deixe vazio para manter a senha atual"
            >

            <br><br>

            <label>Tipo:</label>

            <select name="tipo">
                <option value="Administrador"
                    <?= $usuario['tipo'] == 'Administrador' ? 'selected' : '' ?>>
                    Administrador
                </option>

                <option value="Suporte"
                    <?= $usuario['tipo'] == 'Suporte' ? 'selected' : '' ?>>
                    Suporte
                </option>

                <option value="Usuario"
                    <?= $usuario['tipo'] == 'Usuario' ? 'selected' : '' ?>>
                    Usuário
                </option>
            </select>

            <br><br>

            <button type="submit">Salvar</button>

        </form>

    </main>

</body>
</html>