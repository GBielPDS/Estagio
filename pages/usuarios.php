<?php

declare(strict_types=1);

require_once "../script/sessao.php";
require_once "../script/conexao.php";
require_once "../script/funcoes_usuarios.php";
require_once "../script/funcoes_logs.php";
require_once "../script/sidebar.php";

header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
verificarSessao();
verificarTipo(['Administrador', 'Suporte']);

$mensagemCadastro = $_SESSION['mensagem_cadastro'] ?? null;
unset($_SESSION['mensagem_cadastro']);

$filtroStatus = ($_GET['status'] ?? 'ativos') === 'inativos' ? 'inativos' : 'ativos';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTipo(['Administrador']);
    $resultado = ['sucesso' => false, 'mensagem' => 'Operação inválida.'];
    if (isset($_POST['excluir_id'])) $resultado = excluirUsuario($conn, (int) $_POST['excluir_id']);
    elseif (isset($_POST['reativar_id'])) $resultado = reativarUsuario($conn, (int) $_POST['reativar_id']);
    $_SESSION['mensagem_cadastro'] = ['texto' => $resultado['mensagem'], 'tipo' => $resultado['sucesso'] ? 'sucesso' : 'erro'];
    header('Location: usuarios.php?status=' . $filtroStatus);
    exit;
}

$totalAtivos = contarUsuarios($conn, 'ativos');
$totalInativos = contarUsuarios($conn, 'inativos');
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
            <?php if ($_SESSION['tipo'] === 'Administrador'): ?>
            <a href="<?= BASE_URL ?>pages/cadastrar_usuario.php" class="botao botao--primario">
                Cadastrar usuário
            </a>
            <?php endif; ?>
        </div>

        <?php if ($mensagemCadastro !== null): ?>
            <div class="mensagem-formulario mensagem-<?= ($mensagemCadastro['tipo'] ?? '') === 'sucesso' ? 'sucesso' : 'erro' ?>">
                <?= htmlspecialchars((string) ($mensagemCadastro['texto'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <section class="cartao cartao--usuarios">
            <div class="cartao__cabecalho" style="flex-wrap: wrap; gap: 16px; align-items: center; justify-content: space-between;">
                <div>
                    <h2 class="cartao__titulo">
                        <?= $filtroStatus === 'inativos' ? 'Usuários desativados' : 'Usuários ativos' ?>
                    </h2>
                    <p class="cartao__descricao">
                        <?= $filtroStatus === 'inativos'
                            ? 'Usuários que tiveram o acesso desativado. Você pode reativá-los a qualquer momento.'
                            : 'Consulte, edite ou desative os usuários ativos do sistema.' ?>
                    </p>
                </div>

                <div style="display: flex; gap: 8px;">
                    <a href="usuarios.php?status=ativos" class="botao botao--pequeno <?= $filtroStatus === 'ativos' ? 'botao--primario' : 'botao--secundario' ?>">
                        Ativos (<?= $totalAtivos ?>)
                    </a>
                    <a href="usuarios.php?status=inativos" class="botao botao--pequeno <?= $filtroStatus === 'inativos' ? 'botao--primario' : 'botao--secundario' ?>">
                        Desativados (<?= $totalInativos ?>)
                    </a>
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
                        <?php listarUsuarios($conn, $filtroStatus); ?>
                    </tbody>
                </table>
            </div>

        </section>

    </main>

</body>
</html>
