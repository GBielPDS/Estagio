<?php

declare(strict_types=1);

function cadastrarUsuario(
    mysqli $conn,
    string $nome,
    string $email,
    string $senha,
    string $tipo,
    bool $reativarSeDesativado = true
): array {
    $email = trim($email);
    $nome = trim($nome);

    $sql = 'SELECT id_usuario, nome, tipo, ativo FROM usuario WHERE email = ?';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao verificar o email.'];
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $usuarioExistente = $resultado->fetch_assoc();
    $stmt->close();

    if ($usuarioExistente) {
        $estaAtivo = ((int) $usuarioExistente['ativo']) === 1;

        if ($estaAtivo) {
            return [
                'sucesso' => false,
                'mensagem' => 'Este email já está cadastrado.',
                'codigo' => 'JA_CADASTRADO'
            ];
        }

        if ($reativarSeDesativado) {
            $resReativacao = reativarUsuarioComNovaSenha($conn, $email, $senha, $nome, $tipo);
            if ($resReativacao['sucesso']) {
                return [
                    'sucesso' => true,
                    'id' => (int) $usuarioExistente['id_usuario'],
                    'nome' => (string) ($resReativacao['nome'] ?? $nome),
                    'reativado' => true,
                    'mensagem' => 'Usuário já existia e foi reativado com sucesso com a nova senha de acesso.'
                ];
            }
            return $resReativacao;
        }

        return [
            'sucesso' => false,
            'mensagem' => 'Este e-mail pertence a um usuário desativado.',
            'desativado' => true,
            'id' => (int) $usuarioExistente['id_usuario'],
            'codigo' => 'USUARIO_DESATIVADO'
        ];
    }

    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
    $sql = 'INSERT INTO usuario (nome, email, senha, tipo, ativo) VALUES (?, ?, ?, ?, 1)';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao preparar o cadastro.'];
    }

    $stmt->bind_param('ssss', $nome, $email, $senhaHash, $tipo);
    $sucesso = $stmt->execute();
    $novoId = (int) $conn->insert_id;
    $stmt->close();

    return $sucesso
        ? ['sucesso' => true, 'id' => $novoId, 'nome' => $nome, 'reativado' => false]
        : ['sucesso' => false, 'mensagem' => 'Erro ao realizar o cadastro.'];
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

        if ($estaAtivo) {
            echo "<form method='POST'>
                <input type='hidden' name='excluir_id' value='{$idUser}'>
                <button type='submit' class='botao botao--perigo botao--pequeno' onclick=\"return confirm('Deseja realmente desativar este usuário?')\">
                    Excluir
                </button>
            </form>";
        } else {
            echo "<form method='POST'>
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
    $sql = "SELECT id_usuario, nome, email, senha, tipo, ativo
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

function atualizarUsuario(
    mysqli $conn,
    int $id,
    string $nome,
    string $email,
    string $senha,
    string $tipo,
    ?int $ativo = null
): bool {
    $usuarioAntigo = buscarUsuarioPorId($conn, $id);

    if (!$usuarioAntigo) {
        return false;
    }

    $ativoFinal = ($ativo !== null) ? $ativo : (int) ($usuarioAntigo['ativo'] ?? 1);

    if ($senha !== '') {
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

        $sql = "UPDATE usuario
                SET nome = ?, email = ?, senha = ?, tipo = ?, ativo = ?
                WHERE id_usuario = ?";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            die("Erro ao preparar atualização: " . $conn->error);
        }

        $stmt->bind_param(
            "ssssii",
            $nome,
            $email,
            $senhaHash,
            $tipo,
            $ativoFinal,
            $id
        );
    } else {
        $sql = "UPDATE usuario
                SET nome = ?, email = ?, tipo = ?, ativo = ?
                WHERE id_usuario = ?";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            die("Erro ao preparar atualização: " . $conn->error);
        }

        $stmt->bind_param(
            "sssii",
            $nome,
            $email,
            $tipo,
            $ativoFinal,
            $id
        );
    }

    $sucesso = $stmt->execute();
    $stmt->close();

    return $sucesso;
}

function atualizarPerfilUsuario(
    mysqli $conn,
    int $id,
    string $nome,
    string $email,
    string $senha = ''
): array {
    $nome = trim($nome);
    $email = trim($email);

    if ($nome === '' || $email === '') {
        return ['sucesso' => false, 'mensagem' => 'Nome e e-mail são obrigatórios.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['sucesso' => false, 'mensagem' => 'Digite um e-mail válido.'];
    }

    $sql = 'SELECT id_usuario FROM usuario WHERE email = ? AND id_usuario <> ?';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return ['sucesso' => false, 'mensagem' => 'Não foi possível verificar o e-mail.'];
    }

    $stmt->bind_param('si', $email, $id);
    $stmt->execute();
    $emailEmUso = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($emailEmUso) {
        return ['sucesso' => false, 'mensagem' => 'Este e-mail já está cadastrado.'];
    }

    if ($senha !== '') {
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        $sql = 'UPDATE usuario SET nome = ?, email = ?, senha = ? WHERE id_usuario = ?';
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return ['sucesso' => false, 'mensagem' => 'Não foi possível preparar a atualização.'];
        }

        $stmt->bind_param('sssi', $nome, $email, $senhaHash, $id);
    } else {
        $sql = 'UPDATE usuario SET nome = ?, email = ? WHERE id_usuario = ?';
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return ['sucesso' => false, 'mensagem' => 'Não foi possível preparar a atualização.'];
        }

        $stmt->bind_param('ssi', $nome, $email, $id);
    }

    $sucesso = $stmt->execute();
    $stmt->close();

    return $sucesso
        ? ['sucesso' => true, 'mensagem' => 'Perfil atualizado com sucesso.']
        : ['sucesso' => false, 'mensagem' => 'Não foi possível atualizar o perfil.'];
}

