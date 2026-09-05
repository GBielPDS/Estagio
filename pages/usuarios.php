<?php

require "../script/sessao.php";
require "../script/conexao.php";
require "../script/funcoes_usuarios.php";
require "../script/funcoes_logs.php";
require "../script/sidebar.php";

verificarSessao();
verificarTipo(['Administrador']);

$mensagemCadastro = $_SESSION['mensagem_cadastro'] ?? null;
unset($_SESSION['mensagem_cadastro']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['excluir_id'])) {

        $id = (int) $_POST['excluir_id'];

        if ($id === (int) $_SESSION['id_usuario']) {
            $mensagemCadastro = ['texto' => 'Você não pode excluir o seu próprio usuário.', 'tipo' => 'erro'];
        } else {
            $usuarioExcluir = buscarUsuarioPorId($conn, $id);
            if ($usuarioExcluir) {
                if (excluirUsuario($conn, $id)) {
                    registrarLog(
                        $conn,
                        'Exclusão de usuário',
                        'Usuário ' . $usuarioExcluir['nome'] . ' (ID ' . $id . ') excluído.',
                        $_SESSION['id_usuario']
                    );
                    $mensagemCadastro = ['texto' => 'Usuário excluído com sucesso.', 'tipo' => 'sucesso'];
                } else {
                    $mensagemCadastro = ['texto' => 'Não foi possível excluir o usuário.', 'tipo' => 'erro'];
                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style.css">
    <title>Usuários</title>
</head>
<body>

    <?php sidebar('usuarios'); ?>


    <main class="conteudo">

        <div class="cabecalho-pagina cabecalho-pagina--com-acao">
            <div>
            <h1 class="cabecalho-pagina__titulo">Usuários</h1>
            <p class="cabecalho-pagina__descricao">Gerencie os usuários e permissões do sistema.</p>
            </div>
            <a href="<?= BASE_URL ?>pages/cadastrar_usuario.php?admin=1" class="botao botao--primario">
                Cadastrar usuário
            </a>
        </div>

        <?php if ($mensagemCadastro !== null): ?>
            <div class="mensagem-formulario mensagem-<?= $mensagemCadastro['tipo'] === 'sucesso' ? 'sucesso' : 'erro' ?>">
                <?= htmlspecialchars($mensagemCadastro['texto'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <section class="cartao cartao--usuarios">
            <div class="cartao__cabecalho">
                <div>
                    <h2 class="cartao__titulo">
                        Usuários cadastrados
                    </h2>

                    <p class="cartao__descricao">
                        Consulte, edite ou exclua os usuários do sistema.
                    </p>
                </div>
            </div>

            <div class="tabela-rolagem">
                <table class="table table--usuarios">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Senha</th>
                            <th>Tipo</th>
                            <th>Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php listarUsuarios($conn); ?>
                    </tbody>
                </table>
            </div>

        </section>

    </main>

</body>
</html>
