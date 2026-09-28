<?php
declare(strict_types=1);
require_once __DIR__ . '/captcha.php';

const LOGIN_JANELA = 900;
const LOGIN_BLOQUEIO = 900;
const LOGIN_LIMITE_CONTA = 5;
const LOGIN_LIMITE_IP = 10;

// Hash fictício fixo: apenas equaliza a verificação de contas inexistentes.
const LOGIN_HASH_FICTICIO = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';

function autenticarLogin(mysqli $conn, string $email, string $senha, string $ip, string $captcha = '', ?callable $transporteCaptcha = null): array
{
    // Verificação externa antes de abrir a transação: nenhuma espera de rede com locks.
    $verificacao = validarRecaptchaLogin($captcha, $transporteCaptcha);
    if ($verificacao['status'] !== 200) return $verificacao;
    return executarTentativaLogin($conn, $email, $senha, $ip);
}

function executarTentativaLogin(mysqli $conn, string $email, string $senha, string $ip, int $repeticao = 0): array
{
    $email = trim($email);
    if ($email === '' || $senha === '') return ['status' => 400, 'mensagem' => 'Preencha todos os campos.'];
    if (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['status' => 400, 'mensagem' => 'Digite um e-mail válido.'];
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return ['status' => 503, 'mensagem' => 'Login temporariamente indisponível. Tente novamente.'];
    $ip = inet_ntop(inet_pton($ip));
    $email = strtolower($email);
    $transacao = false;
    try {
        // Ordem única IP -> conta. O lock cobre consulta, senha e contagem, entre sessões.
        $conn->begin_transaction();
        $transacao = true;
        $controles = [];
        foreach (['ip' => $ip, 'conta' => $email] as $tipo => $identificador) {
            $stmt = $conn->prepare("INSERT INTO tentativa_login (identificador,tipo,falhas) VALUES (?,?,'[]') ON DUPLICATE KEY UPDATE id_tentativa=id_tentativa");
            if (!$stmt) throw new RuntimeException('Controle indisponível.');
            $stmt->bind_param('ss', $identificador, $tipo);
            if (!$stmt->execute()) throw new RuntimeException('Controle indisponível.');
            $stmt->close();
            $stmt = $conn->prepare('SELECT id_tentativa,falhas,bloqueio_epoch FROM tentativa_login WHERE identificador=? AND tipo=? FOR UPDATE');
            if (!$stmt) throw new RuntimeException('Controle indisponível.');
            $stmt->bind_param('ss', $identificador, $tipo);
            if (!$stmt->execute()) throw new RuntimeException('Controle indisponível.');
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) throw new RuntimeException('Controle indisponível.');
            $controles[$tipo] = $row;
        }
        // Relógio único, em segundos Unix; independente do fuso PHP/MySQL.
        $agora = time();
        $espera = 0;
        foreach ($controles as &$controle) {
            $fim = (int) $controle['bloqueio_epoch'];
            $espera = max($espera, $fim - $agora);
            $falhas = json_decode($controle['falhas'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($falhas)) throw new RuntimeException('Controle inválido.');
            $controle['falhas'] = $fim > 0 && $fim <= $agora ? [] : array_values(array_filter($falhas,
                static fn($t) => is_int($t) && $t > $agora - LOGIN_JANELA));
            if ($fim <= $agora) $controle['bloqueio_epoch'] = 0;
        }
        unset($controle);
        if ($espera > 0) {
            $conn->rollback();
            return ['status' => 429, 'espera' => $espera, 'mensagem' => 'Muitas tentativas de acesso. Aguarde alguns minutos e tente novamente.'];
        }
        $stmt = $conn->prepare('SELECT id_usuario,nome,email,senha,tipo,ativo,versao_sessao FROM usuario WHERE email=?');
        if (!$stmt) throw new RuntimeException('Consulta indisponível.');
        $stmt->bind_param('s', $email);
        if (!$stmt->execute()) throw new RuntimeException('Consulta indisponível.');
        $usuario = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $senhaCorreta = password_verify($senha, $usuario['senha'] ?? LOGIN_HASH_FICTICIO);
        $valido = $usuario && $senhaCorreta && (int) $usuario['ativo'] === 1;
        foreach ($controles as $tipo => $controle) {
            $falhas = $controle['falhas'];
            if ($valido && $tipo === 'conta') $falhas = [];
            elseif (!$valido) $falhas[] = $agora;
            $limite = $tipo === 'ip' ? LOGIN_LIMITE_IP : LOGIN_LIMITE_CONTA;
            $fim = count($falhas) >= $limite ? $agora + LOGIN_BLOQUEIO : 0;
            $espera = max($espera, $fim - $agora);
            $json = json_encode($falhas, JSON_THROW_ON_ERROR);
            $quantidade = count($falhas);
            $stmt = $conn->prepare('UPDATE tentativa_login SET falhas=?,bloqueio_epoch=?,atividade_epoch=?,tentativas=?,bloqueado_ate=NULL,ultima_tentativa=NOW() WHERE id_tentativa=?');
            if (!$stmt) throw new RuntimeException('Controle indisponível.');
            $stmt->bind_param('siiii', $json, $fim, $agora, $quantidade, $controle['id_tentativa']);
            if (!$stmt->execute()) throw new RuntimeException('Controle indisponível.');
            $stmt->close();
        }
        $conn->commit();
        $transacao = false;
        // Limpeza limitada, fora dos locks de autenticação. Falha de limpeza não libera tentativas.
        try {
            $corte = $agora - 86400;
            // Selecionar sem lock e excluir por chave evita bloquear uma faixa do índice
            // enquanto outros logins inserem seus identificadores.
            $stmt = $conn->prepare('SELECT id_tentativa FROM tentativa_login WHERE atividade_epoch < ? AND bloqueio_epoch < ? LIMIT 100');
            if ($stmt) {
                $stmt->bind_param('ii', $corte, $agora); $stmt->execute();
                $antigos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
                $stmt = $conn->prepare('DELETE FROM tentativa_login WHERE id_tentativa=? AND atividade_epoch < ? AND bloqueio_epoch < ?');
                if ($stmt) {
                    foreach ($antigos as $antigo) {
                        $stmt->bind_param('iii', $antigo['id_tentativa'], $corte, $agora); $stmt->execute();
                    }
                    $stmt->close();
                }
            }
        } catch (Throwable $e) { error_log('Limpeza de tentativas indisponível. Código: ' . $e->getCode()); }
        if ($valido) { unset($usuario['senha']); return ['status' => 200, 'usuario' => $usuario]; }
        if ($espera > 0) return ['status' => 429, 'espera' => $espera, 'mensagem' => 'Muitas tentativas de acesso. Aguarde alguns minutos e tente novamente.'];
        return ['status' => 401, 'mensagem' => $usuario && $senhaCorreta
            ? 'Este usuário foi desativado. Entre em contato com o administrador do sistema.' : 'Email ou senha incorretos.'];
    } catch (Throwable $e) {
        if ($transacao) $conn->rollback();
        // InnoDB pode escolher uma vítima de deadlock mesmo com ordem estável.
        // Repetir somente após rollback, nunca após confirmação da transação.
        if ($transacao && in_array((int) $e->getCode(), [1213, 1205], true) && $repeticao < 4) {
            usleep(random_int(10000, 50000));
            return executarTentativaLogin($conn, $email, $senha, $ip, $repeticao + 1);
        }
        error_log('Controle de login indisponível. Código: ' . $e->getCode());
        return ['status' => 503, 'mensagem' => 'Login temporariamente indisponível. Tente novamente.'];
    }
}
