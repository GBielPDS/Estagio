<?php

declare(strict_types=1);

require_once "../script/sessao.php";
require_once "../script/conexao.php";
require_once "../script/funcoes_unidades.php";
require_once "../script/funcoes_logs.php";
require_once "../script/sidebar.php";

verificarSessao();
verificarTipo(['Administrador', 'Suporte']);

$mensagemCadastro = $_SESSION['mensagem_cadastro'] ?? null;
unset($_SESSION['mensagem_cadastro']);

$filtroStatus = ($_GET['status'] ?? 'ativos') === 'inativos'
    ? 'inativos'
    : 'ativos';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTipo(['Administrador']);

    $resultado = [
        'sucesso' => false,
        'mensagem' => 'Operação inválida.'
    ];

    if (isset($_POST['desativar_id'])) {

        $id = (int) $_POST['desativar_id'];

        $resultado = desativarUnidade($conn, $id);


    } elseif (isset($_POST['reativar_id'])) {

        $id = (int) $_POST['reativar_id'];

        $resultado = reativarUnidade($conn, $id);

    }

    $_SESSION['mensagem_cadastro'] = [
        'texto' => $resultado['mensagem'],
        'tipo' => $resultado['sucesso']
            ? 'sucesso'
            : 'erro'
    ];

    header(
        'Location: unidades.php?status='
        . $filtroStatus
    );

    exit;
}


$totalAtivos = contarUnidades(
    $conn,
    'ativos'
);

$totalInativos = contarUnidades(
    $conn,
    'inativos'
);

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

<title>Unidades de Saúde</title>

</head>

<body>

<?php sidebar('unidades'); ?>

<main class="conteudo">

<div class="cabecalho-pagina cabecalho-pagina--com-acao">

    <div>

        <h1 class="cabecalho-pagina__titulo">
            Unidades de Saúde
        </h1>

        <p class="cabecalho-pagina__descricao">
            Gerencie as unidades de saúde cadastradas no sistema.
        </p>

    </div>


    <a
        href="<?= BASE_URL ?>pages/cadastrar_unidade.php"
        class="botao botao--primario"
    >
        Cadastrar unidade
    </a>

</div>


<?php if ($mensagemCadastro !== null): ?>

    <div
        class="mensagem-formulario mensagem-<?= ($mensagemCadastro['tipo'] ?? '') === 'sucesso'
            ? 'sucesso'
            : 'erro' ?>"
    >

        <?= htmlspecialchars(
            (string) ($mensagemCadastro['texto'] ?? ''),
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </div>

<?php endif; ?>


<section class="cartao cartao--usuarios">


    <div
        class="cartao__cabecalho"
        style="flex-wrap: wrap; gap: 16px; align-items: center; justify-content: space-between;"
    >

        <div>

            <h2 class="cartao__titulo">

                <?= $filtroStatus === 'inativos'
                    ? 'Unidades desativadas'
                    : 'Unidades ativas' ?>

            </h2>


            <p class="cartao__descricao">

                <?= $filtroStatus === 'inativos'
                    ? 'Unidades que estão desativadas no sistema.'
                    : 'Consulte e gerencie as unidades de saúde ativas.' ?>

            </p>

        </div>


        <div style="display: flex; gap: 8px;">

            <a
                href="unidades.php?status=ativos"
                class="botao botao--pequeno <?= $filtroStatus === 'ativos'
                    ? 'botao--primario'
                    : 'botao--secundario' ?>"
            >
                Ativas (<?= $totalAtivos ?>)
            </a>


            <a
                href="unidades.php?status=inativos"
                class="botao botao--pequeno <?= $filtroStatus === 'inativos'
                    ? 'botao--primario'
                    : 'botao--secundario' ?>"
            >
                Desativadas (<?= $totalInativos ?>)
            </a>

        </div>

    </div>


    <div class="tabela-rolagem">

        <table class="table table--usuarios">

            <thead>

                <tr>

                    <th>ID</th>
                    <th>Nome</th>
                    <th>Endereço</th>
                    <th>Telefone</th>
                    <th>Ações</th>

                </tr>

            </thead>


            <tbody>

                <?php
                listarUnidades(
                    $conn,
                    $filtroStatus
                );
                ?>

            </tbody>

        </table>

    </div>


</section>

</main>

</body>

</html>
