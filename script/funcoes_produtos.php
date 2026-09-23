<?php

declare(strict_types=1);

require_once __DIR__ . "/funcoes_logs.php";

function buscarCategorias(mysqli $conn): array
{
    $sql = 'SELECT id_categoria, nome FROM categoria ORDER BY nome';
    $resultado = $conn->query($sql);

    if (!$resultado) {
        return [];
    }

    return $resultado->fetch_all(MYSQLI_ASSOC);
}

function buscarUnidades(mysqli $conn): array
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

function criarCategoria(mysqli $conn, string $nome): array
{
    $nome = trim($nome);

    if ($nome === '') {
        return ['sucesso' => false, 'mensagem' => 'Informe o nome da nova categoria.'];
    }

    $sql = 'SELECT id_categoria FROM categoria WHERE nome = ?';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao preparar consulta.'];
    }

    $stmt->bind_param('s', $nome);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        return ['sucesso' => false, 'mensagem' => 'Esta categoria já está cadastrada.'];
    }

    $stmt->close();

    $sql = 'INSERT INTO categoria (nome) VALUES (?)';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao preparar cadastro da categoria.'];
    }

    $stmt->bind_param('s', $nome);

    if (!$stmt->execute()) {
        $stmt->close();
        return ['sucesso' => false, 'mensagem' => 'Não foi possível cadastrar a categoria.'];
    }

    $idCategoria = (int) $stmt->insert_id;
    $stmt->close();

    return ['sucesso' => true, 'id' => $idCategoria];
}

function produtoJaExiste(mysqli $conn, string $nome, int $categoriaId, string $unidade): bool
{
    $sql = 'SELECT id_produto
            FROM produto
            WHERE nome = ? AND categoria_id = ? AND unidade = ?';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('sis', $nome, $categoriaId, $unidade);
    $stmt->execute();

    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $existe;
}

function unidadeJaExiste(mysqli $conn, string $unidade): bool
{
    $sql = 'SELECT id_produto FROM produto WHERE unidade = ? LIMIT 1';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $unidade);
    $stmt->execute();

    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $existe;
}

function cadastrarProduto(
    mysqli $conn,
    string $nome,
    int $categoriaId,
    string $unidade,
    int|string $estoque,
    int|string $estoqueMinimo
): array {
    $nome = trim($nome);
    $unidade = trim($unidade);
    $estoqueInt = (int) $estoque;
    $estoqueMinimoInt = (int) $estoqueMinimo;

    if (produtoJaExiste($conn, $nome, $categoriaId, $unidade)) {
        return ['sucesso' => false, 'mensagem' => 'Este produto já existe com essa categoria e unidade.'];
    }

    $sql = 'INSERT INTO produto
            (nome, unidade, estoque, estoque_minimo, categoria_id)
            VALUES (?, ?, ?, ?, ?)';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return ['sucesso' => false, 'mensagem' => 'Não foi possível preparar o cadastro do produto.'];
    }

    $stmt->bind_param('ssiii', $nome, $unidade, $estoqueInt, $estoqueMinimoInt, $categoriaId);

    if (!$stmt->execute()) {
        $stmt->close();
        return ['sucesso' => false, 'mensagem' => 'Não foi possível cadastrar o produto.'];
    }

    $produtoId = (int) $conn->insert_id;
    $stmt->close();
    if ($estoqueInt > 0) {
        $autor = (int) $_SESSION['id_usuario'];
        $stmt = $conn->prepare("INSERT INTO movimentacao(tipo,observacao,usuario_id) VALUES ('Entrada','Saldo inicial no cadastro do produto',?)");
        $stmt->bind_param('i', $autor); $stmt->execute();
        $movimento = (int) $conn->insert_id; $stmt->close();
        $stmt = $conn->prepare('INSERT INTO item_lancamento(movimentacao_id,produto_id,quantidade) VALUES (?,?,?)');
        $stmt->bind_param('iii', $movimento, $produtoId, $estoqueInt); $stmt->execute(); $stmt->close();
    }
    return ['sucesso' => true];
}

function buscarProdutoPorId(mysqli $conn, int $id): ?array
{
    $sql = 'SELECT p.id_produto, p.nome, p.unidade, p.estoque, p.estoque_minimo,
                   p.categoria_id, c.nome AS categoria
            FROM produto p
            INNER JOIN categoria c ON c.id_categoria = p.categoria_id
            WHERE p.id_produto = ?';

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $produto = $resultado->fetch_assoc();
    $stmt->close();

    return $produto ?: null;
}

