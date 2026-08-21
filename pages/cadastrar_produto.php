<?php

require_once '../script/sessao.php';
require_once '../script/conexao.php';
require_once '../script/funcoes_produtos.php';
require_once '../script/sidebar.php';

verificarSessao();

$mensagem = '';
$tipoMensagem = '';
$nome = '';
$unidade = '';
$estoque = '';
$estoqueMinimo = '';
$categoriaSelecionada = '';
$unidadeSelecionada = '';
$novaCategoria = '';
$novaUnidade = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $categoriaSelecionada = $_POST['categoria_id'] ?? '';
    $novaCategoria = trim($_POST['nova_categoria'] ?? '');
    $unidadeSelecionada = trim($_POST['unidade'] ?? '');
    $novaUnidade = trim($_POST['nova_unidade'] ?? '');
    $estoque = trim($_POST['estoque'] ?? '');
    $estoqueMinimo = trim($_POST['estoque_minimo'] ?? '');

    if ($categoriaSelecionada === '__nova__') {
        $categoriaSelecionada = '';
    }

    if ($unidadeSelecionada === '__nova__') {
        $unidadeSelecionada = '';
    }

    if ($nome === '') {
        $mensagem = 'Informe o nome do produto.';
    } elseif ($categoriaSelecionada !== '' && $novaCategoria !== '') {
        $mensagem = 'Escolha uma categoria existente ou informe uma nova categoria, não as duas.';
    } elseif ($categoriaSelecionada === '' && $novaCategoria === '') {
        $mensagem = 'Escolha uma categoria ou informe uma nova categoria.';
    } elseif ($unidadeSelecionada !== '' && $novaUnidade !== '') {
        $mensagem = 'Escolha uma unidade existente ou informe uma nova unidade, não as duas.';
    } elseif ($unidadeSelecionada === '' && $novaUnidade === '') {
        $mensagem = 'Escolha uma unidade ou informe uma nova unidade.';
    } elseif ($novaUnidade !== '' && unidadeJaExiste($conn, $novaUnidade)) {
        $mensagem = 'Esta unidade já está cadastrada. Selecione-a na lista.';
    } elseif (($estoque !== '' && filter_var($estoque, FILTER_VALIDATE_INT) === false)
        || ($estoqueMinimo !== '' && filter_var($estoqueMinimo, FILTER_VALIDATE_INT) === false)) {
        $mensagem = 'Estoque e estoque mínimo devem ser números inteiros.';
    } elseif ((int) $estoque < 0 || (int) $estoqueMinimo < 0) {
        $mensagem = 'Estoque e estoque mínimo não podem ser negativos.';
    } else {
        $estoque = $estoque === '' ? 0 : (int) $estoque;
        $estoqueMinimo = $estoqueMinimo === '' ? 0 : (int) $estoqueMinimo;
        $transacaoIniciada = false;

        if ($novaCategoria !== '') {
            $conn->begin_transaction();
            $transacaoIniciada = true;

            $resultadoCategoria = criarCategoria($conn, $novaCategoria);

            if (!$resultadoCategoria['sucesso']) {
                $conn->rollback();
                $mensagem = $resultadoCategoria['mensagem'];
            } else {
                $categoriaId = $resultadoCategoria['id'];
            }
        } else {
            $categoriaId = (int) $categoriaSelecionada;
        }

        if ($mensagem === '') {
            $unidade = $novaUnidade !== '' ? $novaUnidade : $unidadeSelecionada;
            $resultadoProduto = cadastrarProduto(
                $conn,
                $nome,
                $categoriaId,
                $unidade,
                $estoque,
                $estoqueMinimo
            );

            if (!$resultadoProduto['sucesso']) {
                if ($transacaoIniciada) {
                    $conn->rollback();
                }
                $mensagem = $resultadoProduto['mensagem'];
            } else {
                if ($transacaoIniciada) {
                    $conn->commit();
                }
                $mensagem = 'Produto cadastrado com sucesso.';
                $tipoMensagem = 'sucesso';
                $nome = '';
                $categoriaSelecionada = '';
                $unidadeSelecionada = '';
                $novaCategoria = '';
                $novaUnidade = '';
                $estoque = '';
                $estoqueMinimo = '';
            }
        }
    }
}

