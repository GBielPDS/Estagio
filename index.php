<?php

require_once 'script/sessao.php';
require 'script/sidebar.php';

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

    <?php sidebar('home'); ?>


    <main class="conteudo">

        <header class="cabecalho-home">

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