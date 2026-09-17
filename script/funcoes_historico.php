<?php

declare(strict_types=1);

function buscarCategorias(mysqli $conn): mysqli_result|false
{
    $sql = "SELECT
                id_categoria,
                nome
            FROM categoria
            ORDER BY nome";

    return $conn->query($sql);
}

function buscarUnidadesHistorico(mysqli $conn): mysqli_result|false
{
    $sql = "SELECT
                id_unidade,
                nome
            FROM unidade_saude
            WHERE ativo = TRUE
            ORDER BY nome";

    return $conn->query($sql);
}

function buscarHistorico(
    mysqli $conn,
    string $tipo = '',
    string $dataInicio = '',
    string $dataFim = '',
    string $categoria = '',
    string $unidade = ''
): mysqli_result|false {
    $sql = "SELECT
                m.id_movimentacao,
                m.tipo,
                m.data_hora,
                m.observacao,
                i.quantidade,
                p.nome AS produto,
                p.unidade AS unidade_medida,
                c.nome AS categoria,
                u.nome AS unidade_saude,
                usr.nome AS usuario
            FROM movimentacao m
            INNER JOIN item_lancamento i
                ON i.movimentacao_id = m.id_movimentacao
            INNER JOIN produto p
                ON p.id_produto = i.produto_id
            INNER JOIN categoria c
                ON c.id_categoria = p.categoria_id
            LEFT JOIN unidade_saude u
                ON u.id_unidade = m.unidade_destino_id
            INNER JOIN usuario usr
                ON usr.id_usuario = m.usuario_id
            WHERE 1 = 1";

    $parametros = [];
    $tipos = "";

    if ($tipo !== '') {
        $tipoValor = match (strtolower($tipo)) {
            'entrada' => 'Entrada',
            'saida' => 'Saida',
            default => null
        };

        if ($tipoValor !== null) {
            $sql .= " AND m.tipo = ?";
            $parametros[] = $tipoValor;
            $tipos .= "s";
        }
    }

    if ($dataInicio !== '') {
        $sql .= " AND m.data_hora >= ?";
        $parametros[] = $dataInicio . " 00:00:00";
        $tipos .= "s";
    }

    if ($dataFim !== '') {
        $sql .= " AND m.data_hora <= ?";
        $parametros[] = $dataFim . " 23:59:59";
        $tipos .= "s";
    }

    if ($categoria !== '') {
        $sql .= " AND p.categoria_id = ?";
        $parametros[] = (int) $categoria;
        $tipos .= "i";
    }

    if ($unidade !== '') {
        $sql .= " AND m.unidade_destino_id = ?";
        $parametros[] = (int) $unidade;
        $tipos .= "i";
    }

    $sql .= " ORDER BY
                m.data_hora DESC,
                m.id_movimentacao DESC,
                i.id_item ASC";

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