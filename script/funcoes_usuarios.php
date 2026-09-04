<?php

function cadastrarUsuario($conn, $nome, $email, $senha, $tipo)
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
    $stmt->close();

    return $sucesso
        ? ['sucesso' => true]
        : ['sucesso' => false, 'mensagem' => 'Erro ao realizar o cadastro.'];
}

function listarUsuarios($conn)
{
    $sql = "SELECT id_usuario, nome, email, senha, tipo FROM usuario";
    $resultado = $conn->query($sql);

    if (!$resultado) {
        die("Erro na consulta: " . $conn->error);
    }

    while ($usuario = $resultado->fetch_assoc()) {

        echo "<tr>";
        echo "<td>" . $usuario['id_usuario'] . "</td>";
        echo "<td>" . htmlspecialchars($usuario['nome']) . "</td>";
        echo "<td>" . htmlspecialchars($usuario['email']) . "</td>";
        echo "<td>" . htmlspecialchars($usuario['senha']) . "</td>";
        echo "<td>" . htmlspecialchars($usuario['tipo']) . "</td>";

        echo "<td>
        <button type='button' 
            onclick=\"window.location.href='editar_usuario.php?id={$usuario['id_usuario']}'\">
            Editar
        </button>
        
        <form method='POST' style='display:inline;'>
                    
            <input type='hidden' name='excluir_id' value={$usuario['id_usuario']}>

            <button type='submit'
                onclick=\"return confirm('Deseja realmente excluir este usuário?')\">
                Excluir
            </button>

        </form>
        </td>";
    }
}

function buscarUsuarioPorId($conn, $id)
{
    $sql = "SELECT id_usuario, nome, email, senha, tipo
            FROM usuario
            WHERE id_usuario = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Erro ao preparar consulta: " . $conn->error);
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $resultado = $stmt->get_result();

    return $resultado->fetch_assoc();
}

function atualizarUsuario($conn, $id, $nome, $email, $senha, $tipo)
{
    $usuarioAntigo = buscarUsuarioPorId($conn, $id);

    if (!$usuarioAntigo) {
        return false;
    }

    if (!empty($senha)) {

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

    return $stmt->execute();

    $descricao = "Usuário ID {$id} atualizado.";

    registrarLog(
        $conn,
        "Atualização de usuário",
        $descricao,
        $usuarioLogado
    );

    return true;
}

function atualizarPerfilUsuario($conn, $id, $nome, $email, $senha = '')
{
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

function excluirUsuario($conn, $id)
{
    $sql = "DELETE FROM usuario WHERE id_usuario = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Erro ao preparar exclusão: " . $conn->error);
    }

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        return true;
    }

    return false;
}
