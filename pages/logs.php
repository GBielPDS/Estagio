<?php

declare(strict_types=1);

require_once "../script/sessao.php";
require_once "../script/conexao.php";
require_once "../script/funcoes_logs.php";
require_once "../script/sidebar.php";

header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
verificarSessao();
verificarTipo(['Administrador', 'Suporte']);


$visualizacao = ($_GET['visualizacao'] ?? 'logs') === 'login'
    ? 'login'
    : 'logs';


if ($visualizacao === 'login' && ($_SESSION['tipo'] ?? '') !== 'Administrador') {
    respostaAcesso(403, 'Acesso negado.');
}

$dataInicio = (string) ($_GET['data_inicio'] ?? '');
$dataFim = (string) ($_GET['data_fim'] ?? '');
$filtroUsuario = (string) ($_GET['usuario'] ?? '');


$mensagem = '';
$tipoMensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTipo(['Administrador']);
    if (!isset($_POST['confirmar_historico_login'])) respostaAcesso(400, 'Operação inválida.');
    $visualizacao = 'login';
    $resultado = confirmarIdentidade($conn, 'historico_autenticacao', (string) ($_POST['senha_confirmacao'] ?? ''));
    if ($resultado['sucesso']) {
        header('Location: logs.php?visualizacao=login');
        exit;
    }
    http_response_code($resultado['status']);
    if (!empty($resultado['espera'])) header('Retry-After: ' . (int) $resultado['espera']);
    $mensagem = $resultado['mensagem'];
    $tipoMensagem = 'erro';
}
$historicoAutorizado = $visualizacao === 'login' && identidadeConfirmada($conn, 'historico_autenticacao');

$resultadoUsuarios = buscarUsuariosLogs($conn);

$resultadoLogs = null;
$resultadoHistoricoLogin = null;

