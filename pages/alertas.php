<?php

require_once '../script/sessao.php';
require_once '../script/conexao.php';
require_once '../script/funcoes_estoque.php';
require_once '../script/sidebar.php';

verificarSessao();

$alertas = buscarAlertasEstoque($conn);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style.css">
    <title>Alertas de estoque</title>
</head>

<body>

    <?php sidebar('alertas'); ?>

    <header class="topo">
        <div class="topo__interno">
            <a class="marca" href="../index.php">
                <span class="marca__texto">GEST<span>SAÚDE</span></span>
            </a>
            <nav class="nav" aria-label="Módulos do estoque">
                <a class="nav__item" href="../index.php">Início</a>
                <a class="nav__item" href="lancamentos.php">Lançamentos</a>
                <a class="nav__item" href="produtos.php">Produtos</a>
                <a class="nav__item" href="estoque.php">Estoque</a>
                <a class="nav__item" href="alertas.php" aria-current="page">Alertas</a>
                <div class="user-menu">
                    <img src="https://ui-avatars.com/api/?name=V+W&background=e2e8f0&color=64748b&size=150" alt="Perfil" class="avatar">
                    <div class="dropdown-content">
                        <a href="perfil.php">Meu Perfil</a>
                        <a href="login.php" style="color: #ef4444;">Sair</a>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <main class="conteudo">
        <div class="cabecalho-pagina">
            <h1 class="cabecalho-pagina__titulo">Alertas de estoque</h1>
            <p class="cabecalho-pagina__descricao">Consulte os produtos vazios ou abaixo do estoque mínimo.</p>
        </div>

        <?php if (empty($alertas)): ?>
            <section class="resumo-alertas resumo-alertas--regular">
                <div>
                    <span class="resumo-alertas__rotulo">Tudo certo</span>
                    <h2>Estoque regular</h2>
                    <p>Nenhum produto está vazio ou abaixo do estoque mínimo.</p>
                </div>
            </section>
        <?php else: ?>
            <section class="cartao">
                <div class="lista-alertas">
                    <?php foreach ($alertas as $alerta): ?>
                        <article class="alerta-item alerta-item--<?= htmlspecialchars($alerta['situacao']) ?>">
                            <div>
                                <div class="alerta-item__produto">
                                    <?= htmlspecialchars($alerta['nome']) ?>
                                </div>
                                <div class="alerta-item__detalhe">
                                    <?= htmlspecialchars($alerta['categoria']) ?> · <?= htmlspecialchars($alerta['unidade']) ?>
                                    · Estoque atual: <?= (int) $alerta['estoque'] ?>
                                    · Mínimo: <?= (int) $alerta['estoque_minimo'] ?>
                                </div>
                            </div>
                            <div class="alerta-item__situacao">
                                <?php if ($alerta['situacao'] === 'vazio'): ?>
                                    Estoque vazio
                                    <?php if ((int) $alerta['quantidade_faltante'] > 0): ?>
                                        <br>Faltam <?= (int) $alerta['quantidade_faltante'] ?> unidade(s)
                                    <?php endif; ?>
                                <?php else: ?>
                                    Abaixo do mínimo<br>
                                    Faltam <?= (int) $alerta['quantidade_faltante'] ?> unidade(s)
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>

</body>
</html>
