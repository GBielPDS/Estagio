<?php

declare(strict_types=1);

require_once "../script/sessao.php";
require_once "../script/conexao.php";
require_once "../script/funcoes_lancamentos.php";
require_once "../script/sidebar.php";

verificarSessao();

$tipoSelecionado = (string) ($_GET['tipo'] ?? 'Entrada');

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

if ($resultadoSecretaria && $resultadoSecretaria->num_rows > 0) {
    $secretaria = $resultadoSecretaria->fetch_assoc();
    $idSecretaria = (int) $secretaria['id_unidade'];
} else {
    $idSecretaria = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = (string) ($_POST['tipo'] ?? '');
    $observacao = trim((string) ($_POST['observacao'] ?? ''));
    $usuario_id = (int) $_SESSION['id_usuario'];
    $produtosRecebidos = (array) ($_POST['produtos'] ?? []);
    $produtos = [];

    foreach ($produtosRecebidos as $produto) {
        if (!is_array($produto) || !isset($produto['produto_id'], $produto['quantidade'])) {
            continue;
        }

        $produto_id = (int) $produto['produto_id'];
        $quantidade = (int) $produto['quantidade'];

        if ($produto_id > 0 && $quantidade > 0) {
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
                $mensagem = "A Secretaria de Saúde não está cadastrada.";
                $tipoMensagem = "erro";
            } else {
                $resultado = lancamentoEntrada(
                    $conn,
                    $produtos,
                    $idSecretaria,
                    $observacao,
                    $usuario_id
                );

                if (!empty($resultado['sucesso'])) {
                    $mensagem = 'Entrada realizada com sucesso!';
                    $tipoMensagem = 'sucesso';
                } else {
                    $mensagem = (string) ($resultado['mensagem'] ?? "Erro ao realizar a entrada.");
                    $tipoMensagem = "erro";
                }
            }
        } elseif ($tipo === 'Saida') {
            $unidadeDestino = (int) ($_POST['unidade_destino'] ?? 0);

            if ($unidadeDestino <= 0) {
                $mensagem = "Selecione uma unidade de saúde.";
                $tipoMensagem = "erro";
            } else {
                $resultado = lancamentoSaida(
                    $conn,
                    $produtos,
                    $unidadeDestino,
                    $observacao,
                    $usuario_id
                );

                if (!empty($resultado['sucesso'])) {
                    $mensagem = 'Saída realizada com sucesso!';
                    $tipoMensagem = 'sucesso';
                } else {
                    $mensagem = (string) ($resultado['mensagem'] ?? "Erro ao realizar a saída. Verifique o estoque dos produtos.");
                    $tipoMensagem = "erro";
                }
            }
        } else {
            $mensagem = "Tipo de lançamento inválido.";
            $tipoMensagem = "erro";
        }
    }

    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || isset($_POST['ajax']);

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'sucesso' => $tipoMensagem === 'sucesso',
            'mensagem' => $mensagem
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($tipoMensagem === 'sucesso') {
        $_SESSION['mensagem_lancamento'] = [
            'texto' => $mensagem,
            'tipo' => 'sucesso'
        ];
        header('Location: lancamentos.php?tipo=' . urlencode($tipo));
        exit();
    }
}

$sqlProdutos = "SELECT id_produto, nome, unidade, estoque FROM produto ORDER BY nome";
$resultadoProdutos = $conn->query($sqlProdutos);

$produtosCatalogo = [];
if ($resultadoProdutos) {
    while ($p = $resultadoProdutos->fetch_assoc()) {
        $produtosCatalogo[] = $p;
    }
}

$sqlUnidades = "SELECT id_unidade, nome
                FROM unidade_saude
                WHERE ativo = TRUE
                AND id_unidade != ?
                ORDER BY nome";

$stmtUnidades = $conn->prepare($sqlUnidades);
$secretariaFiltroId = (int) ($idSecretaria ?? 0);
$stmtUnidades->bind_param("i", $secretariaFiltroId);
$stmtUnidades->execute();
$resultadoUnidades = $stmtUnidades->get_result();

