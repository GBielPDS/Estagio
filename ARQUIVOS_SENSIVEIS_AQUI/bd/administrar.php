<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$comando = $argv[1] ?? '';

if (!in_array($comando, ['migrar', 'inicializar'], true)) {
    fwrite(
        STDERR,
        "Uso: php bd/administrar.php migrar | inicializar\n"
        . "Importe bd/criar-bd.sql antes da primeira inicialização.\n"
    );
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$possiveisCaminhosScript = [
    __DIR__ . '/../public_html/script',
    dirname(__DIR__, 2) . '/public_html/script',
    __DIR__ . '/../script',
    __DIR__ . '/script',
];

$caminhoScript = null;

foreach ($possiveisCaminhosScript as $candidato) {
    if (is_dir($candidato)) {
        $caminhoScript = $candidato;
        break;
    }
}

if ($caminhoScript === null) {
    fwrite(
        STDERR,
        "Erro: Diretório de scripts não encontrado.\n"
    );
    exit(1);
}

require_once $caminhoScript . '/funcoes_usuarios.php';

function lerInstalacao(string $pergunta): string
{
    echo $pergunta;

    $linha = fgets(STDIN);

    if ($linha === false) {
        throw new DomainException(
            'Entrada encerrada. Instalação cancelada.'
        );
    }

    return rtrim($linha, "\r\n");
}

$travado = false;
$transacao = false;
$codigo = 0;
$conn = null;

try {
    if ($comando === 'migrar') {

        require_once $caminhoScript . '/conexao.php';

        if (
            $conn->query(
                "SHOW COLUMNS FROM usuario LIKE 'versao_sessao'"
            )->num_rows === 0
        ) {
            $conn->query(
                'ALTER TABLE usuario
                 ADD COLUMN versao_sessao INT UNSIGNED NOT NULL DEFAULT 1'
            );
        }

        $migracao = file_get_contents(
            __DIR__ . '/migracoes/002-confirmacao-identidade.sql'
        );

        if ($migracao === false) {
            throw new RuntimeException(
                'Migração não encontrada.'
            );
        }

        $conn->query($migracao);

        $sqlTentativas = file_get_contents(
            __DIR__ . '/migracoes/003-tentativa-login.sql'
        );

        if ($sqlTentativas === false) {
            throw new RuntimeException(
                'Migração não encontrada.'
            );
        }

        $conn->query($sqlTentativas);

        $resultadoMotor = $conn->query(
            "SELECT ENGINE
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'tentativa_login'"
        );

        $linhaMotor = $resultadoMotor->fetch_row();

        if (
            !isset($linhaMotor[0]) ||
            strtolower((string) $linhaMotor[0]) !== 'innodb'
        ) {
            $conn->query(
                'ALTER TABLE tentativa_login ENGINE=InnoDB'
            );
        }

        $sqlJanela = file_get_contents(
            __DIR__ . '/migracoes/004-janela-login.sql'
        );

        if ($sqlJanela === false) {
            throw new RuntimeException(
                'Migração não encontrada.'
            );
        }

        preg_match_all(
            '/ALTER TABLE tentativa_login ADD COLUMN (\w+) ([^;]+);/',
            $sqlJanela,
            $alteracoes,
            PREG_SET_ORDER
        );

        if (count($alteracoes) !== 3) {
            throw new RuntimeException(
                'Migração de login inválida.'
            );
        }

        foreach ($alteracoes as $alteracao) {
            $coluna = $alteracao[1];

            if (
                $conn->query(
                    "SHOW COLUMNS FROM tentativa_login LIKE '$coluna'"
                )->num_rows === 0
            ) {
                $conn->query($alteracao[0]);
            }
        }

        $sqlDatas = file_get_contents(
            __DIR__ . '/migracoes/005-data-cadastro-movimentacao.sql'
        );

        if ($sqlDatas === false) {
            throw new RuntimeException(
                'Migração não encontrada.'
            );
        }

        preg_match_all(
            '/ALTER TABLE movimentacao[^;]+;/',
            $sqlDatas,
            $comandosDatas
        );

        if (count($comandosDatas[0]) !== 2) {
            throw new RuntimeException(
                'Migração de datas inválida.'
            );
        }

        if (
            $conn->query(
                "SHOW COLUMNS FROM movimentacao LIKE 'cadastrado_em'"
            )->num_rows === 0
        ) {
            $conn->query($comandosDatas[0][0]);
        }

        $conn->query($comandosDatas[0][1]);

        echo "Banco atualizado. Os dados existentes foram preservados.\n";

    } else {

        $nome = trim(
            lerInstalacao(
                'Nome do primeiro administrador: '
            )
        );

        $email = trim(
            lerInstalacao(
                'E-mail: '
            )
        );

        $senha = lerInstalacao(
            'Senha inicial (visível neste terminal local): '
        );

        validarDadosUsuario(
            $nome,
            $email,
            $senha,
            'Administrador',
            1,
            true
        );

        $importar = false;

        $catalogoPath = null;

        if (file_exists(__DIR__ . '/inserir-produtos.sql')) {
            $catalogoPath = __DIR__ . '/inserir-produtos.sql';
        } elseif (file_exists(__DIR__ . '/inserir_produtos.sql')) {
            $catalogoPath = __DIR__ . '/inserir_produtos.sql';
        }

        if ($catalogoPath !== null) {

            do {
                $resposta = strtolower(
                    trim(
                        lerInstalacao(
                            'Deseja cadastrar o catálogo inicial de produtos? [s/n] '
                        )
                    )
                );

                if (!in_array($resposta, ['s', 'n'], true)) {
                    echo "Responda s ou n.\n";
                }

            } while (!in_array($resposta, ['s', 'n'], true));

            $importar = $resposta === 's';
        }

        require_once $caminhoScript . '/conexao.php';

        if (
            (int) $conn
                ->query('SELECT COUNT(*) FROM usuario')
                ->fetch_row()[0] !== 0
        ) {
            throw new DomainException(
                'Inicialização bloqueada: já existem usuários. '
                . 'Use a administração do sistema.'
            );
        }

        $travado = (int) $conn
            ->query(
                "SELECT GET_LOCK(
                    CONCAT(DATABASE(), ':inicializacao'),
                    10
                )"
            )
            ->fetch_row()[0] === 1;

        if (!$travado) {
            throw new DomainException(
                'Outra inicialização está em andamento. '
                . 'Tente novamente depois.'
            );
        }

        $conn->begin_transaction();
        $transacao = true;

        if (
            (int) $conn
                ->query('SELECT COUNT(*) FROM usuario')
                ->fetch_row()[0] !== 0
        ) {
            throw new DomainException(
                'Inicialização bloqueada: outro processo '
                . 'já cadastrou usuários.'
            );
        }

        if (
            $importar &&
            (int) $conn
                ->query('SELECT COUNT(*) FROM produto')
                ->fetch_row()[0] !== 0
        ) {
            throw new DomainException(
                'Produtos foram cadastrados durante a preparação. '
                . 'Reinicie a instalação.'
            );
        }

        $hash = password_hash(
            $senha,
            PASSWORD_DEFAULT
        );

        unset($senha);

        $stmt = $conn->prepare(
            "INSERT INTO usuario(nome, email, senha, tipo)
             VALUES (?, ?, ?, 'Administrador')"
        );

        $stmt->bind_param(
            'sss',
            $nome,
            $email,
            $hash
        );

        $stmt->execute();

        $id = (int) $conn->insert_id;

        $stmt->close();

        $conn->query(
            "INSERT INTO unidade_saude(nome)
             VALUES ('Secretaria de Saúde')
             ON DUPLICATE KEY UPDATE nome = nome"
        );

        $conn->query(
            "INSERT INTO categoria(nome)
             VALUES
                ('Cozinha'),
                ('Limpeza'),
                ('Higiene'),
                ('Escritório'),
                ('Papelaria'),
                ('EPI')
             ON DUPLICATE KEY UPDATE nome = nome"
        );

        if ($importar) {

            if ($catalogoPath === null) {
                throw new DomainException(
                    'Arquivo de catálogo não encontrado. '
                    . 'Instalação cancelada.'
                );
            }

            $catalogo = file_get_contents($catalogoPath);

            if (
                $catalogo === false ||
                !preg_match(
                    '/\A\s*'
                    . '(?:USE\s+[\w`]+;\s*)?'
                    . '(?:INSERT IGNORE INTO categoria '
                    . '\(nome\) VALUES[^;]+;\s*)?'
                    . '(INSERT INTO produto '
                    . '\(nome, unidade, estoque, estoque_minimo, categoria_id\) '
                    . 'VALUES[^;]+);'
                    . '\s*\z/s',
                    $catalogo,
                    $partes
                )
            ) {
                throw new DomainException(
                    'Formato do catálogo não reconhecido. '
                    . 'Instalação cancelada.'
                );
            }

            $conn->query($partes[1]);

            if (
                (int) $conn
                    ->query(
                        'SELECT COUNT(*)
                         FROM produto
                         WHERE estoque <> 0'
                    )
                    ->fetch_row()[0] !== 0
            ) {
                throw new DomainException(
                    'O catálogo inicial precisa ter saldo zero. '
                    . 'Instalação cancelada.'
                );
            }

            if (
                !registrarLog(
                    $conn,
                    'Importação de catálogo',
                    'Catálogo inicial importado com saldo zero.',
                    $id
                )
            ) {
                throw new RuntimeException(
                    'Falha de auditoria.'
                );
            }
        }

        if (
            !registrarLog(
                $conn,
                'Inicialização',
                'Primeiro administrador e dependências iniciais cadastrados.',
                $id
            )
        ) {
            throw new RuntimeException(
                'Falha de auditoria.'
            );
        }

        $conn->commit();
        $transacao = false;

        echo "Instalação inicializada. "
            . "Cadastre as UBS reais antes de registrar saídas.\n";
    }

} catch (Throwable $e) {

    if (
        $transacao &&
        $conn instanceof mysqli
    ) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
        }
    }

    fwrite(
        STDERR,
        "ERRO REAL:\n"
        . $e->getMessage()
        . "\nArquivo: "
        . $e->getFile()
        . "\nLinha: "
        . $e->getLine()
        . "\n"
    );

    $codigo = 1;

} finally {

    if (
        $travado &&
        $conn instanceof mysqli
    ) {
        try {
            $conn->query(
                "SELECT RELEASE_LOCK(
                    CONCAT(DATABASE(), ':inicializacao')
                )"
            );
        } catch (Throwable $e) {
        }
    }
}

exit($codigo);