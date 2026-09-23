<?php
declare(strict_types=1);
require_once __DIR__ . '/funcoes_logs.php';

function validarDadosUsuario(string $nome, string $email, string $senha, string $tipo, int $ativo, bool $senhaObrigatoria = false): void
{
    if (trim($nome) === '' || preg_match_all('/./us', $nome) > 100) throw new DomainException('Informe um nome com até 100 caracteres.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) throw new DomainException('Informe um e-mail válido com até 100 caracteres.');
    if (!in_array($tipo, ['Administrador', 'Suporte', 'Usuario'], true)) throw new DomainException('Perfil inválido.');
    if (!in_array($ativo, [0, 1], true)) throw new DomainException('Status inválido.');
    if (($senhaObrigatoria || $senha !== '') && (strlen($senha) < 8 || strlen($senha) > 72)) throw new DomainException('A senha deve ter entre 8 e 72 bytes (letras acentuadas podem ocupar mais de um byte).');
}

function erroUsuario(Throwable $e): array
{
    if ($e instanceof DomainException) return ['sucesso' => false, 'mensagem' => $e->getMessage()];
    if ($e instanceof mysqli_sql_exception && $e->getCode() === 1062) return ['sucesso' => false, 'mensagem' => 'Este e-mail já está cadastrado, inclusive entre as contas desativadas.'];
    error_log((string) $e);
    return ['sucesso' => false, 'mensagem' => 'Não foi possível concluir a operação. Nenhuma alteração foi salva.'];
}

// Ordem única de bloqueio para serializar mudanças de acesso e proteger o último administrador.
function bloquearUsuarios(mysqli $conn): array
{
    $resultado = $conn->query('SELECT * FROM usuario ORDER BY id_usuario FOR UPDATE');
    $usuarios = [];
    while ($u = $resultado->fetch_assoc()) $usuarios[(int) $u['id_usuario']] = $u;
    return $usuarios;
}

function autorUsuario(array $usuarios, bool $admin): array
{
    $autor = $usuarios[(int) ($_SESSION['id_usuario'] ?? 0)] ?? null;
    if (!$autor || (int) $autor['ativo'] !== 1 || (int) $autor['versao_sessao'] !== (int) ($_SESSION['versao_sessao'] ?? -1)
        || ($admin && $autor['tipo'] !== 'Administrador')) throw new DomainException('Seu acesso mudou. Entre novamente no sistema.');
    return $autor;
}

function cadastrarUsuario(mysqli $conn, string $nome, string $email, string $senha, string $tipo): array
{
    $nome = trim($nome); $email = trim($email);
    try {
        validarDadosUsuario($nome, $email, $senha, $tipo, 1, true);
        $conn->begin_transaction();
        $usuarios = bloquearUsuarios($conn);
        $autor = autorUsuario($usuarios, true);
        $stmt = $conn->prepare('SELECT ativo FROM usuario WHERE email = ?');
        $stmt->bind_param('s', $email); $stmt->execute();
        $existente = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($existente) throw new DomainException((int) $existente['ativo'] === 0 ? 'Conta desativada já existente. Use a lista de desativados para reativá-la.' : 'Este e-mail já está cadastrado.');
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('INSERT INTO usuario (nome,email,senha,tipo,ativo) VALUES (?,?,?,?,1)');
        $stmt->bind_param('ssss', $nome, $email, $hash, $tipo); $stmt->execute();
        $id = (int) $conn->insert_id; $stmt->close();
        if (!registrarLog($conn, 'Cadastro de usuário', "Conta ID $id cadastrada como $tipo.", (int) $autor['id_usuario'])) throw new RuntimeException('Falha na auditoria.');
        $conn->commit();
        return ['sucesso' => true, 'id' => $id, 'mensagem' => 'Usuário cadastrado com sucesso.'];
    } catch (Throwable $e) { $conn->rollback(); return erroUsuario($e); }
}

function contarUsuarios(mysqli $conn, string $status = 'ativos'): int
{
    $where = ($status === 'inativos') ? 'WHERE ativo = 0' : (($status === 'todos') ? '' : 'WHERE ativo = 1');
    $sql = "SELECT COUNT(*) AS total FROM usuario $where";
    $res = $conn->query($sql);
    if ($res) {
        $row = $res->fetch_assoc();
        return (int) ($row['total'] ?? 0);
    }
    return 0;
}

function listarUsuarios(mysqli $conn, string|bool $status = 'ativos'): void
{
    if (is_bool($status)) {
        $status = $status ? 'ativos' : 'todos';
    }

    if ($status === 'inativos') {
        $sql = "SELECT id_usuario, nome, email, senha, tipo, ativo FROM usuario WHERE ativo = 0 ORDER BY nome";
    } elseif ($status === 'todos') {
        $sql = "SELECT id_usuario, nome, email, senha, tipo, ativo FROM usuario ORDER BY nome";
    } else {
        $sql = "SELECT id_usuario, nome, email, senha, tipo, ativo FROM usuario WHERE ativo = 1 ORDER BY nome";
    }

    $resultado = $conn->query($sql);

    if (!$resultado) {
        die("Erro na consulta: " . $conn->error);
    }

    if ($resultado->num_rows === 0) {
        $msg = ($status === 'inativos') ? 'Nenhum usuário desativado encontrado.' : 'Nenhum usuário ativo cadastrado.';
        echo "<tr><td colspan='6' style='text-align: center; color: var(--texto-suave); padding: 24px;'>" . $msg . "</td></tr>";
        return;
    }

    while ($usuario = $resultado->fetch_assoc()) {
        $idUser = (int) $usuario['id_usuario'];
        $nomeUser = htmlspecialchars((string) $usuario['nome'], ENT_QUOTES, 'UTF-8');
        $emailUser = htmlspecialchars((string) $usuario['email'], ENT_QUOTES, 'UTF-8');
        $tipoUser = htmlspecialchars((string) $usuario['tipo'], ENT_QUOTES, 'UTF-8');
        $estaAtivo = ((int) ($usuario['ativo'] ?? 1)) === 1;

        echo "<tr>";
        echo "<td>" . $idUser . "</td>";
        echo "<td>" . $nomeUser . "</td>";
        echo "<td>" . $emailUser . "</td>";
        echo "<td>••••••••</td>";
        echo "<td>" . $tipoUser . "</td>";

        echo "<td>
        <div class='tabela-acoes'>
            <button type='button'
                class='botao botao--secundario botao--pequeno'
                onclick=\"window.location.href='editar_usuario.php?id={$idUser}'\">
                Editar
            </button>";

        $csrf = campoCsrf();
        if ($estaAtivo) {
            echo "<form method='POST'>{$csrf}
                <input type='hidden' name='excluir_id' value='{$idUser}'>
                <button type='submit' class='botao botao--perigo botao--pequeno' onclick=\"return confirm('Deseja realmente desativar este usuário?')\">
                    Desativar
                </button>
            </form>";
        } else {
            echo "<form method='POST'>{$csrf}
                <input type='hidden' name='reativar_id' value='{$idUser}'>
                <button type='submit' class='botao botao--sucesso botao--pequeno' onclick=\"return confirm('Deseja realmente reativar este usuário?')\">
                    Reativar
                </button>
            </form>";
        }

        echo "</div>
        </td>";
        echo "</tr>";
    }
}

function buscarUsuarioPorId(mysqli $conn, int $id): ?array
{
    $sql = "SELECT id_usuario, nome, email, senha, tipo, ativo, versao_sessao
            FROM usuario
            WHERE id_usuario = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Erro ao preparar consulta: " . $conn->error);
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $resultado = $stmt->get_result();
    $usuario = $resultado->fetch_assoc();
    $stmt->close();

    return $usuario ?: null;
}

function alterarConta(mysqli $conn, int $id, array $dados, bool $perfil = false): array
{
    try {
        $conn->begin_transaction();
        $usuarios = bloquearUsuarios($conn);
        $autor = autorUsuario($usuarios, !$perfil);
        if ($perfil && $id !== (int) $autor['id_usuario']) throw new DomainException('Acesso negado.');
        $antigo = $usuarios[$id] ?? null;
        if (!$antigo) throw new DomainException('Usuário não encontrado.');
        $nome = trim($dados['nome'] ?? $antigo['nome']);
        $email = trim($dados['email'] ?? $antigo['email']);
        $tipo = $perfil ? $antigo['tipo'] : ($dados['tipo'] ?? $antigo['tipo']);
        $ativo = $perfil ? (int) $antigo['ativo'] : ($dados['ativo'] ?? (int) $antigo['ativo']);
        $senha = $dados['senha'] ?? '';
        validarDadosUsuario($nome, $email, $senha, $tipo, $ativo);
        if (!$perfil && $id === (int) $autor['id_usuario'] && $ativo === 0) throw new DomainException('Você não pode desativar sua própria conta.');
        $admins = count(array_filter($usuarios, fn($u) => $u['tipo'] === 'Administrador' && (int) $u['ativo'] === 1));
        if ($antigo['tipo'] === 'Administrador' && (int) $antigo['ativo'] === 1 && ($tipo !== 'Administrador' || $ativo === 0) && $admins <= 1)
            throw new DomainException('É necessário manter pelo menos um administrador ativo.');
        $versao = (int) $antigo['versao_sessao'];
        if ($senha !== '' || $ativo !== (int) $antigo['ativo']) $versao++;
        $hash = $senha !== '' ? password_hash($senha, PASSWORD_DEFAULT) : $antigo['senha'];
        $stmt = $conn->prepare('UPDATE usuario SET nome=?, email=?, senha=?, tipo=?, ativo=?, versao_sessao=? WHERE id_usuario=?');
        $stmt->bind_param('ssssiii', $nome, $email, $hash, $tipo, $ativo, $versao, $id); $stmt->execute(); $stmt->close();
        $eventos = [];
        if ($ativo !== (int) $antigo['ativo']) $eventos[] = $ativo === 1 ? 'Reativação de usuário' : 'Desativação de usuário';
        if ($tipo !== $antigo['tipo']) $eventos[] = 'Alteração de perfil: ' . $antigo['tipo'] . ' para ' . $tipo;
        if ($senha !== '') $eventos[] = $perfil ? 'Troca da própria senha' : 'Redefinição de senha pelo administrador';
        if ($nome !== $antigo['nome'] || $email !== $antigo['email']) $eventos[] = 'Atualização cadastral';
        foreach ($eventos as $evento) {
            if (!registrarLog($conn, $evento, "Conta afetada: ID $id.", (int) $autor['id_usuario'])) throw new RuntimeException('Falha na auditoria.');
        }
        $conn->commit();
        if ($perfil) {
            $_SESSION['versao_sessao'] = $versao;
            $_SESSION['nome'] = $nome; $_SESSION['email'] = $email;
            if ($senha !== '' && session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
        } elseif ($id === (int) $autor['id_usuario']) {
            $_SESSION['tipo'] = $tipo;
        }
        return ['sucesso' => true, 'mensagem' => 'Usuário atualizado com sucesso.'];
    } catch (Throwable $e) { $conn->rollback(); return erroUsuario($e); }
}

function atualizarUsuario(mysqli $conn, int $id, string $nome, string $email, string $senha, string $tipo, ?int $ativo = null): array
{
    return alterarConta($conn, $id, compact('nome', 'email', 'senha', 'tipo', 'ativo'));
}
function atualizarPerfilUsuario(mysqli $conn, int $id, string $nome, string $email, string $senha = ''): array
{
    return alterarConta($conn, $id, compact('nome', 'email', 'senha'), true);
}
function excluirUsuario(mysqli $conn, int $id): array { return alterarConta($conn, $id, ['ativo' => 0]); }
function desativarUsuario(mysqli $conn, int $id): array { return excluirUsuario($conn, $id); }
function reativarUsuario(mysqli $conn, int $id): array { return alterarConta($conn, $id, ['ativo' => 1]); }
