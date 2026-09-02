<?php


function buscarCategoriasEstoque($conn)
{
    $sql = "SELECT
                id_categoria,
                nome
            FROM categoria
            ORDER BY nome";

    $resultado = $conn->query($sql);

    return $resultado;
}


function buscarUnidadesMedida($conn)
{
    $sql = "SELECT DISTINCT
                unidade
            FROM produto
            WHERE estoque > 0
            ORDER BY unidade";

    $resultado = $conn->query($sql);

    return $resultado;
}


function buscarEstoque(
    $conn,
    $categoria = '',
    $unidade = '',
    $produto = ''
) {

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

        $stmt->bind_param(
            $tipos,
            ...$parametros
        );
    }


    $stmt->execute();


    return $stmt->get_result();
}

function buscarAlertasEstoque($conn)
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
        $produto['situacao'] = (int) $produto['estoque'] === 0
            ? 'vazio'
            : 'abaixo-minimo';
        $produto['quantidade_faltante'] = max(
            0,
            (int) $produto['estoque_minimo'] - (int) $produto['estoque']
        );
        $alertas[] = $produto;
    }

    return $alertas;
}