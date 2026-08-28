<?php 

function lancamentoEntrada($conn, $produtos, $unidadeDestino, $observacao, $usuario_id)
{
    $conn->begin_transaction();

    try {

        $sql = "INSERT INTO movimentacao
                (tipo, unidade_destino_id, observacao, usuario_id)
                VALUES ('Entrada', ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("Erro ao preparar movimentação.");
        }

        $stmt->bind_param(
            "isi",
            $unidadeDestino,
            $observacao,
            $usuario_id
        );

        if (!$stmt->execute()) {
            throw new Exception("Erro ao criar movimentação.");
        }

        $movimentacao_id = $conn->insert_id;

        $stmt->close();

        $sqlItem = "INSERT INTO item_lancamento
                    (movimentacao_id, produto_id, quantidade)
                    VALUES (?, ?, ?)";

        $stmtItem = $conn->prepare($sqlItem);

        if (!$stmtItem) {
            throw new Exception("Erro ao preparar item do lançamento.");
        }

        $sqlEstoque = "UPDATE produto
                       SET estoque = estoque + ?
                       WHERE id_produto = ?";

        $stmtEstoque = $conn->prepare($sqlEstoque);

        if (!$stmtEstoque) {
            throw new Exception("Erro ao preparar atualização do estoque.");
        }

        foreach ($produtos as $produto) {

            $produto_id = $produto['produto_id'];
            $quantidade = $produto['quantidade'];

            if ($quantidade <= 0) {
                throw new Exception("A quantidade deve ser maior que zero.");
            }

            $stmtItem->bind_param(
                "iii",
                $movimentacao_id,
                $produto_id,
                $quantidade
            );

            if (!$stmtItem->execute()) {
                throw new Exception("Erro ao inserir produto no lançamento.");
            }

            $stmtEstoque->bind_param(
                "ii",
                $quantidade,
                $produto_id
            );

            if (!$stmtEstoque->execute()) {
                throw new Exception("Erro ao atualizar estoque.");
            }

            if ($stmtEstoque->affected_rows === 0) {
                throw new Exception("Produto não encontrado.");
            }
        }

        $stmtItem->close();
        $stmtEstoque->close();


        $conn->commit();

        return $movimentacao_id;

    } catch (Exception $e) {

        $conn->rollback();

        return false;
    }
}
