<?php

declare(strict_types=1);

// Compatível com pages/ no desenvolvimento e páginas na raiz da hospedagem.
$raizAplicacao = is_file(__DIR__ . '/script/configuracao.php') ? __DIR__ : dirname(__DIR__);
require_once $raizAplicacao . '/script/sessao.php';
require_once $raizAplicacao . '/script/conexao.php';
require_once $raizAplicacao . '/script/funcoes_lancamentos.php';
require_once $raizAplicacao . '/script/sidebar.php';

verificarSessao();

$tipoSelecionado = (string) (
    $_POST['tipo']
    ?? $_GET['tipo']
    ?? 'Entrada'
);

if (!in_array($tipoSelecionado, ['Entrada', 'Saida'], true)) {
    $tipoSelecionado = 'Entrada';
}

$mensagem = "";
$tipoMensagem = "";

if (isset($_SESSION['mensagem_lancamento'])) {

    $mensagem = (string) $_SESSION['mensagem_lancamento']['texto'];
    $tipoMensagem = (string) $_SESSION['mensagem_lancamento']['tipo'];

    unset($_SESSION['mensagem_lancamento']);
}


$sqlSecretaria = "SELECT id_unidade
                  FROM unidade_saude
                  WHERE nome = 'Secretaria de Saúde'
                  LIMIT 1";

$resultadoSecretaria = $conn->query($sqlSecretaria);

if (
    $resultadoSecretaria &&
    $resultadoSecretaria->num_rows > 0
) {

    $secretaria = $resultadoSecretaria->fetch_assoc();

    $idSecretaria = (int) $secretaria['id_unidade'];

} else {

    $idSecretaria = null;
}


