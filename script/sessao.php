<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax']);
    session_start();
}
if (!defined('BASE_URL')) define('BASE_URL', '/git/ESTAGIO/');
function tokenCsrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function campoCsrf(): string { return '<input type="hidden" name="csrf" value="' . tokenCsrf() . '">'; }
function respostaAcesso(int $status, string $mensagem): never {
    http_response_code($status);
    if (isset($_POST['ajax']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['sucesso' => false, 'mensagem' => $mensagem]);
    } else echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8');
    exit;
}
function encerrarSessao(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}
function verificarSessao(): void {
    global $conn;
    $valida = false;
    if (isset($_SESSION['id_usuario'], $_SESSION['versao_sessao'])) {
        try {
            $stmt = $conn->prepare('SELECT nome, email, tipo, ativo, versao_sessao FROM usuario WHERE id_usuario = ?');
            $stmt->bind_param('i', $_SESSION['id_usuario']);
            $stmt->execute();
            $usuario = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $valida = $usuario && (int) $usuario['ativo'] === 1 && (int) $usuario['versao_sessao'] === (int) $_SESSION['versao_sessao'];
            if ($valida) foreach (['nome', 'email', 'tipo'] as $campo) $_SESSION[$campo] = $usuario[$campo];
        } catch (Throwable $e) {
            error_log((string) $e);
            respostaAcesso(503, 'Não foi possível verificar o acesso. Tente novamente.');
        }
    }
    if (!$valida) {
        encerrarSessao();
        if (isset($_POST['ajax'])) respostaAcesso(401, 'Sua sessão terminou. Entre novamente e confira o histórico antes de repetir um lançamento.');
        header('Location: ' . BASE_URL . 'pages/login.php');
        exit;
    }
}
function verificarTipo(array $tiposPermitidos): void {
    if (!in_array($_SESSION['tipo'] ?? '', $tiposPermitidos, true)) respostaAcesso(403, 'Acesso negado.');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['csrf'] ?? null;
    if (!is_string($token) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token))
        respostaAcesso(403, 'Formulário expirado ou inválido. Atualize a página e tente novamente.');
}

// Regras compartilhadas pela apresentação e pelas operações de gravação.
function podeGerenciarCadastros(?string $tipo = null): bool
{
    return in_array($tipo ?? ($_SESSION['tipo'] ?? ''), ['Administrador', 'Suporte'], true);
}

function podeEditarConta(array $conta, ?string $tipo = null): bool
{
    $tipo ??= $_SESSION['tipo'] ?? '';
    return $tipo === 'Administrador' || ($tipo === 'Suporte' && $conta['tipo'] === 'Usuario'
        && (int) $conta['ativo'] === 1 && (int) $conta['id_usuario'] !== (int) ($_SESSION['id_usuario'] ?? 0));
}

function podeEditarUnidade(array $unidade, ?string $tipo = null): bool
{
    $tipo ??= $_SESSION['tipo'] ?? '';
    return $tipo === 'Administrador' || ($tipo === 'Suporte' && (int) ($unidade['central'] ?? 0) === 0);
}
