<?php

declare(strict_types=1);

require_once '../script/sessao.php';
require_once '../script/conexao.php';
require_once '../script/funcoes_unidades.php';
require_once '../script/funcoes_logs.php';
require_once '../script/sidebar.php';

verificarSessao();
verificarTipo(['Administrador']);

$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
if (!$id || $id < 1) respostaAcesso(400, 'Unidade de saúde inválida.');
$unidade = buscarUnidadePorId($conn, $id);
if (!$unidade) respostaAcesso(404, 'Unidade de saúde não encontrada.');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $endereco = trim((string) ($_POST['endereco'] ?? ''));
    $telefone = trim((string) ($_POST['telefone'] ?? ''));
    $resultado = atualizarUnidade($conn, $id, $nome, $endereco, $telefone);
    if ($resultado['sucesso']) {
        $_SESSION['mensagem_cadastro'] = ['texto' => $resultado['mensagem'], 'tipo' => 'sucesso'];
        header('Location: unidades.php');
        exit;
    }
    $mensagem = $resultado['mensagem'];
    $tipoMensagem = 'erro';
    if (unidadeCentral($unidade)) $nome = $unidade['nome'];
    $unidade = array_merge($unidade, compact('nome', 'endereco', 'telefone'));
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../css/style.css">

    <title>Editar Unidade de Saúde</title>
</head>

<body>

    <?php sidebar('unidades'); ?>

    <main class="conteudo">

        <div class="cabecalho-pagina">

            <h1 class="cabecalho-pagina__titulo">
                Editar unidade de saúde
            </h1>

            <p class="cabecalho-pagina__descricao">
                Atualize os dados da unidade de saúde.
            </p>

        </div>

        <?php if (isset($mensagem)): ?>

            <div class="mensagem-formulario mensagem-<?= $tipoMensagem === 'sucesso' ? 'sucesso' : 'erro' ?>">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

        <section class="cartao">

            <?php if (unidadeCentral($unidade)): ?>
                <p>A Secretaria de Saúde é a unidade central. Seu nome e sua desativação são protegidos; endereço e telefone podem ser atualizados.</p>
            <?php endif; ?>
            <form method="POST" class="formulario">

                <?= campoCsrf() ?>

                <div class="campo campo--largo">

                    <label class="campo__rotulo" for="nome">
                        Nome da unidade
                    </label>

                    <input
                        class="campo__controle"
                        type="text"
                        id="nome"
                        name="nome"
                        <?= unidadeCentral($unidade) ? 'readonly' : '' ?>
                        value="<?= htmlspecialchars((string) $unidade['nome'], ENT_QUOTES, 'UTF-8') ?>"
                        maxlength="100"
                        required
                    >

                </div>


                <div class="campo campo--largo">

                    <label class="campo__rotulo" for="endereco">
                        Endereço
                    </label>

                    <input
                        class="campo__controle"
                        type="text"
                        id="endereco"
                        name="endereco"
                        value="<?= htmlspecialchars((string) ($unidade['endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        maxlength="255"
                    >

                </div>


                <div class="campo campo--largo">

                    <label class="campo__rotulo" for="telefone">
                        Telefone
                    </label>

                    <input
                        class="campo__controle"
                        type="text"
                        id="telefone"
                        name="telefone"
                        value="<?= htmlspecialchars((string) ($unidade['telefone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        maxlength="20"
                    >

                </div>


                <div class="campo campo--largo">

                    <label class="campo__rotulo">
                        Status
                    </label>

                    <input
                        class="campo__controle"
                        type="text"
                        value="<?= (int) $unidade['ativo'] === 1 ? 'Ativa' : 'Desativada' ?>"
                        disabled
                    >

                </div>


                <div class="formulario__acoes">

                    <a
                        href="unidades.php"
                        class="botao botao--secundario"
                    >
                        Cancelar
                    </a>

                    <button
                        type="submit"
                        class="botao botao--primario"
                    >
                        Salvar alterações
                    </button>

                </div>

            </form>

        </section>

    </main>

</body>

</html>