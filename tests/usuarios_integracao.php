<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../script/funcoes_usuarios.php';
require_once __DIR__ . '/../script/funcoes_lancamentos.php';
require_once __DIR__ . '/../script/funcoes_produtos.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli('localhost', 'root', '');
$bancoTeste = 'gestsaude_teste_' . bin2hex(random_bytes(6));
$conn->query("CREATE DATABASE `$bancoTeste` CHARACTER SET utf8mb4");
$conn->select_db($bancoTeste);
$conn->set_charset('utf8mb4');
$total = 0;
function conferir(bool $ok, string $caso): void {
    global $total;
    if (!$ok) throw new RuntimeException('Falhou: ' . $caso);
    $total++;
    echo "OK: $caso\n";
}
try {
    $sql = file_get_contents(__DIR__ . '/../bd/criar-bd.sql');
    $sql = preg_replace('/CREATE DATABASE almoxarifado;\s*USE almoxarifado;/', '', $sql);
    $conn->multi_query($sql);
    while ($conn->more_results()) $conn->next_result();
    $hash = password_hash('Inicial123!', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO usuario(nome,email,senha,tipo) VALUES ('Admin','admin@teste.local',?,'Administrador')");
    $stmt->bind_param('s', $hash); $stmt->execute();
    $_SESSION = ['id_usuario' => 1, 'versao_sessao' => 1];
    conferir(!excluirUsuario($conn, 1)['sucesso'], 'autodesativação bloqueada');
    conferir(!atualizarUsuario($conn, 1, 'Admin', 'admin@teste.local', '', 'Usuario', 1)['sucesso'], 'último administrador preservado');
    conferir(!cadastrarUsuario($conn, 'X', 'invalido', 'Senha123!', 'Usuario')['sucesso'], 'e-mail inválido rejeitado');
    conferir(cadastrarUsuario($conn, 'Segundo', 'segundo@teste.local', 'Senha123!', 'Administrador')['sucesso'], 'cadastro administrativo');
    conferir(!cadastrarUsuario($conn, 'Duplicado', 'segundo@teste.local', 'Senha123!', 'Usuario')['sucesso'], 'e-mail duplicado rejeitado');
    conferir(excluirUsuario($conn, 2)['sucesso'], 'desativação preserva a conta');
    conferir(!cadastrarUsuario($conn, 'Outro', 'segundo@teste.local', 'Senha123!', 'Usuario')['sucesso'], 'cadastro não reativa conta');
    conferir(reativarUsuario($conn, 2)['sucesso'], 'reativação explícita');
    conferir((int) buscarUsuarioPorId($conn, 2)['versao_sessao'] === 3, 'sessões antigas não revivem após reativação');
    conferir(!atualizarUsuario($conn, 2, 'Segundo', 'segundo@teste.local', '', 'Administrador', 2)['sucesso'], 'status inválido rejeitado');
    conferir(atualizarUsuario($conn, 2, 'Segundo', 'segundo@teste.local', 'Outra123!', 'Administrador', 1)['sucesso'], 'redefinição administrativa');
    conferir((int) buscarUsuarioPorId($conn, 2)['versao_sessao'] === 4, 'redefinição invalida sessões');
    conferir(atualizarPerfilUsuario($conn, 1, 'Admin', 'admin@teste.local', 'Propria123!')['sucesso'], 'troca da própria senha');
    conferir($_SESSION['versao_sessao'] === 2, 'sessão atual preservada na troca própria');
    conferir(atualizarUsuario($conn, 1, 'Admin', 'admin@teste.local', '', 'Usuario', 1)['sucesso'], 'redução de perfil com outro administrador');
    conferir(!reativarUsuario($conn, 2)['sucesso'], 'permissão antiga não autoriza operação');
    $_SESSION = ['id_usuario' => 2, 'versao_sessao' => 4];
    $conn->query("CREATE TRIGGER impedir_log BEFORE INSERT ON log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Falha simulada de auditoria'");
    conferir(!atualizarUsuario($conn, 1, 'Não salvar', 'admin@teste.local', '', 'Usuario', 1)['sucesso'], 'falha de auditoria reportada');
    conferir(buscarUsuarioPorId($conn, 1)['nome'] === 'Admin', 'rollback da conta quando log falha');
    $conn->query("INSERT INTO categoria(nome) VALUES ('Teste')");
    $conn->query("INSERT INTO produto(nome,unidade,categoria_id,estoque) VALUES ('Material','Unidade',1,10)");
    $conn->query("INSERT INTO unidade_saude(nome) VALUES ('Secretaria de Saúde'),('UBS Teste')");
    conferir(!lancamentoSaida($conn, [['produto_id'=>1,'quantidade'=>2]], 2, '', 2)['sucesso'], 'falha de auditoria cancela saída');
    conferir((int) $conn->query('SELECT estoque FROM produto WHERE id_produto=1')->fetch_row()[0] === 10, 'estoque preservado após falha');
    $conn->query('DROP TRIGGER impedir_log');
    conferir(!lancamentoSaida($conn, [['produto_id'=>1,'quantidade'=>2]], 1, '', 2)['sucesso'], 'saída para Secretaria bloqueada');
    conferir(lancamentoSaida($conn, [['produto_id'=>1,'quantidade'=>2]], 2, '', 2)['sucesso'], 'saída válida');
    conferir((int) $conn->query('SELECT estoque FROM produto WHERE id_produto=1')->fetch_row()[0] === 8, 'saldo após saída');
    $_SESSION['tipo'] = 'Administrador';
    conferir(!atualizarProduto($conn, 1, 'Material', 1, 'Unidade', 9, 0, '', 8)['sucesso'], 'ajuste exige justificativa');
    conferir(!atualizarProduto($conn, 1, 'Material', 1, 'Unidade', 9, 0, 'Contagem física', 10)['sucesso'], 'edição antiga não sobrescreve movimentação');
    conferir(atualizarProduto($conn, 1, 'Material', 1, 'Unidade', 9, 0, 'Contagem física', 8)['sucesso'], 'ajuste justificado');
    conferir((int) $conn->query("SELECT COUNT(*) FROM movimentacao WHERE observacao LIKE 'Ajuste de inventário:%'")->fetch_row()[0] === 1, 'ajuste presente no histórico');
    $conn->begin_transaction();
    conferir(cadastrarProduto($conn, 'Novo', 1, 'Caixa', 5, 1)['sucesso'], 'cadastro com saldo inicial');
    $conn->commit();
    conferir((int) $conn->query("SELECT COUNT(*) FROM movimentacao WHERE observacao='Saldo inicial no cadastro do produto'")->fetch_row()[0] === 1, 'saldo inicial presente no histórico');
    echo "$total verificações aprovadas.\n";
} finally {
    // Exclusivamente o banco temporário criado por este processo.
    $conn->query("DROP DATABASE `$bancoTeste`");
}
