<?php

require_once '../script/sessao.php';
require_once '../script/conexao.php';
require_once '../script/funcoes_produtos.php';
require_once '../script/sidebar.php';

verificarSessao();
verificarTipo(['Administrador']);

$mensagem = '';
$tipoMensagem = '';

if (!isset($_GET['id'])) {
    header('Location: produtos.php');
    exit;
}

$idProduto = (int) $_GET['id'];
$produto = buscarProdutoPorId($conn, $idProduto);

if (!$produto) {
    die('Produto não encontrado.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $categoriaId = (int) ($_POST['categoria_id'] ?? 0);
    $unidade = trim($_POST['unidade'] ?? '');
    $estoque = trim($_POST['estoque'] ?? '0');
    $estoqueMinimo = trim($_POST['estoque_minimo'] ?? '0');

    $resultado = atualizarProduto($conn, $idProduto, $nome, $categoriaId, $unidade, $estoque, $estoqueMinimo);

    if ($resultado['sucesso']) {
        header('Location: produtos.php?mensagem=' . urlencode($resultado['mensagem']));
        exit;
    }

    $mensagem = $resultado['mensagem'];
    $tipoMensagem = 'erro';
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
    <title>Editar produto</title>
</head>

<body>

    <?php sidebar('produtos'); ?>

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
                <a class="nav__item" href="lancamentos.php">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="m17 10-5 5-5-5"/><path d="M4 21h16"/></svg>
                    Entrada
                </a>
                <a class="nav__item" href="lancamentos.php">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21V9"/><path d="m7 14 5-5 5 5"/><path d="M4 3h16"/></svg>
                    Saída
                </a>
                <a class="nav__item" href="produtos.php" aria-current="page">
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
            <h1 class="cabecalho-pagina__titulo">Editar produto</h1>
            <p class="cabecalho-pagina__descricao">Atualize os dados do item e o limite mínimo de estoque.</p>
        </div>

        <?php if ($mensagem !== ''): ?>
            <div class="mensagem-modal mensagem-erro">
                <div class="mensagem-conteudo">
                    <strong>Atenção!</strong>
                    <p><?= htmlspecialchars($mensagem) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <section class="cartao">
            <form method="POST" class="formulario">
                <div class="campo campo--largo">
                    <label class="campo__rotulo" for="nome">Nome do produto</label>
                    <input class="campo__controle" type="text" id="nome" name="nome" value="<?= htmlspecialchars($produto['nome']) ?>" required>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="categoria_id">Categoria</label>
                    <select class="campo__controle" id="categoria_id" name="categoria_id" required>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?= (int) $categoria['id_categoria'] ?>"
                                <?= (int) $produto['categoria_id'] === (int) $categoria['id_categoria'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($categoria['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="unidade">Unidade</label>
                    <select class="campo__controle" id="unidade" name="unidade" required>
                        <?php foreach ($unidades as $itemUnidade): ?>
                            <option value="<?= htmlspecialchars($itemUnidade['unidade']) ?>"
                                <?= $produto['unidade'] === $itemUnidade['unidade'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($itemUnidade['unidade']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="estoque">Estoque atual</label>
                    <input class="campo__controle" type="number" id="estoque" name="estoque" min="0" step="1" value="<?= (int) $produto['estoque'] ?>" required>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="estoque_minimo">Estoque mínimo</label>
                    <input class="campo__controle" type="number" id="estoque_minimo" name="estoque_minimo" min="0" step="1" value="<?= (int) $produto['estoque_minimo'] ?>" required>
                </div>

                <div class="campo campo--largo" style="display:flex; gap:12px; margin-top:12px;">
                    <button class="botao botao--primario" type="submit">Salvar alterações</button>
                    <a class="botao botao--secundario" href="produtos.php">Cancelar</a>
                </div>
            </form>
        </section>
    </main>

</body>
</html>
