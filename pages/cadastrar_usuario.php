<?php

declare(strict_types=1);

require_once '../script/sessao.php';
require_once '../script/conexao.php';
require_once '../script/funcoes_usuarios.php';
require_once '../script/funcoes_logs.php';
require_once '../script/sidebar.php';

verificarSessao();
verificarTipo(['Administrador']);

$mensagem = '';
$tipo_mensagem = '';
$nome = '';
$email = '';
$tipo = 'Usuario';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');
    $tipo = (string) ($_POST['tipo'] ?? 'Usuario');

    if ($nome === '' || $email === '' || $senha === '') {
        $mensagem = 'Preencha todos os campos.';
        $tipo_mensagem = 'erro';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = 'Digite um e-mail válido.';
        $tipo_mensagem = 'erro';
    } elseif (!in_array($tipo, ['Administrador', 'Suporte', 'Usuario'], true)) {
        $mensagem = 'Tipo de usuário inválido.';
        $tipo_mensagem = 'erro';
    } else {
        $resultado = cadastrarUsuario($conn, $nome, $email, $senha, $tipo, true);

        if ($resultado['sucesso']) {
            $foiReativado = !empty($resultado['reativado']);
            $adminId = (int) ($_SESSION['id_usuario'] ?? 0);

            if ($foiReativado) {
                registrarLog(
                    $conn,
                    'Reativação de usuário',
                    'Usuário ' . $nome . ' (' . $tipo . ') foi reativado com nova senha pelo administrador.',
                    $adminId
                );
                $_SESSION['mensagem_cadastro'] = [
                    'texto' => 'Usuário reativado com sucesso com a nova senha de acesso.',
                    'tipo' => 'sucesso'
                ];
            } else {
                registrarLog(
                    $conn,
                    'Cadastro de usuário',
                    'Usuário ' . $nome . ' (' . $tipo . ') cadastrado pelo administrador.',
                    $adminId
                );
                $_SESSION['mensagem_cadastro'] = [
                    'texto' => 'Usuário cadastrado com sucesso.',
                    'tipo' => 'sucesso'
                ];
            }

            header('Location: ' . BASE_URL . 'pages/usuarios.php');
            exit;
        }

        $mensagem = (string) $resultado['mensagem'];
        $tipo_mensagem = 'erro';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style.css">
    <title>Cadastrar Usuário</title>
</head>

<body>

    <?php sidebar('usuarios'); ?>

    <main class="conteudo">

        <div class="cabecalho-pagina">
            <h1 class="cabecalho-pagina__titulo">Cadastrar usuário</h1>
            <p class="cabecalho-pagina__descricao">Cadastre um novo usuário ou reative um usuário inativo atribuindo uma nova senha.</p>
        </div>

        <?php if ($mensagem !== ''): ?>
            <div class="mensagem-formulario mensagem-<?= $tipo_mensagem === 'sucesso' ? 'sucesso' : 'erro' ?>">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <section class="cartao">

            <form method="POST" action="<?= htmlspecialchars((string) $_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" class="formulario">

                <div class="campo campo--largo">
                    <label class="campo__rotulo" for="nome">Nome:</label>
                    <input class="campo__controle"
                        type="text"
                        id="nome"
                        name="nome"
                        placeholder="Nome completo do usuário"
                        value="<?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                </div>

                <div class="campo campo--largo">
                    <label class="campo__rotulo" for="email">E-mail:</label>
                    <input class="campo__controle"
                        type="email"
                        id="email"
                        name="email"
                        placeholder="usuario@email.com"
                        value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                </div>

                <div class="campo campo--largo">
                    <label class="campo__rotulo" for="senha">Senha de acesso:</label>
                    <input class="campo__controle"
                        type="password"
                        id="senha"
                        name="senha"
                        placeholder="Digite a senha inicial de acesso"
                        required
                    >
                </div>

                <div class="campo campo--largo">
                    <label class="campo__rotulo" for="tipo">Tipo de usuário:</label>
                    <select class="campo__controle" id="tipo" name="tipo">
                        <option value="Usuario" <?= $tipo === 'Usuario' ? 'selected' : '' ?>>Usuário</option>
                        <option value="Suporte" <?= $tipo === 'Suporte' ? 'selected' : '' ?>>Suporte</option>
                        <option value="Administrador" <?= $tipo === 'Administrador' ? 'selected' : '' ?>>Administrador</option>
                    </select>
                </div>

                <div class="formulario__acoes">
                    <a href="usuarios.php" class="botao botao--secundario">Cancelar</a>
                    <button type="submit" class="botao botao--primario">Cadastrar usuário</button>
                </div>

            </form>

        </section>

    </main>

</body>

</html>