<?php

declare(strict_types=1);

require_once __DIR__ . '/funcoes_logs.php';


function validarDataLancamento(?string $dataHora): ?string
{
    if ($dataHora === null || trim($dataHora) === '') {
        return null;
    }

    $dataHora = trim($dataHora);

    $data = DateTimeImmutable::createFromFormat(
        'Y-m-d\TH:i',
        $dataHora,
        new DateTimeZone('America/Sao_Paulo')
    );

    $erros = DateTimeImmutable::getLastErrors();

    if (
        $data === false ||
        ($erros !== false && ($erros['warning_count'] > 0 || $erros['error_count'] > 0))
    ) {
        throw new DomainException('A data do lançamento é inválida.');
    }

    $agora = new DateTimeImmutable(
        'now',
        new DateTimeZone('America/Sao_Paulo')
    );

    if ($data >= $agora) {
        throw new DomainException(
            'A data escolhida deve ser anterior à data e hora atuais.'
        );
    }

    return $data->format('Y-m-d H:i:s');
}


function validarObservacaoData(
    ?string $dataHora,
    string $observacao
): void {
    if ($dataHora !== null && trim($dataHora) !== '' && trim($observacao) === '') {
        throw new DomainException(
            'A observação é obrigatória quando o lançamento possui uma data anterior.'
        );
    }
}


function lancamentoEntrada(
    mysqli $conn,
    array $produtos,
    int $unidadeDestino,
    string $observacao,
    int $usuario_id,
    ?string $dataHora = null
): array {
    $conn->begin_transaction();

    try {

        if (!$produtos) {
            throw new DomainException('Adicione pelo menos um produto.');
        }

        $dataHoraFormatada = validarDataLancamento($dataHora);

        validarObservacaoData(
            $dataHora,
            $observacao
        );


        if ($dataHoraFormatada === null) {

            $sql = "INSERT INTO movimentacao
                    (tipo, unidade_destino_id, observacao, usuario_id)
                    VALUES ('Entrada', ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                throw new Exception(
                    "Erro ao preparar movimentação."
                );
            }

            $stmt->bind_param(
                "isi",
                $unidadeDestino,
                $observacao,
                $usuario_id
            );

        } else {

            $sql = "INSERT INTO movimentacao
                    (tipo, data_hora, unidade_destino_id, observacao, usuario_id)
                    VALUES ('Entrada', ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                throw new Exception(
                    "Erro ao preparar movimentação."
                );
            }

            $stmt->bind_param(
                "sisi",
                $dataHoraFormatada,
                $unidadeDestino,
                $observacao,
                $usuario_id
            );
        }

        if (!$stmt->execute()) {
            throw new Exception(
                "Erro ao criar movimentação."
            );
        }

        $movimentacao_id = (int) $conn->insert_id;

        $stmt->close();


        $sqlItem = "INSERT INTO item_lancamento
                    (movimentacao_id, produto_id, quantidade)
                    VALUES (?, ?, ?)";

        $stmtItem = $conn->prepare($sqlItem);

        if (!$stmtItem) {
            throw new Exception(
                "Erro ao preparar item."
            );
        }


        $sqlEstoque = "UPDATE produto
                       SET estoque = estoque + ?
                       WHERE id_produto = ?";

        $stmtEstoque = $conn->prepare($sqlEstoque);

        if (!$stmtEstoque) {
            throw new Exception(
                "Erro ao preparar estoque."
            );
        }


        $logsParaRegistrar = [];


        foreach ($produtos as $produto) {

            $produto_id = (int) ($produto['produto_id'] ?? 0);
            $quantidade = (int) ($produto['quantidade'] ?? 0);

            if ($quantidade <= 0) {
                throw new Exception(
                    "A quantidade deve ser maior que zero."
                );
            }


            $sqlProduto = "SELECT id_produto, nome, unidade
                           FROM produto
                           WHERE id_produto = ?";

            $stmtProduto = $conn->prepare($sqlProduto);

            if (!$stmtProduto) {
                throw new Exception(
                    "Erro ao consultar produto."
                );
            }

            $stmtProduto->bind_param(
                "i",
                $produto_id
            );

            $stmtProduto->execute();

            $resultado = $stmtProduto->get_result();


            if ($resultado->num_rows === 0) {

                $stmtProduto->close();

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
                'descricao' =>
                    "Entrada de {$quantidade} " .
                    "{$dadosProduto['unidade']} de " .
                    "{$dadosProduto['nome']}."
            ];
        }


        $stmtItem->close();
        $stmtEstoque->close();


        foreach ($logsParaRegistrar as $logItem) {

            if (
                !registrarLog(
                    $conn,
                    $logItem['acao'],
                    $logItem['descricao'],
                    $usuario_id
                )
            ) {
                throw new RuntimeException(
                    'Falha ao registrar auditoria.'
                );
            }
        }


        $conn->commit();


        return [
            'sucesso' => true,
            'id' => $movimentacao_id
        ];

    } catch (Throwable $e) {

        $conn->rollback();

        return [
            'sucesso' => false,
            'mensagem' =>
                $e instanceof mysqli_sql_exception
                    ? 'Não foi possível concluir o lançamento. Nenhuma alteração foi salva.'
                    : $e->getMessage()
        ];
    }
}


