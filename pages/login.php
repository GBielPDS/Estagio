<?php

require_once '../script/sessao.php';
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

                header('Location: ' . BASE_URL . 'index.php');
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

    <title>GestSaúde</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="../css/style.css">
</head>

<body class="pagina-login">

    <div class="login-container">

        <div class="logo">
            <img src="../gestsaude-logo.svg" alt="Logo GestSaúde">
        </div>

        <h2 class="titulo-formulario">Seja Bem-Vindo</h2>

    <?php if ($mensagem !== ''): ?>

        <p>
            <?php echo $mensagem; ?>
        </p>

    <?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" id="formLogin">

            <div class="grupamento">
                <label for="emailInput">E-mail</label>
                <input type="email" id="emailInput" name="email" placeholder="usuario@gmail.com" required>
            </div>

            <div class="grupamento">
                <label for="senhaInput">Senha</label>
                <input type="password" id="senhaInput" name="senha" placeholder="Senha" required>
            </div>

           <button type="submit" class="button-submit">Entrar</button>
         

            <div class="footer-links">
                <a href="login.php">Esqueceu sua senha?</a>
                <a href="cadastrar_usuario.php">Primeiro Acesso</a>
            </div>

        </form>

    <br>

</body>

</html>
