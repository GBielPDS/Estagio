<?php

require "../script/sessao.php";
require "../script/conexao.php";
require "../script/funcoes_graficos.php";
require "../script/sidebar.php";

verificarSessao();


$tipo =
    $_GET['tipo'] ?? 'Saida';

$dataInicio =
    $_GET['data_inicio'] ?? '';

$dataFim =
    $_GET['data_fim'] ?? '';

$categoria =
    $_GET['categoria'] ?? '';

$unidade =
    $_GET['unidade'] ?? '';


$resultadoCategorias =
    buscarCategoriasGraficos($conn);

$resultadoUnidades =
    buscarUnidadesGraficos($conn);


$resultadoProdutos =
    buscarProdutosMaisUsados(
        $conn,
        $tipo,
        $dataInicio,
        $dataFim,
        $categoria,
        $unidade
    );


$resultadoUnidadesGrafico =
    buscarUnidadesMaisUsadas(
        $conn,
        $tipo,
        $dataInicio,
        $dataFim,
        $categoria,
        $unidade
    );


$resultadoCategoriasGrafico =
    buscarCategoriasMaisUsadas(
        $conn,
        $tipo,
        $dataInicio,
        $dataFim,
        $categoria,
        $unidade
    );


$produtosLabels = [];
$produtosDados = [];

while (
    $linha =
    $resultadoProdutos->fetch_assoc()
) {

    $produtosLabels[] =
        $linha['produto'];

    $produtosDados[] =
        (int) $linha['quantidade'];
}


$unidadesLabels = [];
$unidadesDados = [];

while (
    $linha =
    $resultadoUnidadesGrafico->fetch_assoc()
) {

    $unidadesLabels[] =
        $linha['unidade'];

    $unidadesDados[] =
        (int) $linha['quantidade'];
}


$categoriasLabels = [];
$categoriasDados = [];

while (
    $linha =
    $resultadoCategoriasGrafico->fetch_assoc()
) {

    $categoriasLabels[] =
        $linha['categoria'];

    $categoriasDados[] =
        (int) $linha['quantidade'];
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

    <title>Gráficos</title>


    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>


<body>


<?php sidebar('Graficos'); ?>


<main class="conteudo">


    <div class="cabecalho-pagina">

        <h1 class="cabecalho-pagina__titulo">

            Gráficos

        </h1>


        <p class="cabecalho-pagina__descricao">

            Visualize informações sobre o consumo
            e movimentação dos produtos.

        </p>

    </div>

    <section class="cartao">


        <form
            method="GET"
            class="filtros"
        >


            <div class="campo">

                <label
                    class="campo__rotulo"
                    for="tipo"
                >

                    Tipo

                </label>


                <select
                    class="campo__controle"
                    name="tipo"
                    id="tipo"
                >

                    <option value="">

                        Todos

                    </option>


                    <option
                        value="Saida"
                        <?= $tipo === 'Saida'
                            ? 'selected'
                            : '' ?>
                    >

                        Saídas

                    </option>


                    <option
                        value="Entrada"
                        <?= $tipo === 'Entrada'
                            ? 'selected'
                            : '' ?>
                    >

                        Entradas

                    </option>

                </select>

            </div>


            <div class="campo">

                <label
                    class="campo__rotulo"
                    for="data_inicio"
                >

                    De

                </label>


                <input
                    class="campo__controle"
                    type="date"
                    name="data_inicio"
                    id="data_inicio"
                    value="<?= htmlspecialchars(
                        $dataInicio
                    ) ?>"
                >

            </div>


            <div class="campo">

                <label
                    class="campo__rotulo"
                    for="data_fim"
                >

                    Até

                </label>


                <input
                    class="campo__controle"
                    type="date"
                    name="data_fim"
                    id="data_fim"
                    value="<?= htmlspecialchars(
                        $dataFim
                    ) ?>"
                >

            </div>


            <div class="campo">

                <label
                    class="campo__rotulo"
                    for="categoria"
                >

                    Categoria

                </label>


                <select
                    class="campo__controle"
                    name="categoria"
                    id="categoria"
                >

                    <option value="">

                        Todas

                    </option>


                    <?php while (
                        $cat =
                        $resultadoCategorias->fetch_assoc()
                    ): ?>

                        <option
                            value="<?= $cat['id_categoria'] ?>"
                            <?= $categoria ==
                                $cat['id_categoria']
                                ? 'selected'
                                : '' ?>
                        >

                            <?= htmlspecialchars(
                                $cat['nome']
                            ) ?>

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <div class="campo">

                <label
                    class="campo__rotulo"
                    for="unidade"
                >

                    Unidade de saúde

                </label>


                <select
                    class="campo__controle"
                    name="unidade"
                    id="unidade"
                >

                    <option value="">

                        Todas

                    </option>


                    <?php while (
                        $uni =
                        $resultadoUnidades->fetch_assoc()
                    ): ?>

                        <option
                            value="<?= $uni['id_unidade'] ?>"
                            <?= $unidade ==
                                $uni['id_unidade']
                                ? 'selected'
                                : '' ?>
                        >

                            <?= htmlspecialchars(
                                $uni['nome']
                            ) ?>

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <button
                class="botao botao--primario"
                type="submit"
            >

                Filtrar

            </button>


            <a
                class="botao botao--secundario"
                href="graficos.php"
            >

                Limpar filtros

            </a>


        </form>

    </section>


    <br>


    <section>

        <h2>Tipo de gráfico</h2>


        <div class="grade-modulos">


            <button
                class="modulo botao-grafico"
                type="button"
                onclick="alterarTipoGrafico('bar')"
            >

                <div class="modulo__icone">

                    <svg
                        width="22"
                        height="22"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >

                        <path d="M4 20V10"/>

                        <path d="M10 20V4"/>

                        <path d="M16 20v-7"/>

                        <path d="M22 20V7"/>

                    </svg>

                </div>


                <h2 class="modulo__titulo">

                    Barras

                </h2>


                <p class="modulo__descricao">

                    Compare os valores de cada categoria.

                </p>

            </button>


            <button
                class="modulo botao-grafico"
                type="button"
                onclick="alterarTipoGrafico('line')"
            >

                <div class="modulo__icone">

                    <svg
                        width="22"
                        height="22"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >

                        <path d="m3 17 6-6 4 4 8-8"/>

                        <path d="M21 7v5h-5"/>

                    </svg>

                </div>


                <h2 class="modulo__titulo">

                    Linhas

                </h2>


                <p class="modulo__descricao">

                    Visualize a evolução dos dados.

                </p>

            </button>


            <button
                class="modulo botao-grafico"
                type="button"
                onclick="alterarTipoGrafico('pie')"
            >

                <div class="modulo__icone">

                    <svg
                        width="22"
                        height="22"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >

                        <path d="M12 2v10h10"/>

                        <path d="M20.5 15a9 9 0 1 1-11.5-11"/>

                    </svg>

                </div>


                <h2 class="modulo__titulo">

                    Pizza

                </h2>


                <p class="modulo__descricao">

                    Veja a proporção entre os valores.

                </p>

            </button>


            <button
                class="modulo botao-grafico"
                type="button"
                onclick="alterarTipoGrafico('doughnut')"
            >

                <div class="modulo__icone">

                    <svg
                        width="22"
                        height="22"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >

                        <circle
                            cx="12"
                            cy="12"
                            r="9"
                        />

                        <path d="M12 3v9h9"/>

                    </svg>

                </div>


                <h2 class="modulo__titulo">

                    Rosca

                </h2>


                <p class="modulo__descricao">

                    Compare a participação de cada grupo.

                </p>

            </button>


        </div>

    </section>


    <br>


    <section class="cartao">


        <h2>

            Produtos mais utilizados

        </h2>


        <div style="height: 400px;">

            <canvas id="graficoProdutos"></canvas>

        </div>

    </section>


    <br>


    <section class="cartao">


        <h2>

            Unidades de saúde que mais utilizam recursos

        </h2>


        <div style="height: 400px;">

            <canvas id="graficoUnidades"></canvas>

        </div>

    </section>


    <br>


    <section class="cartao">


        <h2>

            Categorias mais utilizadas

        </h2>


        <div style="height: 400px;">

            <canvas id="graficoCategorias"></canvas>

        </div>

    </section>


