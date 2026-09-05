<?php

require_once '../script/sessao.php';
require_once "../script/conexao.php";
require_once "../script/funcoes_usuarios.php";
require_once "../script/funcoes_logs.php";
require_once "../script/sidebar.php";

verificarSessao();

verificarTipo(['Administrador']);

if (!isset($_GET['id'])) {
    die("Usuário não informado.");
}

$id = (int) $_GET['id'];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $tipo = $_POST['tipo'] ?? '';

    if (atualizarUsuario($conn, $id, $nome, $email, $senha, $tipo)) {

        registrarLog(
            $conn,
            'Atualização de usuário',
            'Usuário ' . $nome . ' (ID ' . $id . ') atualizado.',
            $_SESSION['id_usuario']
        );

        header("Location: usuarios.php");
        exit;

    } else {
        echo "Erro ao atualizar usuário.";
    }
}

$usuario = buscarUsuarioPorId($conn, $id);

if (!$usuario) {
    die("Usuário não encontrado.");
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style.css">
    <title>Editar Usuário</title>
</head>

<body>

    <?php sidebar('usuarios'); ?>

    <main class="conteudo">

        <div class="cabecalho-pagina">
            <h1 class="cabecalho-pagina__titulo">Editar usuário</h1>
            <p class="cabecalho-pagina__descricao">Atualize os dados do usuário.</p>
        </div>

        <Section class="cartao">

            <form method="POST" class="formulario">
                <div class="campo campo--largo">
                    <label class="campo__rotulo">Nome:</label>
                    <input class="campo__controle"                
                        type="text"
                        name="nome"
                        value="<?= htmlspecialchars($usuario['nome']) ?>"
                        required
                    >
                </div>


                <div class="campo campo--largo"">
                <label class="campo__rotulo">Email:</label>
                <input class="campo__controle"
                    type="email"
                    name="email"
                    value="<?= htmlspecialchars($usuario['email']) ?>"
                    required
                >
                </div>


                <div class="campo campo--largo"">
                <label class="campo__rotulo">Nova senha:</label>
                <input class="campo__controle"
                    type="password"
                    name="senha"
                    placeholder="Deixe vazio para manter a senha atual"
                >
                </div>

                <div class="campo campo--largo"> 
                    <label class="campo__rotulo" for="tipo" > Tipo de usuário </label> 
                    <select class="campo__controle" id="tipo" name="tipo" > 
                    
                        <option value="Administrador" <?= $usuario['tipo'] === 'Administrador' ? 'selected' : '' ?> > Administrador 
                        </option> 
                        <option value="Suporte" <?= $usuario['tipo'] === 'Suporte' ? 'selected' : '' ?> > Suporte 
                        </option> 
                        <option value="Usuario" <?= $usuario['tipo'] === 'Usuario' ? 'selected' : '' ?> > Usuário </option> 
                    </select> 
                </div>

                <div class="formulario__acoes"> <a href="usuarios.php" class="botao botao--secundario" > Cancelar </a> 
                <button type="submit" class="botao botao--primario" onclick="document.getElementById('acao').value='atualizar'" > Salvar </button> 

            </form>

        </Section>

    </main>

</body>
</html>
