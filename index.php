<?php

require_once 'script/sessao.php';

verificarSessao();

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Home</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <aside class="sidebar">

        <div class="logo">
            <h2>ESTAGIO</h2>
        </div>

        <nav>

            <a href="index.php" class="ativo">
                Home
            </a>

            <?php if ($_SESSION['tipo'] === 'Administrador'): ?>

                <a href="pages/usuarios.php">
                    Usuários
                </a>

                <a href="pages/logs.php">
                    Logs
                </a>

            <?php endif; ?>

            <?php if (
                $_SESSION['tipo'] === 'Administrador' ||
                $_SESSION['tipo'] === 'Suporte'
            ): ?>

                <a href="pages/produtos.php">
                    Produtos
                </a>

            <?php endif; ?>

            <a href="pages/perfil.php">
                Meu perfil
            </a>

            <a href="pages/logout.php">
                Sair
            </a>

        </nav>

    </aside>


    <main class="conteudo">

        <header class="topo">

            <h1>Home</h1>

            <div class="usuario">

                Olá,
                <strong>
                    <?= htmlspecialchars($_SESSION['nome']) ?>
                </strong>

            </div>

        </header>


        <section class="dashboard">

            <h2>Bem-vindo!</h2>

            <p>
                Você está conectado como
                <strong>
                    <?= htmlspecialchars($_SESSION['tipo']) ?>
                </strong>.
            </p>

        </section>

    </main>

</body>

</html>