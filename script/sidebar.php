<?php

require_once __DIR__ . '/funcoes_estoque.php';

function iconesNavegacao()
{
    return [
        'cadastro' => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
        'entrada' => '<path d="M12 3v12"/><path d="m17 10-5 5-5-5"/><path d="M4 21h16"/>',
        'saida' => '<path d="M12 21V9"/><path d="m7 14 5-5 5 5"/><path d="M4 3h16"/>',
        'produtos' => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="M3.29 7 12 12l8.71-5"/><path d="M12 22V12"/>',
        'historico' => '<path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><path d="M12 7v5l4 2"/>',
        'estoque' => '<path d="M22 8.35V20a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V8.35A2 2 0 0 1 3.26 6.5l8-3.2a2 2 0 0 1 1.48 0l8 3.2A2 2 0 0 1 22 8.35Z"/><path d="M6 10v.01"/><path d="M6 14v.01"/><path d="M10 10v.01"/><path d="M10 14v.01"/><path d="M14 10v.01"/><path d="M14 14v.01"/><path d="M18 10v.01"/><path d="M18 14v.01"/><path d="M6 18v.01"/><path d="M10 18v.01"/><path d="M14 18v.01"/><path d="M18 18v.01"/>',
        'alertas' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
        'graficos' => '<path d="M4 19V5"/><path d="M4 19h16"/><path d="m7 15 3-4 3 2 5-7"/>'
    ];
}

function sidebar($paginaAtiva = '')
{
    global $conn;

    $tipo = $_SESSION['tipo'] ?? '';
    $nome = trim($_SESSION['nome'] ?? '');
    $iniciais = strtoupper(substr($nome !== '' ? $nome : '?', 0, 1));
    $paginaAtiva = strtolower($paginaAtiva);
    $icones = iconesNavegacao();
    $totalAlertas = 0;

    if (isset($conn) && function_exists('buscarAlertasEstoque')) {
        $totalAlertas = count(buscarAlertasEstoque($conn));
    }

    $ativo = function ($pagina) use ($paginaAtiva) {
        return $paginaAtiva === strtolower($pagina) ? ' aria-current="page"' : '';
    };

    $icone = function ($nome) use ($icones) {
        return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icones[$nome] . '</svg>';
    };

    echo '
    <header class="topo">
        <div class="topo__interno">
            <a class="marca" href="' . BASE_URL . 'index.php">
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
                <a class="nav__item" href="' . BASE_URL . 'pages/cadastrar_produto.php"' . $ativo('cadastrar-produto') . '>' . $icone('cadastro') . 'Cadastro</a>
                <a class="nav__item" href="' . BASE_URL . 'pages/lancamentos.php?tipo=Entrada"' . $ativo('lancamentos') . '>' . $icone('entrada') . 'Entrada</a>
                <a class="nav__item" href="' . BASE_URL . 'pages/lancamentos.php?tipo=Saida"' . $ativo('saida') . '>' . $icone('saida') . 'Saída</a>
                <a class="nav__item" href="' . BASE_URL . 'pages/produtos.php"' . $ativo('produtos') . '>' . $icone('produtos') . 'Produtos</a>
                <a class="nav__item" href="' . BASE_URL . 'pages/historico.php"' . $ativo('historico') . '>' . $icone('historico') . 'Histórico</a>
                <a class="nav__item" href="' . BASE_URL . 'pages/estoque.php"' . $ativo('estoque') . '>' . $icone('estoque') . 'Estoque</a>
                <a class="nav__item" href="' . BASE_URL . 'pages/graficos.php"' . $ativo('graficos') . '>' . $icone('graficos') . 'Gráficos</a>';

    echo '
            </nav>

            <div class="user-menu">
                <span class="avatar avatar--iniciais" aria-label="Abrir menu do perfil">' . htmlspecialchars($iniciais, ENT_QUOTES, 'UTF-8') . '</span>
                <div class="dropdown-content">
                    <a href="' . BASE_URL . 'pages/perfil.php">Meu Perfil</a>
                    <a class="dropdown-notificacao" href="' . BASE_URL . 'pages/alertas.php">Notificações <span class="notificacao-contador">' . $totalAlertas . '</span></a>';

    if ($tipo === 'Administrador') {
        echo '
                    <a href="' . BASE_URL . 'pages/usuarios.php">Usuários</a>
                    <a href="' . BASE_URL . 'pages/logs.php">Logs</a>';
    }

    echo '
                    <hr>
                    <a class="sair-link" href="' . BASE_URL . 'script/logout.php">Sair</a>
                </div>
            </div>
        </div>
    </header>
    ';
}
