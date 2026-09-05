<?php

require_once '../script/sessao.php';
require_once '../script/conexao.php';
require_once '../script/funcoes_usuarios.php';
require_once '../script/funcoes_logs.php';
require_once '../script/sidebar.php';

verificarSessao();

$idUsuario = (int) $_SESSION['id_usuario'];
$usuario = buscarUsuarioPorId($conn, $idUsuario);

if (!$usuario) {
    die('Usuário não encontrado.');
}

$mensagem = '';
$tipoMensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';

    if ($senha !== $confirmarSenha) {
        $mensagem = 'As senhas não coincidem.';
        $tipoMensagem = 'erro';
    } else {
        $resultado = atualizarPerfilUsuario($conn, $idUsuario, $nome, $email, $senha);

        if ($resultado['sucesso']) {
            $_SESSION['nome'] = $nome;
            $_SESSION['email'] = $email;
            $mensagem = $resultado['mensagem'];
            $tipoMensagem = 'sucesso';
            $usuario = buscarUsuarioPorId($conn, $idUsuario);

            registrarLog(
                $conn,
                'Atualização de perfil',
                'Perfil atualizado pelo próprio usuário.',
                $idUsuario
            );
        } else {
            $mensagem = $resultado['mensagem'];
            $tipoMensagem = 'erro';
        }
    }
}

$inicial = strtoupper(substr($usuario['nome'], 0, 1));
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style.css">
    <title>Meu perfil</title>
</head>

<body>

    <?php sidebar('perfil'); ?>

    <main class="conteudo perfil-pagina">
        <div class="cabecalho-pagina">
            <h1 class="cabecalho-pagina__titulo">Meu Perfil</h1>
            <p class="cabecalho-pagina__descricao">Gerencie suas informações pessoais e credenciais de acesso.</p>
        </div>

        <?php if ($mensagem !== ''): ?>
            <div class="mensagem-formulario mensagem-<?= $tipoMensagem === 'sucesso' ? 'sucesso' : 'erro' ?>">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <section class="cartao perfil-resumo">
            <div class="perfil-avatar"><?= htmlspecialchars($inicial) ?></div>
            <div>
                <h2><?= htmlspecialchars($usuario['nome']) ?></h2>
                <p><?= htmlspecialchars($usuario['email']) ?></p>
                <span class="perfil-tipo"><?= htmlspecialchars($usuario['tipo']) ?></span>
            </div>
        </section>

        <section class="cartao">
            <h2 class="cartao__titulo">Dados cadastrais</h2>
            <p class="cartao__legenda">Preencha os campos de senha somente se quiser alterá-la.</p>

            <form method="POST" class="formulario">
                <div class="campo">
                    <label class="campo__rotulo" for="nome">Nome <span class="obrigatorio">*</span></label>
                    <input class="campo__controle" type="text" id="nome" name="nome" value="<?= htmlspecialchars($usuario['nome']) ?>" required>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="email">E-mail <span class="obrigatorio">*</span></label>
                    <input class="campo__controle" type="email" id="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" required>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="senha">Nova senha</label>
                    <input class="campo__controle" type="password" id="senha" name="senha" placeholder="Deixe vazio para manter a atual">
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="confirmar_senha">Confirmar nova senha</label>
                    <input class="campo__controle" type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Repita a nova senha">
                </div>

                <div class="acoes">
                    <button class="botao botao--primario" type="submit">Salvar alterações</button>
                </div>
            </form>
        </section>
    </main>

</body>
</html><?php
