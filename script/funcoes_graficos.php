<?php

function buscarCategoriasGraficos($conn)
{
    $sql = "SELECT
                id_categoria,
                nome
            FROM categoria
            ORDER BY nome";

    $resultado = $conn->query($sql);

    return $resultado;
}


function buscarUnidadesGraficos($conn)
{
    $sql = "SELECT
                id_unidade,
                nome
            FROM unidade_saude
            WHERE ativo = TRUE
            ORDER BY nome";

    $resultado = $conn->query($sql);

    return $resultado;
}


function montarFiltrosGraficos(
    $tipo,
    $dataInicio,
    $dataFim,
    $categoria,
    $unidade
) {

    $where = "";
    $parametros = [];
    $tipos = "";


    if ($tipo !== '') {

        $where .= " AND m.tipo = ?";

        $parametros[] = $tipo;

        $tipos .= "s";
    }


    if ($dataInicio !== '') {

        $where .= " AND m.data_hora >= ?";

        $parametros[] =
            $dataInicio . " 00:00:00";

        $tipos .= "s";
    }


    if ($dataFim !== '') {

        $where .= " AND m.data_hora <= ?";

        $parametros[] =
            $dataFim . " 23:59:59";

        $tipos .= "s";
    }


    if ($categoria !== '') {

        $where .= " AND p.categoria_id = ?";

        $parametros[] =
            (int) $categoria;

        $tipos .= "i";
    }


    if ($unidade !== '') {

        $where .= " AND m.unidade_destino_id = ?";

        $parametros[] =
            (int) $unidade;

        $tipos .= "i";
    }


    return [
        'where' => $where,
        'parametros' => $parametros,
        'tipos' => $tipos
    ];
}


function buscarProdutosMaisUsados(
    $conn,
    $tipo = '',
    $dataInicio = '',
    $dataFim = '',
    $categoria = '',
    $unidade = ''
) {

    $filtros = montarFiltrosGraficos(
        $tipo,
        $dataInicio,
        $dataFim,
        $categoria,
        $unidade
    );


    $sql = "SELECT
                p.nome AS produto,
                SUM(i.quantidade) AS quantidade

            FROM movimentacao m

            INNER JOIN item_lancamento i
                ON i.movimentacao_id = m.id_movimentacao

            INNER JOIN produto p
                ON p.id_produto = i.produto_id

            WHERE 1 = 1

            {$filtros['where']}

            GROUP BY
                p.id_produto,
                p.nome

            ORDER BY
                quantidade DESC

            LIMIT 10";


    $stmt = $conn->prepare($sql);


    if (!$stmt) {
        return false;
    }


    if (!empty($filtros['parametros'])) {

        $stmt->bind_param(
            $filtros['tipos'],
            ...$filtros['parametros']
        );
    }


    $stmt->execute();

    return $stmt->get_result();
}


function buscarUnidadesMaisUsadas(
    $conn,
    $tipo = '',
    $dataInicio = '',
    $dataFim = '',
    $categoria = '',
    $unidade = ''
) {

    $filtros = montarFiltrosGraficos(
        $tipo,
        $dataInicio,
        $dataFim,
        $categoria,
        $unidade
    );


    $sql = "SELECT
                u.nome AS unidade,
                SUM(i.quantidade) AS quantidade

            FROM movimentacao m

            INNER JOIN item_lancamento i
                ON i.movimentacao_id = m.id_movimentacao

            INNER JOIN unidade_saude u
                ON u.id_unidade = m.unidade_destino_id

            INNER JOIN produto p
                ON p.id_produto = i.produto_id

            WHERE 1 = 1

            AND m.unidade_destino_id IS NOT NULL

            {$filtros['where']}

            GROUP BY
                u.id_unidade,
                u.nome

            ORDER BY
                quantidade DESC

            LIMIT 10";


    $stmt = $conn->prepare($sql);


    if (!$stmt) {
        return false;
    }


    if (!empty($filtros['parametros'])) {

        $stmt->bind_param(
            $filtros['tipos'],
            ...$filtros['parametros']
        );
    }


    $stmt->execute();

    return $stmt->get_result();
}


function buscarCategoriasMaisUsadas(
    $conn,
    $tipo = '',
    $dataInicio = '',
    $dataFim = '',
    $categoria = '',
    $unidade = ''
) {

    $filtros = montarFiltrosGraficos(
        $tipo,
        $dataInicio,
        $dataFim,
        $categoria,
        $unidade
    );


    $sql = "SELECT
                c.nome AS categoria,
                SUM(i.quantidade) AS quantidade

            FROM movimentacao m

            INNER JOIN item_lancamento i
                ON i.movimentacao_id = m.id_movimentacao

            INNER JOIN produto p
                ON p.id_produto = i.produto_id

            INNER JOIN categoria c
                ON c.id_categoria = p.categoria_id

            WHERE 1 = 1

            {$filtros['where']}

            GROUP BY
                c.id_categoria,
                c.nome

            ORDER BY
                quantidade DESC

            LIMIT 10";


    $stmt = $conn->prepare($sql);


    if (!$stmt) {
        return false;
    }


    if (!empty($filtros['parametros'])) {

        $stmt->bind_param(
            $filtros['tipos'],
            ...$filtros['parametros']
        );
    }


    $stmt->execute();

    return $stmt->get_result();
}