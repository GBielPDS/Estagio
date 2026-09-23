<?php

declare(strict_types=1);

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
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');
    $confirmarSenha = (string) ($_POST['confirmar_senha'] ?? '');

    if ($senha !== $confirmarSenha) {
        $mensagem = 'As senhas não coincidem.';
        $tipoMensagem = 'erro';
    } else {
        $resultado = atualizarPerfilUsuario($conn, $idUsuario, $nome, $email, $senha);

        if ($resultado['sucesso']) {
            $_SESSION['nome'] = $nome;
            $_SESSION['email'] = $email;
            $mensagem = (string) $resultado['mensagem'];
            $tipoMensagem = 'sucesso';
            $usuario = buscarUsuarioPorId($conn, $idUsuario);


        } else {
            $mensagem = (string) $resultado['mensagem'];
            $tipoMensagem = 'erro';
        }
    }
}

$nomeAtual = (string) ($usuario['nome'] ?? '');
$inicial = strtoupper(substr($nomeAtual !== '' ? $nomeAtual : '?', 0, 1));
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
            <div class="perfil-avatar"><?= htmlspecialchars($inicial, ENT_QUOTES, 'UTF-8') ?></div>
            <div>
                <h2><?= htmlspecialchars((string) ($usuario['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= htmlspecialchars((string) ($usuario['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <span class="perfil-tipo"><?= htmlspecialchars((string) ($usuario['tipo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </section>

        <section class="cartao">
            <h2 class="cartao__titulo">Dados cadastrais</h2>
            <p class="cartao__legenda">Preencha os campos de senha somente se quiser alterá-la.</p>

            <form method="POST" class="formulario">
                <?= campoCsrf() ?>
                <div class="campo">
                    <label class="campo__rotulo" for="nome">Nome <span class="obrigatorio">*</span></label>
                    <input class="campo__controle" type="text" id="nome" name="nome" value="<?= htmlspecialchars((string) ($usuario['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="email">E-mail <span class="obrigatorio">*</span></label>
                    <input class="campo__controle" type="email" id="email" name="email" value="<?= htmlspecialchars((string) ($usuario['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
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
</html>
