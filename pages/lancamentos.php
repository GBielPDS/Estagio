<?php

require "../script/sessao.php";
require "../script/conexao.php";
require "../script/funcoes_lancamentos.php";
require "../script/sidebar.php";

verificarSessao();

$mensagem = "";
$tipoMensagem = "";

if (isset($_SESSION['mensagem_lancamento'])) {
    $mensagem = $_SESSION['mensagem_lancamento']['texto'];
    $tipoMensagem = $_SESSION['mensagem_lancamento']['tipo'];
    unset($_SESSION['mensagem_lancamento']);
}


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

                    $_SESSION['mensagem_lancamento'] = [
                        'texto' => 'Entrada realizada com sucesso!',
                        'tipo' => 'sucesso'
                    ];
                    header('Location: lancamentos.php');
                    exit();

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

                    $_SESSION['mensagem_lancamento'] = [
                        'texto' => 'Saída realizada com sucesso!',
                        'tipo' => 'sucesso'
                    ];
                    header('Location: lancamentos.php');
                    exit();

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

<header class="topo topo--legado">
    <div class="topo__interno">
        <a class="marca" href="../index.php">
            <svg class="marca__svg" width="34" height="34" viewBox="0 0 64 64" aria-hidden="true">
                <defs>
                    <linearGradient id="grad-coracao" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="#6fd0f2"/>
                        <stop offset="100%" stop-color="#1a3f8f"/>
                    </linearGradient>
                </defs>
                <path d="M32 57C32 57 6 42 6 24.5C6 15.4 13.2 9 21.3 9C26 9 30.1 11.2 32 14.6C33.9 11.2 38 9 42.7 9C50.8 9 58 15.4 58 24.5C58 42 32 57 32 57Z" fill="url(#grad-coracao)"/>
                <path d="M12 31 H22 L26 22 L31 38 L36 27 L40 31 H52" fill="none" stroke="#ffffff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M45 8 h6 v5 h5 v6 h-5 v5 h-6 v-5 h-5 v-6 h5 z" fill="#7fd3f0" stroke="#ffffff" stroke-width="2.5" stroke-linejoin="round"/>
            </svg>
            <span class="marca__texto">GEST<span>SAÚDE</span></span>
        </a>

        <nav class="nav" aria-label="Módulos do estoque">
            <a class="nav__item" href="cadastrar_produto.php">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                Cadastro
            </a>
            <a class="nav__item" href="lancamentos.php" aria-current="page">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="m17 10-5 5-5-5"/><path d="M4 21h16"/></svg>
                Entrada
            </a>
            <a class="nav__item" href="lancamentos.php">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21V9"/><path d="m7 14 5-5 5 5"/><path d="M4 3h16"/></svg>
                Saída
            </a>
            <a class="nav__item" href="produtos.php">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="M3.29 7 12 12l8.71-5"/><path d="M12 22V12"/></svg>
                Produtos
            </a>
            <a class="nav__item" href="historico.php">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><path d="M12 7v5l4 2"/></svg>
                Histórico
            </a>
            <a class="nav__item" href="estoque.php">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 8.35V20a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V8.35A2 2 0 0 1 3.26 6.5l8-3.2a2 2 0 0 1 1.48 0l8 3.2A2 2 0 0 1 22 8.35Z"/><path d="M6 10v.01"/><path d="M6 14v.01"/><path d="M10 10v.01"/><path d="M10 14v.01"/><path d="M14 10v.01"/><path d="M14 14v.01"/><path d="M18 10v.01"/><path d="M18 14v.01"/><path d="M6 18v.01"/><path d="M10 18v.01"/><path d="M14 18v.01"/><path d="M18 18v.01"/></svg>
                Estoque
            </a>

            <div class="user-menu">
                <img src="https://ui-avatars.com/api/?name=V+W&background=e2e8f0&color=64748b&size=150" alt="Perfil" class="avatar">
                <div class="dropdown-content">
                    <a href="perfil.php"><i class="fa-solid fa-user"></i> Meu Perfil</a>
                    <a href="perfil.php"><i class="fa-solid fa-gear"></i> Configurações</a>
                    <hr>
                    <a href="login.php" style="color: #ef4444;"><i class="fa-solid fa-right-from-bracket"></i> Sair</a>
                </div>
            </div>
        </nav>
    </div>
</header>

<main class="conteudo">

    <div class="cabecalho-pagina">
        <h1 class="cabecalho-pagina__titulo">Lançamentos</h1>
        <p class="cabecalho-pagina__descricao">Registre entradas e saídas de materiais do almoxarifado.</p>
    </div>

    <?php if ($mensagem !== ''): ?>

        <div class="mensagem <?= htmlspecialchars($tipoMensagem) ?>">

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
                    required
                >


                    <option
                        value="<?= $idSecretaria ?>"
                        data-secretaria="true"
                    >

                        Secretaria de Saúde

                    </option>


                    <?php while (
                        $unidade =
                        $resultadoUnidades->fetch_assoc()
                    ): ?>

                        <option
                            value="<?= $unidade['id_unidade'] ?>"
                            data-secretaria="false"
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
                        class="select-produto"
                        required
                    >

                        <option value="">
                            Selecione um produto
                        </option>


                        <?php while (
                            $produto =
                            $resultadoProdutos->fetch_assoc()
                        ): ?>

                            <option
                                value="<?= $produto['id_produto'] ?>"
                                data-estoque="<?= $produto['estoque'] ?>"
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


    const selectUnidade =
        document.getElementById(
            'unidade_destino'
        );


    const opcoesUnidade =
        selectUnidade.querySelectorAll(
            'option'
        );


    if (tipo === 'Entrada') {


        opcoesUnidade.forEach(
            function(opcao)
            {

                opcao.hidden = false;

            }
        );


        selectUnidade.value =
            "<?= $idSecretaria ?>";


        selectUnidade.disabled = true;

    }


    else {


        selectUnidade.disabled = false;


        selectUnidade.value = "";


        opcoesUnidade.forEach(
            function(opcao)
            {

                if (
                    opcao.dataset.secretaria === 'true'
                ) {

                    opcao.hidden = true;

                } else {

                    opcao.hidden = false;

                }

            }
        );

    }


    atualizarProdutos();

}


function atualizarProdutos()
{
    const tipo =
        document.querySelector(
            'input[name="tipo"]:checked'
        ).value;


    const selects =
        document.querySelectorAll(
            '.select-produto'
        );


    selects.forEach(
        function(select)
        {

            const opcoes =
                select.querySelectorAll(
                    'option'
                );


            opcoes.forEach(
                function(opcao)
                {

                    if (opcao.value === '') {
                        return;
                    }


                    const estoque =
                        parseInt(
                            opcao.dataset.estoque
                        );


                    if (tipo === 'Saida') {

                        if (estoque <= 0) {

                            opcao.hidden = true;

                        } else {

                            opcao.hidden = false;

                        }

                    }


                    else {

                        opcao.hidden = false;

                    }

                }
            );

        }
    );
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
            class="select-produto"
            required
        >

            <option value="">
                Selecione um produto
            </option>


            <?php


            $resultadoProdutos =
                $conn->query($sqlProdutos);

            while (
                $produto =
                $resultadoProdutos->fetch_assoc()
            ):

            ?>

                <option
                    value="<?= $produto['id_produto'] ?>"
                    data-estoque="<?= $produto['estoque'] ?>"
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


    atualizarProdutos();

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


document.addEventListener(
    'wheel',
    function(evento)
    {
        if (evento.target.matches('input[type="number"]')) {
            evento.target.blur();
        }
    }
);


alterarTipo();

</script>


</body>
</html>