function lancamentoSaida(
    mysqli $conn,
    array $produtos,
    int $unidadeDestino,
    string $observacao,
    int $usuario_id,
    ?string $dataHora = null
): array {
    $conn->begin_transaction();

    try {

        if (!$produtos) {
            throw new DomainException(
                'Adicione pelo menos um produto.'
            );
        }


        $dataHoraFormatada = validarDataLancamento($dataHora);

        validarObservacaoData(
            $dataHora,
            $observacao
        );


        $nomeUnidade = 'Unidade de Saúde';


        $sqlUnidade = "SELECT nome
                       FROM unidade_saude
                       WHERE id_unidade = ?
                       AND ativo = 1
                       AND nome <> 'Secretaria de Saúde'";

        $stmtUni = $conn->prepare($sqlUnidade);

        if (!$stmtUni) {
            throw new Exception(
                'Erro ao consultar unidade de saúde.'
            );
        }


        $stmtUni->bind_param(
            "i",
            $unidadeDestino
        );

        $stmtUni->execute();

        $resUni = $stmtUni->get_result();


        if ($linhaUni = $resUni->fetch_assoc()) {

            $nomeUnidade = (string) $linhaUni['nome'];

        } else {

            $stmtUni->close();

            throw new DomainException(
                'Selecione uma unidade de saúde ativa de destino.'
            );
        }

        $stmtUni->close();


        if ($dataHoraFormatada === null) {

            $sql = "INSERT INTO movimentacao
                    (tipo, unidade_destino_id, observacao, usuario_id)
                    VALUES ('Saida', ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                throw new Exception(
                    "Erro ao preparar movimentação."
                );
            }

            $stmt->bind_param(
                "isi",
                $unidadeDestino,
                $observacao,
                $usuario_id
            );

        } else {

            $sql = "INSERT INTO movimentacao
                    (tipo, data_hora, unidade_destino_id, observacao, usuario_id)
                    VALUES ('Saida', ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                throw new Exception(
                    "Erro ao preparar movimentação."
                );
            }

            $stmt->bind_param(
                "sisi",
                $dataHoraFormatada,
                $unidadeDestino,
                $observacao,
                $usuario_id
            );
        }


        if (!$stmt->execute()) {
            throw new Exception(
                "Erro ao criar movimentação."
            );
        }


        $movimentacao_id = (int) $conn->insert_id;

        $stmt->close();


        $sqlItem = "INSERT INTO item_lancamento
                    (movimentacao_id, produto_id, quantidade)
                    VALUES (?, ?, ?)";

        $stmtItem = $conn->prepare($sqlItem);

        if (!$stmtItem) {
            throw new Exception(
                "Erro ao preparar item."
            );
        }


        $sqlEstoque = "UPDATE produto
                       SET estoque = estoque - ?
                       WHERE id_produto = ?
                       AND estoque >= ?";

        $stmtEstoque = $conn->prepare($sqlEstoque);

        if (!$stmtEstoque) {
            throw new Exception(
                "Erro ao preparar estoque."
            );
        }


        $logsParaRegistrar = [];


        foreach ($produtos as $produto) {

            $produto_id = (int) ($produto['produto_id'] ?? 0);
            $quantidade = (int) ($produto['quantidade'] ?? 0);


            if ($quantidade <= 0) {
                throw new Exception(
                    "A quantidade deve ser maior que zero."
                );
            }


            $sqlProduto = "SELECT
                                id_produto,
                                nome,
                                unidade,
                                estoque
                           FROM produto
                           WHERE id_produto = ?";

            $stmtProduto = $conn->prepare($sqlProduto);

            if (!$stmtProduto) {
                throw new Exception(
                    "Erro ao consultar produto."
                );
            }


            $stmtProduto->bind_param(
                "i",
                $produto_id
            );

            $stmtProduto->execute();

            $resultado = $stmtProduto->get_result();


            if ($resultado->num_rows === 0) {

                $stmtProduto->close();

                throw new Exception(
                    "Produto ID $produto_id não encontrado."
                );
            }


            $dadosProduto = $resultado->fetch_assoc();

            $stmtProduto->close();


            if (
                $quantidade >
                (int) $dadosProduto['estoque']
            ) {

                throw new Exception(
                    "Estoque insuficiente para o produto " .
                    "'{$dadosProduto['nome']}'. " .
                    "Disponível: {$dadosProduto['estoque']}, " .
                    "Solicitado: {$quantidade}."
                );
            }


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
                    "Estoque insuficiente para o produto " .
                    "'{$dadosProduto['nome']}'."
                );
            }


            $logsParaRegistrar[] = [
                'acao' => 'Saída de produto',
                'descricao' =>
                    "Saída de {$quantidade} " .
                    "{$dadosProduto['unidade']} de " .
                    "{$dadosProduto['nome']} para " .
                    "{$nomeUnidade}."
            ];
        }


        $stmtItem->close();
        $stmtEstoque->close();


        foreach ($logsParaRegistrar as $logItem) {

            if (
                !registrarLog(
                    $conn,
                    $logItem['acao'],
                    $logItem['descricao'],
                    $usuario_id
                )
            ) {
                throw new RuntimeException(
                    'Falha ao registrar auditoria.'
                );
            }
        }


        $conn->commit();


        return [
            'sucesso' => true,
            'id' => $movimentacao_id
        ];

    } catch (Throwable $e) {

        $conn->rollback();

        return [
            'sucesso' => false,
            'mensagem' =>
                $e instanceof mysqli_sql_exception
                    ? 'Não foi possível concluir o lançamento. Nenhuma alteração foi salva.'
                    : $e->getMessage()
        ];
    }
}