$categorias = buscarCategorias($conn);
$unidades = buscarUnidades($conn);

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style.css">
    <title>Cadastrar produto</title>
</head>

<body>

    <?php sidebar('cadastrar-produto'); ?>

    <main class="conteudo">
        <h1>Cadastrar produto</h1>

        <?php if ($mensagem !== ''): ?>
            <p class="mensagem <?= htmlspecialchars($tipoMensagem) ?>">
                <?= htmlspecialchars($mensagem) ?>
            </p>
        <?php endif; ?>

        <form method="POST">
            <label for="nome">Nome do produto:</label>
            <input type="text" id="nome" name="nome" maxlength="100"
                value="<?= htmlspecialchars($nome) ?>" required>

            <label for="categoria_id">Categoria existente:</label>
            <select id="categoria_id" name="categoria_id">
                <option value="">Selecione uma categoria</option>
                <?php foreach ($categorias as $categoria): ?>
                    <option value="<?= $categoria['id_categoria'] ?>"
                        <?= (string) $categoriaSelecionada === (string) $categoria['id_categoria'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($categoria['nome']) ?>
                    </option>
                <?php endforeach; ?>
                <option value="__nova__" <?= $novaCategoria !== '' ? 'selected' : '' ?>>
                    Criar nova categoria...
                </option>
            </select>

            <div id="campo_nova_categoria">
                <label for="nova_categoria">Nome da nova categoria:</label>
                <input type="text" id="nova_categoria" name="nova_categoria" maxlength="100"
                value="<?= htmlspecialchars($novaCategoria) ?>">
            </div>

            <label for="unidade">Unidade existente:</label>
            <select id="unidade" name="unidade">
                <option value="">Selecione uma unidade</option>
                <?php foreach ($unidades as $itemUnidade): ?>
                    <option value="<?= htmlspecialchars($itemUnidade['unidade']) ?>"
                        <?= $unidadeSelecionada === $itemUnidade['unidade'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($itemUnidade['unidade']) ?>
                    </option>
                <?php endforeach; ?>
                <option value="__nova__" <?= $novaUnidade !== '' ? 'selected' : '' ?>>
                    Criar nova unidade...
                </option>
            </select>

            <div id="campo_nova_unidade">
                <label for="nova_unidade">Nome da nova unidade:</label>
                <input type="text" id="nova_unidade" name="nova_unidade" maxlength="20"
                    value="<?= htmlspecialchars($novaUnidade) ?>">
            </div>

            <label for="estoque">Estoque inicial:</label>
            <input type="number" id="estoque" name="estoque" min="0" step="1"
                value="<?= htmlspecialchars($estoque) ?>" placeholder="Deixe vazio para 0">

            <label for="estoque_minimo">Estoque mínimo:</label>
            <input type="number" id="estoque_minimo" name="estoque_minimo" min="0" step="1"
                value="<?= htmlspecialchars($estoqueMinimo) ?>" placeholder="Deixe vazio para 0">

            <button type="submit">Cadastrar produto</button>
        </form>
    </main>

    <script>
        const categoriaSelect = document.getElementById('categoria_id');
        const unidadeSelect = document.getElementById('unidade');
        const campoNovaCategoria = document.getElementById('campo_nova_categoria');
        const campoNovaUnidade = document.getElementById('campo_nova_unidade');
        const novaCategoria = document.getElementById('nova_categoria');
        const novaUnidade = document.getElementById('nova_unidade');

        function atualizarCampoNovo(select, campo, input) {
            const exibir = select.value === '__nova__';
            campo.hidden = !exibir;
            input.required = exibir;

            if (!exibir) {
                input.value = '';
            }
        }

        categoriaSelect.addEventListener('change', () => {
            atualizarCampoNovo(categoriaSelect, campoNovaCategoria, novaCategoria);
        });

        unidadeSelect.addEventListener('change', () => {
            atualizarCampoNovo(unidadeSelect, campoNovaUnidade, novaUnidade);
        });

        atualizarCampoNovo(categoriaSelect, campoNovaCategoria, novaCategoria);
        atualizarCampoNovo(unidadeSelect, campoNovaUnidade, novaUnidade);
    </script>

    <?php if ($tipoMensagem === 'sucesso'): ?>
        <script>
            alert('Novo cadastro realizado com sucesso!');
        </script>
    <?php endif; ?>

</body>

</html>