if ($visualizacao === 'logs') {

    $resultadoLogs = buscarLogs(
        $conn,
        $dataInicio,
        $dataFim,
        $filtroUsuario
    );

} elseif ($visualizacao === 'login' && $historicoAutorizado) {

    $resultadoHistoricoLogin = buscarHistoricoLogin(
        $conn,
        $dataInicio,
        $dataFim,
        $filtroUsuario
    );
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <title>Logs · GestSaúde</title>

</head>

<body>

<?php sidebar('logs'); ?>

<main class="conteudo">

    <div class="cabecalho-pagina">

        <h1 class="cabecalho-pagina__titulo">
            Logs
        </h1>

        <p class="cabecalho-pagina__descricao">
            Acompanhe as ações registradas no sistema.
        </p>

    </div>


    <?php if ($mensagem !== ''): ?>

        <div class="mensagem-formulario mensagem-<?= $tipoMensagem === 'erro' ? 'erro' : 'sucesso' ?>">

            <?= htmlspecialchars(
                $mensagem,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <!--
        Navegação entre os dois tipos de histórico.
    -->
    <section class="cartao cartao--usuarios">

        <div
            class="cartao__cabecalho"
            style="flex-wrap: wrap; gap: 16px; align-items: center; justify-content: space-between;"
        >

            <div>

                <h2 class="cartao__titulo">

                    <?= $visualizacao === 'login'
                        ? 'Histórico de autenticação'
                        : 'Logs do sistema'
                    ?>

                </h2>

                <p class="cartao__descricao">

                    <?php if ($visualizacao === 'login'): ?>

                        Registros de acesso dos usuários ao sistema.

                    <?php else: ?>

                        Consulte as ações realizadas pelos usuários.

                    <?php endif; ?>

                </p>

            </div>


            <div style="display: flex; gap: 8px;">

                <a
                    href="logs.php?visualizacao=logs"
                    class="botao botao--pequeno <?= $visualizacao === 'logs'
                        ? 'botao--primario'
                        : 'botao--secundario' ?>"
                >
                    Logs
                </a>


                <?php if ($_SESSION['tipo'] === 'Administrador'): ?>

                    <a
                        href="logs.php?visualizacao=login"
                        class="botao botao--pequeno <?= $visualizacao === 'login'
                            ? 'botao--primario'
                            : 'botao--secundario' ?>"
                    >
                        Histórico de autenticação
                    </a>

                <?php endif; ?>

            </div>

        </div>


        <?php if ($visualizacao === 'login' && !$historicoAutorizado): ?>

            <!--
                Confirmação da senha do administrador.
            -->
            <div class="cartao" style="margin-top: 20px;">

                <div class="cartao__cabecalho">

                    <div>

                        <h2 class="cartao__titulo">
                            Confirmar identidade
                        </h2>

                        <p class="cartao__descricao">
                            Para acessar o histórico de autenticação,
                            confirme sua senha de administrador.
                        </p>

                    </div>

                </div>


                <form method="POST" class="formulario">

                    <?= campoCsrf() ?>

                    <input
                        type="hidden"
                        name="confirmar_historico_login"
                        value="1"
                    >


                    <div class="campo campo--largo">

                        <label
                            class="campo__rotulo"
                            for="senha-confirmacao"
                        >
                            Senha
                        </label>

                        <input
                            class="campo__controle"
                            type="password"
                            id="senha-confirmacao"
                            name="senha_confirmacao"
                            required
                            autocomplete="current-password"
                        >

                    </div>


                    <button
                        type="submit"
                        class="botao botao--primario"
                    >
                        Confirmar e acessar
                    </button>

                </form>

            </div>


        <?php else: ?>


            <!--
                Filtros.
            -->
            <form
                method="GET"
                class="filtros"
                action="logs.php"
            >

                <input
                    type="hidden"
                    name="visualizacao"
                    value="<?= htmlspecialchars(
                        $visualizacao,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >


                <div class="campo campo--filtro-categoria">

                    <label
                        class="campo__rotulo"
                        for="usuario"
                    >
                        Usuário
                    </label>

                    <select
                        class="campo__controle"
                        id="usuario"
                        name="usuario"
                    >

                        <option value="">
                            Todos os usuários
                        </option>

                        <?php if ($resultadoUsuarios): ?>

                            <?php while ($usuario = $resultadoUsuarios->fetch_assoc()): ?>

                                <option
                                    value="<?= (int) $usuario['id_usuario'] ?>"
                                    <?= (string) $filtroUsuario ===
                                        (string) $usuario['id_usuario']
                                        ? 'selected'
                                        : '' ?>
                                >

                                    <?= htmlspecialchars(
                                        (string) $usuario['nome'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </option>

                            <?php endwhile; ?>

                        <?php endif; ?>

                    </select>

                </div>


                <div class="campo campo--filtro-categoria">

                    <label
                        class="campo__rotulo"
                        for="filtro-data-inicio"
                    >
                        De
                    </label>

                    <input
                        class="campo__controle"
                        type="date"
                        id="filtro-data-inicio"
                        name="data_inicio"
                        value="<?= htmlspecialchars(
                            $dataInicio,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>


                <div class="campo campo--filtro-categoria">

                    <label
                        class="campo__rotulo"
                        for="filtro-data-fim"
                    >
                        Até
                    </label>

                    <input
                        class="campo__controle"
                        type="date"
                        id="filtro-data-fim"
                        name="data_fim"
                        value="<?= htmlspecialchars(
                            $dataFim,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>


                <button
                    class="botao botao--primario"
                    type="submit"
                >
                    Pesquisar
                </button>


                <a
                    class="botao botao--secundario"
                    href="logs.php?visualizacao=<?= $visualizacao ?>"
                >
                    Limpar filtros
                </a>

            </form>


            <!--
                TABELA DE LOGS NORMAIS
            -->
            <?php if ($visualizacao === 'logs'): ?>

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

                            <?php if (
                                !$resultadoLogs ||
                                $resultadoLogs->num_rows === 0
                            ): ?>

                                <tr>

                                    <td
                                        colspan="4"
                                        class="tabela__vazio"
                                        style="text-align: center;"
                                    >
                                        Nenhum registro encontrado.
                                    </td>

                                </tr>

                            <?php else: ?>

                                <?php while (
                                    $log = $resultadoLogs->fetch_assoc()
                                ): ?>

                                    <tr>

                                        <td>
                                            <?= date(
                                                'd/m/Y H:i',
                                                strtotime(
                                                    (string) $log['data_hora']
                                                )
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                (string) $log['usuario'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                (string) $log['acao'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                (string) (
                                                    $log['descricao'] ?? ''
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                    </tr>

                                <?php endwhile; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>


            <!--
                TABELA DE HISTÓRICO DE LOGIN
            -->
            <?php elseif ($visualizacao === 'login'): ?>

                <div class="tabela-rolagem">

                    <table class="tabela">

                        <thead>

                            <tr>

                                <th>DATA</th>
                                <th>USUÁRIO</th>
                                <th>EVENTO</th>
                                <th>DESCRIÇÃO</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php if (
                                !$resultadoHistoricoLogin ||
                                $resultadoHistoricoLogin->num_rows === 0
                            ): ?>

                                <tr>

                                    <td
                                        colspan="4"
                                        class="tabela__vazio"
                                        style="text-align: center;"
                                    >
                                        Nenhum evento de autenticação encontrado.
                                    </td>

                                </tr>

                            <?php else: ?>

                                <?php while (
                                    $login = $resultadoHistoricoLogin->fetch_assoc()
                                ): ?>

                                    <tr>

                                        <td>
                                            <?= date(
                                                'd/m/Y H:i',
                                                strtotime(
                                                    (string) $login['data_hora']
                                                )
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                (string) $login['usuario'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <td><?= htmlspecialchars((string) $login['acao'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?= htmlspecialchars(
                                                (string) (
                                                    $login['descricao'] ?? ''
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                    </tr>

                                <?php endwhile; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        <?php endif; ?>

    </section>

</main>

<footer class="rodape">
    GestSaúde · Módulo de Controle de Estoque
</footer>

</body>
</html>