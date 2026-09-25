<?php

declare(strict_types=1);

const RECAPTCHA_SITE_KEY = 'SUA_SITE_KEY';
const RECAPTCHA_SECRET_KEY = 'SUA_SECRET_KEY';


function verificarRecaptcha(string $response): bool
{
    if ($response === '') {
        return false;
    }

    $dados = http_build_query([
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $response,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    $opcoes = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $dados,
            'timeout' => 10
        ]
    ];

    $contexto = stream_context_create($opcoes);

    $resultado = file_get_contents(
        'https://www.google.com/recaptcha/api/siteverify',
        false,
        $contexto
    );

    if ($resultado === false) {
        return false;
    }

    $dadosResposta = json_decode($resultado, true);

    return ($dadosResposta['success'] ?? false) === true;
}