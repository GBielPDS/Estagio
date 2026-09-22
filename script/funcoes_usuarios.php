<?php

declare(strict_types=1);

function cadastrarUsuario(mysqli $conn, string $nome, string $email, string $senha, string $tipo): array
{
    $sql = 'SELECT id_usuario FROM usuario WHERE email = ?';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao verificar o email.'];
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        return ['sucesso' => false, 'mensagem' => 'Este email já está cadastrado.'];
    }

    $stmt->close();

    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
    $sql = 'INSERT INTO usuario (nome, email, senha, tipo) VALUES (?, ?, ?, ?)';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao preparar o cadastro.'];
    }

    $stmt->bind_param('ssss', $nome, $email, $senhaHash, $tipo);
    $sucesso = $stmt->execute();
    $novoId = (int) $conn->insert_id;
    $stmt->close();

    return $sucesso
        ? ['sucesso' => true, 'id' => $novoId]
        : ['sucesso' => false, 'mensagem' => 'Erro ao realizar o cadastro.'];
}

function listarUsuarios(mysqli $conn, bool $apenasAtivos = true): void
{
    $sql = $apenasAtivos
        ? "SELECT id_usuario, nome, email, senha, tipo, ativo FROM usuario WHERE ativo = 1"
        : "SELECT id_usuario, nome, email, senha, tipo, ativo FROM usuario";
    $resultado = $conn->query($sql);

    if (!$resultado) {
        die("Erro na consulta: " . $conn->error);
    }

    while ($usuario = $resultado->fetch_assoc()) {
        $idUser = (int) $usuario['id_usuario'];
        $nomeUser = htmlspecialchars((string) $usuario['nome'], ENT_QUOTES, 'UTF-8');
        $emailUser = htmlspecialchars((string) $usuario['email'], ENT_QUOTES, 'UTF-8');
        $tipoUser = htmlspecialchars((string) $usuario['tipo'], ENT_QUOTES, 'UTF-8');

        echo "<tr>";
        echo "<td>" . $idUser . "</td>";
        echo "<td>" . $nomeUser . "</td>";
        echo "<td>" . $emailUser . "</td>";
        echo "<td>••••••••</td>";
        echo "<td>" . $tipoUser . "</td>";

        echo "<td>
        <button type='button'
            class='botao botao--secundario botao--pequeno'
            onclick=\"window.location.href='editar_usuario.php?id={$idUser}'\">
            Editar
        </button>
        <form method='POST' class='formulario-excluir'>
            <input type='hidden' name='excluir_id' value='{$idUser}'>
            <button type='submit' class='botao botao--perigo botao--pequeno' onclick=\"return confirm('Deseja realmente excluir este usuário?')\">
                Excluir
            </button>
        </form>
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
    string $tipo
): bool {
    $usuarioAntigo = buscarUsuarioPorId($conn, $id);

    if (!$usuarioAntigo) {
        return false;
    }

    if ($senha !== '') {
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

        $sql = "UPDATE usuario
                SET nome = ?, email = ?, senha = ?, tipo = ?
                WHERE id_usuario = ?";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            die("Erro ao preparar atualização: " . $conn->error);
        }

        $stmt->bind_param(
            "ssssi",
            $nome,
            $email,
            $senhaHash,
            $tipo,
            $id
        );
    } else {
        $sql = "UPDATE usuario
                SET nome = ?, email = ?, tipo = ?
                WHERE id_usuario = ?";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            die("Erro ao preparar atualização: " . $conn->error);
        }

        $stmt->bind_param(
            "sssi",
            $nome,
            $email,
            $tipo,
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
