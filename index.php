<?php

require "script/conexao.php";
require "script/funcoes.php";

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <title>Document</title>
</head>
<body>

<h2>Lista de Usuários</h2>

<table class="table">
    <tr>
        <th>ID</th>
        <th>Nome</th>
        <th>Email</th>
        <th>Senha</th>
        <th>Tipo</th>
        <th>Ações</th>
    </tr>

    <?php listarUsuarios($conn); ?>

</table>

</body>
</html>
