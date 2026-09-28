<?php
declare(strict_types=1);
require_once '../script/sessao.php';
require_once '../script/conexao.php';
require_once '../script/funcoes_logs.php';
require_once '../script/funcoes_login.php';

header('Cache-Control: no-store, private, max-age=0');
$captchaConfig = configuracaoRecaptcha();
$mensagem = '';
$tipo_mensagem = '';
if (isset($_SESSION['mensagem_cadastro'])) {
    $mensagem = (string) $_SESSION['mensagem_cadastro']['texto'];
    $tipo_mensagem = (string) $_SESSION['mensagem_cadastro']['tipo'];
    unset($_SESSION['mensagem_cadastro']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = autenticarLogin($conn,
        is_string($_POST['email'] ?? null) ? $_POST['email'] : '',
        is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '',
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        is_string($_POST['g-recaptcha-response'] ?? null) ? $_POST['g-recaptcha-response'] : '');
    if (isset($resultado['usuario'])) {
        $usuario = $resultado['usuario'];
        try {
            $auditado = registrarLog($conn, 'Login', 'Usuário ' . $usuario['nome'] . ' entrou no sistema.', (int) $usuario['id_usuario']);
        } catch (Throwable $e) {
            error_log('Auditoria de login indisponível. Código: ' . $e->getCode());
            $auditado = false;
        }
        if (!$auditado) {
            http_response_code(503);
            $mensagem = 'Login temporariamente indisponível. Tente novamente.';
            $tipo_mensagem = 'erro';
        } else {
            limparConfirmacoesIdentidade();
            session_regenerate_id(true);
            $_SESSION['id_usuario'] = (int) $usuario['id_usuario'];
            $_SESSION['versao_sessao'] = (int) $usuario['versao_sessao'];
            foreach (['nome','email','tipo'] as $campo) $_SESSION[$campo] = $usuario[$campo];
            header('Location: ' . BASE_URL . 'index.php');
            exit;
        }
    } else {
        http_response_code($resultado['status']);
        if (isset($resultado['espera'])) header('Retry-After: ' . $resultado['espera']);
        $mensagem = $resultado['mensagem'];
        $tipo_mensagem = 'erro';
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

<?php if ($captchaConfig['ativo'] && $captchaConfig['valida']): ?>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>
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


        <?php if ($captchaConfig['ativo']): ?>
            <?php if ($captchaConfig['valida']): ?>
                <div class="recaptcha-container">
                    <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($captchaConfig['site'], ENT_QUOTES, 'UTF-8') ?>"></div>
                </div>
            <?php else: ?>
                <p class="mensagem-erro">Verificação de segurança indisponível. Contate o responsável pelo sistema.</p>
            <?php endif; ?>
        <?php endif; ?>

        <button
            type="submit"
            class="button-submit"
        >
            Entrar
        </button>

    </form>

</div>


<script
    src="../script/senhas.js"
    defer
></script>

</body>

</html>