function atualizarProduto(
    mysqli $conn, int $id, string $nome, int $categoriaId, string $unidade,
    int|string $estoque, int|string $estoqueMinimo, string $justificativa = '', ?int $estoqueOriginal = null
): array {
    try {
        if (($_SESSION['tipo'] ?? '') !== 'Administrador') throw new DomainException('Acesso negado.');
        $nome = trim($nome); $unidade = trim($unidade); $justificativa = trim($justificativa);
        if ($nome === '' || $unidade === '' || preg_match_all('/./us', $nome) > 100 || preg_match_all('/./us', $unidade) > 20)
            throw new DomainException('Informe nome e unidade dentro dos limites permitidos.');
        if (filter_var($estoque, FILTER_VALIDATE_INT) === false || filter_var($estoqueMinimo, FILTER_VALIDATE_INT) === false || (int) $estoque < 0 || (int) $estoqueMinimo < 0)
            throw new DomainException('Estoque e mínimo devem ser inteiros não negativos.');
        $estoque = (int) $estoque; $estoqueMinimo = (int) $estoqueMinimo;
        $conn->begin_transaction();
        $stmt = $conn->prepare('SELECT estoque FROM produto WHERE id_produto=? FOR UPDATE');
        $stmt->bind_param('i', $id); $stmt->execute();
        $anterior = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$anterior) throw new DomainException('Produto não encontrado.');
        if ($estoqueOriginal === null || (int) $anterior['estoque'] !== $estoqueOriginal)
            throw new DomainException('O estoque mudou desde a abertura da tela. Atualize a página antes de salvar.');
        $diferenca = $estoque - (int) $anterior['estoque'];
        if ($diferenca !== 0 && $justificativa === '') throw new DomainException('Informe a justificativa para o ajuste de estoque.');
        $stmt = $conn->prepare('SELECT id_categoria FROM categoria WHERE id_categoria=?');
        $stmt->bind_param('i', $categoriaId); $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) throw new DomainException('Categoria inválida.');
        $stmt->close();
        $stmt = $conn->prepare('UPDATE produto SET nome=?,unidade=?,estoque=?,estoque_minimo=?,categoria_id=? WHERE id_produto=?');
        $stmt->bind_param('ssiiii', $nome, $unidade, $estoque, $estoqueMinimo, $categoriaId, $id); $stmt->execute(); $stmt->close();
        $autor = (int) $_SESSION['id_usuario'];
        if ($diferenca !== 0) {
            $tipo = $diferenca > 0 ? 'Entrada' : 'Saida';
            $observacao = 'Ajuste de inventário: ' . $justificativa;
            $stmt = $conn->prepare('INSERT INTO movimentacao(tipo,observacao,usuario_id) VALUES (?,?,?)');
            $stmt->bind_param('ssi', $tipo, $observacao, $autor); $stmt->execute();
            $movimento = (int) $conn->insert_id; $stmt->close();
            $qtd = abs($diferenca);
            $stmt = $conn->prepare('INSERT INTO item_lancamento(movimentacao_id,produto_id,quantidade) VALUES (?,?,?)');
            $stmt->bind_param('iii', $movimento, $id, $qtd); $stmt->execute(); $stmt->close();
        }
        $descricao = "Produto ID $id atualizado. Saldo: {$anterior['estoque']} para $estoque.";
        if ($diferenca !== 0) $descricao .= ' Justificativa: ' . $justificativa;
        if (!registrarLog($conn, $diferenca !== 0 ? 'Ajuste de estoque' : 'Edição de produto', $descricao, $autor)) throw new RuntimeException('Falha de auditoria.');
        $conn->commit();
        return ['sucesso'=>true, 'mensagem'=>'Produto atualizado com sucesso.'];
    } catch (Throwable $e) {
        $conn->rollback();
        if (!$e instanceof DomainException) error_log((string) $e);
        return ['sucesso'=>false, 'mensagem'=>$e instanceof DomainException ? $e->getMessage() : 'Não foi possível salvar o produto. Nenhuma alteração foi salva.'];
    }
}

function excluirProduto(mysqli $conn, int $id): array
{
    $sql = 'SELECT id_produto FROM produto WHERE id_produto = ?';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return ['sucesso' => false, 'mensagem' => 'Não foi possível verificar o produto.'];
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $produtoExiste = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$produtoExiste) {
        return ['sucesso' => false, 'mensagem' => 'Produto não encontrado.'];
    }

    $sql = 'SELECT id_item FROM item_lancamento WHERE produto_id = ? LIMIT 1';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return ['sucesso' => false, 'mensagem' => 'Não foi possível verificar o histórico do produto.'];
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $possuiHistorico = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($possuiHistorico) {
        return [
            'sucesso' => false,
            'mensagem' => 'Este produto não pode ser excluído porque possui movimentações registradas.'
        ];
    }

    $sql = 'DELETE FROM produto WHERE id_produto = ?';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return ['sucesso' => false, 'mensagem' => 'Não foi possível preparar a exclusão do produto.'];
    }

    $stmt->bind_param('i', $id);
    $sucesso = $stmt->execute();
    $stmt->close();

    return $sucesso
        ? ['sucesso' => true, 'mensagem' => 'Produto excluído com sucesso.']
        : ['sucesso' => false, 'mensagem' => 'Não foi possível excluir o produto.'];
}

function listarProdutos(mysqli $conn, bool $mostrarEstoqueMinimo = false): void
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
        $idProd = (int) $produto['id_produto'];
        $nomeProd = htmlspecialchars((string) $produto['nome'], ENT_QUOTES, 'UTF-8');
        $catProd = htmlspecialchars((string) $produto['categoria'], ENT_QUOTES, 'UTF-8');
        $uniProd = htmlspecialchars((string) $produto['unidade'], ENT_QUOTES, 'UTF-8');
        $estProd = (int) $produto['estoque'];

        echo '<tr data-nome="' . $nomeProd . '" data-categoria="' . $catProd . '" data-unidade="' . $uniProd . '">';
        echo '<td>' . $idProd . '</td>';
        echo '<td>' . $nomeProd . '</td>';
        echo '<td>' . $catProd . '</td>';
        echo '<td>' . $uniProd . '</td>';
        echo '<td>' . $estProd . '</td>';

        if ($mostrarEstoqueMinimo) {
            $estMin = (int) $produto['estoque_minimo'];
            echo '<td>' . $estMin . '</td>';
            echo '<td>';
            echo '<a class="botao botao--secundario" href="editar_produto.php?id=' . $idProd . '">Editar</a>';
            echo '</td>';
        }

        echo '</tr>';
    }
}
