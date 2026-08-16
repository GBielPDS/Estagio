<?php

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
            onclick=\"window.location.href='pages/editar_usuario.php?id={$usuario['id_usuario']}'\">
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
    $sql = "UPDATE usuario
            SET nome = ?, email = ?, senha = ?, tipo = ?
            WHERE id_usuario = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Erro ao preparar atualização: " . $conn->error);
    }

    $stmt->bind_param("ssssi", $nome, $email, $senha, $tipo, $id);

    if ($stmt->execute()) {
        return true;
    }

    return false;
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
