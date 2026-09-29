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
verificarTipo(['Administrador', 'Suporte']);

if (!isset($_GET['id'])) {
    die("Usuário não informado.");
}

$id = (int) $_GET['id'];

$usuario = buscarUsuarioPorId($conn, $id);
if (!$usuario) { http_response_code(404); exit('Usuário não encontrado.'); }
if (!podeEditarConta($usuario)) respostaAcesso(403, 'Acesso negado.');
$administrador = $_SESSION['tipo'] === 'Administrador';
$retorno = lerResultadoConta('editar_usuario', $id);
$mensagem = $retorno['mensagem'] ?? '';
$tipoMensagem = $retorno ? 'sucesso' : 'erro';
$acaoMensagem = $retorno['acao'] ?? '';
$emailRevelado = null;
$nomeFormulario = $usuario['nome'];
$tipoFormulario = $usuario['tipo'];
$ativoFormulario = (string) $usuario['ativo'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'editar';
    if (!in_array($acao, ['editar', 'consultar_email', 'corrigir_email', 'redefinir_senha'], true)) respostaAcesso(400, 'Operação inválida.');
    if (in_array($acao, ['consultar_email','corrigir_email'], true)) verificarTipo(['Administrador']);
    if ($acao === 'redefinir_senha' && $id === (int) $_SESSION['id_usuario']) respostaAcesso(403, 'Altere sua própria senha em Meu Perfil.');
    $permitidos = match ($acao) {
        'editar' => $administrador ? ['csrf','acao','formulario_conta','nome','tipo','ativo'] : ['csrf','acao','formulario_conta','nome'],
        'redefinir_senha' => ['csrf','acao','formulario_conta','senha_atual','nova_senha','confirmar_senha'],
        'corrigir_email' => ['csrf','acao','formulario_conta','novo_email','senha_confirmacao'],
        default => ['csrf','acao','senha_confirmacao']
    };
    if (array_diff(array_keys($_POST), $permitidos)) respostaAcesso(403, 'Campos não permitidos nesta operação.');
    try {
        $formulario = $acao === 'consultar_email' ? null : consumirFormularioConta($_POST['formulario_conta'] ?? null, $acao, $id);
        if ($acao === 'redefinir_senha') {
            $resultado = mudarSenhaUsuario($conn, $id, (string) ($_POST['senha_atual'] ?? ''), (string) ($_POST['nova_senha'] ?? ''), (string) ($_POST['confirmar_senha'] ?? ''), false, $formulario);
        } elseif ($acao === 'consultar_email') {
            $senhaAutor = is_string($_POST['senha_confirmacao'] ?? null) ? $_POST['senha_confirmacao'] : '';
            $resultado = identidadeConfirmada($conn, 'consultar_email') ? ['sucesso'=>true] : confirmarIdentidade($conn, 'consultar_email', $senhaAutor);
            if ($resultado['sucesso']) $resultado = consultarEmailUsuario($conn, $id);
            if ($resultado['sucesso']) $emailRevelado = $resultado['email'];
        } elseif ($acao === 'corrigir_email') {
            $resultado = corrigirEmailUsuario($conn, $id, is_string($_POST['novo_email'] ?? null) ? $_POST['novo_email'] : '', is_string($_POST['senha_confirmacao'] ?? null) ? $_POST['senha_confirmacao'] : '', $formulario);
        } else {
            $dados = ['nome'=>trim((string) ($_POST['nome'] ?? ''))];
            if ($administrador) {
                $dados['tipo'] = (string) ($_POST['tipo'] ?? '');
                $dados['ativo'] = in_array($_POST['ativo'] ?? '', ['0','1'], true) ? (int) $_POST['ativo'] : -1;
            }
            $resultado = alterarConta($conn, $id, $dados, false, $formulario);
        }
    } catch (FormularioContaException $e) { $resultado = erroUsuario($e); }
    if ($resultado['sucesso'] && $acao !== 'consultar_email') {
        if ($id === (int) $_SESSION['id_usuario'] && $acao === 'editar') {
            verificarSessao();
            if ($_SESSION['tipo'] !== 'Administrador') redirecionarResultadoConta('perfil', $id, 'editar', $resultado['mensagem']);
        }
        redirecionarResultadoConta('editar_usuario', $id, $acao, $resultado['mensagem']);
    }
    if (isset($resultado['status'])) http_response_code($resultado['status']);
    if (!empty($resultado['espera'])) header('Retry-After: ' . (int) $resultado['espera']);
    $mensagem = $resultado['mensagem'];
    $tipoMensagem = $resultado['sucesso'] ? 'sucesso' : 'erro';
    $acaoMensagem = $acao;
    $usuario = buscarUsuarioPorId($conn, $id);
    $preservar = $acao === 'editar' && ($resultado['status'] ?? 200) !== 409;
    $nomeFormulario = $preservar ? (string) ($_POST['nome'] ?? '') : $usuario['nome'];
    $tipoFormulario = $preservar && in_array($_POST['tipo'] ?? '', ['Administrador','Suporte','Usuario'], true) ? $_POST['tipo'] : $usuario['tipo'];
    $ativoFormulario = $preservar && in_array($_POST['ativo'] ?? '', ['0','1'], true) ? $_POST['ativo'] : (string) $usuario['ativo'];
}
$consultaConfirmada = $administrador && identidadeConfirmada($conn, 'consultar_email');

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <title>Editar Usuário</title>
</head>

