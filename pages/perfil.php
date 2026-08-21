<?php

require "../script/sessao.php";
require "../script/sidebar.php";

verificarSessao();


?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style.css">
    <title>Document</title>
</head>
<body>

    <?php sidebar('perfil'); ?>

    <main class="conteudo">

        <header class="topo">

            <h1>
                
                <strong>
                    <?= htmlspecialchars($_SESSION['nome']) ?>    
                </strong>
            
            </h1>

        </header>
    
        <section class="dashboard">

            <h2>Em Breve</h2>

            <p>
                Já estamos trabalhando nessa página, ela estará pronta muito em breve
            </p>

        </section>

    </main>

</body>
</html>
