<?php

function sidebar($paginaAtiva = '')
{
    $tipo = $_SESSION['tipo'] ?? '';
    $paginaAtiva = strtolower($paginaAtiva);

    $ativo = function ($pagina) use ($paginaAtiva) {
        return $paginaAtiva === strtolower($pagina) ? ' aria-current="page"' : '';
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
                <a class="nav__item" href="' . BASE_URL . 'pages/cadastrar_produto.php"' . $ativo('cadastrar-produto') . '>Cadastro</a>
                <a class="nav__item" href="' . BASE_URL . 'pages/lancamentos.php"' . $ativo('lancamentos') . '>Entrada</a>
                <a class="nav__item" href="' . BASE_URL . 'pages/lancamentos.php"' . $ativo('saida') . '>Saída</a>
                <a class="nav__item" href="' . BASE_URL . 'pages/produtos.php"' . $ativo('produtos') . '>Produtos</a>
                <a class="nav__item" href="' . BASE_URL . 'pages/historico.php"' . $ativo('historico') . '>Histórico</a>
                <a class="nav__item" href="' . BASE_URL . 'pages/estoque.php"' . $ativo('estoque') . '>Estoque</a>
                <a class="nav__item" href="' . BASE_URL . 'pages/alertas.php"' . $ativo('alertas') . '>Alertas</a>
                <a class="nav__item" href="' . BASE_URL . 'pages/graficos.php"' . $ativo('graficos') . '>Gráficos</a>';

    if ($tipo === 'Administrador') {
        echo '
                <a class="nav__item" href="' . BASE_URL . 'pages/usuarios.php"' . $ativo('usuarios') . '>Usuários</a>
                <a class="nav__item" href="' . BASE_URL . 'pages/logs.php"' . $ativo('logs') . '>Logs</a>';
    }

    echo '
                <div class="user-menu">
                    <img src="https://ui-avatars.com/api/?name=V+W&background=e2e8f0&color=64748b&size=150" alt="Perfil" class="avatar">
                    <div class="dropdown-content">
                        <a href="' . BASE_URL . 'pages/perfil.php">Meu Perfil</a>
                        <a href="' . BASE_URL . 'pages/perfil.php">Configurações</a>
                        <hr>
                        <a href="' . BASE_URL . 'pages/login.php" style="color: #ef4444;">Sair</a>
                    </div>
                </div>
            </nav>
        </div>
    </header>
    ';
}
