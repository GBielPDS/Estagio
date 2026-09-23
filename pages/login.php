<?php

declare(strict_types=1);

require_once '../script/sessao.php';
require_once '../script/conexao.php';
require_once '../script/funcoes_logs.php';

$mensagem = '';
$tipo_mensagem = '';

if (isset($_SESSION['mensagem_cadastro'])) {
    $mensagem = (string) $_SESSION['mensagem_cadastro']['texto'];
    $tipo_mensagem = (string) $_SESSION['mensagem_cadastro']['tipo'];
    unset($_SESSION['mensagem_cadastro']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim((string) ($_POST['email'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');

    if ($email === '' || $senha === '') {

        $mensagem = 'Preencha todos os campos.';
        $tipo_mensagem = 'erro';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $mensagem = 'Digite um e-mail válido.';
        $tipo_mensagem = 'erro';

    } else {

        $sql = 'SELECT id_usuario, nome, email, senha, tipo, ativo, versao_sessao
                FROM usuario
                WHERE email = ?';

        $stmt = $conn->prepare($sql);

        if ($stmt) {

            $stmt->bind_param('s', $email);
            $stmt->execute();

            $resultado = $stmt->get_result();

            if ($resultado->num_rows > 0) {

                $usuario = $resultado->fetch_assoc();

                if (!password_verify($senha, (string) $usuario['senha'])) {
                    $mensagem = 'Email ou senha incorretos.';
                    $tipo_mensagem = 'erro';
                } elseif ((int) ($usuario['ativo'] ?? 1) === 0) {

                    $mensagem = 'Este usuário foi desativado. Entre em contato com o administrador do sistema.';
                    $tipo_mensagem = 'erro';

                } else {
                    session_regenerate_id(true);
                    $_SESSION['versao_sessao'] = (int) $usuario['versao_sessao'];

                    $_SESSION['id_usuario'] = (int) $usuario['id_usuario'];
                    $_SESSION['nome'] = (string) $usuario['nome'];
                    $_SESSION['email'] = (string) $usuario['email'];
                    $_SESSION['tipo'] = (string) $usuario['tipo'];

                    registrarLog(
                        $conn,
                        'Login',
                        'Usuário ' . (string) $usuario['nome'] . ' entrou no sistema.',
                        (int) $usuario['id_usuario']
                    );

                    header('Location: ' . BASE_URL . 'index.php');
                    exit();

                }

            } else {

                $mensagem = 'Email ou senha incorretos.';
                $tipo_mensagem = 'erro';
            }

            $stmt->close();

        } else {

            $mensagem = 'Erro ao processar login.';
            $tipo_mensagem = 'erro';
        }
    }
}
?>

<!DOCTYPE html>

<html lang="pt-br">

<head>
<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>GestSaúde</title>

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&display=swap"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
>

<link
    rel="stylesheet"
    href="../css/style.css"
>

</head>

<body class="pagina-login">

<div class="login-container">

    <div class="logo">

        <img
            src="../gestsaude-logo.svg"
            alt="Logo GestSaúde"
        >

    </div>

    <h2 class="titulo-formulario">
        Seja Bem-Vindo
    </h2>

    <?php if ($mensagem !== ''): ?>

        <p class="mensagem-<?= $tipo_mensagem === 'erro' ? 'erro' : 'sucesso' ?>">

            <?= htmlspecialchars(
                $mensagem,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </p>

    <?php endif; ?>

    <form
        method="POST"
        action="<?= htmlspecialchars(
            (string) $_SERVER['PHP_SELF'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
        id="formLogin"
    >
        <?= campoCsrf() ?>

        <div class="grupamento">

            <label for="emailInput">
                E-mail
            </label>

            <input
                type="email"
                id="emailInput"
                name="email"
                placeholder="usuario@gmail.com"
                required
            >

        </div>

        <div class="grupamento">

            <label for="senhaInput">
                Senha
            </label>

            <input
                type="password"
                id="senhaInput"
                name="senha"
                placeholder="Senha"
                required
            >

        </div>

        <button
            type="submit"
            class="button-submit"
        >
            Entrar
        </button>

    </form>

</div>

</body>

</html>
