<?php

require "../script/sessao.php";
require "../script/conexao.php";
require "../script/funcoes_lancamentos.php";
require "../script/sidebar.php";

verificarSessao();

$mensagem = "";
$tipoMensagem = "";


$sqlSecretaria = "SELECT id_unidade
                  FROM unidade_saude
                  WHERE nome = 'Secretaria de Saúde'
                  LIMIT 1";

$resultadoSecretaria = $conn->query($sqlSecretaria);

if ($resultadoSecretaria->num_rows > 0) {

    $secretaria = $resultadoSecretaria->fetch_assoc();

    $idSecretaria = $secretaria['id_unidade'];

} else {

    $idSecretaria = null;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tipo = $_POST['tipo'] ?? '';

    $observacao = trim(
        $_POST['observacao'] ?? ''
    );

    $usuario_id = $_SESSION['id_usuario'];

    $produtosRecebidos = $_POST['produtos'] ?? [];

    $produtos = [];


    foreach ($produtosRecebidos as $produto) {

        if (
            !isset($produto['produto_id']) ||
            !isset($produto['quantidade'])
        ) {
            continue;
        }

        $produto_id = (int) $produto['produto_id'];

        $quantidade = (int) $produto['quantidade'];


        if (
            $produto_id > 0 &&
            $quantidade > 0
        ) {

            $produtos[] = [
                'produto_id' => $produto_id,
                'quantidade' => $quantidade
            ];
        }
    }


    if (empty($produtos)) {

        $mensagem = "Adicione pelo menos um produto.";

        $tipoMensagem = "erro";

    } else {


        if ($tipo === 'Entrada') {

            if ($idSecretaria === null) {

                $mensagem =
                    "A Secretaria de Saúde não está cadastrada.";

                $tipoMensagem = "erro";

            } else {

                $resultado = lancamentoEntrada(
                    $conn,
                    $produtos,
                    $idSecretaria,
                    $observacao,
                    $usuario_id
                );


                if ($resultado !== false) {

                    $mensagem =
                        "Entrada realizada com sucesso!";

                    $tipoMensagem = "sucesso";

                } else {

                    $mensagem =
                        "Erro ao realizar a entrada.";

                    $tipoMensagem = "erro";
                }
            }
        }


        elseif ($tipo === 'Saida') {

            $unidadeDestino =
                (int) ($_POST['unidade_destino'] ?? 0);


            if ($unidadeDestino <= 0) {

                $mensagem =
                    "Selecione uma unidade de saúde.";

                $tipoMensagem = "erro";

            } else {

                $resultado = lancamentoSaida(
                    $conn,
                    $produtos,
                    $unidadeDestino,
                    $observacao,
                    $usuario_id
                );


                if ($resultado !== false) {

                    $mensagem =
                        "Saída realizada com sucesso!";

                    $tipoMensagem = "sucesso";

                } else {

                    $mensagem =
                        "Erro ao realizar a saída. Verifique o estoque dos produtos.";

                    $tipoMensagem = "erro";
                }
            }

        } else {

            $mensagem =
                "Tipo de lançamento inválido.";

            $tipoMensagem = "erro";
        }
    }
}


$sqlProdutos = "SELECT
                    id_produto,
                    nome,
                    unidade,
                    estoque
                FROM produto
                ORDER BY nome";

$resultadoProdutos = $conn->query($sqlProdutos);


$sqlUnidades = "SELECT
                    id_unidade,
                    nome
                FROM unidade_saude
                WHERE ativo = TRUE
                AND id_unidade != ?
                ORDER BY nome";

$stmtUnidades = $conn->prepare($sqlUnidades);

$stmtUnidades->bind_param(
    "i",
    $idSecretaria
);

$stmtUnidades->execute();

$resultadoUnidades =
    $stmtUnidades->get_result();

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

    <title>Lançamentos</title>

</head>

<body>


<?php sidebar('Lancamentos'); ?>