<body>

    <?php sidebar('usuarios'); ?>

    <main class="conteudo">

        <div class="cabecalho-pagina">
            <h1 class="cabecalho-pagina__titulo">Editar usuário</h1>
            <p class="cabecalho-pagina__descricao">Atualize os dados do usuário.</p>
        </div>

        <?php if ($mensagem !== '' && !in_array($acaoMensagem, ['redefinir_senha','corrigir_email','consultar_email'], true)): ?>
            <p class="mensagem-formulario mensagem-<?= $tipoMensagem === 'sucesso' ? 'sucesso' : 'erro' ?>"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <section class="cartao" id="dados-conta">

            <?php if ($administrador): ?>
            <!-- Controles associados por form: consultar não envia nem valida o cadastro. -->
            <form id="form-consulta-email" method="POST" action="editar_usuario.php?id=<?= $id ?>#consulta-email">
                <?= campoCsrf() ?>
                <input type="hidden" name="acao" value="consultar_email">
            </form>
            <?php endif; ?>

            <form method="POST" action="editar_usuario.php?id=<?= $id ?>#dados-conta" class="formulario">
                <?= campoCsrf() ?>
                <?= campoFormularioConta('editar', $usuario) ?>
                <div class="campo campo--largo">
                    <label class="campo__rotulo">Nome:</label>
                    <input class="campo__controle"
                        type="text"
                        name="nome"
                        value="<?= htmlspecialchars((string) $nomeFormulario, ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                </div>

                <?php if ($administrador): ?>
                <div class="consulta-email" id="consulta-email">
                    <h2 class="cartao__titulo">Visualizar e-mail</h2>
                    <p>A consulta exige sua senha e fica autorizada por cinco minutos. Cada consulta é registrada.</p>
                    <?php if ($mensagem !== '' && $acaoMensagem === 'consultar_email'): ?>
                        <p role="status" class="mensagem-formulario mensagem-<?= $tipoMensagem === 'sucesso' ? 'sucesso' : 'erro' ?>"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <div class="consulta-email__campos">
                        <?php if (!$consultaConfirmada): ?>
                        <div class="campo">
                            <label class="campo__rotulo" for="senha_consulta">Sua senha de administrador</label>
                            <input class="campo__controle" id="senha_consulta" name="senha_confirmacao" form="form-consulta-email" type="password" autocomplete="current-password" required>
                        </div>
                        <?php else: ?>
                            <p class="consulta-email__autorizada">Identidade confirmada para consulta.</p>
                        <?php endif; ?>
                        <button class="botao botao--primario" type="submit" form="form-consulta-email">Visualizar e-mail</button>
                    </div>
                    <?php if ($emailRevelado !== null): ?>
                        <p class="consulta-email__resultado">E-mail: <strong><?= htmlspecialchars($emailRevelado, ENT_QUOTES, 'UTF-8') ?></strong></p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                    <p>E-mail: <?= htmlspecialchars(mascararEmail((string) $usuario['email']), ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($administrador): ?>
                <div class="campo campo--largo">
                    <label class="campo__rotulo" for="tipo">Tipo de usuário</label>
                    <select class="campo__controle" id="tipo" name="tipo">
                        <option value="Administrador" <?= $tipoFormulario === 'Administrador' ? 'selected' : '' ?>>Administrador</option>
                        <option value="Suporte" <?= $tipoFormulario === 'Suporte' ? 'selected' : '' ?>>Suporte</option>
                        <option value="Usuario" <?= $tipoFormulario === 'Usuario' ? 'selected' : '' ?>>Usuário</option>
                    </select>
                </div>

                <div class="campo campo--largo">
                    <label class="campo__rotulo" for="ativo">Status da conta</label>
                    <select class="campo__controle" id="ativo" name="ativo">
                        <option value="1" <?= (int) $ativoFormulario === 1 ? 'selected' : '' ?>>Ativo</option>
                        <option value="0" <?= (int) $ativoFormulario === 0 ? 'selected' : '' ?>>Inativo (Desativado)</option>
                    </select>
                </div>

                <?php else: ?>
                    <p>Perfil: Usuário. Status: Ativo. Alterações de perfil e status são exclusivas do administrador.</p>
                <?php endif; ?>
                <div class="formulario__acoes">
                    <a href="usuarios.php" class="botao botao--secundario">Cancelar</a>
                    <button type="submit" class="botao botao--primario">Salvar dados</button>
                </div>

            </form>

        </section>

        <section class="cartao cartao--senha" id="redefinir-senha">
            <?php if ($id === (int) $_SESSION['id_usuario']): ?>
                <a href="perfil.php#alterar-senha">Alterar minha senha em Meu Perfil</a>
            <?php else: ?>
            <h2 class="cartao__titulo">Redefinir senha de <?= htmlspecialchars((string) $usuario['nome'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p>Confirme sua própria senha, não a senha da pessoa atendida. Os acessos anteriores dela serão encerrados.</p>
            <?php if ($mensagem !== '' && $acaoMensagem === 'redefinir_senha'): ?>
                <p role="status" class="mensagem-formulario mensagem-<?= $tipoMensagem === 'sucesso' ? 'sucesso' : 'erro' ?>"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <form method="POST" action="editar_usuario.php?id=<?= $id ?>#redefinir-senha" class="formulario formulario--senha">
                <?= campoCsrf() ?>
                <?= campoFormularioConta('redefinir_senha', $usuario) ?>
                <input type="hidden" name="acao" value="redefinir_senha">
                <div class="campo">
                    <label class="campo__rotulo" for="nova_senha">Nova senha da conta</label>
                    <input class="campo__controle" type="password" id="nova_senha" name="nova_senha" autocomplete="new-password" required>
                </div>
                <div class="campo">
                    <label class="campo__rotulo" for="confirmar_senha">Confirmar nova senha</label>
                    <input class="campo__controle" type="password" id="confirmar_senha" name="confirmar_senha" autocomplete="new-password" required>
                </div>
                <div class="senha-confirmacao">
                    <div class="campo">
                        <label class="campo__rotulo" for="senha_atual"><?= $administrador ? 'Sua senha de administrador' : 'Sua senha de suporte' ?></label>
                        <input class="campo__controle" type="password" id="senha_atual" name="senha_atual" autocomplete="current-password" required>
                    </div>
                </div>
                <div class="senha-acoes"><button class="botao botao--primario" type="submit">Redefinir senha</button></div>
            </form>
            <?php endif; ?>
        </section>

        <?php if ($administrador): ?>
        <section class="cartao" id="email-protegido">
            <h2 class="cartao__titulo">Corrigir e-mail</h2>
            <?php if ($mensagem !== '' && $acaoMensagem === 'corrigir_email'): ?>
                <p role="status" class="mensagem-formulario mensagem-<?= $tipoMensagem === 'sucesso' ? 'sucesso' : 'erro' ?>"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <p>O novo endereço será usado no próximo login. Confirme sua própria senha em cada correção.</p>
            <form method="POST" class="formulario" action="editar_usuario.php?id=<?= $id ?>#email-protegido">
                <?= campoCsrf() ?>
                <?= campoFormularioConta('corrigir_email', $usuario) ?>
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

    <script src="<?= BASE_URL ?>script/senhas.js" defer></script>
</body>
</html>
