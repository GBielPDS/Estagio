<?php

declare(strict_types=1);

function buscarCategoriasEstoque(mysqli $conn): mysqli_result|false
{
    $sql = "SELECT
                id_categoria,
                nome
            FROM categoria
            ORDER BY nome";

    return $conn->query($sql);
}

function buscarUnidadesMedida(mysqli $conn): mysqli_result|false
{
    $sql = "SELECT DISTINCT
                unidade
            FROM produto
            WHERE estoque > 0
            ORDER BY unidade";

    return $conn->query($sql);
}

function buscarEstoque(
    mysqli $conn,
    string $categoria = '',
    string $unidade = '',
    string $produto = ''
): mysqli_result|false {
    $sql = "SELECT
                p.id_produto,
                p.nome,
                p.unidade,
                p.estoque,
                p.estoque_minimo,
                c.nome AS categoria
            FROM produto p
            INNER JOIN categoria c
                ON c.id_categoria = p.categoria_id
            WHERE p.estoque > 0";

    $parametros = [];
    $tipos = "";

    if ($categoria !== '') {
        $sql .= " AND p.categoria_id = ?";
        $parametros[] = (int) $categoria;
        $tipos .= "i";
    }

    if ($unidade !== '') {
        $sql .= " AND p.unidade = ?";
        $parametros[] = $unidade;
        $tipos .= "s";
    }

    if ($produto !== '') {
        $sql .= " AND p.nome LIKE ?";
        $parametros[] = "%" . $produto . "%";
        $tipos .= "s";
    }

    $sql .= " ORDER BY p.nome";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    if (!empty($parametros)) {
        $stmt->bind_param($tipos, ...$parametros);
    }

    $stmt->execute();

    return $stmt->get_result();
}

function buscarAlertasEstoque(mysqli $conn): array
{
    $sql = "SELECT
                p.id_produto,
                p.nome,
                p.unidade,
                p.estoque,
                p.estoque_minimo,
                c.nome AS categoria
            FROM produto p
            INNER JOIN categoria c ON c.id_categoria = p.categoria_id
            WHERE p.estoque = 0
               OR p.estoque < p.estoque_minimo
            ORDER BY p.estoque ASC, p.nome ASC";

    $resultado = $conn->query($sql);

    if (!$resultado) {
        return [];
    }

    $alertas = [];

    while ($produto = $resultado->fetch_assoc()) {
        $estoque = (int) $produto['estoque'];
        $estoqueMinimo = (int) $produto['estoque_minimo'];

        $produto['situacao'] = match (true) {
            $estoque === 0 => 'vazio',
            default => 'abaixo-minimo'
        };
        $produto['quantidade_faltante'] = max(0, $estoqueMinimo - $estoque);
        $alertas[] = $produto;
    }

    return $alertas;
}