<?php

declare(strict_types=1);
require_once __DIR__ . '/funcoes_logs.php';

function listarUnidades($conn, string $status = 'ativos'): void
{
    $ativo = $status === 'inativos' ? 0 : 1;

    $sql = "SELECT id_unidade, nome, endereco, telefone, ativo, (nome = 'Secretaria de Saúde') AS central
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

            if (!unidadeCentral($unidade)) echo '<form
                    method="POST"
                    class="formulario-excluir"
                  >

                    ' . campoCsrf() . '

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

                    ' . campoCsrf() . '

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


function unidadeCentral(array $unidade): bool
{
    return (int) ($unidade['central'] ?? 0) === 1;
}

function buscarUnidadePorId(mysqli $conn, int $id, bool $bloquear = false): ?array
{
    $stmt = $conn->prepare("SELECT *, (nome = 'Secretaria de Saúde') AS central FROM unidade_saude WHERE id_unidade = ?" . ($bloquear ? ' FOR UPDATE' : ''));
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $unidade = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $unidade ?: null;
}

function salvarUnidade(mysqli $conn, ?int $id, array $dados): array
{
    $transacao = false;
    try {
        $conn->begin_transaction();
        $transacao = true;
        $autor = (int) ($_SESSION['id_usuario'] ?? 0);
        $stmt = $conn->prepare('SELECT tipo, ativo, versao_sessao FROM usuario WHERE id_usuario = ? FOR UPDATE');
        $stmt->bind_param('i', $autor);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$usuario || $usuario['tipo'] !== 'Administrador' || (int) $usuario['ativo'] !== 1
            || (int) $usuario['versao_sessao'] !== (int) ($_SESSION['versao_sessao'] ?? -1)) {
            throw new DomainException('Seu acesso não permite administrar unidades de saúde.');
        }
        $antiga = $id === null ? null : buscarUnidadePorId($conn, $id, true);
        if ($id !== null && !$antiga) throw new DomainException('Unidade de saúde não encontrada.');
        if (isset($dados['ativo'])) {
            $ativo = $dados['ativo'];
            if (!in_array($ativo, [0, 1], true)) throw new DomainException('Status inválido.');
            if (unidadeCentral($antiga) && $ativo === 0) throw new DomainException('A Secretaria de Saúde não pode ser desativada.');
            if ((int) $antiga['ativo'] === $ativo) throw new DomainException($ativo ? 'A unidade já está ativa.' : 'A unidade já está desativada.');
            $stmt = $conn->prepare('UPDATE unidade_saude SET ativo = ? WHERE id_unidade = ?');
            $stmt->bind_param('ii', $ativo, $id);
            $acao = $ativo ? 'Reativação de unidade' : 'Desativação de unidade';
        } else {
            $nome = trim($dados['nome']);
            $endereco = trim($dados['endereco']);
            $telefone = trim($dados['telefone']);
            foreach (['Nome' => [$nome, 100], 'Endereço' => [$endereco, 255], 'Telefone' => [$telefone, 20]] as $campo => [$valor, $limite]) {
                $tamanho = preg_match_all('/./us', $valor);
                if ($tamanho === false || $tamanho > $limite) throw new DomainException("$campo deve ter até $limite caracteres.");
            }
            if ($nome === '') throw new DomainException('O nome da unidade é obrigatório.');
            if ($antiga && unidadeCentral($antiga) && $nome !== $antiga['nome']) {
                throw new DomainException('O nome da Secretaria de Saúde não pode ser alterado.');
            }
            if ($id === null) {
                $stmt = $conn->prepare('INSERT INTO unidade_saude(nome, endereco, telefone, ativo) VALUES (?, ?, ?, 1)');
                $stmt->bind_param('sss', $nome, $endereco, $telefone);
                $acao = 'Cadastro de unidade';
            } else {
                $stmt = $conn->prepare('UPDATE unidade_saude SET nome = ?, endereco = ?, telefone = ? WHERE id_unidade = ?');
                $stmt->bind_param('sssi', $nome, $endereco, $telefone, $id);
                $acao = 'Atualização de unidade';
            }
        }
        $stmt->execute();
        if ($id === null) $id = (int) $conn->insert_id;
        $stmt->close();
        if (!registrarLog($conn, $acao, "Unidade afetada: ID $id.", $autor)) throw new RuntimeException('Falha de auditoria.');
        $conn->commit();
        return ['sucesso' => true, 'mensagem' => $acao . ' realizada com sucesso.', 'id' => $id];
    } catch (Throwable $e) {
        if ($transacao) $conn->rollback();
        if ($e instanceof DomainException) $mensagem = $e->getMessage();
        elseif ($e instanceof mysqli_sql_exception && $e->getCode() === 1062) $mensagem = 'Já existe uma unidade com esse nome, inclusive entre as desativadas.';
        else {
            error_log((string) $e);
            $mensagem = 'Não foi possível salvar a unidade. Nenhuma alteração foi salva.';
        }
        return ['sucesso' => false, 'mensagem' => $mensagem];
    }
}

function cadastrarUnidade(mysqli $conn, string $nome, string $endereco = '', string $telefone = ''): array
{
    return salvarUnidade($conn, null, compact('nome', 'endereco', 'telefone'));
}

function atualizarUnidade(mysqli $conn, int $id, string $nome, string $endereco, string $telefone): array
{
    return salvarUnidade($conn, $id, compact('nome', 'endereco', 'telefone'));
}

function desativarUnidade(mysqli $conn, int $id): array
{
    return salvarUnidade($conn, $id, ['ativo' => 0]);
}

function reativarUnidade(mysqli $conn, int $id): array
{
    return salvarUnidade($conn, $id, ['ativo' => 1]);
}
