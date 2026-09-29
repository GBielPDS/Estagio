<?php
declare(strict_types=1);

// Esta ferramenta nunca executa pelo navegador.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$comando = $argv[1] ?? '';
if (!in_array($comando, ['migrar', 'inicializar'], true)) {
    fwrite(STDERR, "Uso: php bd/administrar.php migrar | inicializar\nImporte bd/criar-bd.sql antes da primeira inicialização.\n");
    exit(1);
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// Local: bd/ ao lado de script/. Hospedagem: bd/ na pasta privada irmã de public_html/.
$raizPublica = is_file(dirname(__DIR__) . '/script/configuracao.php')
    ? dirname(__DIR__) : dirname(__DIR__, 2) . '/public_html';
require_once $raizPublica . '/script/conexao.php';
require_once $raizPublica . '/script/funcoes_usuarios.php';

function lerInstalacao(string $pergunta): string
{
    echo $pergunta;
    $linha = fgets(STDIN);
    if ($linha === false) throw new DomainException('Entrada encerrada. Instalação cancelada.');
    return rtrim($linha, "\r\n");
}

$travado = false;
$transacao = false;
$codigo = 0;
try {
    if ($comando === 'migrar') {
        if ($conn->query("SHOW COLUMNS FROM usuario LIKE 'versao_sessao'")->num_rows === 0) {
            $conn->query('ALTER TABLE usuario ADD COLUMN versao_sessao INT UNSIGNED NOT NULL DEFAULT 1');
        }
        $migracao = file_get_contents(__DIR__ . '/migracoes/002-confirmacao-identidade.sql');
        if ($migracao === false) throw new RuntimeException('Migração não encontrada.');
        $conn->query($migracao);
        $sqlTentativas = file_get_contents(__DIR__ . '/migracoes/003-tentativa-login.sql');
        if ($sqlTentativas === false) throw new RuntimeException('Migração não encontrada.');
        $conn->query($sqlTentativas);
        $motor = $conn->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tentativa_login'")->fetch_row()[0];
        if (strtolower($motor) !== 'innodb') $conn->query('ALTER TABLE tentativa_login ENGINE=InnoDB');
        $sqlJanela = file_get_contents(__DIR__ . '/migracoes/004-janela-login.sql');
        if ($sqlJanela === false) throw new RuntimeException('Migração não encontrada.');
        preg_match_all('/ALTER TABLE tentativa_login ADD COLUMN (\w+) ([^;]+);/', $sqlJanela, $alteracoes, PREG_SET_ORDER);
        if (count($alteracoes) !== 3) throw new RuntimeException('Migração de login inválida.');
        foreach ($alteracoes as $alteracao) {
            if ($conn->query("SHOW COLUMNS FROM tentativa_login LIKE '" . $alteracao[1] . "'")->num_rows === 0) {
                $conn->query($alteracao[0]);
            }
        }
        $sqlDatas = file_get_contents(__DIR__ . '/migracoes/005-data-cadastro-movimentacao.sql');
        if ($sqlDatas === false) throw new RuntimeException('Migração não encontrada.');
        preg_match_all('/ALTER TABLE movimentacao[^;]+;/', $sqlDatas, $comandosDatas);
        if (count($comandosDatas[0]) !== 2) throw new RuntimeException('Migração de datas inválida.');
        if ($conn->query("SHOW COLUMNS FROM movimentacao LIKE 'cadastrado_em'")->num_rows === 0) {
            $conn->query($comandosDatas[0][0]);
        }
        $conn->query($comandosDatas[0][1]);
        echo "Banco atualizado. Os dados existentes foram preservados.\n";
    } else {
        if ((int) $conn->query('SELECT COUNT(*) FROM usuario')->fetch_row()[0] !== 0) {
            throw new DomainException('Inicialização bloqueada: já existem usuários. Use a administração do sistema.');
        }
        $nome = trim(lerInstalacao('Nome do primeiro administrador: '));
        $email = trim(lerInstalacao('E-mail: '));
        $senha = lerInstalacao('Senha inicial (visível neste terminal local): ');
        validarDadosUsuario($nome, $email, $senha, 'Administrador', 1, true);
        $importar = false;
        if ((int) $conn->query('SELECT COUNT(*) FROM produto')->fetch_row()[0] === 0) {
            do {
                $resposta = strtolower(trim(lerInstalacao('Deseja cadastrar o catálogo inicial de produtos? [s/n] ')));
                if (!in_array($resposta, ['s', 'n'], true)) echo "Responda s ou n.\n";
            } while (!in_array($resposta, ['s', 'n'], true));
            $importar = $resposta === 's';
        } else {
            echo "Produtos existentes preservados; catálogo não será importado.\n";
        }
        $travado = (int) $conn->query("SELECT GET_LOCK(CONCAT(DATABASE(), ':inicializacao'), 10)")->fetch_row()[0] === 1;
        if (!$travado) throw new DomainException('Outra inicialização está em andamento. Tente novamente depois.');
        $conn->begin_transaction();
        $transacao = true;
        if ((int) $conn->query('SELECT COUNT(*) FROM usuario')->fetch_row()[0] !== 0) {
            throw new DomainException('Inicialização bloqueada: outro processo já cadastrou usuários.');
        }
        if ($importar && (int) $conn->query('SELECT COUNT(*) FROM produto')->fetch_row()[0] !== 0) {
            throw new DomainException('Produtos foram cadastrados durante a preparação. Reinicie a instalação.');
        }
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        unset($senha);
        $stmt = $conn->prepare("INSERT INTO usuario(nome,email,senha,tipo) VALUES (?,?,?,'Administrador')");
        $stmt->bind_param('sss', $nome, $email, $hash);
        $stmt->execute();
        $id = (int) $conn->insert_id;
        $stmt->close();
        $conn->query("INSERT INTO unidade_saude(nome) VALUES ('Secretaria de Saúde') ON DUPLICATE KEY UPDATE nome=nome");
        $conn->query("INSERT INTO categoria(nome) VALUES ('Cozinha'),('Limpeza'),('Higiene'),('Escritório'),('Papelaria'),('EPI') ON DUPLICATE KEY UPDATE nome=nome");
        if ($importar) {
            // Apenas o INSERT de produtos do catálogo versionado. Nunca executar USE, DDL ou SQL recebido do usuário.
            $catalogo = file_get_contents(__DIR__ . '/inserir-produtos.sql');
            if ($catalogo === false || !preg_match('/\A\s*USE almoxarifado;\s*INSERT IGNORE INTO categoria \(nome\) VALUES[^;]+;\s*(INSERT INTO produto \(nome, unidade, estoque, estoque_minimo, categoria_id\) VALUES[^;]+);\s*\z/s', $catalogo, $partes)) {
                throw new DomainException('Formato do catálogo não reconhecido. Instalação cancelada.');
            }
            $conn->query($partes[1]);
            if ((int) $conn->query('SELECT COUNT(*) FROM produto WHERE estoque <> 0')->fetch_row()[0] !== 0) {
                throw new DomainException('O catálogo inicial precisa ter saldo zero. Instalação cancelada.');
            }
            if (!registrarLog($conn, 'Importação de catálogo', 'Catálogo inicial importado com saldo zero.', $id)) {
                throw new RuntimeException('Falha de auditoria.');
            }
        }
        if (!registrarLog($conn, 'Inicialização', 'Primeiro administrador e dependências iniciais cadastrados.', $id)) {
            throw new RuntimeException('Falha de auditoria.');
        }
        $conn->commit();
        $transacao = false;
        echo "Instalação inicializada. Cadastre as UBS reais antes de registrar saídas.\n";
    }
} catch (Throwable $e) {
    if ($transacao) $conn->rollback();
    fwrite(STDERR, ($e instanceof DomainException ? $e->getMessage() : 'Falha na operação. Verifique a estrutura do banco e a configuração; alterações da instalação foram desfeitas.') . "\n");
    $codigo = 1;
} finally {
    if ($travado) $conn->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':inicializacao'))");
}
exit($codigo);
