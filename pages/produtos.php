<?php

require_once '../script/sessao.php';
require_once '../script/conexao.php';
require '../script/funcoes_produtos.php';
require "../script/sidebar.php";

verificarSessao();

$categorias = buscarCategorias($conn);
$unidades = buscarUnidades($conn);

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style.css">
    <title>Produtos</title>
</head>

<body>

    <?php sidebar('produtos'); ?>

    <main class="conteudo">

        <Header class="topo">

            <h1>Produtos</h1>

        </Header>

        <section class="cartao cartao--produtos">
            <div class="filtros">
                <div class="campo campo--largo">
                    <label class="campo__rotulo" for="pesquisa-produto">Pesquisar produto</label>
                    <input class="campo__controle" type="search" id="pesquisa-produto"
                        placeholder="Digite o nome do produto" autocomplete="off">
                </div>

                <div class="campo campo--filtro-categoria">
                    <label class="campo__rotulo" for="filtro-categoria">Categoria</label>
                    <select class="campo__controle" id="filtro-categoria">
                        <option value="">Todas as categorias</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?= htmlspecialchars($categoria['nome'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($categoria['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo campo--filtro-categoria">
                    <label class="campo__rotulo" for="filtro-unidade">Unidade</label>
                    <select class="campo__controle" id="filtro-unidade">
                        <option value="">Todas as unidades</option>
                        <?php foreach ($unidades as $itemUnidade): ?>
                            <option value="<?= htmlspecialchars($itemUnidade['unidade'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($itemUnidade['unidade']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button class="botao botao--secundario" type="button" id="limpar-filtros">
                    Limpar filtros
                </button>
            </div>

            <p id="nenhum-produto" hidden>Nenhum produto encontrado.</p>

            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Categoria</th>
                        <th>Unidade de medida</th>
                        <th>Quantidade em estoque</th>
                    </tr>
                </thead>
                <tbody id="produtos-tbody">
                    <?php listarProdutos($conn); ?>
                </tbody>
            </table>
        </section>

    </main>

    <script src="../script/produtos.js"></script>

</body>

</html>