function excluirUsuario(mysqli $conn, int $id): bool
{
    $sql = "UPDATE usuario SET ativo = 0 WHERE id_usuario = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Erro ao preparar desativação: " . $conn->error);
    }

    $stmt->bind_param("i", $id);
    $sucesso = $stmt->execute();
    $stmt->close();

    return $sucesso;
}

function desativarUsuario(mysqli $conn, int $id): bool
{
    return excluirUsuario($conn, $id);
}

function reativarUsuario(mysqli $conn, int $id): bool
{
    $sql = "UPDATE usuario SET ativo = 1 WHERE id_usuario = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Erro ao preparar reativação: " . $conn->error);
    }

    $stmt->bind_param("i", $id);
    $sucesso = $stmt->execute();
    $stmt->close();

    return $sucesso;
}

function reativarUsuarioComNovaSenha(
    mysqli $conn,
    string $email,
    string $senha,
    ?string $nome = null,
    ?string $tipo = null
): array {
    $email = trim($email);

    if ($email === '' || $senha === '') {
        return ['sucesso' => false, 'mensagem' => 'E-mail e nova senha são obrigatórios.'];
    }

    $sqlBusca = 'SELECT id_usuario, nome, tipo, ativo FROM usuario WHERE email = ?';
    $stmtBusca = $conn->prepare($sqlBusca);

    if (!$stmtBusca) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao consultar usuário.'];
    }

    $stmtBusca->bind_param('s', $email);
    $stmtBusca->execute();
    $resultado = $stmtBusca->get_result();
    $usuario = $resultado->fetch_assoc();
    $stmtBusca->close();

    if (!$usuario) {
        return ['sucesso' => false, 'mensagem' => 'Nenhum usuário encontrado com este e-mail.'];
    }

    $id = (int) $usuario['id_usuario'];
    $nomeFinal = ($nome !== null && trim($nome) !== '') ? trim($nome) : (string) $usuario['nome'];
    $tipoFinal = ($tipo !== null && in_array($tipo, ['Administrador', 'Suporte', 'Usuario'], true))
        ? $tipo
        : (string) $usuario['tipo'];
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    $sqlUpdate = 'UPDATE usuario SET nome = ?, senha = ?, tipo = ?, ativo = 1 WHERE id_usuario = ?';
    $stmtUpdate = $conn->prepare($sqlUpdate);

    if (!$stmtUpdate) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao preparar a reativação do usuário.'];
    }

    $stmtUpdate->bind_param('sssi', $nomeFinal, $senhaHash, $tipoFinal, $id);
    $sucesso = $stmtUpdate->execute();
    $stmtUpdate->close();

    return $sucesso
        ? [
            'sucesso' => true,
            'id' => $id,
            'nome' => $nomeFinal,
            'tipo' => $tipoFinal,
            'mensagem' => 'Usuário reativado com sucesso com a nova senha de acesso.'
        ]
        : ['sucesso' => false, 'mensagem' => 'Não foi possível reativar o usuário.'];
}

function recuperarSenhaUsuario(mysqli $conn, string $email, string $novaSenha): array
{
    $email = trim($email);

    if ($email === '' || $novaSenha === '') {
        return ['sucesso' => false, 'mensagem' => 'E-mail e nova senha são obrigatórios.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['sucesso' => false, 'mensagem' => 'Digite um e-mail válido.'];
    }

    $sqlBusca = 'SELECT id_usuario, nome, tipo, ativo FROM usuario WHERE email = ?';
    $stmtBusca = $conn->prepare($sqlBusca);

    if (!$stmtBusca) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao verificar o e-mail.'];
    }

    $stmtBusca->bind_param('s', $email);
    $stmtBusca->execute();
    $resultado = $stmtBusca->get_result();
    $usuario = $resultado->fetch_assoc();
    $stmtBusca->close();

    if (!$usuario) {
        return ['sucesso' => false, 'mensagem' => 'Nenhuma conta cadastrada com este e-mail.'];
    }

    $id = (int) $usuario['id_usuario'];
    $nome = (string) $usuario['nome'];
    $estavaInativo = ((int) $usuario['ativo']) === 0;
    $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);

    $sqlUpdate = 'UPDATE usuario SET senha = ?, ativo = 1 WHERE id_usuario = ?';
    $stmtUpdate = $conn->prepare($sqlUpdate);

    if (!$stmtUpdate) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao preparar a atualização da senha.'];
    }

    $stmtUpdate->bind_param('si', $senhaHash, $id);
    $sucesso = $stmtUpdate->execute();
    $stmtUpdate->close();

    if ($sucesso) {
        $mensagemSucesso = $estavaInativo
            ? 'Conta reativada e nova senha definida com sucesso!'
            : 'Senha atualizada com sucesso!';

        return [
            'sucesso' => true,
            'id' => $id,
            'nome' => $nome,
            'reativado' => $estavaInativo,
            'mensagem' => $mensagemSucesso
        ];
    }

    return ['sucesso' => false, 'mensagem' => 'Não foi possível redefinir a senha.'];
}
