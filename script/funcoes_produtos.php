<?php

function buscarCategorias($conn)
{
    $sql = 'SELECT id_categoria, nome FROM categoria ORDER BY nome';
    $resultado = $conn->query($sql);

    if (!$resultado) {
        return [];
    }

    return $resultado->fetch_all(MYSQLI_ASSOC);
}

function buscarUnidades($conn)
{
    $sql = "SELECT DISTINCT unidade
            FROM produto
            WHERE unidade <> ''
            ORDER BY unidade";
    $resultado = $conn->query($sql);

    if (!$resultado) {
        return [];
    }

    return $resultado->fetch_all(MYSQLI_ASSOC);
}

function criarCategoria($conn, $nome)
{
    $nome = trim($nome);

    if ($nome === '') {
        return ['sucesso' => false, 'mensagem' => 'Informe o nome da nova categoria.'];
    }

    $sql = 'SELECT id_categoria FROM categoria WHERE nome = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $nome);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        return ['sucesso' => false, 'mensagem' => 'Esta categoria já está cadastrada.'];
    }

    $stmt->close();

    $sql = 'INSERT INTO categoria (nome) VALUES (?)';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $nome);

    if (!$stmt->execute()) {
        $stmt->close();
        return ['sucesso' => false, 'mensagem' => 'Não foi possível cadastrar a categoria.'];
    }

    $idCategoria = $stmt->insert_id;
    $stmt->close();

    return ['sucesso' => true, 'id' => $idCategoria];
}

function produtoJaExiste($conn, $nome, $categoriaId, $unidade)
{
    $sql = 'SELECT id_produto
            FROM produto
            WHERE nome = ? AND categoria_id = ? AND unidade = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sis', $nome, $categoriaId, $unidade);
    $stmt->execute();

    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $existe;
}

function unidadeJaExiste($conn, $unidade)
{
    $sql = 'SELECT id_produto FROM produto WHERE unidade = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $unidade);
    $stmt->execute();

    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $existe;
}

function cadastrarProduto($conn, $nome, $categoriaId, $unidade, $estoque, $estoqueMinimo)
{
    $nome = trim($nome);
    $unidade = trim($unidade);

    if (produtoJaExiste($conn, $nome, $categoriaId, $unidade)) {
        return ['sucesso' => false, 'mensagem' => 'Este produto já existe com essa categoria e unidade.'];
    }

    $sql = 'INSERT INTO produto
            (nome, unidade, estoque, estoque_minimo, categoria_id)
            VALUES (?, ?, ?, ?, ?)';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssiii', $nome, $unidade, $estoque, $estoqueMinimo, $categoriaId);

    if (!$stmt->execute()) {
        $stmt->close();
        return ['sucesso' => false, 'mensagem' => 'Não foi possível cadastrar o produto.'];
    }

    $stmt->close();
    return ['sucesso' => true];
}

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
        echo '<tr data-nome="' . htmlspecialchars($produto['nome'], ENT_QUOTES, 'UTF-8') . '"'
            . ' data-categoria="' . htmlspecialchars($produto['categoria'], ENT_QUOTES, 'UTF-8') . '"'
            . ' data-unidade="' . htmlspecialchars($produto['unidade'], ENT_QUOTES, 'UTF-8') . '">';
        echo '<td>' . htmlspecialchars($produto['id_produto']) . '</td>';
        echo '<td>' . htmlspecialchars($produto['nome']) . '</td>';
        echo '<td>' . htmlspecialchars($produto['categoria']) . '</td>';
        echo '<td>' . htmlspecialchars($produto['unidade']) . '</td>';
        echo '<td>' . htmlspecialchars($produto['estoque']) . '</td>';
        echo '</tr>';
    }
}
