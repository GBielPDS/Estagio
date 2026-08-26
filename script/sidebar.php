<?php 

require_once "../url/url.php";

function sidebar($paginaAtiva = '')
{
    $tipo = $_SESSION['tipo'] ?? '';

    echo '
    <aside class="sidebar">

        <div class="logo">
            <h2>ESTAGIO</h2>
        </div>

        <nav>

            <a href="/git/ESTAGIO/index.php" class="' .
                ($paginaAtiva === 'home' ? 'ativo' : '') . '">
                Home
            </a>
    ';

    /////// Itens exclusivos do Administrador  \\\\\\\\
    if ($tipo === 'Administrador') {

        echo '
            <a href="/git/ESTAGIO/pages/usuarios.php" class="' .
                ($paginaAtiva === 'usuarios' ? 'ativo' : '') . '">
                Usuários
            </a>

            <a href="/git/ESTAGIO/pages/logs.php" class="' .
                ($paginaAtiva === 'logs' ? 'ativo' : '') . '">
                Logs
            </a>
        ';
    }

    ///////  Administrador e Suporte  \\\\\\\
    if ($tipo === 'Administrador' || $tipo === 'Suporte') {

        echo '
            <a href="/git/ESTAGIO/pages/produtos.php" class="' .
                ($paginaAtiva === 'produtos' ? 'ativo' : '') . '">
                Produtos
            </a>
        ';
    }

    echo '
            <a href="/git/ESTAGIO/pages/perfil.php" class="' .
                ($paginaAtiva === 'perfil' ? 'ativo' : '') . '">
                Meu perfil
            </a>

            <a href="/git/ESTAGIO/pages/logout.php">
                Sair
            </a>

        </nav>

    </aside>
    ';
}