$unidadesLista = [];
if ($resultadoUnidades) {
    while ($u = $resultadoUnidades->fetch_assoc()) {
        $unidadesLista[] = $u;
    }
}
$stmtUnidades->close();
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style.css">
    <title>Lançamentos</title>
</head>

<body>

<?php sidebar($tipoSelecionado === 'Saida' ? 'saida' : 'lancamentos'); ?>

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
        <div class="modal-overlay <?= $tipoMensagem === 'sucesso' ? 'modal-overlay--sucesso' : 'modal-overlay--erro' ?>">
            <div class="modal-card">
                <div class="modal-icone">
                    <?php if ($tipoMensagem === 'sucesso'): ?>
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    <?php else: ?>
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    <?php endif; ?>
                </div>
                <h3 class="modal-titulo"><?= $tipoMensagem === 'sucesso' ? 'Sucesso!' : 'Atenção' ?></h3>
                <p class="modal-texto"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></p>
                <button type="button" class="modal-botao" onclick="fecharMensagem(<?= $tipoMensagem === 'sucesso' ? 'true' : 'false' ?>)">
                    <?= $tipoMensagem === 'sucesso' ? 'OK, Concluir' : 'Entendido, Corrigir' ?>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <section class="dashboard">

        <h2>Novo lançamento</h2>

        <form method="POST" id="form-lancamento">

            <div class="campo">
                <label>Tipo de lançamento:</label>
                <br>
                <label>
                    <input
                        type="radio"
                        name="tipo"
                        value="Entrada"
                        <?= $tipoSelecionado === 'Entrada' ? 'checked' : '' ?>
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
                        <?= $tipoSelecionado === 'Saida' ? 'checked' : '' ?>
                        onchange="alterarTipo()"
                    >
                    Saída
                </label>
            </div>

            <br>

            <div class="campo">
                <label for="unidade_destino">Unidade de destino:</label>
                <select name="unidade_destino" id="unidade_destino" required>
                    <option value="<?= $idSecretaria ?? '' ?>" data-secretaria="true">
                        Secretaria de Saúde
                    </option>
                    <?php foreach ($unidadesLista as $unidade): ?>
                        <option value="<?= (int) $unidade['id_unidade'] ?>" data-secretaria="false">
                            <?= htmlspecialchars((string) $unidade['nome'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <br>

            <h3>Produtos</h3>

            <div id="produtos-container">
                <div class="produto-item">
                    <select name="produtos[0][produto_id]" class="select-produto" required>
                        <option value="">Selecione um produto</option>
                        <?php foreach ($produtosCatalogo as $produto): ?>
                            <option
                                value="<?= (int) $produto['id_produto'] ?>"
                                data-estoque="<?= (int) $produto['estoque'] ?>"
                            >
                                <?= htmlspecialchars((string) $produto['nome'], ENT_QUOTES, 'UTF-8') ?>
                                — Estoque: <?= (int) $produto['estoque'] ?> <?= htmlspecialchars((string) $produto['unidade'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
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

                    <div class="produto-item__info"></div>
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

            <label for="observacao">Observação:</label>
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

function fecharMensagem(recarregar = false)
{
    const overlay = document.querySelector('.modal-overlay');
    if (overlay) {
        overlay.remove();
    }
    const inlineMsg = document.querySelector('.mensagem-modal');
    if (inlineMsg) {
        inlineMsg.remove();
    }
    if (recarregar) {
        window.location.href = 'lancamentos.php';
    }
}

function exibirMensagem(tipo, titulo, texto)
{
    fecharMensagem(false);

    const isSucesso = tipo === 'sucesso';
    const acaoClique = isSucesso ? 'fecharMensagem(true)' : 'fecharMensagem(false)';
    const classeTipo = isSucesso ? 'modal-overlay--sucesso' : 'modal-overlay--erro';

    const iconeHtml = isSucesso
        ? `<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
             <polyline points="20 6 9 17 4 12"></polyline>
           </svg>`
        : `<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
             <circle cx="12" cy="12" r="10"></circle>
             <line x1="12" y1="8" x2="12" y2="12"></line>
             <line x1="12" y1="16" x2="12.01" y2="16"></line>
           </svg>`

    const textoBotao = isSucesso ? 'OK, Concluir' : 'Entendido, Corrigir';

    const overlay = document.createElement('div');
    overlay.className = `modal-overlay ${classeTipo}`;
    overlay.innerHTML = `
        <div class="modal-card">
            <div class="modal-icone">
                ${iconeHtml}
            </div>
            <h3 class="modal-titulo">${titulo}</h3>
            <p class="modal-texto">${texto}</p>
            <button type="button" class="modal-botao" onclick="${acaoClique}">
                ${textoBotao}
            </button>
        </div>
    `;

    overlay.addEventListener('click', function(e) {
        if (e.target === overlay && !isSucesso) {
            fecharMensagem(false);
        }
    });

    document.body.appendChild(overlay);

    const btn = overlay.querySelector('.modal-botao');
    if (btn) {
        btn.focus();
    }
}

function alterarTipo()
{
    const tipo = document.querySelector('input[name="tipo"]:checked').value;
    const selectUnidade = document.getElementById('unidade_destino');
    const opcoesUnidade = selectUnidade.querySelectorAll('option');

    if (tipo === 'Entrada') {
        opcoesUnidade.forEach(function(opcao) {
            opcao.hidden = false;
        });

        selectUnidade.value = "<?= $idSecretaria ?? '' ?>";
        selectUnidade.disabled = true;
    } else {
        selectUnidade.disabled = false;
        selectUnidade.value = "";

        opcoesUnidade.forEach(function(opcao) {
            if (opcao.dataset.secretaria === 'true') {
                opcao.hidden = true;
            } else {
                opcao.hidden = false;
            }
        });
    }

    atualizarProdutos();
    validarTodosProdutos();
}

function atualizarProdutos()
{
    const tipo = document.querySelector('input[name="tipo"]:checked').value;
    const selects = document.querySelectorAll('.select-produto');

    selects.forEach(function(select) {
        const opcoes = select.querySelectorAll('option');

        opcoes.forEach(function(opcao) {
            if (opcao.value === '') {
                return;
            }

            const estoque = parseInt(opcao.dataset.estoque, 10);

            if (tipo === 'Saida') {
                if (estoque <= 0) {
                    opcao.hidden = true;
                } else {
                    opcao.hidden = false;
                }
            } else {
                opcao.hidden = false;
            }
        });
    });
}

function validarLinha(linhaDiv)
{
    const tipo = document.querySelector('input[name="tipo"]:checked')
        ? document.querySelector('input[name="tipo"]:checked').value
        : 'Saida';
    const select = linhaDiv.querySelector('.select-produto');
    const inputQtd = linhaDiv.querySelector('input[type="number"]');
    let infoDiv = linhaDiv.querySelector('.produto-item__info');

    if (!infoDiv) {
        infoDiv = document.createElement('div');
        infoDiv.className = 'produto-item__info';
        linhaDiv.appendChild(infoDiv);
    }

    linhaDiv.classList.remove('com-erro');
    infoDiv.innerHTML = '';

    if (!select || !select.value) {
        if (inputQtd) inputQtd.removeAttribute('max');
        return { valido: true };
    }

    const selectedOption = select.options[select.selectedIndex];
    const estoque = selectedOption && selectedOption.dataset.estoque !== undefined
        ? parseInt(selectedOption.dataset.estoque, 10)
        : null;

    const nomeProd = selectedOption ? selectedOption.text.split('—')[0].trim() : 'Produto';

    if (tipo === 'Saida') {
        if (estoque !== null) {
            inputQtd.max = estoque;
            const qtdVal = inputQtd.value !== '' ? parseInt(inputQtd.value, 10) : null;

            if (estoque <= 0) {
                linhaDiv.classList.add('com-erro');
                infoDiv.innerHTML = `<span class="erro-estoque">⚠️ Produto sem estoque disponível (0).</span>`;
                return { valido: false, erro: `O produto "${nomeProd}" não possui estoque disponível.` };
            }

            if (qtdVal !== null && qtdVal > estoque) {
                linhaDiv.classList.add('com-erro');
                infoDiv.innerHTML = `<span class="erro-estoque">⚠️ Quantidade (${qtdVal}) excede o estoque disponível (${estoque}).</span>`;
                return { valido: false, erro: `Quantidade solicitada (${qtdVal}) excede o estoque disponível (${estoque}) para "${nomeProd}".` };
            } else if (qtdVal !== null && qtdVal <= 0) {
                linhaDiv.classList.add('com-erro');
                infoDiv.innerHTML = `<span class="erro-estoque">⚠️ A quantidade deve ser no mínimo 1.</span>`;
                return { valido: false, erro: `A quantidade para "${nomeProd}" deve ser maior que zero.` };
            } else {
                infoDiv.innerHTML = `<span class="dica-estoque">Estoque disponível: <strong>${estoque}</strong></span>`;
                return { valido: true };
            }
        }
    } else {
        if (inputQtd) inputQtd.removeAttribute('max');
        const qtdVal = inputQtd.value !== '' ? parseInt(inputQtd.value, 10) : null;
        if (qtdVal !== null && qtdVal <= 0) {
            linhaDiv.classList.add('com-erro');
            infoDiv.innerHTML = `<span class="erro-estoque">⚠️ A quantidade deve ser no mínimo 1.</span>`;
            return { valido: false, erro: `A quantidade para "${nomeProd}" deve ser maior que zero.` };
        }
        if (estoque !== null) {
            infoDiv.innerHTML = `<span class="dica-estoque">Estoque atual: <strong>${estoque}</strong></span>`;
        }
    }

    return { valido: true };
}

function validarTodosProdutos()
{
    const tipo = document.querySelector('input[name="tipo"]:checked')
        ? document.querySelector('input[name="tipo"]:checked').value
        : 'Saida';
    const linhas = document.querySelectorAll('#produtos-container .produto-item');
    let formValido = true;
    let erros = [];

    const somaPorProduto = {};
    const linhasPorProduto = {};

    linhas.forEach(function(linha) {
        const res = validarLinha(linha);
        if (!res.valido) {
            formValido = false;
            if (res.erro && !erros.includes(res.erro)) {
                erros.push(res.erro);
            }
        }

        if (tipo === 'Saida') {
            const select = linha.querySelector('.select-produto');
            const inputQtd = linha.querySelector('input[type="number"]');
            if (select && select.value && inputQtd && inputQtd.value) {
                const prodId = select.value;
                const qtd = parseInt(inputQtd.value, 10) || 0;
                const selectedOption = select.options[select.selectedIndex];
                const estoque = selectedOption && selectedOption.dataset.estoque !== undefined
                    ? parseInt(selectedOption.dataset.estoque, 10)
                    : 0;
                const nomeProd = selectedOption.text.split('—')[0].trim();

                if (!somaPorProduto[prodId]) {
                    somaPorProduto[prodId] = { total: 0, estoque: estoque, nome: nomeProd };
                    linhasPorProduto[prodId] = [];
                }
                somaPorProduto[prodId].total += qtd;
                linhasPorProduto[prodId].push(linha);
            }
        }
    });

    if (tipo === 'Saida') {
        for (const prodId in somaPorProduto) {
            const item = somaPorProduto[prodId];
            if (linhasPorProduto[prodId].length > 1 && item.total > item.estoque) {
                formValido = false;
                const msg = `A soma das linhas para "${item.nome}" (${item.total}) excede o estoque disponível (${item.estoque}).`;
                if (!erros.includes(msg)) {
                    erros.push(msg);
                }

                linhasPorProduto[prodId].forEach(function(linha) {
                    linha.classList.add('com-erro');
                    const infoDiv = linha.querySelector('.produto-item__info');
                    if (infoDiv) {
                        infoDiv.innerHTML = `<span class="erro-estoque">⚠️ Soma das linhas deste produto (${item.total}) excede o estoque (${item.estoque}).</span>`;
                    }
                });
            }
        }
    }

    return { valido: formValido, erros: erros };
}

function adicionarProduto()
{
    const container = document.getElementById('produtos-container');
    const div = document.createElement('div');
    div.classList.add('produto-item');

    div.innerHTML = `
        <select
            name="produtos[${contadorProdutos}][produto_id]"
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
                >
                    <?= htmlspecialchars((string) $produto['nome'], ENT_QUOTES, 'UTF-8') ?>
                    — Estoque: <?= (int) $produto['estoque'] ?> <?= htmlspecialchars((string) $produto['unidade'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
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

    container.appendChild(div);
    contadorProdutos++;

    atualizarProdutos();
    validarLinha(div);
}

function removerProduto(botao)
{
    const produtos = document.querySelectorAll('.produto-item');

    if (produtos.length <= 1) {
        alert('É necessário ter pelo menos um produto.');
        return;
    }

    botao.parentElement.remove();
    validarTodosProdutos();
}

document.addEventListener('wheel', function(evento) {
    if (evento.target.matches('input[type="number"]')) {
        evento.target.blur();
    }
});

const produtosContainer = document.getElementById('produtos-container');
if (produtosContainer) {
    produtosContainer.addEventListener('input', function(e) {
        if (e.target.matches('input[type="number"]')) {
            validarTodosProdutos();
        }
    });

    produtosContainer.addEventListener('change', function(e) {
        if (e.target.matches('.select-produto') || e.target.matches('input[type="number"]')) {
            validarTodosProdutos();
        }
    });
}

const formLancamento = document.getElementById('form-lancamento');
if (formLancamento) {
    formLancamento.addEventListener('submit', async function(e) {
        e.preventDefault();

        const validacao = validarTodosProdutos();
        if (!validacao.valido) {
            const mensagemErro = validacao.erros.length > 0
                ? validacao.erros[0]
                : 'Corrija os produtos com erro de quantidade antes de enviar.';
            exibirMensagem('erro', 'Não foi possível concluir', mensagemErro);

            const primeiraLinhaErro = document.querySelector('.produto-item.com-erro');
            if (primeiraLinhaErro) {
                primeiraLinhaErro.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }

        const btnSubmit = formLancamento.querySelector('button[type="submit"]');
        const textoOriginal = btnSubmit ? btnSubmit.textContent : 'Realizar lançamento';
        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.textContent = 'Processando lançamento...';
        }

        const formData = new FormData(formLancamento);
        const selectUnidade = document.getElementById('unidade_destino');
        if (selectUnidade && selectUnidade.value) {
            formData.set('unidade_destino', selectUnidade.value);
        }
        formData.append('ajax', '1');

        try {
            const resposta = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const textoResposta = await resposta.text();
            let dados;
            try {
                dados = JSON.parse(textoResposta);
            } catch (parseErr) {
                console.error("Resposta inválida do servidor:", textoResposta);
                throw new Error("Resposta inesperada do servidor ao processar o lançamento.");
            }

            if (dados.sucesso) {
                exibirMensagem('sucesso', 'Sucesso!', dados.mensagem || 'Lançamento realizado com sucesso!');
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = textoOriginal;
                }
            } else {
                exibirMensagem('erro', 'Não foi possível concluir', dados.mensagem || 'Erro ao realizar lançamento.');
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = textoOriginal;
                }
            }
        } catch (erro) {
            exibirMensagem('erro', 'Erro de comunicação', erro.message || 'Falha ao comunicar com o servidor.');
            if (btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.textContent = textoOriginal;
            }
        }
    });
}

alterarTipo();
</script>

</body>
</html>