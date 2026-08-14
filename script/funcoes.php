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
        <a href='editar.php?id={$usuario['id_usuario']}'><button>Editar</button></a>

        <a href='excluir.php?id={$usuario['id_usuario']}'
           onclick=\"return confirm('Deseja realmente excluir este usuário?')\">
            <button>Excluir</button>
        </a>
        </td>";

        echo "</tr>";
    }
}