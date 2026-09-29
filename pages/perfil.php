<?php

declare(strict_types=1);

// Compatível com pages/ no desenvolvimento e páginas na raiz da hospedagem.
$raizAplicacao = is_file(__DIR__ . '/script/configuracao.php') ? __DIR__ : dirname(__DIR__);
require_once $raizAplicacao . '/script/sessao.php';
require_once $raizAplicacao . '/script/conexao.php';
require_once $raizAplicacao . '/script/funcoes_usuarios.php';
require_once $raizAplicacao . '/script/funcoes_logs.php';
require_once $raizAplicacao . '/script/sidebar.php';

header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
verificarSessao();

$idUsuario = (int) $_SESSION['id_usuario'];
$usuario = buscarUsuarioPorId($conn, $idUsuario);

if (!$usuario) {
    die('Usuário não encontrado.');
}

$retorno = lerResultadoConta('perfil', $idUsuario);
$mensagem = $retorno['mensagem'] ?? '';
$tipoMensagem = $retorno ? 'sucesso' : '';
$acaoMensagem = $retorno['acao'] ?? '';
$nomeFormulario = $usuario['nome'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'editar';
    if (!in_array($acao, ['editar', 'alterar_senha'], true)) respostaAcesso(400, 'Operação inválida.');
    $permitidos = $acao === 'editar' ? ['csrf','acao','nome','formulario_conta'] : ['csrf','acao','senha_atual','nova_senha','confirmar_senha','formulario_conta'];
    if (array_diff(array_keys($_POST), $permitidos)) respostaAcesso(403, 'Use o formulário específico para alterar dados protegidos.');
    try {
        $formulario = consumirFormularioConta($_POST['formulario_conta'] ?? null, $acao === 'editar' ? 'perfil_dados' : 'alterar_senha', $idUsuario);
        if ($acao === 'alterar_senha') {
            $resultado = mudarSenhaUsuario($conn, $idUsuario, (string) ($_POST['senha_atual'] ?? ''), (string) ($_POST['nova_senha'] ?? ''), (string) ($_POST['confirmar_senha'] ?? ''), true, $formulario);
        } else {
            $resultado = alterarConta($conn, $idUsuario, ['nome'=>trim((string) ($_POST['nome'] ?? ''))], true, $formulario);
        }
    } catch (FormularioContaException $e) { $resultado = erroUsuario($e); }
    if ($resultado['sucesso']) redirecionarResultadoConta('perfil', $idUsuario, $acao, $resultado['mensagem']);
    if (isset($resultado['status'])) http_response_code($resultado['status']);
    if (!empty($resultado['espera'])) header('Retry-After: ' . (int) $resultado['espera']);
    $mensagem = $resultado['mensagem'];
    $tipoMensagem = 'erro';
    $acaoMensagem = $acao;
    $usuario = buscarUsuarioPorId($conn, $idUsuario);
    $nomeFormulario = $acao === 'editar' && ($resultado['status'] ?? 200) !== 409 ? (string) ($_POST['nome'] ?? '') : $usuario['nome'];
}

$nomeAtual = (string) ($usuario['nome'] ?? '');
$inicial = strtoupper(substr($nomeAtual !== '' ? $nomeAtual : '?', 0, 1));
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <title>Meu perfil</title>
</head>

<body>

    <?php sidebar('perfil'); ?>

    <main class="conteudo perfil-pagina">
        <div class="cabecalho-pagina">
            <h1 class="cabecalho-pagina__titulo">Meu Perfil</h1>
            <p class="cabecalho-pagina__descricao">Gerencie suas informações pessoais e credenciais de acesso.</p>
        </div>

        <?php if ($mensagem !== '' && $acaoMensagem !== 'alterar_senha'): ?>
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

        <section class="cartao" id="dados-conta">
            <h2 class="cartao__titulo">Dados cadastrais</h2>
            <p class="cartao__legenda">A alteração de senha possui um formulário separado abaixo.</p>

            <form method="POST" action="perfil.php#dados-conta" class="formulario">
                <?= campoCsrf() ?>
                <?= campoFormularioConta('perfil_dados', $usuario) ?>
                <div class="campo">
                    <label class="campo__rotulo" for="nome">Nome <span class="obrigatorio">*</span></label>
                    <input class="campo__controle" type="text" id="nome" name="nome" value="<?= htmlspecialchars((string) $nomeFormulario, ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <p>Para corrigir seu e-mail, solicite ao administrador.
                    <?php if ($_SESSION['tipo'] === 'Administrador'): ?><a href="editar_usuario.php?id=<?= $idUsuario ?>#email-protegido">Abrir correção protegida</a><?php endif; ?>
                </p>

                <div class="acoes">
                    <button class="botao botao--primario" type="submit">Salvar dados</button>
                </div>
            </form>
        </section>
        <section class="cartao cartao--senha" id="alterar-senha">
            <h2 class="cartao__titulo">Alterar minha senha</h2>
            <p>Confirme sua senha atual. Os outros acessos da sua conta serão encerrados.</p>
            <?php if ($mensagem !== '' && $acaoMensagem === 'alterar_senha'): ?>
                <p role="status" class="mensagem-formulario mensagem-<?= $tipoMensagem === 'sucesso' ? 'sucesso' : 'erro' ?>"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <form method="POST" action="perfil.php#alterar-senha" class="formulario formulario--senha">
                <?= campoCsrf() ?>
                <?= campoFormularioConta('alterar_senha', $usuario) ?>
                <input type="hidden" name="acao" value="alterar_senha">
                <div class="campo">
                    <label class="campo__rotulo" for="nova_senha">Nova senha</label>
                    <input class="campo__controle" type="password" id="nova_senha" name="nova_senha" autocomplete="new-password" required>
                </div>
                <div class="campo">
                    <label class="campo__rotulo" for="confirmar_senha">Confirmar nova senha</label>
                    <input class="campo__controle" type="password" id="confirmar_senha" name="confirmar_senha" autocomplete="new-password" required>
                </div>
                <div class="senha-confirmacao">
                    <div class="campo">
                        <label class="campo__rotulo" for="senha_atual">Confirme sua senha atual</label>
                        <input class="campo__controle" type="password" id="senha_atual" name="senha_atual" autocomplete="current-password" required>
                    </div>
                </div>
                <div class="senha-acoes"><button class="botao botao--primario" type="submit">Alterar minha senha</button></div>
            </form>
        </section>
    </main>

    <script src="<?= BASE_URL ?>script/senhas.js" defer></script>
</body>
</html>