// Estado do formulário em respostas de erro, inclusive quando JavaScript não estiver disponível.
function campoLancamento(string $nome): string {
    return is_string($_POST[$nome] ?? null) ? $_POST[$nome] : '';
}
$modoFormulario = campoLancamento('modo_data') === 'passada' ? 'passada' : 'agora';
$linhasFormulario = [];
foreach (is_array($_POST['produtos'] ?? null) ? $_POST['produtos'] : [] as $linha) {
    if (!is_array($linha)) continue;
    $linhasFormulario[] = [
        'produto_id' => is_scalar($linha['produto_id'] ?? null) ? (string) $linha['produto_id'] : '',
        'quantidade' => is_scalar($linha['quantidade'] ?? null) ? (string) $linha['quantidade'] : ''
    ];
}
if (!$linhasFormulario) $linhasFormulario[] = ['produto_id'=>'', 'quantidade'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tipo = campoLancamento('tipo');

    $observacao = trim(campoLancamento('observacao'));

    $modoData = isset($_POST['modo_data']) ? campoLancamento('modo_data') : 'agora';

    $dataHora = null;

    if ($modoData === 'passada') {

        $dataHora = trim(campoLancamento('data_hora'));

    } elseif ($modoData !== 'agora') {

        $mensagem = 'Selecione uma opção válida para a data do lançamento.';
        $tipoMensagem = 'erro';
    }


    $usuario_id = (int) $_SESSION['id_usuario'];


    if ($modoData === 'passada') {

        if ($dataHora === '') {

            $mensagem = 'Informe a data e hora do lançamento.';
            $tipoMensagem = 'erro';

        } elseif ($observacao === '') {

            $mensagem =
                'A observação é obrigatória quando o lançamento possui uma data anterior.';

            $tipoMensagem = 'erro';
        }
    }


    if ($tipoMensagem === '') {

        $produtosRecebidos =
            (array) ($_POST['produtos'] ?? []);

        $produtos = [];

        $itensInvalidos = false;


        foreach ($produtosRecebidos as $produto) {

            if (
                !is_array($produto) ||
                !isset(
                    $produto['produto_id'],
                    $produto['quantidade']
                )
            ) {

                $itensInvalidos = true;

                continue;
            }


            $produto_id = filter_var(
                $produto['produto_id'],
                FILTER_VALIDATE_INT
            );

            $quantidade = filter_var(
                $produto['quantidade'],
                FILTER_VALIDATE_INT
            );


            if (
                $produto_id > 0 &&
                $quantidade > 0
            ) {

                $produtos[] = [
                    'produto_id' => $produto_id,
                    'quantidade' => $quantidade
                ];

            } else {

                $itensInvalidos = true;
            }
        }


        if ($itensInvalidos) {

            $mensagem =
                'Revise todos os itens: produto e quantidade devem ser inteiros positivos. Nenhum item foi salvo.';

            $tipoMensagem = 'erro';

        } elseif (empty($produtos)) {

            $mensagem =
                'Adicione pelo menos um produto.';

            $tipoMensagem = 'erro';
        }
    }


    if ($tipoMensagem === '') {

        if ($tipo === 'Entrada') {

            if ($idSecretaria === null) {

                $mensagem =
                    'A Secretaria de Saúde não está cadastrada.';

                $tipoMensagem = 'erro';

            } else {

                $resultado = lancamentoEntrada(
                    $conn,
                    $produtos,
                    $idSecretaria,
                    $observacao,
                    $usuario_id,
                    $dataHora
                );


                if (!empty($resultado['sucesso'])) {

                    $mensagem =
                        'Entrada realizada com sucesso!';

                    $tipoMensagem = 'sucesso';

                } else {

                    $mensagem =
                        (string) (
                            $resultado['mensagem']
                            ?? 'Erro ao realizar a entrada.'
                        );

                    $tipoMensagem = 'erro';
                }
            }

        } elseif ($tipo === 'Saida') {

            $unidadeDestino = (int) (
                $_POST['unidade_destino'] ?? 0
            );


            if ($unidadeDestino <= 0) {

                $mensagem =
                    'Selecione uma unidade de saúde.';

                $tipoMensagem = 'erro';

            } else {

                $resultado = lancamentoSaida(
                    $conn,
                    $produtos,
                    $unidadeDestino,
                    $observacao,
                    $usuario_id,
                    $dataHora
                );


                if (!empty($resultado['sucesso'])) {

                    $mensagem =
                        'Saída realizada com sucesso!';

                    $tipoMensagem = 'sucesso';

                } else {

                    $mensagem =
                        (string) (
                            $resultado['mensagem']
                            ?? 'Erro ao realizar a saída. Verifique o estoque dos produtos.'
                        );

                    $tipoMensagem = 'erro';
                }
            }

        } else {

            $mensagem =
                'Tipo de lançamento inválido.';

            $tipoMensagem = 'erro';
        }
    }


    $isAjax =
        (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower(
                (string) $_SERVER['HTTP_X_REQUESTED_WITH']
            ) === 'xmlhttprequest'
        )
        || isset($_POST['ajax']);


    if ($isAjax) {

        header(
            'Content-Type: application/json; charset=utf-8'
        );

        echo json_encode(
            [
                'sucesso' =>
                    $tipoMensagem === 'sucesso',

                'mensagem' =>
                    $mensagem
            ],
            JSON_THROW_ON_ERROR |
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }


    if ($tipoMensagem === 'sucesso') {

        $_SESSION['mensagem_lancamento'] = [
            'texto' => $mensagem,
            'tipo' => 'sucesso'
        ];

        header(
            'Location: lancamentos.php?tipo=' .
            urlencode($tipo)
        );

        exit();
    }
}


$sqlProdutos = "
    SELECT
        id_produto,
        nome,
        unidade,
        estoque
    FROM produto
    ORDER BY nome
";

$resultadoProdutos = $conn->query(
    $sqlProdutos
);

$produtosCatalogo = [];

if ($resultadoProdutos) {

    while (
        $p = $resultadoProdutos->fetch_assoc()
    ) {

        $produtosCatalogo[] = $p;
    }
}


$sqlUnidades = "
    SELECT
        id_unidade,
        nome
    FROM unidade_saude
    WHERE ativo = TRUE
    AND id_unidade != ?
    ORDER BY nome
";

$stmtUnidades = $conn->prepare(
    $sqlUnidades
);

$secretariaFiltroId =
    (int) ($idSecretaria ?? 0);

$stmtUnidades->bind_param(
    "i",
    $secretariaFiltroId
);

$stmtUnidades->execute();

$resultadoUnidades =
    $stmtUnidades->get_result();

$unidadesLista = [];

if ($resultadoUnidades) {

    while (
        $u = $resultadoUnidades->fetch_assoc()
    ) {

        $unidadesLista[] = $u;
    }
}

$stmtUnidades->close();

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
        href="<?= BASE_URL ?>css/style.css"
    >

    <title>Lançamentos</title>

</head>

<body>

<?php sidebar(
    $tipoSelecionado === 'Saida'
        ? 'saida'
        : 'lancamentos'
); ?>


<main class="conteudo">

    <div class="cabecalho-pagina">

        <h1 class="cabecalho-pagina__titulo">
            Lançamentos
        </h1>

        <p class="cabecalho-pagina__descricao">
            Registre entradas e saídas de materiais do almoxarifado.
        </p>

    </div>


    <?php if ($mensagem !== ''): ?>

        <div
            class="modal-overlay
            <?= $tipoMensagem === 'sucesso'
                ? 'modal-overlay--sucesso'
                : 'modal-overlay--erro' ?>"
        >

            <div class="modal-card">

                <div class="modal-icone">

                    <?php if ($tipoMensagem === 'sucesso'): ?>

                        <svg
                            width="36"
                            height="36"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>

                    <?php else: ?>

                        <svg
                            width="36"
                            height="36"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <circle
                                cx="12"
                                cy="12"
                                r="10"
                            ></circle>

                            <line
                                x1="12"
                                y1="8"
                                x2="12"
                                y2="12"
                            ></line>

                            <line
                                x1="12"
                                y1="16"
                                x2="12.01"
                                y2="16"
                            ></line>

                        </svg>

                    <?php endif; ?>

                </div>


                <h3 class="modal-titulo">

                    <?= $tipoMensagem === 'sucesso'
                        ? 'Sucesso!'
                        : 'Atenção' ?>

                </h3>


                <p class="modal-texto">

                    <?= htmlspecialchars(
                        $mensagem,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </p>


                <button
                    type="button"
                    class="modal-botao"
                    onclick="fecharMensagem(<?= $tipoMensagem === 'sucesso' ? 'true' : 'false' ?>)"
                >

                    <?= $tipoMensagem === 'sucesso'
                        ? 'OK, Concluir'
                        : 'Entendido, Corrigir' ?>

                </button>

            </div>

        </div>

    <?php endif; ?>


    <section class="dashboard">

        <h2>Novo lançamento</h2>


        <form
            method="POST"
            id="form-lancamento"
        >

            <?= campoCsrf() ?>


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
                        <?= $tipoSelecionado === 'Entrada'
                            ? 'checked'
                            : '' ?>
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
                        <?= $tipoSelecionado === 'Saida'
                            ? 'checked'
                            : '' ?>
                        onchange="alterarTipo()"
                    >

                    Saída

                </label>

            </div>


            <br>


            <div class="campo">

                <label>
                    Data do lançamento:
                </label>

                <br>

                <label>

                    <input
                        type="radio"
                        name="modo_data"
                        value="agora"
                        <?= $modoFormulario === 'agora' ? 'checked' : '' ?>
                        onchange="alterarModoData()"
                    >

                    Agora

                </label>

                &nbsp;&nbsp;

                <label>

                    <input
                        type="radio"
                        name="modo_data"
                        value="passada"
                        <?= $modoFormulario === 'passada' ? 'checked' : '' ?>
                        onchange="alterarModoData()"
                    >

                    Escolher data anterior

                </label>

            </div>


            <div
                class="campo"
                id="campo-data-lancamento"
                style="display: <?= $modoFormulario === 'passada' ? 'block' : 'none' ?>;"
            >

                <label
                    for="data_hora"
                >
                    Data e hora do lançamento:
                </label>

                <input
                    class="campo__controle"
                    type="datetime-local"
                    name="data_hora"
                    id="data_hora"
                    value="<?= htmlspecialchars(campoLancamento('data_hora'), ENT_QUOTES, 'UTF-8') ?>"
                    <?= $modoFormulario === 'passada' ? 'required' : '' ?>
                >

                <small id="ajuda-data">
                    A data deve ser anterior ao momento atual.
                </small>

            </div>


            <br>


            <div class="campo">

                <label
                    for="unidade_destino"
                >
                    Unidade de destino:
                </label>

                <select
                    name="unidade_destino"
                    id="unidade_destino"
                    required
                >

                    <option
                        value="<?= $idSecretaria ?? '' ?>"
                        data-secretaria="true"
                    >
                        Secretaria de Saúde
                    </option>


                    <?php foreach ($unidadesLista as $unidade): ?>

                        <option
                            value="<?= (int) $unidade['id_unidade'] ?>"
                            data-secretaria="false"
                            <?= campoLancamento('unidade_destino') === (string) $unidade['id_unidade'] ? 'selected' : '' ?>
                        >

                            <?= htmlspecialchars(
                                (string) $unidade['nome'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <br>


            <h3>
                Produtos
            </h3>


            <div id="produtos-container">
                <?php foreach ($linhasFormulario as $indice => $linhaFormulario): ?>

                <div class="produto-item">

                    <select
                        name="produtos[<?= $indice ?>][produto_id]"
                        class="select-produto"
                        required
                    >

                        <option value="">
                            Selecione um produto
                        </option>


                        <?php foreach ($produtosCatalogo as $produto): ?>

                            <option
                                value="<?= (int) $produto['id_produto'] ?>"
                                data-estoque="<?= (int) $produto['estoque'] ?>"
                                <?= $linhaFormulario['produto_id'] === (string) $produto['id_produto'] ? 'selected' : '' ?>
                            >

                                <?= htmlspecialchars(
                                    (string) $produto['nome'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                                —
                                Estoque:
                                <?= (int) $produto['estoque'] ?>

                                <?= htmlspecialchars(
                                    (string) $produto['unidade'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>


                    <input
                        type="number"
                        name="produtos[<?= $indice ?>][quantidade]"
                        value="<?= htmlspecialchars($linhaFormulario['quantidade'], ENT_QUOTES, 'UTF-8') ?>"
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


                    <div class="produto-item__info"></div>

                </div>
                <?php endforeach; ?>

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


            <div class="campo">

                <label
                    for="observacao"
                >
                    Observação:
                </label>

                <br>

                <textarea
                    name="observacao"
                    id="observacao"
                    rows="4"
                    placeholder="Observação sobre o lançamento..."
                    <?= $modoFormulario === 'passada' ? 'required' : '' ?>
                ><?= htmlspecialchars(campoLancamento('observacao'), ENT_QUOTES, 'UTF-8') ?></textarea>

                <small id="observacao-ajuda">
                    Opcional quando o lançamento for realizado agora.
                </small>

            </div>


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

let contadorProdutos = <?= count($linhasFormulario) ?>;

let tipoLancamentoConcluido =
    <?= json_encode(
        $tipoSelecionado,
        JSON_THROW_ON_ERROR
    ) ?>;


// Relógio inicial do servidor + tempo decorrido: independe do fuso/relógio do navegador.
const instanteServidor = <?= (int) floor(microtime(true) * 1000) ?>;
const inicioRelogio = performance.now();
function obterDataHoraAtual() {
    const agora = new Date(instanteServidor + performance.now() - inicioRelogio);
    const partes = new Intl.DateTimeFormat('en-CA', {
        timeZone: <?= json_encode(FUSO_HORARIO_SISTEMA, JSON_THROW_ON_ERROR) ?>, year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hourCycle: 'h23'
    }).formatToParts(agora);
    const p = Object.fromEntries(partes.map(item => [item.type, item.value]));
    return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}`;
}

function atualizarLimiteData()
{
    const campo =
        document.getElementById('data_hora');

    if (!campo) {
        return;
    }

    campo.max = obterDataHoraAtual();
}


function alterarModoData()
{
    const modo =
        document.querySelector(
            'input[name="modo_data"]:checked'
        )?.value;

    const campoData =
        document.getElementById(
            'campo-data-lancamento'
        );

    const dataHora =
        document.getElementById(
            'data_hora'
        );

    const observacao =
        document.getElementById(
            'observacao'
        );

    const observacaoAjuda =
        document.getElementById(
            'observacao-ajuda'
        );


    if (modo === 'passada') {

        campoData.style.display = 'block';

        dataHora.required = true;

        observacao.required = true;

        observacaoAjuda.textContent =
            'A observação é obrigatória quando a data do lançamento é anterior.';

        atualizarLimiteData();

    } else {

        campoData.style.display = 'none';

        dataHora.required = false;

        dataHora.value = '';

        observacao.required = false;

        observacaoAjuda.textContent =
            'Opcional quando o lançamento for realizado agora.';
    }
}


function fecharMensagem(
    recarregar = false
)
{
    const overlay =
        document.querySelector(
            '.modal-overlay'
        );

    if (overlay) {
        overlay.remove();
    }


    if (recarregar) {

        window.location.href =
            'lancamentos.php?tipo=' +
            encodeURIComponent(
                tipoLancamentoConcluido
            );
    }
}


function exibirMensagem(
    tipo,
    titulo,
    texto
)
{
    fecharMensagem(false);

    const isSucesso =
        tipo === 'sucesso';

    const acaoClique =
        isSucesso
            ? 'fecharMensagem(true)'
            : 'fecharMensagem(false)';

    const classeTipo =
        isSucesso
            ? 'modal-overlay--sucesso'
            : 'modal-overlay--erro';


    const iconeHtml =
        isSucesso

        ? `
            <svg
                width="36"
                height="36"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        `

        : `
            <svg
                width="36"
                height="36"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <circle
                    cx="12"
                    cy="12"
                    r="10"
                ></circle>

                <line
                    x1="12"
                    y1="8"
                    x2="12"
                    y2="12"
                ></line>

                <line
                    x1="12"
                    y1="16"
                    x2="12.01"
                    y2="16"
                ></line>
            </svg>
        `;


    const textoBotao =
        isSucesso
            ? 'OK, Concluir'
            : 'Entendido, Corrigir';


    const overlay =
        document.createElement('div');

    overlay.className =
        `modal-overlay ${classeTipo}`;


    overlay.innerHTML = `

        <div class="modal-card">

            <div class="modal-icone">
                ${iconeHtml}
            </div>

            <h3 class="modal-titulo"></h3>

            <p class="modal-texto"></p>

            <button
                type="button"
                class="modal-botao"
                onclick="${acaoClique}"
            >
                ${textoBotao}
            </button>

        </div>
    `;


    overlay.querySelector(
        '.modal-titulo'
    ).textContent = titulo;


    overlay.querySelector(
        '.modal-texto'
    ).textContent = texto;


    overlay.addEventListener(
        'click',
        function (e) {

            if (
                e.target === overlay &&
                !isSucesso
            ) {
                fecharMensagem(false);
            }
        }
    );


    document.body.appendChild(
        overlay
    );


    const btn =
        overlay.querySelector(
            '.modal-botao'
        );

    if (btn) {
        btn.focus();
    }
}


function alterarTipo()
{
    const tipo =
        document.querySelector(
            'input[name="tipo"]:checked'
        ).value;


    const url =
        new URL(
            window.location.href
        );

    url.searchParams.set(
        'tipo',
        tipo
    );

    window.history.replaceState(
        null,
        '',
        url
    );


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
            function (opcao) {

                opcao.hidden = false;
            }
        );


        selectUnidade.value =
            "<?= $idSecretaria ?? '' ?>";

        selectUnidade.disabled = true;

    } else {

        selectUnidade.disabled = false;

        if (selectUnidade.selectedOptions[0]?.dataset.secretaria === 'true') selectUnidade.value = "";


        opcoesUnidade.forEach(
            function (opcao) {

                if (
                    opcao.dataset.secretaria ===
                    'true'
                ) {

                    opcao.hidden = true;

                } else {

                    opcao.hidden = false;
                }
            }
        );
    }


    atualizarProdutos();

    validarTodosProdutos();
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
        function (select) {

            const opcoes =
                select.querySelectorAll(
                    'option'
                );


            opcoes.forEach(
                function (opcao) {

                    if (
                        opcao.value === ''
                    ) {
                        return;
                    }


                    const estoque =
                        parseInt(
                            opcao.dataset.estoque,
                            10
                        );


                    if (
                        tipo === 'Saida'
                    ) {

                        opcao.hidden =
                            estoque <= 0;

                    } else {

                        opcao.hidden =
                            false;
                    }
                }
            );
        }
    );
}


function validarLinha(linhaDiv)
{
    const tipo =
        document.querySelector(
            'input[name="tipo"]:checked'
        )?.value || 'Saida';


    const select =
        linhaDiv.querySelector(
            '.select-produto'
        );

    const inputQtd =
        linhaDiv.querySelector(
            'input[type="number"]'
        );


    let infoDiv =
        linhaDiv.querySelector(
            '.produto-item__info'
        );


    if (!infoDiv) {

        infoDiv =
            document.createElement(
                'div'
            );

        infoDiv.className =
            'produto-item__info';

        linhaDiv.appendChild(
            infoDiv
        );
    }


    linhaDiv.classList.remove(
        'com-erro'
    );

    infoDiv.innerHTML = '';


    if (
        !select ||
        !select.value
    ) {

        if (inputQtd) {
            inputQtd.removeAttribute(
                'max'
            );
        }

        return {
            valido: true
        };
    }


    const selectedOption =
        select.options[
            select.selectedIndex
        ];


    const estoque =
        selectedOption &&
        selectedOption.dataset.estoque !== undefined

        ? parseInt(
            selectedOption.dataset.estoque,
            10
        )

        : null;


    const nomeProd =
        selectedOption
            ? selectedOption.text
                .split('—')[0]
                .trim()
            : 'Produto';


    if (tipo === 'Saida') {

        if (estoque !== null) {

            inputQtd.max =
                estoque;


            const qtdVal =
                inputQtd.value !== ''

                    ? parseInt(
                        inputQtd.value,
                        10
                    )

                    : null;


            if (estoque <= 0) {

                linhaDiv.classList.add(
                    'com-erro'
                );

                infoDiv.innerHTML =
                    `<span class="erro-estoque">
                        ⚠️ Produto sem estoque disponível (0).
                    </span>`;

                return {
                    valido: false,
                    erro:
                        `O produto "${nomeProd}" não possui estoque disponível.`
                };
            }


            if (
                qtdVal !== null &&
                qtdVal > estoque
            ) {

                linhaDiv.classList.add(
                    'com-erro'
                );

                infoDiv.innerHTML =
                    `<span class="erro-estoque">
                        ⚠️ Quantidade (${qtdVal})
                        excede o estoque disponível (${estoque}).
                    </span>`;


                return {
                    valido: false,
                    erro:
                        `Quantidade solicitada (${qtdVal}) excede o estoque disponível (${estoque}) para "${nomeProd}".`
                };
            }


            if (
                qtdVal !== null &&
                qtdVal <= 0
            ) {

                linhaDiv.classList.add(
                    'com-erro'
                );

                infoDiv.innerHTML =
                    `<span class="erro-estoque">
                        ⚠️ A quantidade deve ser no mínimo 1.
                    </span>`;


                return {
                    valido: false,
                    erro:
                        `A quantidade para "${nomeProd}" deve ser maior que zero.`
                };
            }


            infoDiv.innerHTML =
                `<span class="dica-estoque">
                    Estoque disponível:
                    <strong>${estoque}</strong>
                </span>`;

            return {
                valido: true
            };
        }

    } else {

        if (inputQtd) {
            inputQtd.removeAttribute(
                'max'
            );
        }


        const qtdVal =
            inputQtd.value !== ''

                ? parseInt(
                    inputQtd.value,
                    10
                )

                : null;


        if (
            qtdVal !== null &&
            qtdVal <= 0
        ) {

            linhaDiv.classList.add(
                'com-erro'
            );

            infoDiv.innerHTML =
                `<span class="erro-estoque">
                    ⚠️ A quantidade deve ser no mínimo 1.
                </span>`;


            return {
                valido: false,
                erro:
                    `A quantidade para "${nomeProd}" deve ser maior que zero.`
            };
        }


        if (estoque !== null) {

            infoDiv.innerHTML =
                `<span class="dica-estoque">
                    Estoque atual:
                    <strong>${estoque}</strong>
                </span>`;
        }
    }


    return {
        valido: true
    };
}


function validarTodosProdutos()
{
    const tipo =
        document.querySelector(
            'input[name="tipo"]:checked'
        )?.value || 'Saida';


    const linhas =
        document.querySelectorAll(
            '#produtos-container .produto-item'
        );


    let formValido = true;

    let erros = [];


    const somaPorProduto = {};

    const linhasPorProduto = {};


    linhas.forEach(
        function (linha) {

            const res =
                validarLinha(linha);


            if (!res.valido) {

                formValido = false;


                if (
                    res.erro &&
                    !erros.includes(
                        res.erro
                    )
                ) {

                    erros.push(
                        res.erro
                    );
                }
            }


            if (
                tipo === 'Saida'
            ) {

                const select =
                    linha.querySelector(
                        '.select-produto'
                    );

                const inputQtd =
                    linha.querySelector(
                        'input[type="number"]'
                    );


                if (
                    select &&
                    select.value &&
                    inputQtd &&
                    inputQtd.value
                ) {

                    const prodId =
                        select.value;


                    const qtd =
                        parseInt(
                            inputQtd.value,
                            10
                        ) || 0;


                    const selectedOption =
                        select.options[
                            select.selectedIndex
                        ];


                    const estoque =
                        selectedOption &&
                        selectedOption.dataset.estoque !== undefined

                        ? parseInt(
                            selectedOption.dataset.estoque,
                            10
                        )

                        : 0;


                    const nomeProd =
                        selectedOption.text
                            .split('—')[0]
                            .trim();


                    if (
                        !somaPorProduto[prodId]
                    ) {

                        somaPorProduto[prodId] = {
                            total: 0,
                            estoque: estoque,
                            nome: nomeProd
                        };

                        linhasPorProduto[prodId] = [];
                    }


                    somaPorProduto[
                        prodId
                    ].total += qtd;


                    linhasPorProduto[
                        prodId
                    ].push(linha);
                }
            }
        }
    );


    if (
        tipo === 'Saida'
    ) {

        for (
            const prodId in somaPorProduto
        ) {

            const item =
                somaPorProduto[prodId];


            if (
                linhasPorProduto[prodId].length > 1 &&
                item.total > item.estoque
            ) {

                formValido = false;


                const msg =
                    `A soma das linhas para "${item.nome}" (${item.total}) excede o estoque disponível (${item.estoque}).`;


                if (
                    !erros.includes(msg)
                ) {

                    erros.push(msg);
                }


                linhasPorProduto[
                    prodId
                ].forEach(
                    function (linha) {

                        linha.classList.add(
                            'com-erro'
                        );


                        const infoDiv =
                            linha.querySelector(
                                '.produto-item__info'
                            );


                        if (infoDiv) {

                            infoDiv.innerHTML =
                                `<span class="erro-estoque">
                                    ⚠️ Soma das linhas deste produto
                                    (${item.total})
                                    excede o estoque
                                    (${item.estoque}).
                                </span>`;
                        }
                    }
                );
            }
        }
    }


    const modoData =
        document.querySelector(
            'input[name="modo_data"]:checked'
        )?.value;


    const dataHora =
        document.getElementById(
            'data_hora'
        );


    const observacao =
        document.getElementById(
            'observacao'
        );


    if (
        modoData === 'passada'
    ) {

        if (
            !dataHora ||
            dataHora.value === ''
        ) {

            formValido = false;

            erros.push(
                'Informe a data e hora do lançamento.'
            );

        } else {

            if (dataHora.value > obterDataHoraAtual()) {

                formValido = false;

                erros.push(
                    'A data escolhida deve ser anterior ao momento atual.'
                );
            }
        }


        if (
            !observacao ||
            observacao.value.trim() === ''
        ) {

            formValido = false;

            erros.push(
                'A observação é obrigatória quando o lançamento possui uma data anterior.'
            );
        }
    }


    return {
        valido: formValido,
        erros: erros
    };
}

function adicionarProduto()
{
    const container =
        document.getElementById(
            'produtos-container'
        );


    const div =
        document.createElement(
            'div'
        );


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


        <div class="produto-item__info"></div>
    `;


    div.querySelector(
        '.select-produto'
    ).innerHTML =
        document.querySelector(
            '.select-produto'
        ).innerHTML;


    div.querySelector('.select-produto').selectedIndex = 0;
    container.appendChild(
        div
    );


    contadorProdutos++;


    atualizarProdutos();

    validarLinha(div);
}


function removerProduto(botao)
{
    const produtos =
        document.querySelectorAll(
            '.produto-item'
        );


    if (
        produtos.length <= 1
    ) {

        alert(
            'É necessário ter pelo menos um produto.'
        );

        return;
    }


    botao.parentElement.remove();

    validarTodosProdutos();
}


document.addEventListener(
    'wheel',
    function (evento) {

        if (
            evento.target.matches(
                'input[type="number"]'
            )
        ) {

            evento.target.blur();
        }
    }
);


const produtosContainer =
    document.getElementById(
        'produtos-container'
    );


if (produtosContainer) {

    produtosContainer.addEventListener(
        'input',
        function (e) {

            if (
                e.target.matches(
                    'input[type="number"]'
                )
            ) {

                validarTodosProdutos();
            }
        }
    );


    produtosContainer.addEventListener(
        'change',
        function (e) {

            if (
                e.target.matches(
                    '.select-produto'
                ) ||
                e.target.matches(
                    'input[type="number"]'
                )
            ) {

                validarTodosProdutos();
            }
        }
    );
}


const dataHora =
    document.getElementById(
        'data_hora'
    );


const observacao =
    document.getElementById(
        'observacao'
    );


if (dataHora) {

    dataHora.addEventListener(
        'change',
        validarTodosProdutos
    );
}


if (observacao) {

    observacao.addEventListener(
        'input',
        validarTodosProdutos
    );
}


const formLancamento =
    document.getElementById(
        'form-lancamento'
    );


if (formLancamento) {

    formLancamento.addEventListener(
        'submit',
        async function (e) {

            e.preventDefault();


            atualizarLimiteData();


            const validacao =
                validarTodosProdutos();


            if (!validacao.valido) {

                const mensagemErro =
                    validacao.erros.length > 0

                        ? validacao.erros[0]

                        : 'Corrija os campos antes de enviar.';


                exibirMensagem(
                    'erro',
                    'Não foi possível concluir',
                    mensagemErro
                );


                const primeiraLinhaErro =
                    document.querySelector(
                        '.produto-item.com-erro'
                    );


                if (
                    primeiraLinhaErro
                ) {

                    primeiraLinhaErro.scrollIntoView(
                        {
                            behavior: 'smooth',
                            block: 'center'
                        }
                    );
                }


                return;
            }


            const btnSubmit =
                formLancamento.querySelector(
                    'button[type="submit"]'
                );


            const textoOriginal =
                btnSubmit
                    ? btnSubmit.textContent
                    : 'Realizar lançamento';


            if (btnSubmit) {

                btnSubmit.disabled = true;

                btnSubmit.textContent =
                    'Processando lançamento...';
            }


            const formData =
                new FormData(
                    formLancamento
                );


            const selectUnidade =
                document.getElementById(
                    'unidade_destino'
                );


            if (
                selectUnidade &&
                selectUnidade.value
            ) {

                formData.set(
                    'unidade_destino',
                    selectUnidade.value
                );
            }


            formData.append(
                'ajax',
                '1'
            );


            try {

                const resposta =
                    await fetch(
                        window.location.href,
                        {
                            method: 'POST',

                            headers: {
                                'X-Requested-With':
                                    'XMLHttpRequest'
                            },

                            body: formData
                        }
                    );


                const textoResposta =
                    await resposta.text();


                let dados;


                try {

                    dados =
                        JSON.parse(
                            textoResposta
                        );

                } catch (parseErr) {

                    console.error(
                        'Resposta inválida do servidor:',
                        textoResposta
                    );

                    throw new Error(
                        'Resposta inesperada do servidor ao processar o lançamento.'
                    );
                }


                if (
                    dados.sucesso
                ) {

                    tipoLancamentoConcluido =
                        formData.get(
                            'tipo'
                        );


                    exibirMensagem(
                        'sucesso',
                        'Sucesso!',
                        dados.mensagem ||
                        'Lançamento realizado com sucesso!'
                    );

                } else {

                    exibirMensagem(
                        'erro',
                        'Não foi possível concluir',
                        dados.mensagem ||
                        'Erro ao realizar lançamento.'
                    );
                }


                if (btnSubmit) {

                    btnSubmit.disabled =
                        false;

                    btnSubmit.textContent =
                        textoOriginal;
                }

            } catch (erro) {

                exibirMensagem(
                    'erro',
                    'Erro de comunicação',
                    erro.message ||
                    'Falha ao comunicar com o servidor.'
                );


                if (btnSubmit) {

                    btnSubmit.disabled =
                        false;

                    btnSubmit.textContent =
                        textoOriginal;
                }
            }
        }
    );
}


atualizarLimiteData();

alterarModoData();

alterarTipo();

</script>


</body>

</html>