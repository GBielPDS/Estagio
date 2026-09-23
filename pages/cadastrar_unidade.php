<?php

declare(strict_types=1);

require_once "../script/sessao.php";
require_once "../script/conexao.php";
require_once "../script/funcoes_unidades.php";
require_once "../script/funcoes_logs.php";
require_once "../script/sidebar.php";

verificarSessao();
verificarTipo(['Administrador']);

$mensagem = '';
$tipoMensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome = trim((string) ($_POST['nome'] ?? ''));
    $endereco = trim((string) ($_POST['endereco'] ?? ''));
    $telefone = trim((string) ($_POST['telefone'] ?? ''));

    $resultado = cadastrarUnidade(
        $conn,
        $nome,
        $endereco,
        $telefone
    );

    if ($resultado['sucesso']) {

        registrarLog(
            $conn,
            'Cadastro de unidade',
            'Unidade de saúde "' . $nome . '" cadastrada.',
            (int) $_SESSION['id_usuario']
        );

        $_SESSION['mensagem_cadastro'] = [
            'texto' => $resultado['mensagem'],
            'tipo' => 'sucesso'
        ];

        header('Location: unidades.php');
        exit;

    } else {

        $mensagem = $resultado['mensagem'];
        $tipoMensagem = 'erro';
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

<link
    rel="stylesheet"
    href="../css/style.css"
>

<title>Cadastrar Unidade</title>


</head>

<body>

<?php sidebar('unidades'); ?>

<main class="conteudo">


<div class="cabecalho-pagina">

    <h1 class="cabecalho-pagina__titulo">
        Cadastrar unidade
    </h1>

    <p class="cabecalho-pagina__descricao">
        Cadastre uma nova unidade de saúde no sistema.
    </p>

</div>


<?php if ($mensagem !== ''): ?>

    <div
        class="mensagem-formulario mensagem-<?= $tipoMensagem === 'sucesso'
            ? 'sucesso'
            : 'erro' ?>"
    >

        <?= htmlspecialchars(
            $mensagem,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </div>

<?php endif; ?>


<section class="cartao">

    <form method="POST" class="formulario">

        <?= campoCsrf() ?>

        <div class="campo campo--largo">

            <label
                class="campo__rotulo"
                for="nome"
            >
                Nome da unidade
            </label>

            <input
                class="campo__controle"
                type="text"
                id="nome"
                name="nome"
                maxlength="100"
                placeholder="Ex.: Unidade Básica de Saúde Central"
                value="<?= htmlspecialchars(
                    (string) ($_POST['nome'] ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                required
            >

        </div>


        <div class="campo campo--largo">

            <label
                class="campo__rotulo"
                for="endereco"
            >
                Endereço
            </label>

            <input
                class="campo__controle"
                type="text"
                id="endereco"
                name="endereco"
                maxlength="255"
                placeholder="Rua, número, bairro..."
                value="<?= htmlspecialchars(
                    (string) ($_POST['endereco'] ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

        </div>


        <div class="campo campo--largo">

            <label
                class="campo__rotulo"
                for="telefone"
            >
                Telefone
            </label>

            <input
                class="campo__controle"
                type="tel"
                id="telefone"
                name="telefone"
                maxlength="20"
                placeholder="(00) 00000-0000"
                value="<?= htmlspecialchars(
                    (string) ($_POST['telefone'] ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

        </div>


        <div
            style="
                display: flex;
                gap: 10px;
                margin-top: 10px;
            "
        >

            <button
                type="submit"
                class="botao botao--primario"
            >
                Cadastrar unidade
            </button>


            <a
                href="unidades.php"
                class="botao botao--secundario"
            >
                Cancelar
            </a>

        </div>


    </form>

</section>


</main>

</body>
</html>
