<?php

require_once '../script/sessao.php';
require_once '../script/conexao.php';

verificarSessao();

verificarTipo(['Administrador']);

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];

    if ($nome === '' || $email === '' || $senha === '') {

        $mensagem = 'Preencha todos os campos.';
        $tipo_mensagem = 'erro';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $mensagem = 'Digite um email válido.';
        $tipo_mensagem = 'erro';

    } else {

        $sql = 'SELECT id_usuario FROM usuario WHERE email = ?';

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $email);
        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {

            $mensagem = 'Este email já está cadastrado.';
            $tipo_mensagem = 'erro';

        } else {

            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

            $sql = 'INSERT INTO usuario (nome, email, senha, tipo)
                    VALUES (?, ?, ?, ?)';

            $stmt = $conn->prepare($sql);

            $tipo = 'Usuario';

            $stmt->bind_param('ssss', $nome, $email, $senha_hash, $tipo);

            if ($stmt->execute()) {

                $mensagem = 'Cadastro realizado com sucesso.';
                $tipo_mensagem = 'sucesso';

            } else {

                $mensagem = 'Erro ao realizar o cadastro.';
                $tipo_mensagem = 'erro';
            }
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Cadastro</title>
</head>

<body>

    <h1>Cadastro</h1>

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

        <br><br>

        <button type="submit">Cadastrar</button>

    </form>

    <br>

    <a href="login.php">Já possui uma conta? Fazer login</a>

</body>

</html>