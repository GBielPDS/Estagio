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
            if ($valida && ($_SESSION['tipo'] ?? '') !== $usuario['tipo']) limparConfirmacoesIdentidade();
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


function limparConfirmacoesIdentidade(): void
{
    unset($_SESSION['confirmacoes_identidade'], $_SESSION['historico_login_autorizado'], $_SESSION['historico_login_autorizado_em']);
}

function permiteConfirmacaoIdentidade(string $finalidade, string $tipo): bool
{
    // Nenhuma finalidade futura é liberada implicitamente.
    return in_array($finalidade, ['historico_autenticacao', 'consultar_email', 'corrigir_email'], true) && $tipo === 'Administrador';
}

function identidadeConfirmada(mysqli $conn, string $finalidade): bool
{
    $grant = $_SESSION['confirmacoes_identidade'][$finalidade] ?? null;
    $agora = time();
    if (!is_array($grant) || (int) ($grant['usuario_id'] ?? 0) !== (int) ($_SESSION['id_usuario'] ?? -1)
        || (int) ($grant['versao'] ?? 0) !== (int) ($_SESSION['versao_sessao'] ?? -1)
        || ($grant['finalidade'] ?? '') !== $finalidade || (int) ($grant['emitida_em'] ?? 0) > $agora
        || (int) ($grant['expira_em'] ?? 0) <= $agora) {
        unset($_SESSION['confirmacoes_identidade'][$finalidade]);
        return false;
    }
    try {
        $id = (int) $_SESSION['id_usuario'];
        $stmt = $conn->prepare('SELECT u.tipo, u.ativo, u.versao_sessao, c.revisao, c.bloqueado_ate FROM usuario u INNER JOIN confirmacao_identidade c ON c.usuario_id=u.id_usuario WHERE u.id_usuario=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $atual = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($atual && (int) $atual['ativo'] === 1 && permiteConfirmacaoIdentidade($finalidade, $atual['tipo'])
            && (int) $atual['versao_sessao'] === (int) $grant['versao'] && $atual['tipo'] === $grant['tipo']
            && (int) $atual['revisao'] === (int) $grant['revisao'] && (int) $atual['bloqueado_ate'] <= $agora) return true;
    } catch (Throwable $e) {
        error_log('Falha ao conferir autorização sensível. Código: ' . $e->getCode());
    }
    unset($_SESSION['confirmacoes_identidade'][$finalidade]);
    return false;
}

function confirmarIdentidade(mysqli $conn, string $finalidade, string $senha): array
{
    require_once __DIR__ . '/funcoes_logs.php';
    unset($_SESSION['confirmacoes_identidade'][$finalidade]);
    $transacao = false;
    try {
        $conn->begin_transaction();
        $transacao = true;
        $id = (int) ($_SESSION['id_usuario'] ?? 0);
        // Mesma ordem de bloqueio das alterações de conta: usuário antes da confirmação.
        $stmt = $conn->prepare('SELECT senha, tipo, ativo, versao_sessao FROM usuario WHERE id_usuario=? FOR UPDATE');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$usuario || (int) $usuario['ativo'] !== 1 || !permiteConfirmacaoIdentidade($finalidade, $usuario['tipo'])
            || (int) $usuario['versao_sessao'] !== (int) ($_SESSION['versao_sessao'] ?? -1)) {
            $conn->rollback();
            return ['sucesso'=>false, 'status'=>403, 'mensagem'=>'Acesso negado.'];
        }
        $stmt = $conn->prepare("INSERT INTO confirmacao_identidade(usuario_id, falhas) VALUES (?, '[]') ON DUPLICATE KEY UPDATE usuario_id=usuario_id");
        $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
        $stmt = $conn->prepare('SELECT falhas, bloqueado_ate, revisao FROM confirmacao_identidade WHERE usuario_id=? FOR UPDATE');
        $stmt->bind_param('i', $id); $stmt->execute();
        $controle = $stmt->get_result()->fetch_assoc(); $stmt->close();
        $agora = time();
        if ((int) $controle['bloqueado_ate'] > $agora) {
            $conn->commit();
            return ['sucesso'=>false, 'status'=>429, 'espera'=>(int) $controle['bloqueado_ate']-$agora,
                'mensagem'=>'Confirmação temporariamente bloqueada. Aguarde até 15 minutos e tente novamente.'];
        }
        $falhas = json_decode($controle['falhas'], true, 512, JSON_THROW_ON_ERROR);
        $falhas = array_values(array_filter($falhas, fn($t) => (int) $t > $agora - 900));
        if ($senha === '' || !password_verify($senha, $usuario['senha'])) {
            $falhas[] = $agora;
            $bloqueado = count($falhas) >= 5;
            $ate = $bloqueado ? $agora + 900 : 0;
            $revisao = (int) $controle['revisao'] + ($bloqueado ? 1 : 0);
            $json = json_encode($falhas, JSON_THROW_ON_ERROR);
            $stmt = $conn->prepare('UPDATE confirmacao_identidade SET falhas=?, bloqueado_ate=?, revisao=? WHERE usuario_id=?');
            $stmt->bind_param('siii', $json, $ate, $revisao, $id); $stmt->execute(); $stmt->close();
            // Uma falha no log não deve permitir zerar o limite de tentativas.
            try {
                if (!registrarLog($conn, $bloqueado ? 'Confirmação sensível bloqueada' : 'Confirmação sensível recusada',
                    'Finalidade: ' . $finalidade . '.', $id)) throw new RuntimeException('Falha de auditoria.');
            } catch (Throwable $e) {
                error_log('Falha ao auditar tentativa de confirmação. Código: ' . $e->getCode());
            }
            $conn->commit();
            return ['sucesso'=>false, 'status'=>$bloqueado ? 429 : 200, 'espera'=>$bloqueado ? 900 : 0,
                'mensagem'=>$bloqueado ? 'Confirmação bloqueada por 15 minutos após cinco erros.' : 'Senha incorreta.'];
        }
        if (!registrarLog($conn, $finalidade === 'historico_autenticacao' ? 'Acesso ao histórico de autenticação' : 'Confirmação de identidade para e-mail', 'Finalidade: ' . $finalidade . '.', $id)) {
            throw new RuntimeException('Falha de auditoria.');
        }
        $stmt = $conn->prepare("UPDATE confirmacao_identidade SET falhas='[]', bloqueado_ate=0 WHERE usuario_id=?");
        $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
        $conn->commit();
        $transacao = false;
        $_SESSION['confirmacoes_identidade'][$finalidade] = [
            'usuario_id'=>$id, 'versao'=>(int) $usuario['versao_sessao'], 'tipo'=>$usuario['tipo'],
            'finalidade'=>$finalidade, 'emitida_em'=>$agora, 'expira_em'=>$agora+300, 'revisao'=>(int) $controle['revisao']
        ];
        return ['sucesso'=>true, 'status'=>200, 'mensagem'=>'Identidade confirmada.'];
    } catch (Throwable $e) {
        if ($transacao) $conn->rollback();
        error_log('Falha na confirmação de identidade. Código: ' . $e->getCode());
        return ['sucesso'=>false, 'status'=>503, 'mensagem'=>$e->getCode() === 1146
            ? 'Confirmação indisponível: a atualização do banco ainda precisa ser aplicada pelo responsável.'
            : 'Não foi possível confirmar sua identidade. Nenhum acesso adicional foi liberado.'];
    }
}
