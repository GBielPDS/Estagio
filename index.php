<?php

require_once 'script/sessao.php';
require_once 'script/conexao.php';
require_once 'script/funcoes_estoque.php';
require 'script/sidebar.php';

verificarSessao();

$alertasEstoque = buscarAlertasEstoque($conn);
$produtosVazios = count(array_filter($alertasEstoque, function ($alerta) {
  return $alerta['situacao'] === 'vazio';
}));
$produtosAbaixoMinimo = count($alertasEstoque) - $produtosVazios;

?>


<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Controle de Estoque · GestSaúde</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>

<body>

   <?php sidebar('home'); ?>


    <header class="topo">
    <div class="topo__interno">
      <a class="marca" href="index.php">
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
        <a class="nav__item" href="pages/cadastrar_produto.php">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
          Cadastro
        </a>
        <a class="nav__item" href="pages/lancamentos.php">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="m17 10-5 5-5-5"/><path d="M4 21h16"/></svg>
          Entrada
        </a>
        <a class="nav__item" href="pages/lancamentos.php">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21V9"/><path d="m7 14 5-5 5 5"/><path d="M4 3h16"/></svg>
          Saída
        </a>
        <a class="nav__item" href="pages/produtos.php">
           <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="M3.29 7 12 12l8.71-5"/><path d="M12 22V12"/></svg>
          Produtos
        </a>
        <a class="nav__item" href="pages/historico.php">
           <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><path d="M12 7v5l4 2"/></svg>
          Histórico
        </a>
        <a class="nav__item" href="pages/estoque.php">
           <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 8.35V20a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V8.35A2 2 0 0 1 3.26 6.5l8-3.2a2 2 0 0 1 1.48 0l8 3.2A2 2 0 0 1 22 8.35Z"/><path d="M6 10v.01"/><path d="M6 14v.01"/><path d="M10 10v.01"/><path d="M10 14v.01"/><path d="M14 10v.01"/><path d="M14 14v.01"/><path d="M18 10v.01"/><path d="M18 14v.01"/><path d="M6 18v.01"/><path d="M10 18v.01"/><path d="M14 18v.01"/><path d="M18 18v.01"/></svg>
          Estoque
        </a>

        <div class="user-menu">
                    <img src="https://ui-avatars.com/api/?name=V+W&background=e2e8f0&color=64748b&size=150" alt="Perfil" class="avatar">
                    <div class="dropdown-content">
                        <a href="pages/perfil.php"><i class="fa-solid fa-user"></i> Meu Perfil</a>
                        <a href="pages/perfil.php"><i class="fa-solid fa-gear"></i> Configurações</a>
                        <hr>
                        <a href="pages/login.php" style="color: #ef4444;"><i class="fa-solid fa-right-from-bracket"></i>
                            Sair</a>
                    </div>
      </nav>
    </div>
  </header>
 
    <main class="conteudo">

      <div class="cabecalho-pagina">
      <h1 class="cabecalho-pagina__titulo">Controle de Estoque</h1>
      <p class="cabecalho-pagina__descricao">Escolha a operação que deseja realizar no almoxarifado.</p>
    </div>

      <section class="resumo-alertas <?= count($alertasEstoque) > 0 ? 'resumo-alertas--atencao' : 'resumo-alertas--regular' ?>" aria-labelledby="titulo-alertas">
        <div>
          <span class="resumo-alertas__rotulo">Pendências de estoque</span>
          <h2 id="titulo-alertas">
            <?= count($alertasEstoque) > 0 ? count($alertasEstoque) . ' produto(s) precisam de atenção' : 'Estoque regular' ?>
          </h2>
          <?php if (count($alertasEstoque) > 0): ?>
            <p><?= $produtosVazios ?> vazio(s) e <?= $produtosAbaixoMinimo ?> abaixo do estoque mínimo.</p>
          <?php else: ?>
            <p>Nenhum produto está vazio ou abaixo do estoque mínimo.</p>
          <?php endif; ?>
        </div>
        <a class="botao <?= count($alertasEstoque) > 0 ? 'botao--alerta' : 'botao--secundario' ?>" href="pages/alertas.php">
          Ver alertas
        </a>
      </section>

    <div class="cabecalho-pagina-usuario">

                <span>Olá,</span>
                <strong>
                    //<?= htmlspecialchars($_SESSION['nome']) ?>
                </strong>

            </div>

            <h2>Bem-vindo!</h2>

            <p>
                Você está conectado como
                <strong>
                    //<?= htmlspecialchars($_SESSION['tipo']) ?>
                </strong>.
            </p>

        </section>
   

        <div class="grade-modulos">

      <a class="modulo" href="pages/cadastrar_produto.php">
        <div class="modulo__icone">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
        </div>
        <h2 class="modulo__titulo">Cadastro de Itens</h2>
        <p class="modulo__descricao">Inclua um novo item no catálogo com categoria, unidade de medida e fornecedor.</p>
      </a>

      <a class="modulo" href="pages/lancamentos.php">
        <div class="modulo__icone">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="m17 10-5 5-5-5"/><path d="M4 21h16"/></svg>
        </div>
        <h2 class="modulo__titulo">Entrada de Produtos</h2>
        <p class="modulo__descricao">Registre o recebimento de materiais, com quantidade, data, hora e responsável.</p>
      </a>

      <a class="modulo" href="pages/lancamentos.php">
        <div class="modulo__icone">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21V9"/><path d="m7 14 5-5 5 5"/><path d="M4 3h16"/></svg>
        </div>
        <h2 class="modulo__titulo">Saída de Produtos</h2>
        <p class="modulo__descricao">Lance a retirada de materiais por setor solicitante, com observação opcional.</p>
      </a>

      <a class="modulo" href="pages/produtos.php">
        <div class="modulo__icone">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="M3.29 7 12 12l8.71-5"/><path d="M12 22V12"/></svg>
        </div>
        <h2 class="modulo__titulo">Produtos</h2>
        <p class="modulo__descricao">Verifique a lista dos Produtos cadastrados.</p>
      </a>

      <a class="modulo" href="pages/historico.php">
        <div class="modulo__icone">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><path d="M12 7v5l4 2"/></svg>
        </div>
        <h2 class="modulo__titulo">Histórico</h2>
        <p class="modulo__descricao">Histórico de Produtos e Lançamentos.</p>
      </a>

      <a class="modulo" href="pages/estoque.php">
        <div class="modulo__icone">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 8.35V20a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V8.35A2 2 0 0 1 3.26 6.5l8-3.2a2 2 0 0 1 1.48 0l8 3.2A2 2 0 0 1 22 8.35Z"/><path d="M6 10v.01"/><path d="M6 14v.01"/><path d="M10 10v.01"/><path d="M10 14v.01"/><path d="M14 10v.01"/><path d="M14 14v.01"/><path d="M18 10v.01"/><path d="M18 14v.01"/><path d="M6 18v.01"/><path d="M10 18v.01"/><path d="M14 18v.01"/><path d="M18 18v.01"/></svg>
          </div>
        <h2 class="modulo__titulo">Estoque</h2>
        <p class="modulo__descricao">Verifique a quantidade de Produtos no estoque.</p>
      </a>

    </div>
  </main>

  <footer class="rodape">GestSaúde · Módulo de Controle de Estoque</footer>


    </main>

</body>

</html>
