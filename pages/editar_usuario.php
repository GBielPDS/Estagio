<?php

declare(strict_types=1);

require_once '../script/sessao.php';
require_once "../script/conexao.php";
require_once "../script/funcoes_usuarios.php";
require_once "../script/funcoes_logs.php";
require_once "../script/sidebar.php";

verificarSessao();
verificarTipo(['Administrador', 'Suporte']);

if (!isset($_GET['id'])) {
    die("Usuário não informado.");
}

$id = (int) $_GET['id'];

$usuario = buscarUsuarioPorId($conn, $id);
if (!$usuario) { http_response_code(404); exit('Usuário não encontrado.'); }
if (!podeEditarConta($usuario)) respostaAcesso(403, 'Acesso negado.');
$administrador = $_SESSION['tipo'] === 'Administrador';
$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');
    $tipo = (string) ($_POST['tipo'] ?? '');
    $status = $_POST['ativo'] ?? '';
    $ativo = in_array($status, ['0', '1'], true) ? (int) $status : -1;
    if (!$administrador && array_diff(array_keys($_POST), ['csrf', 'nome', 'email', 'senha'])) {
        respostaAcesso(403, 'Suporte só pode editar nome, e-mail e senha.');
    }
    $resultado = $administrador
        ? atualizarUsuario($conn, $id, $nome, $email, $senha, $tipo, $ativo)
        : atualizarCadastroUsuario($conn, $id, $nome, $email, $senha);
    if ($resultado['sucesso']) {
        $_SESSION['mensagem_cadastro'] = ['texto' => $resultado['mensagem'], 'tipo' => 'sucesso'];
        if ($id === (int) $_SESSION['id_usuario']) {
            verificarSessao();
            if ($_SESSION['tipo'] !== 'Administrador') { header('Location: ' . BASE_URL . 'index.php'); exit; }
        }
        header('Location: usuarios.php'); exit;
    }
    $mensagem = $resultado['mensagem'];
    $usuario = array_merge($usuario, compact('nome', 'email'));
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style.css">
    <title>Editar Usuário</title>
</head>

<body>

    <?php sidebar('usuarios'); ?>

    <main class="conteudo">

        <div class="cabecalho-pagina">
            <h1 class="cabecalho-pagina__titulo">Editar usuário</h1>
            <p class="cabecalho-pagina__descricao">Atualize os dados do usuário.</p>
        </div>

        <?php if ($mensagem !== ''): ?>
            <p class="mensagem-formulario mensagem-erro"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <section class="cartao">

            <form method="POST" class="formulario">
                <?= campoCsrf() ?>
                <div class="campo campo--largo">
                    <label class="campo__rotulo">Nome:</label>
                    <input class="campo__controle"                
                        type="text"
                        name="nome"
                        value="<?= htmlspecialchars((string) $usuario['nome'], ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                </div>

                <div class="campo campo--largo">
                    <label class="campo__rotulo">Email:</label>
                    <input class="campo__controle"
                        type="email"
                        name="email"
                        value="<?= htmlspecialchars((string) $usuario['email'], ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                </div>

                <p>Alterar o e-mail muda o identificador usado no próximo login.</p>
                <div class="campo campo--largo">
                    <label class="campo__rotulo">Nova senha:</label>
                    <input class="campo__controle"
                        type="password"
                        name="senha"
                        placeholder="Deixe vazio para manter a senha atual"
                    >
                </div>

                <p>Uma nova senha encerra os acessos anteriores desta conta.</p>
                <?php if ($administrador): ?>
                <div class="campo campo--largo"> 
                    <label class="campo__rotulo" for="tipo">Tipo de usuário</label> 
                    <select class="campo__controle" id="tipo" name="tipo"> 
                        <option value="Administrador" <?= $usuario['tipo'] === 'Administrador' ? 'selected' : '' ?>>Administrador</option> 
                        <option value="Suporte" <?= $usuario['tipo'] === 'Suporte' ? 'selected' : '' ?>>Suporte</option> 
                        <option value="Usuario" <?= $usuario['tipo'] === 'Usuario' ? 'selected' : '' ?>>Usuário</option> 
                    </select> 
                </div>

                <div class="campo campo--largo"> 
                    <label class="campo__rotulo" for="ativo">Status da conta</label> 
                    <select class="campo__controle" id="ativo" name="ativo"> 
                        <option value="1" <?= (int)($usuario['ativo'] ?? 1) === 1 ? 'selected' : '' ?>>Ativo</option> 
                        <option value="0" <?= (int)($usuario['ativo'] ?? 1) === 0 ? 'selected' : '' ?>>Inativo (Desativado)</option> 
                    </select> 
                </div>

                <?php else: ?>
                    <p>Perfil: Usuário. Status: Ativo. Alterações de perfil e status são exclusivas do administrador.</p>
                <?php endif; ?>
                <div class="formulario__acoes">
                    <a href="usuarios.php" class="botao botao--secundario">Cancelar</a> 
                    <button type="submit" class="botao botao--primario">Salvar</button> 
                </div>

            </form>

        </section>

    </main>

</body>
</html>
