<?php

declare(strict_types=1);

require_once __DIR__ . "/script/conexao.php";

$mensagens = [];
$sucesso = true;


function tabelaVazia(mysqli $conn, string $tabela): bool
{
    $permitidas = [
        'usuario',
        'categoria',
        'produto',
        'unidade_saude',
        'log'
    ];

    if (!in_array($tabela, $permitidas, true)) {
        throw new Exception("Tabela inválida.");
    }

    $sql = "SELECT COUNT(*) AS total FROM `$tabela`";

    $resultado = $conn->query($sql);

    if (!$resultado) {
        throw new Exception(
            "Erro ao consultar a tabela {$tabela}: " . $conn->error
        );
    }

    $dados = $resultado->fetch_assoc();

    return (int) $dados['total'] === 0;
}


/*
##########################################################
Início da configuração
##########################################################
*/

try {

    $conn->begin_transaction();

    $usuarioSetupId = null;

    if (tabelaVazia($conn, 'usuario')) {

        $nome = 'admin';
        $email = 'admin';
        $senha = password_hash(
            'adminadmin',
            PASSWORD_DEFAULT
        );
        $tipo = 'Administrador';
        $ativo = 1;

        $sql = "
            INSERT INTO usuario
                (nome, email, senha, tipo, ativo)
            VALUES
                (?, ?, ?, ?, ?)
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception(
                "Erro ao preparar cadastro do administrador: "
                . $conn->error
            );
        }

        $stmt->bind_param(
            "ssssi",
            $nome,
            $email,
            $senha,
            $tipo,
            $ativo
        );

        if (!$stmt->execute()) {
            throw new Exception(
                "Erro ao cadastrar administrador: "
                . $stmt->error
            );
        }

        $usuarioSetupId = $conn->insert_id;

        $stmt->close();

        $mensagens[] =
            "Administrador criado com sucesso.";

    } else {

        /*
        ##########################################################
        Se já existem usuários, não criamos outro.
        Pegamos o primeiro usuário para poder registrar
        o log da configuração.
        ##########################################################
         */

        $sql = "
            SELECT id_usuario
            FROM usuario
            ORDER BY id_usuario
            LIMIT 1
        ";

        $resultado = $conn->query($sql);

        if (!$resultado) {
            throw new Exception(
                "Erro ao buscar usuário existente: "
                . $conn->error
            );
        }

        $usuario = $resultado->fetch_assoc();

        if ($usuario) {
            $usuarioSetupId = (int) $usuario['id_usuario'];
        }

        $mensagens[] =
            "A tabela de usuários já possui dados. Nenhum usuário foi criado.";
    }


    /*
    ##########################################################
    Os dados são carregados do arquivo:
    bd/inserir_produtos.sql
    ##########################################################
    */

    $arquivoProdutos =
        __DIR__ . "/bd/inserir-produtos.sql";


    if (!file_exists($arquivoProdutos)) {

        throw new Exception(
            "Arquivo bd/inserir_produtos.sql não encontrado."
        );
    }


    $sqlProdutos =
        file_get_contents($arquivoProdutos);


    if ($sqlProdutos === false) {

        throw new Exception(
            "Não foi possível ler o arquivo inserir_produtos.sql."
        );
    }


    $sqlProdutos = preg_replace(
        '/^\s*USE\s+[^;]+;\s*/im',
        '',
        $sqlProdutos
    );


    $comandos = array_filter(
        array_map(
            'trim',
            explode(';', $sqlProdutos)
        )
    );


    foreach ($comandos as $comando) {


        if (
            preg_match(
                '/INSERT\s+(?:IGNORE\s+)?INTO\s+categoria/i',
                $comando
            )
        ) {

            if (tabelaVazia($conn, 'categoria')) {

                if (!$conn->query($comando)) {

                    throw new Exception(
                        "Erro ao inserir categorias: "
                        . $conn->error
                    );
                }

                $mensagens[] =
                    "Categorias iniciais cadastradas.";

            } else {

                $mensagens[] =
                    "A tabela de categorias já possui dados.";

            }
        }


        elseif (
            preg_match(
                '/INSERT\s+(?:IGNORE\s+)?INTO\s+produto/i',
                $comando
            )
        ) {

            if (tabelaVazia($conn, 'produto')) {

                if (!$conn->query($comando)) {

                    throw new Exception(
                        "Erro ao inserir produtos: "
                        . $conn->error
                    );
                }

                $mensagens[] =
                    "Produtos iniciais cadastrados.";

            } else {

                $mensagens[] =
                    "A tabela de produtos já possui dados.";

            }
        }
    }


    if (tabelaVazia($conn, 'unidade_saude')) {

        $nomeUnidade = 'Secretária de Saúde';
        $ativo = 1;

        $sql = "
            INSERT INTO unidade_saude
                (nome, ativo)
            VALUES
                (?, ?)
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception(
                "Erro ao preparar cadastro da unidade: "
                . $conn->error
            );
        }

        $stmt->bind_param(
            "si",
            $nomeUnidade,
            $ativo
        );

        if (!$stmt->execute()) {
            throw new Exception(
                "Erro ao cadastrar unidade de saúde: "
                . $stmt->error
            );
        }

        $stmt->close();

        $mensagens[] =
            "Secretária de Saúde cadastrada.";

    } else {

        $mensagens[] =
            "A tabela de unidades de saúde já possui dados.";
    }


    if ($usuarioSetupId === null) {

        throw new Exception(
            "Não foi possível identificar um usuário para registrar o log."
        );
    }


    $sql = "
        SELECT id_log
        FROM log
        WHERE acao = 'Sistema configurado'
        LIMIT 1
    ";

    $resultado = $conn->query($sql);

    if (!$resultado) {
        throw new Exception(
            "Erro ao verificar o log de configuração: "
            . $conn->error
        );
    }


    if ($resultado->num_rows === 0) {

        $acao = 'Sistema configurado';
        $descricao = 'Configuração inicial do sistema realizada.';

        $sql = "
            INSERT INTO log
                (acao, descricao, usuario_id)
            VALUES
                (?, ?, ?)
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception(
                "Erro ao preparar log: "
                . $conn->error
            );
        }

        $stmt->bind_param(
            "ssi",
            $acao,
            $descricao,
            $usuarioSetupId
        );

        if (!$stmt->execute()) {
            throw new Exception(
                "Erro ao registrar log: "
                . $stmt->error
            );
        }

        $stmt->close();

        $mensagens[] =
            "Log 'Sistema configurado' registrado.";

    } else {

        $mensagens[] =
            "O log de configuração já existe.";
    }



    $conn->commit();

} catch (Throwable $e) {



    $conn->rollback();

    $sucesso = false;

    $mensagens = [
        "Erro durante a configuração:",
        $e->getMessage()
    ];
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
        href="css/style.css"
    >

    <title>Configuração do Sistema</title>

</head>

<body>

<main class="conteudo">

    <div class="cabecalho-pagina">

        <h1 class="cabecalho-pagina__titulo">
            Configuração do Sistema
        </h1>

        <p class="cabecalho-pagina__descricao">
            Configuração inicial do sistema GestSaúde.
        </p>

    </div>


    <section class="cartao">

        <?php if ($sucesso): ?>

            <div class="mensagem-formulario mensagem-sucesso">

                <strong>
                    Sistema configurado com sucesso!
                </strong>

            </div>

        <?php else: ?>

            <div class="mensagem-formulario mensagem-erro">

                <strong>
                    A configuração não foi concluída.
                </strong>

            </div>

        <?php endif; ?>


        <div class="cartao__cabecalho">

            <h2 class="cartao__titulo">
                Resultado
            </h2>

        </div>


        <ul>

            <?php foreach ($mensagens as $mensagem): ?>

                <li>
                    <?= htmlspecialchars($mensagem) ?>
                </li>

            <?php endforeach; ?>

        </ul>

    </section>

</main>

</body>

</html>