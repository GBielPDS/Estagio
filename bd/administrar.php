<?php
declare(strict_types=1);
// Ferramenta local: nunca pode ser executada por uma página web.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli('localhost', 'root', '', getenv('GESTSAUDE_DB') ?: 'almoxarifado');
$conn->set_charset('utf8mb4');
$comando = $argv[1] ?? '';
if ($comando === 'migrar') {
    $coluna = $conn->query("SHOW COLUMNS FROM usuario LIKE 'versao_sessao'");
    if ($coluna->num_rows === 0) {
        $conn->query('ALTER TABLE usuario ADD COLUMN versao_sessao INT UNSIGNED NOT NULL DEFAULT 1');
    }
    echo "Banco atualizado. Os dados existentes foram preservados.\n";
    exit;
}
if ($comando !== 'inicializar') {
    exit("Uso: php bd/administrar.php migrar | inicializar\nImporte bd/criar-bd.sql antes da primeira inicialização.\n");
}
if ((int) $conn->query('SELECT COUNT(*) FROM usuario')->fetch_row()[0] !== 0) {
    exit("Inicialização bloqueada: já existem usuários. Use a administração do sistema.\n");
}
require_once __DIR__ . '/../script/funcoes_usuarios.php';
echo "Nome do primeiro administrador: "; $nome = trim((string) fgets(STDIN));
echo "E-mail: "; $email = trim((string) fgets(STDIN));
echo "Senha inicial (a digitação fica visível neste terminal local): "; $senha = rtrim((string) fgets(STDIN), "\r\n");
try {
    validarDadosUsuario($nome, $email, $senha, 'Administrador', 1, true);
    $lock = $conn->query("SELECT GET_LOCK(CONCAT(DATABASE(), ':inicializacao'), 10)")->fetch_row()[0];
    if ((int) $lock !== 1) throw new RuntimeException('Outra inicialização está em andamento.');
    $conn->begin_transaction();
    if ((int) $conn->query('SELECT COUNT(*) FROM usuario')->fetch_row()[0] !== 0) throw new DomainException('Já existe usuário cadastrado.');
    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO usuario(nome,email,senha,tipo) VALUES (?,?,?,'Administrador')");
    $stmt->bind_param('sss', $nome, $email, $hash); $stmt->execute();
    $id = (int) $conn->insert_id;
    $conn->query("INSERT IGNORE INTO unidade_saude(nome) VALUES ('Secretaria de Saúde')");
    $conn->query("INSERT IGNORE INTO categoria(nome) VALUES ('Cozinha'),('Limpeza'),('Higiene'),('Escritório'),('Papelaria'),('EPI')");
    if (!registrarLog($conn, 'Inicialização', 'Primeiro administrador e dependências iniciais cadastrados.', $id)) throw new RuntimeException('Falha de auditoria.');
    $conn->commit();
    echo "Instalação inicializada. Cadastre as UBS reais antes de registrar saídas.\n";
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, erroUsuario($e)['mensagem'] . "\n");
    exit(1);
} finally {
    $conn->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':inicializacao'))");
}
