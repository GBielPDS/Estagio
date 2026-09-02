<?php

function buscarUsuariosLogs($conn)
{
    $sql = "SELECT
                id_usuario,
                nome
            FROM usuario
            ORDER BY nome";

    $resultado = $conn->query($sql);

    return $resultado;
}


function buscarLogs(
    $conn,
    $dataInicio = '',
    $dataFim = '',
    $usuario = ''
) {

    $sql = "SELECT
                l.id_log,
                l.data_hora,
                l.acao,
                l.descricao,
                l.usuario_id,
                u.nome AS usuario

            FROM log l

            INNER JOIN usuario u
                ON u.id_usuario = l.usuario_id

            WHERE 1 = 1";


    $parametros = [];
    $tipos = "";


    if ($dataInicio !== '') {

        $sql .= " AND l.data_hora >= ?";

        $parametros[] =
            $dataInicio . " 00:00:00";

        $tipos .= "s";
    }


    if ($dataFim !== '') {

        $sql .= " AND l.data_hora <= ?";

        $parametros[] =
            $dataFim . " 23:59:59";

        $tipos .= "s";
    }


    if ($usuario !== '') {

        $sql .= " AND l.usuario_id = ?";

        $parametros[] =
            (int) $usuario;

        $tipos .= "i";
    }


    $sql .= " ORDER BY
                l.data_hora DESC,
                l.id_log DESC";


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