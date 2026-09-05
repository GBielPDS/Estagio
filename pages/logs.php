<?php

require_once "../script/sessao.php";
require_once "../script/conexao.php";
require_once "../script/funcoes_logs.php";
require_once "../script/sidebar.php";

verificarSessao();
verificarTipo(['Administrador']);

$dataInicio = $_GET['data_inicio'] ?? '';
$dataFim = $_GET['data_fim'] ?? '';
$filtroUsuario = $_GET['usuario'] ?? '';

$resultadoUsuarios = buscarUsuariosLogs($conn);
$resultadoLogs = buscarLogs($conn, $dataInicio, $dataFim, $filtroUsuario);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style.css">
    <title>Logs · GestSaúde</title>
</head>
<body>

<?php sidebar('logs'); ?>

<main class="conteudo">

    <div class="cabecalho-pagina">
        <h1 class="cabecalho-pagina__titulo">Logs</h1>
        <p class="cabecalho-pagina__descricao">Acompanhe as ações registradas no sistema.</p>
    </div>

    <section class="cartao cartao--produtos">

        <form method="GET" class="filtros" action="">

            <div class="campo campo--filtro-categoria">
                <label class="campo__rotulo" for="usuario">Usuário</label>
                <select class="campo__controle" id="usuario" name="usuario">
                    <option value="">Todos os usuários</option>
                    <?php if ($resultadoUsuarios): ?>
                        <?php while ($usuario = $resultadoUsuarios->fetch_assoc()): ?>
                            <option value="<?= (int) $usuario['id_usuario'] ?>"
                                <?= (string) $filtroUsuario === (string) $usuario['id_usuario'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($usuario['nome']) ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="campo campo--filtro-categoria">
                <label class="campo__rotulo" for="filtro-data-inicio">De</label>
                <input class="campo__controle" type="date" id="filtro-data-inicio" name="data_inicio"
                    value="<?= htmlspecialchars($dataInicio) ?>">
            </div>

            <div class="campo campo--filtro-categoria">
                <label class="campo__rotulo" for="filtro-data-fim">Até</label>
                <input class="campo__controle" type="date" id="filtro-data-fim" name="data_fim"
                    value="<?= htmlspecialchars($dataFim) ?>">
            </div>

            <button class="botao botao--primario" type="submit">Pesquisar</button>
            <a class="botao botao--secundario" href="logs.php">Limpar filtros</a>

        </form>

        <div class="tabela-rolagem">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>DATA</th>
                        <th>USUÁRIO</th>
                        <th>AÇÃO</th>
                        <th>DESCRIÇÃO</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$resultadoLogs || $resultadoLogs->num_rows === 0): ?>
                        <tr>
                            <td colspan="4" class="tabela__vazio" style="text-align: center;">Nenhum registro encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php while ($log = $resultadoLogs->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($log['data_hora'])) ?></td>
                                <td><?= htmlspecialchars($log['usuario']) ?></td>
                                <td><?= htmlspecialchars($log['acao']) ?></td>
                                <td><?= htmlspecialchars($log['descricao'] ?? '') ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </section>

</main>

<footer class="rodape">GestSaúde · Módulo de Controle de Estoque</footer>

</body>
</html>
