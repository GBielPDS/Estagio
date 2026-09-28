<?php
declare(strict_types=1);

function carregarAmbiente(string $diretorio): \Dotenv\Repository\RepositoryInterface
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) throw new RuntimeException('Dependências de configuração ausentes.');
    require_once $autoload;
    $arquivo = $diretorio . '/.env';
    if (is_file($arquivo) && !is_readable($arquivo)) throw new RuntimeException('Configuração indisponível.');
    // Ler o ambiente do processo sem escrever via putenv (PHP ZTS).
    $repositorio = \Dotenv\Repository\RepositoryBuilder::createWithDefaultAdapters()
        ->addReader(\Dotenv\Repository\Adapter\PutenvAdapter::class)
        ->immutable()->make();
    try {
        \Dotenv\Dotenv::create($repositorio, $diretorio)->safeLoad();
        if (is_file($arquivo) && $repositorio->get('GESTSAUDE_RECAPTCHA_ATIVO') === null) {
            throw new RuntimeException('Configuração de ativação ausente.');
        }
    } catch (Throwable $e) {
        // A exceção do parser pode conter trechos do .env. Não propagá-la nem registrá-la.
        throw new RuntimeException('Arquivo de configuração inválido.');
    }
    return $repositorio;
}

function ambienteSistema(): \Dotenv\Repository\RepositoryInterface
{
    static $repositorio = null;
    if ($repositorio === null) {
        try {
            $repositorio = carregarAmbiente(dirname(__DIR__));
        } catch (Throwable $e) {
            error_log('Configuração do sistema indisponível. Verifique o .env e as dependências.');
            $mensagem = 'Configuração do sistema indisponível. Contate o responsável pelo sistema.';
            if (PHP_SAPI === 'cli') { fwrite(STDERR, $mensagem . PHP_EOL); exit(1); }
            http_response_code(503);
            exit($mensagem);
        }
    }
    return $repositorio;
}

function valorConfiguracao(string $nome, string $padrao = ''): string
{
    return ambienteSistema()->get($nome) ?? $padrao;
}
