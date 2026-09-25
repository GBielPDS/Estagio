<?php

declare(strict_types=1);

require_once '../script/sessao.php';
require_once "../script/conexao.php";
require_once "../script/funcoes_usuarios.php";
require_once "../script/funcoes_logs.php";
require_once "../script/sidebar.php";

header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
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
$tipoMensagem = 'erro';
$emailRevelado = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'editar';
    if (!in_array($acao, ['editar', 'consultar_email', 'corrigir_email'], true)) respostaAcesso(400, 'Operação inválida.');
    if ($acao !== 'editar') {
        verificarTipo(['Administrador']);
        $senhaAutor = is_string($_POST['senha_confirmacao'] ?? null) ? $_POST['senha_confirmacao'] : '';
        if ($acao === 'consultar_email') {
            $resultado = identidadeConfirmada($conn, 'consultar_email')
                ? ['sucesso'=>true] : confirmarIdentidade($conn, 'consultar_email', $senhaAutor);
            if ($resultado['sucesso']) $resultado = consultarEmailUsuario($conn, $id);
            if ($resultado['sucesso']) $emailRevelado = $resultado['email'];
        } else {
            $novoEmail = is_string($_POST['novo_email'] ?? null) ? $_POST['novo_email'] : '';
            $resultado = corrigirEmailUsuario($conn, $id, $novoEmail, $senhaAutor);
        }
        if (isset($resultado['status'])) http_response_code($resultado['status']);
        if (!empty($resultado['espera'])) header('Retry-After: ' . (int) $resultado['espera']);
        $mensagem = $resultado['mensagem'];
        $tipoMensagem = $resultado['sucesso'] ? 'sucesso' : 'erro';
        $usuario = buscarUsuarioPorId($conn, $id);
    } else {
        if (array_key_exists('email', $_POST)) respostaAcesso(403, 'Use a correção protegida de e-mail.');
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $senha = (string) ($_POST['senha'] ?? '');
        $tipo = (string) ($_POST['tipo'] ?? '');
        $status = $_POST['ativo'] ?? '';
        $ativo = in_array($status, ['0', '1'], true) ? (int) $status : -1;
        if (!$administrador && array_diff(array_keys($_POST), ['csrf', 'acao', 'nome', 'senha'])) respostaAcesso(403, 'Suporte só pode editar nome e senha.');
        $dados = compact('nome', 'senha');
        if ($administrador) $dados += compact('tipo', 'ativo');
        $resultado = alterarConta($conn, $id, $dados);
        if ($resultado['sucesso']) {
            $_SESSION['mensagem_cadastro'] = ['texto' => $resultado['mensagem'], 'tipo' => 'sucesso'];
            if ($id === (int) $_SESSION['id_usuario']) {
                verificarSessao();
                if ($_SESSION['tipo'] !== 'Administrador') { header('Location: ' . BASE_URL . 'index.php'); exit; }
            }
            header('Location: usuarios.php'); exit;
        }
        $mensagem = $resultado['mensagem'];
        $usuario['nome'] = $nome;
    }
}
$consultaConfirmada = $administrador && identidadeConfirmada($conn, 'consultar_email');

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
            <p class="mensagem-formulario mensagem-<?= $tipoMensagem === 'sucesso' ? 'sucesso' : 'erro' ?>"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></p>
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

                <p>E-mail: <?= htmlspecialchars(mascararEmail((string) $usuario['email']), ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($administrador): ?><a href="#email-protegido">Consultar ou corrigir e-mail</a><?php endif; ?>
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

        <?php if ($administrador): ?>
        <section class="cartao" id="email-protegido">
            <h2 class="cartao__titulo">E-mail protegido</h2>
            <p>A consulta exige sua senha e fica autorizada por cinco minutos. Cada consulta é registrada.</p>
            <?php if ($emailRevelado !== null): ?>
                <p>E-mail: <strong><?= htmlspecialchars($emailRevelado, ENT_QUOTES, 'UTF-8') ?></strong></p>
            <?php endif; ?>
            <form method="POST" class="formulario" action="editar_usuario.php?id=<?= $id ?>#email-protegido">
                <?= campoCsrf() ?>
                <input type="hidden" name="acao" value="consultar_email">
                <?php if (!$consultaConfirmada): ?>
                <div class="campo"><label class="campo__rotulo" for="senha_consulta">Sua senha de administrador</label>
                    <input class="campo__controle" id="senha_consulta" name="senha_confirmacao" type="password" autocomplete="current-password" required></div>
                <?php endif; ?>
                <button class="botao botao--secundario" type="submit">Visualizar e-mail</button>
            </form>
            <h3>Corrigir e-mail</h3>
            <p>O novo endereço será usado no próximo login. Confirme sua própria senha em cada correção.</p>
            <form method="POST" class="formulario" action="editar_usuario.php?id=<?= $id ?>#email-protegido">
                <?= campoCsrf() ?>
                <input type="hidden" name="acao" value="corrigir_email">
                <div class="campo"><label class="campo__rotulo" for="novo_email">Novo e-mail</label>
                    <input class="campo__controle" id="novo_email" name="novo_email" type="email" maxlength="100" autocomplete="off" required></div>
                <div class="campo"><label class="campo__rotulo" for="senha_correcao">Sua senha de administrador</label>
                    <input class="campo__controle" id="senha_correcao" name="senha_confirmacao" type="password" autocomplete="current-password" required></div>
                <button class="botao botao--primario" type="submit">Confirmar correção</button>
            </form>
        </section>
        <?php endif; ?>
    </main>

</body>
</html>
