<?php

declare(strict_types=1);

function listarUnidades($conn, string $status = 'ativos'): void
{
    $ativo = $status === 'inativos' ? 0 : 1;

    $sql = "SELECT id_unidade, nome, endereco, telefone, ativo
            FROM unidade_saude
            WHERE ativo = ?
            ORDER BY nome";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo '<tr><td colspan="5">Erro ao consultar unidades de saúde.</td></tr>';
        return;
    }

    $stmt->bind_param('i', $ativo);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {
        echo '<tr><td colspan="5">Nenhuma unidade de saúde encontrada.</td></tr>';
        $stmt->close();
        return;
    }

    while ($unidade = $resultado->fetch_assoc()) {

        echo '<tr>';

        echo '<td>'
            . (int) $unidade['id_unidade']
            . '</td>';

        echo '<td>'
            . htmlspecialchars(
                (string) $unidade['nome'],
                ENT_QUOTES,
                'UTF-8'
            )
            . '</td>';

        echo '<td>'
            . htmlspecialchars(
                (string) ($unidade['endereco'] ?? '-'),
                ENT_QUOTES,
                'UTF-8'
            )
            . '</td>';

        echo '<td>'
            . htmlspecialchars(
                (string) ($unidade['telefone'] ?? '-'),
                ENT_QUOTES,
                'UTF-8'
            )
            . '</td>';

        echo '<td class="acoes-tabela">';

        if ($ativo === 1) {

            echo '<a
                    href="editar_unidade.php?id=' . (int) $unidade['id_unidade'] . '"
                    class="botao botao--secundario botao--pequeno"
                  >
                    Editar
                  </a>';

            echo '<form
                    method="POST"
                    class="formulario-excluir"
                  >

                    <?= campoCsrf() ?>

                    <input
                        type="hidden"
                        name="desativar_id"
                        value="' . (int) $unidade['id_unidade'] . '"
                    >

                    <button
                        type="submit"
                        class="botao botao--perigo botao--pequeno"
                        onclick="return confirm(\'Deseja realmente desativar esta unidade de saúde?\')"
                    >
                        Desativar
                    </button>

                  </form>';

        } else {

            echo '<form
                    method="POST"
                    class="formulario-excluir"
                  >

                    <?= campoCsrf() ?>

                    <input
                        type="hidden"
                        name="reativar_id"
                        value="' . (int) $unidade['id_unidade'] . '"
                    >

                    <button
                        type="submit"
                        class="botao botao--primario botao--pequeno"
                        onclick="return confirm(\'Deseja reativar esta unidade de saúde?\')"
                    >
                        Reativar
                    </button>

                  </form>';
        }

        echo '</td>';

        echo '</tr>';
    }

    $stmt->close();
}


function contarUnidades($conn, string $status = 'ativos'): int
{
    $ativo = $status === 'inativos' ? 0 : 1;

    $sql = "SELECT COUNT(*) AS total
            FROM unidade_saude
            WHERE ativo = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $ativo);
    $stmt->execute();

    $resultado = $stmt->get_result();
    $dados = $resultado->fetch_assoc();

    $stmt->close();

    return (int) ($dados['total'] ?? 0);
}


function desativarUnidade($conn, int $id): array
{
    $sql = "UPDATE unidade_saude
            SET ativo = FALSE
            WHERE id_unidade = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [
            'sucesso' => false,
            'mensagem' => 'Não foi possível desativar a unidade.'
        ];
    }

    $stmt->bind_param('i', $id);

    $sucesso = $stmt->execute();

    $stmt->close();

    return $sucesso
        ? [
            'sucesso' => true,
            'mensagem' => 'Unidade de saúde desativada com sucesso.'
        ]
        : [
            'sucesso' => false,
            'mensagem' => 'Não foi possível desativar a unidade.'
        ];
}


function reativarUnidade($conn, int $id): array
{
    $sql = "UPDATE unidade_saude
            SET ativo = TRUE
            WHERE id_unidade = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [
            'sucesso' => false,
            'mensagem' => 'Não foi possível reativar a unidade.'
        ];
    }

    $stmt->bind_param('i', $id);

    $sucesso = $stmt->execute();

    $stmt->close();

    return $sucesso
        ? [
            'sucesso' => true,
            'mensagem' => 'Unidade de saúde reativada com sucesso.'
        ]
        : [
            'sucesso' => false,
            'mensagem' => 'Não foi possível reativar a unidade.'
        ];
}

function cadastrarUnidade($conn, string $nome, string $endereco = '', string $telefone = ''): array
{
$nome = trim($nome);
$endereco = trim($endereco);
$telefone = trim($telefone);

if ($nome === '') {
    return [
        'sucesso' => false,
        'mensagem' => 'O nome da unidade é obrigatório.'
    ];
}

// Verifica se já existe uma unidade com esse nome
$sql = "SELECT id_unidade
        FROM unidade_saude
        WHERE nome = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    return [
        'sucesso' => false,
        'mensagem' => 'Não foi possível verificar a unidade.'
    ];
}

$stmt->bind_param('s', $nome);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {

    $stmt->close();

    return [
        'sucesso' => false,
        'mensagem' => 'Já existe uma unidade de saúde com esse nome.'
    ];
}

$stmt->close();


// Cadastra a unidade como ativa
$sql = "INSERT INTO unidade_saude
            (nome, endereco, telefone, ativo)
        VALUES
            (?, ?, ?, TRUE)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    return [
        'sucesso' => false,
        'mensagem' => 'Não foi possível preparar o cadastro.'
    ];
}

$stmt->bind_param(
    'sss',
    $nome,
    $endereco,
    $telefone
);

$sucesso = $stmt->execute();

$stmt->close();

if (!$sucesso) {
    return [
        'sucesso' => false,
        'mensagem' => 'Não foi possível cadastrar a unidade.'
    ];
}

return [
    'sucesso' => true,
    'mensagem' => 'Unidade de saúde cadastrada com sucesso.',
    'id' => $conn->insert_id
];

}
