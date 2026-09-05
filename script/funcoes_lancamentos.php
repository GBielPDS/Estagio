<?php

require_once __DIR__ . '/funcoes_logs.php';

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
            throw new Exception("Erro ao preparar item.");
        }


        $sqlEstoque = "UPDATE produto
                       SET estoque = estoque + ?
                       WHERE id_produto = ?";

        $stmtEstoque = $conn->prepare($sqlEstoque);

        if (!$stmtEstoque) {
            throw new Exception("Erro ao preparar estoque.");
        }

        $logsParaRegistrar = [];

        foreach ($produtos as $produto) {

            $produto_id = (int) $produto['produto_id'];
            $quantidade = (int) $produto['quantidade'];

            if ($quantidade <= 0) {
                throw new Exception(
                    "A quantidade deve ser maior que zero."
                );
            }


            $sqlProduto = "SELECT id_produto, nome, unidade
                           FROM produto
                           WHERE id_produto = ?";

            $stmtProduto = $conn->prepare($sqlProduto);
            $stmtProduto->bind_param("i", $produto_id);
            $stmtProduto->execute();

            $resultado = $stmtProduto->get_result();

            if ($resultado->num_rows === 0) {
                throw new Exception(
                    "Produto ID $produto_id não encontrado."
                );
            }

            $dadosProduto = $resultado->fetch_assoc();
            $stmtProduto->close();


            $stmtItem->bind_param(
                "iii",
                $movimentacao_id,
                $produto_id,
                $quantidade
            );

            if (!$stmtItem->execute()) {
                throw new Exception(
                    "Erro ao inserir produto no lançamento."
                );
            }


            $stmtEstoque->bind_param(
                "ii",
                $quantidade,
                $produto_id
            );

            if (!$stmtEstoque->execute()) {
                throw new Exception(
                    "Erro ao atualizar estoque."
                );
            }

            $logsParaRegistrar[] = [
                'acao' => 'Entrada de produto',
                'descricao' => "Entrada de {$quantidade} {$dadosProduto['unidade']} de {$dadosProduto['nome']}."
            ];
        }

        $stmtItem->close();
        $stmtEstoque->close();

        $conn->commit();

        foreach ($logsParaRegistrar as $logItem) {
            registrarLog($conn, $logItem['acao'], $logItem['descricao'], $usuario_id);
        }

        return $movimentacao_id;

    } catch (Exception $e) {

        $conn->rollback();

        return false;
    }
}

function lancamentoSaida($conn, $produtos, $unidadeDestino, $observacao, $usuario_id)
{
    $conn->begin_transaction();

    try {

        $nomeUnidade = 'Unidade de Saúde';
        $sqlUnidade = "SELECT nome FROM unidade_saude WHERE id_unidade = ?";
        $stmtUni = $conn->prepare($sqlUnidade);
        if ($stmtUni) {
            $stmtUni->bind_param("i", $unidadeDestino);
            $stmtUni->execute();
            $resUni = $stmtUni->get_result();
            if ($linhaUni = $resUni->fetch_assoc()) {
                $nomeUnidade = $linhaUni['nome'];
            }
            $stmtUni->close();
        }

        $sql = "INSERT INTO movimentacao
                (tipo, unidade_destino_id, observacao, usuario_id)
                VALUES ('Saida', ?, ?, ?)";

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
            throw new Exception("Erro ao preparar item.");
        }


        $sqlEstoque = "UPDATE produto
                       SET estoque = estoque - ?
                       WHERE id_produto = ?
                       AND estoque >= ?";

        $stmtEstoque = $conn->prepare($sqlEstoque);

        if (!$stmtEstoque) {
            throw new Exception("Erro ao preparar estoque.");
        }

        $logsParaRegistrar = [];

        foreach ($produtos as $produto) {

            $produto_id = (int) $produto['produto_id'];
            $quantidade = (int) $produto['quantidade'];

            if ($quantidade <= 0) {
                throw new Exception(
                    "A quantidade deve ser maior que zero."
                );
            }

            $sqlProduto = "SELECT id_produto, nome, unidade
                           FROM produto
                           WHERE id_produto = ?";

            $stmtProduto = $conn->prepare($sqlProduto);
            $stmtProduto->bind_param("i", $produto_id);
            $stmtProduto->execute();

            $resultado = $stmtProduto->get_result();

            if ($resultado->num_rows === 0) {
                throw new Exception(
                    "Produto ID $produto_id não encontrado."
                );
            }

            $dadosProduto = $resultado->fetch_assoc();
            $stmtProduto->close();


            $stmtItem->bind_param(
                "iii",
                $movimentacao_id,
                $produto_id,
                $quantidade
            );

            if (!$stmtItem->execute()) {
                throw new Exception(
                    "Erro ao inserir produto no lançamento."
                );
            }


            $stmtEstoque->bind_param(
                "iii",
                $quantidade,
                $produto_id,
                $quantidade
            );

            if (!$stmtEstoque->execute()) {
                throw new Exception(
                    "Erro ao atualizar estoque."
                );
            }


            if ($stmtEstoque->affected_rows === 0) {
                throw new Exception(
                    "Estoque insuficiente para o produto ID $produto_id."
                );
            }

            $logsParaRegistrar[] = [
                'acao' => 'Saída de produto',
                'descricao' => "Saída de {$quantidade} {$dadosProduto['unidade']} de {$dadosProduto['nome']} para {$nomeUnidade}."
            ];
        }


        $stmtItem->close();
        $stmtEstoque->close();

        $conn->commit();

        foreach ($logsParaRegistrar as $logItem) {
            registrarLog($conn, $logItem['acao'], $logItem['descricao'], $usuario_id);
        }

        return $movimentacao_id;

    } catch (Exception $e) {

        $conn->rollback();

        return false;
    }
}