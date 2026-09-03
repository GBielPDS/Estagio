<?php

require_once 'script/sessao.php';
require_once 'script/conexao.php';
require_once 'script/funcoes_estoque.php';
require_once 'script/sidebar.php';

verificarSessao();

$alertasEstoque = buscarAlertasEstoque($conn);
$produtosVazios = count(array_filter($alertasEstoque, function ($alerta) {
    return $alerta['situacao'] === 'vazio';
}));
$produtosAbaixoMinimo = count($alertasEstoque) - $produtosVazios;
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle de Estoque · GestSaúde</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <?php sidebar('home'); ?>

    <main class="conteudo">
        <div class="cabecalho-pagina">
            <h1 class="cabecalho-pagina__titulo">Controle de Estoque</h1>
            <p class="cabecalho-pagina__descricao">Escolha a operação que deseja realizar no almoxarifado.</p>
        </div>

        <section class="resumo-alertas <?= count($alertasEstoque) > 0 ? 'resumo-alertas--atencao' : 'resumo-alertas--regular' ?>" aria-labelledby="titulo-alertas">
            <div>
                <span class="resumo-alertas__rotulo">Pendências de estoque</span>
                <h2 id="titulo-alertas">
                    <?= count($alertasEstoque) > 0 ? count($alertasEstoque) . ' produto(s) precisam de atenção' : 'Estoque regular' ?>
                </h2>
                <?php if (count($alertasEstoque) > 0): ?>
                    <p><?= $produtosVazios ?> vazio(s) e <?= $produtosAbaixoMinimo ?> abaixo do estoque mínimo.</p>
                <?php else: ?>
                    <p>Nenhum produto está vazio ou abaixo do estoque mínimo.</p>
                <?php endif; ?>
            </div>
            <a class="botao <?= count($alertasEstoque) > 0 ? 'botao--alerta' : 'botao--secundario' ?>" href="pages/alertas.php">
                Ver alertas
            </a>
        </section>

        <div class="cabecalho-pagina-usuario">
            <span>Olá,</span>
            <strong><?= htmlspecialchars($_SESSION['nome']) ?></strong>
        </div>

        <h2>Bem-vindo!</h2>
        <p>Você está conectado como <strong><?= htmlspecialchars($_SESSION['tipo']) ?></strong>.</p>

        <div class="grade-modulos">
            <a class="modulo" href="pages/cadastrar_produto.php">
                <div class="modulo__icone">+</div>
                <h2 class="modulo__titulo">Cadastro de Itens</h2>
                <p class="modulo__descricao">Inclua um novo item no catálogo com categoria, unidade de medida e fornecedor.</p>
            </a>

            <a class="modulo" href="pages/lancamentos.php">
                <div class="modulo__icone">Entrada</div>
                <h2 class="modulo__titulo">Entrada de Produtos</h2>
                <p class="modulo__descricao">Registre o recebimento de materiais, com quantidade, data, hora e responsável.</p>
            </a>

            <a class="modulo" href="pages/lancamentos.php">
                <div class="modulo__icone">Saída</div>
                <h2 class="modulo__titulo">Saída de Produtos</h2>
                <p class="modulo__descricao">Lance a retirada de materiais por setor solicitante, com observação opcional.</p>
            </a>

            <a class="modulo" href="pages/produtos.php">
                <div class="modulo__icone">Itens</div>
                <h2 class="modulo__titulo">Produtos</h2>
                <p class="modulo__descricao">Verifique a lista dos produtos cadastrados.</p>
            </a>

            <a class="modulo" href="pages/historico.php">
                <div class="modulo__icone">Hist.</div>
                <h2 class="modulo__titulo">Histórico</h2>
                <p class="modulo__descricao">Consulte o histórico de produtos e lançamentos.</p>
            </a>

            <a class="modulo" href="pages/estoque.php">
                <div class="modulo__icone">Est.</div>
                <h2 class="modulo__titulo">Estoque</h2>
                <p class="modulo__descricao">Verifique a quantidade de produtos no estoque.</p>
            </a>
        </div>
    </main>

    <footer class="rodape">GestSaúde · Módulo de Controle de Estoque</footer>

</body>
</html>
