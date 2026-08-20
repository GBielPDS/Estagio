<?php

require_once '../script/protecao.php';
require_once '../script/conexao.php';
require '../script/funcoes_produtos.php';

verificarSessao();

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style.css">
    <title>Produtos</title>
</head>

<body>

    <h1>Produtos</h1>

    <table class="table">
        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Categoria</th>
            <th>Unidade de medida</th>
            <th>Quantidade em estoque</th>
        </tr>

        <?php listarProdutos($conn); ?>
    </table>

</body>

</html>
