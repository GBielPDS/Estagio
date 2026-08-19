<?php
function registrarLog($conn, $acao, $descricao, $usuario_id)
{
    $sql = "INSERT INTO log (acao, descricao, usuario_id)
            VALUES (?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Erro ao preparar registro do log: " . $conn->error);
    }

    $stmt->bind_param(
        "ssi",
        $acao,
        $descricao,
        $usuario_id
    );

    return $stmt->execute();
}

