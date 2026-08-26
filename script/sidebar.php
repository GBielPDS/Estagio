<?php 


function sidebar($paginaAtiva = '')
{
    $tipo = $_SESSION['tipo'] ?? '';

    echo '
    <aside class="sidebar">

        <div class="logo">
            <h2>ESTAGIO</h2>
        </div>

        <nav>

            <a href="' . BASE_URL . 'index.php" class="' .
                ($paginaAtiva === 'home' ? 'ativo' : '') . '">
                Home
            </a>

            <a href="' . BASE_URL . 'pages/lancamentos.php" class="' .
                ($paginaAtiva === 'Lançamentos' ? 'ativo' : '') . '">
                Lançamentos
            </a>
    ';

    /////// Itens exclusivos do Administrador  \\\\\\\\
    if ($tipo === 'Administrador') {

        echo '
            <a href="' . BASE_URL . 'pages/usuarios.php" class="' .
                ($paginaAtiva === 'usuarios' ? 'ativo' : '') . '">
                Usuários
            </a>

            <a href="' . BASE_URL . 'pages/logs.php" class="' .
                ($paginaAtiva === 'logs' ? 'ativo' : '') . '">
                Logs
            </a>
        ';
    }

    ///////  Administrador e Suporte  \\\\\\\
        if ($tipo === 'Administrador' || $tipo === 'Suporte' || $tipo === 'Usuario') {

        echo '
            <a href="' . BASE_URL . 'pages/produtos.php" class="' .
                ($paginaAtiva === 'produtos' ? 'ativo' : '') . '">
                Produtos
            </a>

            <a href="' . BASE_URL . 'pages/cadastrar_produto.php" class="' .
                ($paginaAtiva === 'cadastrar-produto' ? 'ativo' : '') . '">
                Cadastrar produto
            </a>
        ';
    }

    echo '
            <a href="' . BASE_URL . 'pages/perfil.php" class="' .
                ($paginaAtiva === 'perfil' ? 'ativo' : '') . '">
                Meu perfil
            </a>

            <a href="' . BASE_URL . 'pages/login.php">
                Sair
            </a>

        </nav>

    </aside>
    ';
}