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
        <a href='pages/editar_usuario.php?id={$usuario['id_usuario']}'>
            <button>Editar</button>
        </a>
        
        <a href='excluir.php?id={$usuario['id_usuario']}'
           onclick=\"return confirm('Deseja realmente excluir este usuário?')\">
            <button>Excluir</button>
        </a>
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