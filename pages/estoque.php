<?php

require "../script/sessao.php";
require "../script/conexao.php";
require "../script/funcoes_estoque.php";
require "../script/sidebar.php";

verificarSessao();


$filtroCategoria =
    $_GET['categoria'] ?? '';

$filtroUnidade =
    $_GET['unidade'] ?? '';

$filtroProduto =
    trim($_GET['produto'] ?? '');


$resultadoCategorias =
    buscarCategoriasEstoque($conn);


$resultadoUnidades =
    buscarUnidadesMedida($conn);


$resultadoEstoque =
    buscarEstoque(
        $conn,
        $filtroCategoria,
        $filtroUnidade,
        $filtroProduto
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

    <title>Estoque</title>

</head>


<body>


<?php sidebar('Estoque'); ?>


<main class="conteudo">


    <div class="cabecalho-pagina">

        <h1 class="cabecalho-pagina__titulo">
            Estoque
        </h1>

        <p class="cabecalho-pagina__descricao">
            Consulte os produtos disponíveis no estoque.
        </p>

    </div>


    <section class="cartao cartao--produtos">


        <form
            method="GET"
            class="filtros"
        >


            <div class="campo campo--filtro-categoria">

                <label
                    class="campo__rotulo"
                    for="filtro-categoria"
                >

                    Categoria

                </label>


                <select
                    class="campo__controle"
                    id="filtro-categoria"
                    name="categoria"
                >

                    <option value="">

                        Todas as categorias

                    </option>


                    <?php while (
                        $categoria =
                        $resultadoCategorias->fetch_assoc()
                    ): ?>

                        <option
                            value="<?= $categoria['id_categoria'] ?>"
                            <?= $filtroCategoria ==
                                $categoria['id_categoria']
                                ? 'selected'
                                : '' ?>
                        >

                            <?= htmlspecialchars(
                                $categoria['nome']
                            ) ?>

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <div class="campo campo--filtro-categoria">

                <label
                    class="campo__rotulo"
                    for="filtro-unidade"
                >

                    Unidade de medida

                </label>


                <select
                    class="campo__controle"
                    id="filtro-unidade"
                    name="unidade"
                >

                    <option value="">

                        Todas

                    </option>


                    <?php while (
                        $unidade =
                        $resultadoUnidades->fetch_assoc()
                    ): ?>

                        <option
                            value="<?= htmlspecialchars(
                                $unidade['unidade']
                            ) ?>"
                            <?= $filtroUnidade ===
                                $unidade['unidade']
                                ? 'selected'
                                : '' ?>
                        >

                            <?= htmlspecialchars(
                                $unidade['unidade']
                            ) ?>

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <div class="campo campo--filtro-categoria">

                <label
                    class="campo__rotulo"
                    for="filtro-produto"
                >

                    Produto

                </label>


                <input
                    class="campo__controle"
                    type="text"
                    id="filtro-produto"
                    name="produto"
                    placeholder="Pesquisar produto..."
                    value="<?= htmlspecialchars(
                        $filtroProduto
                    ) ?>"
                >

            </div>


            <button
                class="botao botao--primario"
                type="submit"
            >

                Pesquisar

            </button>


            <a
                class="botao botao--secundario"
                href="estoque.php"
            >

                Limpar filtros

            </a>


        </form>


        <br>


        <div class="tabela-container">

            <table class="tabela">

                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Produto</th>

                        <th>Categoria</th>

                        <th>Estoque</th>

                        <th>Unidade</th>

                        <th>Estoque mínimo</th>

                    </tr>

                </thead>


                <tbody>

                    <?php if (
                        $resultadoEstoque &&
                        $resultadoEstoque->num_rows > 0
                    ): ?>


                        <?php while (
                            $produto =
                            $resultadoEstoque->fetch_assoc()
                        ): ?>

                            <tr>

                                <td>

                                    <?= $produto[
                                        'id_produto'
                                    ] ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $produto['nome']
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $produto['categoria']
                                    ) ?>

                                </td>


                                <td>

                                    <?= $produto[
                                        'estoque'
                                    ] ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $produto['unidade']
                                    ) ?>

                                </td>


                                <td>

                                    <?= $produto[
                                        'estoque_minimo'
                                    ] ?>

                                </td>

                            </tr>

                        <?php endwhile; ?>


                    <?php else: ?>

                        <tr>

                            <td
                                colspan="6"
                                style="text-align: center;"
                            >

                                Nenhum produto encontrado
                                no estoque.

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>


    </section>


</main>


<footer class="rodape">

    GestSaúde · Módulo de Controle de Estoque

</footer>


</body>

</html>