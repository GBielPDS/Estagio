<?php

function listarProdutos($conn)
{
    $sql = "SELECT p.id_produto, p.nome, p.unidade, p.estoque,
                   p.estoque_minimo, c.nome AS categoria
            FROM produto p
            INNER JOIN categoria c ON c.id_categoria = p.categoria_id
            ORDER BY p.nome";

    $resultado = $conn->query($sql);

    if (!$resultado) {
        die('Erro ao consultar produtos: ' . $conn->error);
    }

    while ($produto = $resultado->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($produto['id_produto']) . '</td>';
        echo '<td>' . htmlspecialchars($produto['nome']) . '</td>';
        echo '<td>' . htmlspecialchars($produto['categoria']) . '</td>';
        echo '<td>' . htmlspecialchars($produto['unidade']) . '</td>';
        echo '<td>' . htmlspecialchars($produto['estoque']) . '</td>';
        echo '</tr>';
    }
}