<main class="conteudo">

    <header class="topo">

        <h1>Lançamentos</h1>

    </header>


    <?php if ($mensagem !== ''): ?>

        <div class="mensagem <?= $tipoMensagem ?>">

            <?= htmlspecialchars($mensagem) ?>

        </div>

    <?php endif; ?>


    <section class="dashboard">

        <h2>Novo lançamento</h2>


        <form
            method="POST"
            id="form-lancamento"
        >


            <div class="campo">

                <label>
                    Tipo de lançamento:
                </label>

                <br>

                <label>

                    <input
                        type="radio"
                        name="tipo"
                        value="Entrada"
                        checked
                        onchange="alterarTipo()"
                    >

                    Entrada

                </label>


                &nbsp;&nbsp;


                <label>

                    <input
                        type="radio"
                        name="tipo"
                        value="Saida"
                        onchange="alterarTipo()"
                    >

                    Saída

                </label>

            </div>


            <br>


            <div class="campo">

                <label for="unidade_destino">

                    Unidade de destino:

                </label>


                <select
                    name="unidade_destino"
                    id="unidade_destino"
                >

                    <option
                        value="<?= $idSecretaria ?>"
                        data-secretaria="true"
                    >
                        Secretaria de Saúde
                    </option>


                    <?php

                    while (
                        $unidade =
                        $resultadoUnidades->fetch_assoc()
                    ):

                    ?>

                        <option
                            value="<?= $unidade['id_unidade'] ?>"
                        >

                            <?= htmlspecialchars(
                                $unidade['nome']
                            ) ?>

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <br>

            <h3>Produtos</h3>


            <div id="produtos-container">


                <div class="produto-item">


                    <select
                        name="produtos[0][produto_id]"
                        required
                    >

                        <option value="">
                            Selecione um produto
                        </option>


                        <?php

                        $resultadoProdutos->data_seek(0);

                        while (
                            $produto =
                            $resultadoProdutos->fetch_assoc()
                        ):

                        ?>

                            <option
                                value="<?= $produto['id_produto'] ?>"
                            >

                                <?= htmlspecialchars(
                                    $produto['nome']
                                ) ?>

                                — Estoque:

                                <?= $produto['estoque'] ?>

                                <?= htmlspecialchars(
                                    $produto['unidade']
                                ) ?>

                            </option>

                        <?php endwhile; ?>

                    </select>


                    <input
                        type="number"
                        name="produtos[0][quantidade]"
                        min="1"
                        placeholder="Quantidade"
                        required
                    >


                    <button
                        type="button"
                        class="botao-remover"
                        onclick="removerProduto(this)"
                    >

                        Remover

                    </button>


                </div>

            </div>


            <br>


            <button
                type="button"
                class="botao botao--secundario"
                onclick="adicionarProduto()"
            >

                + Adicionar produto

            </button>


            <br><br>


            <label for="observacao">

                Observação:

            </label>


            <br>


            <textarea
                name="observacao"
                id="observacao"
                rows="4"
                placeholder="Observação sobre o lançamento..."
            ></textarea>


            <br><br>


            <button
                type="submit"
                class="botao botao--principal"
            >

                Realizar lançamento

            </button>


        </form>

    </section>

</main>


<script>


let contadorProdutos = 1;


function alterarTipo()
{

    const tipo =
        document.querySelector(
            'input[name="tipo"]:checked'
        ).value;


    const select =
        document.getElementById(
            'unidade_destino'
        );


    if (tipo === 'Entrada') {

        select.value =
            "<?= $idSecretaria ?>";

        select.disabled = true;

    } else {

        select.disabled = false;

        select.value = "";

    }

}


function adicionarProduto()
{

    const container =
        document.getElementById(
            'produtos-container'
        );


    const div =
        document.createElement('div');


    div.classList.add(
        'produto-item'
    );


    div.innerHTML = `

        <select
            name="produtos[${contadorProdutos}][produto_id]"
            required
        >

            <option value="">
                Selecione um produto
            </option>

            <?php

            $resultadoProdutos->data_seek(0);

            while (
                $produto =
                $resultadoProdutos->fetch_assoc()
            ):

            ?>

                <option
                    value="<?= $produto['id_produto'] ?>"
                >

                    <?= htmlspecialchars(
                        $produto['nome']
                    ) ?>

                    — Estoque:

                    <?= $produto['estoque'] ?>

                    <?= htmlspecialchars(
                        $produto['unidade']
                    ) ?>

                </option>

            <?php endwhile; ?>

        </select>


        <input
            type="number"
            name="produtos[${contadorProdutos}][quantidade]"
            min="1"
            placeholder="Quantidade"
            required
        >


        <button
            type="button"
            class="botao-remover"
            onclick="removerProduto(this)"
        >

            Remover

        </button>

    `;


    container.appendChild(div);

    contadorProdutos++;

}


function removerProduto(botao)
{

    const produtos =
        document.querySelectorAll(
            '.produto-item'
        );


    if (produtos.length <= 1) {

        alert(
            'É necessário ter pelo menos um produto.'
        );

        return;
    }


    botao.parentElement.remove();

}


alterarTipo();

</script>


</body>
</html>