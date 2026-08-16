<?php

require_once '../script/conexao.php';

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);
    $senha = $_POST['senha'];

    if ($email === '' || $senha === '') {

        $mensagem = 'Preencha todos os campos.';
        $tipo_mensagem = 'erro';

    } else {

        $sql = 'SELECT id_usuario, nome, email, senha, tipo FROM usuario WHERE email = ?';

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $email);
        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {

            $usuario = $resultado->fetch_assoc();

            if (password_verify($senha, $usuario['senha'])) {

                session_start();

                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                $_SESSION['nome'] = $usuario['nome'];
                $_SESSION['email'] = $usuario['email'];
                $_SESSION['tipo'] = $usuario['tipo'];

                header('Location: ../index.php');
                exit();

            } else {

                $mensagem = 'Email ou senha incorretos.';
                $tipo_mensagem = 'erro';
            }

        } else {

            $mensagem = 'Email ou senha incorretos.';
            $tipo_mensagem = 'erro';
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

    <title>Login</title>
</head>

<body>

    <h1>Login</h1>

    <?php if ($mensagem !== ''): ?>

        <p>
            <?php echo $mensagem; ?>
        </p>

    <?php endif; ?>

    <form method="POST" action="">

        <label for="email">Email:</label>
        <br>
        <input type="email" id="email" name="email">

        <br><br>

        <label for="senha">Senha:</label>
        <br>
        <input type="password" id="senha" name="senha">

        <br><br>

        <button type="submit">Entrar</button>

    </form>

    <br>

    <a href="cadastrar_usuario.php">Não possui conta? Cadastre-se</a>

</body>

</html>