</main>


<footer class="rodape">

    GestSaúde · Módulo de Controle de Estoque

</footer>


<script>

const produtosLabels =
    <?= json_encode($produtosLabels) ?>;

const produtosDados =
    <?= json_encode($produtosDados) ?>;


const unidadesLabels =
    <?= json_encode($unidadesLabels) ?>;

const unidadesDados =
    <?= json_encode($unidadesDados) ?>;


const categoriasLabels =
    <?= json_encode($categoriasLabels) ?>;

const categoriasDados =
    <?= json_encode($categoriasDados) ?>;


let tipoGrafico = 'bar';


let graficoProdutos;

let graficoUnidades;

let graficoCategorias;


function criarGraficos()
{


    if (graficoProdutos) {

        graficoProdutos.destroy();

    }


    graficoProdutos =
        criarGrafico(
            'graficoProdutos',
            produtosLabels,
            produtosDados,
            'Quantidade utilizada'
        );


    if (graficoUnidades) {

        graficoUnidades.destroy();

    }


    graficoUnidades =
        criarGrafico(
            'graficoUnidades',
            unidadesLabels,
            unidadesDados,
            'Quantidade utilizada'
        );


    if (graficoCategorias) {

        graficoCategorias.destroy();

    }


    graficoCategorias =
        criarGrafico(
            'graficoCategorias',
            categoriasLabels,
            categoriasDados,
            'Quantidade utilizada'
        );
}


function criarGrafico(
    id,
    labels,
    dados,
    titulo
) {

    const canvas =
        document.getElementById(id);


    return new Chart(
        canvas,
        {

            type: tipoGrafico,

            data: {

                labels: labels,

                datasets: [

                    {

                        label: titulo,

                        data: dados

                    }

                ]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                plugins: {

                    legend: {

                        display:
                            tipoGrafico === 'pie' ||
                            tipoGrafico === 'doughnut'

                    }

                },

                scales:
                    tipoGrafico === 'pie' ||
                    tipoGrafico === 'doughnut'
                    ? {}
                    : {

                        y: {

                            beginAtZero: true

                        }

                    }

            }

        }
    );
}


function alterarTipoGrafico(tipo)
{

    tipoGrafico = tipo;

    criarGraficos();

}


criarGraficos();

</script>


</body>

</html>