<?php

require "../script/conexao.php";
require "../script/funcoes.php";

if (!isset($_GET['id'])) {
    die("Usuário não informado.");
}

$id = $_GET['id'];

// Se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = $_POST['senha'];
    $tipo = $_POST['tipo'];

    if (atualizarUsuario($conn, $id, $nome, $email, $senha, $tipo)) {

        header("Location: ../index.php");
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
    <title>Editar Usuário</title>
</head>

<body>

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

    <label>Senha:</label>
    <input
        type="text"
        name="senha"
        value="<?= htmlspecialchars($usuario['senha']) ?>"
        required
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

</body>
</html>