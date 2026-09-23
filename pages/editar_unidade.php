<?php

declare(strict_types=1);

require_once '../script/sessao.php';
require_once '../script/conexao.php';
require_once '../script/funcoes_unidades.php';
require_once '../script/funcoes_logs.php';
require_once '../script/sidebar.php';

verificarSessao();
verificarTipo(['Administrador']);

if (!isset($_GET['id'])) {
    die('Unidade de saúde não informada.');
}

$id = (int) $_GET['id'];

if ($id <= 0) {
    die('Unidade de saúde inválida.');
}

/*
 * Busca a unidade antes de processar o formulário.
 */
$sql = "SELECT id_unidade, nome, endereco, telefone, ativo
        FROM unidade_saude
        WHERE id_unidade = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die('Erro ao consultar unidade de saúde.');
}

$stmt->bind_param('i', $id);
$stmt->execute();

$resultado = $stmt->get_result();
$unidade = $resultado->fetch_assoc();

$stmt->close();

if (!$unidade) {
    die('Unidade de saúde não encontrada.');
}


/*
 * Atualização da unidade.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome = trim((string) ($_POST['nome'] ?? ''));
    $endereco = trim((string) ($_POST['endereco'] ?? ''));
    $telefone = trim((string) ($_POST['telefone'] ?? ''));

    if ($nome === '') {
        $mensagem = 'O nome da unidade é obrigatório.';
        $tipoMensagem = 'erro';

    } else {

        /*
         * Verifica se já existe outra unidade
         * com o mesmo nome.
         */
        $sql = "SELECT id_unidade
                FROM unidade_saude
                WHERE nome = ?
                AND id_unidade <> ?";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $mensagem = 'Não foi possível verificar o nome da unidade.';
            $tipoMensagem = 'erro';

        } else {

            $stmt->bind_param('si', $nome, $id);
            $stmt->execute();

            $resultado = $stmt->get_result();

            if ($resultado->num_rows > 0) {

                $mensagem = 'Já existe outra unidade de saúde com esse nome.';
                $tipoMensagem = 'erro';

                $stmt->close();

            } else {

                $stmt->close();

                $sql = "UPDATE unidade_saude
                        SET nome = ?, endereco = ?, telefone = ?
                        WHERE id_unidade = ?";

                $stmt = $conn->prepare($sql);

                if (!$stmt) {

                    $mensagem = 'Não foi possível preparar a atualização.';
                    $tipoMensagem = 'erro';

                } else {

                    $stmt->bind_param(
                        'sssi',
                        $nome,
                        $endereco,
                        $telefone,
                        $id
                    );

                    if ($stmt->execute()) {

                        registrarLog(
                            $conn,
                            'Atualização de unidade',
                            'Unidade de saúde "' . $nome . '" (ID ' . $id . ') atualizada.',
                            (int) $_SESSION['id_usuario']
                        );

                        $stmt->close();

                        $_SESSION['mensagem_cadastro'] = [
                            'texto' => 'Unidade de saúde atualizada com sucesso.',
                            'tipo' => 'sucesso'
                        ];

                        header('Location: unidades.php');
                        exit;

                    } else {

                        $mensagem = 'Não foi possível atualizar a unidade.';
                        $tipoMensagem = 'erro';

                        $stmt->close();
                    }
                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../css/style.css">

    <title>Editar Unidade de Saúde</title>
</head>

<body>

    <?php sidebar('unidades'); ?>

    <main class="conteudo">

        <div class="cabecalho-pagina">

            <h1 class="cabecalho-pagina__titulo">
                Editar unidade de saúde
            </h1>

            <p class="cabecalho-pagina__descricao">
                Atualize os dados da unidade de saúde.
            </p>

        </div>

        <?php if (isset($mensagem)): ?>

            <div class="mensagem-formulario mensagem-<?= $tipoMensagem === 'sucesso' ? 'sucesso' : 'erro' ?>">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

        <section class="cartao">

            <form method="POST" class="formulario">

                <?= campoCsrf() ?>

                <div class="campo campo--largo">

                    <label class="campo__rotulo" for="nome">
                        Nome da unidade
                    </label>

                    <input
                        class="campo__controle"
                        type="text"
                        id="nome"
                        name="nome"
                        value="<?= htmlspecialchars((string) $unidade['nome'], ENT_QUOTES, 'UTF-8') ?>"
                        maxlength="100"
                        required
                    >

                </div>


                <div class="campo campo--largo">

                    <label class="campo__rotulo" for="endereco">
                        Endereço
                    </label>

                    <input
                        class="campo__controle"
                        type="text"
                        id="endereco"
                        name="endereco"
                        value="<?= htmlspecialchars((string) ($unidade['endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        maxlength="255"
                    >

                </div>


                <div class="campo campo--largo">

                    <label class="campo__rotulo" for="telefone">
                        Telefone
                    </label>

                    <input
                        class="campo__controle"
                        type="text"
                        id="telefone"
                        name="telefone"
                        value="<?= htmlspecialchars((string) ($unidade['telefone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        maxlength="20"
                    >

                </div>


                <div class="campo campo--largo">

                    <label class="campo__rotulo">
                        Status
                    </label>

                    <input
                        class="campo__controle"
                        type="text"
                        value="<?= (int) $unidade['ativo'] === 1 ? 'Ativa' : 'Desativada' ?>"
                        disabled
                    >

                </div>


                <div class="formulario__acoes">

                    <a
                        href="unidades.php"
                        class="botao botao--secundario"
                    >
                        Cancelar
                    </a>

                    <button
                        type="submit"
                        class="botao botao--primario"
                    >
                        Salvar alterações
                    </button>

                </div>

            </form>

        </section>

    </main>

</body>

</html>