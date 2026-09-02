<?php

require "../script/sessao.php";
require "../script/conexao.php";
require "../script/funcoes_logs.php";
require "../script/sidebar.php";

verificarSessao();


$dataInicio =
    $_GET['data_inicio'] ?? '';

$dataFim =
    $_GET['data_fim'] ?? '';

$filtroUsuario =
    $_GET['usuario'] ?? '';


$resultadoUsuarios =
    buscarUsuariosLogs($conn);


$resultadoLogs =
    buscarLogs(
        $conn,
        $dataInicio,
        $dataFim,
        $filtroUsuario
    );


?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style.css">
    <title>Logs</title>
</head>
<body>


<?php sidebar('logs'); ?>


<main class="conteudo">


    <!-- =============================================
         CABEÇALHO
    ============================================== -->

    <div class="cabecalho-pagina">

        <h1 class="cabecalho-pagina__titulo">
            Logs
        </h1>

        <p class="cabecalho-pagina__descricao">

            Consulte o registro de ações realizadas
            no sistema.

        </p>

    </div>


    <section class="cartao cartao--produtos">


        <form
            method="GET"
            class="filtros"
        >


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
                        $dataInicio
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
                        $dataFim
                    ) ?>"
                >

            </div>


            <div class="campo campo--filtro-categoria">

                <label
                    class="campo__rotulo"
                    for="filtro-usuario"
                >

                    Usuário

                </label>


                <select
                    class="campo__controle"
                    id="filtro-usuario"
                    name="usuario"
                >

                    <option value="">

                        Todos os usuários

                    </option>


                    <?php while (
                        $usuario =
                        $resultadoUsuarios->fetch_assoc()
                    ): ?>

                        <option
                            value="<?= $usuario['id_usuario'] ?>"
                            <?= $filtroUsuario ==
                                $usuario['id_usuario']
                                ? 'selected'
                                : '' ?>
                        >

                            <?= htmlspecialchars(
                                $usuario['nome']
                            ) ?>

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <button
                class="botao botao--primario"
                type="submit"
            >

                Pesquisar

            </button>

            <a
                class="botao botao--secundario"
                href="logs.php"
            >

                Limpar filtros

            </a>


        </form>


        <br>


        <div class="tabela-container">

            <table class="tabela">

                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Data/Hora</th>

                        <th>Ação</th>

                        <th>Descrição</th>

                        <th>Usuário</th>

                    </tr>

                </thead>


                <tbody>


                    <?php if (
                        $resultadoLogs &&
                        $resultadoLogs->num_rows > 0
                    ): ?>


                        <?php while (
                            $log =
                            $resultadoLogs->fetch_assoc()
                        ): ?>

                            <tr>

                                <td>

                                    <?= $log['id_log'] ?>

                                </td>

                                <td>

                                    <?= date(
                                        'd/m/Y H:i:s',
                                        strtotime(
                                            $log['data_hora']
                                        )
                                    ) ?>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $log['acao']
                                    ) ?>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $log['descricao'] ?? ''
                                    ) ?>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $log['usuario']
                                    ) ?>

                                </td>


                            </tr>

                        <?php endwhile; ?>


                    <?php else: ?>

                        <tr>

                            <td
                                colspan="5"
                                style="text-align: center;"
                            >

                                Nenhum log encontrado.

                            </td>

                        </tr>

                    <?php endif; ?>


                </tbody>

            </table>

        </div>


    </section>


</main>


<footer class="rodape">

    GestSaúde · Módulo de Controle de Estoque

</footer>


</body>

</html>
