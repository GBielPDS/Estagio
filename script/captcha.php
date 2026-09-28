<?php
declare(strict_types=1);
require_once __DIR__ . '/ambiente.php';

function configuracaoRecaptcha(): array
{
    $modo = strtolower(trim((string) valorConfiguracao('GESTSAUDE_RECAPTCHA_ATIVO')));
    $ativo = !in_array($modo, ['', '0', 'false', 'off'], true);
    $site = trim((string) valorConfiguracao('GESTSAUDE_RECAPTCHA_SITE_KEY'));
    $secret = trim((string) valorConfiguracao('GESTSAUDE_RECAPTCHA_SECRET_KEY'));
    $hosts = array_values(array_filter(array_map('trim', explode(',', strtolower((string) valorConfiguracao('GESTSAUDE_RECAPTCHA_HOSTNAMES'))))));
    $valida = in_array($modo, ['1','true','on'], true) && $site !== '' && $secret !== '' && $hosts !== []
        && $site !== 'SUA_SITE_KEY' && $secret !== 'SUA_SECRET_KEY';
    return ['ativo'=>$ativo, 'valida'=>$valida, 'site'=>$site, 'secret'=>$secret, 'hosts'=>$hosts];
}

function enviarVerificacaoRecaptcha(array $dados): array
{
    $contexto = stream_context_create([
        'http'=>['method'=>'POST', 'header'=>"Content-Type: application/x-www-form-urlencoded\r\n",
            'content'=>http_build_query($dados), 'timeout'=>8, 'ignore_errors'=>true, 'follow_location'=>0],
        'ssl'=>['verify_peer'=>true, 'verify_peer_name'=>true]
    ]);
    // Não registrar avisos de transporte, tokens ou chave secreta.
    $stream = @fopen('https://www.google.com/recaptcha/api/siteverify', 'r', false, $contexto);
    if ($stream === false) return ['status'=>0, 'body'=>false];
    $corpo = @stream_get_contents($stream, 65536);
    $meta = stream_get_meta_data($stream);
    fclose($stream);
    $status = 0;
    $cabecalhos = $meta['wrapper_data'] ?? [];
    if (isset($cabecalhos[0]) && preg_match('/^HTTP\/\S+ (\d{3})/', $cabecalhos[0], $m)) $status = (int) $m[1];
    if (!empty($meta['timed_out'])) $status = 0;
    return ['status'=>$status, 'body'=>$corpo];
}

// Transporte injetável somente por código PHP para testes; nunca por parâmetros HTTP/configuração.
function validarRecaptchaLogin(string $token, ?callable $transporte = null): array
{
    $config = configuracaoRecaptcha();
    if (!$config['ativo']) return ['status'=>200];
    $indisponivel = ['status'=>503, 'mensagem'=>'Verificação de segurança indisponível. Tente novamente mais tarde.'];
    $invalido = ['status'=>400, 'mensagem'=>'Confirme novamente que você não é um robô.'];
    if (!$config['valida']) return $indisponivel;
    if (trim($token) === '' || strlen($token) > 8192) return $invalido;
    try {
        $resposta = ($transporte ?? 'enviarVerificacaoRecaptcha')(['secret'=>$config['secret'], 'response'=>$token]);
        if (($resposta['status'] ?? 0) !== 200 || !is_string($resposta['body'] ?? null)) return $indisponivel;
        $dados = json_decode($resposta['body'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($dados) || !is_bool($dados['success'] ?? null)) return $indisponivel;
        if (!$dados['success']) {
            $erros = $dados['error-codes'] ?? [];
            if (!is_array($erros)) return $indisponivel;
            return array_intersect($erros, ['missing-input-secret','invalid-input-secret','bad-request']) ? $indisponivel : $invalido;
        }
        $host = $dados['hostname'] ?? null;
        if (!is_string($host) || !in_array(strtolower($host), $config['hosts'], true)) return $invalido;
        return ['status'=>200];
    } catch (Throwable $e) {
        return $indisponivel;
    }
}
