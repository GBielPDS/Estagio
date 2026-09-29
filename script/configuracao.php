<?php
declare(strict_types=1);

// Configuração única para páginas, conexão e ferramentas de terminal.
$raizPublica = dirname(__DIR__);
$pastaAmbiente = getenv('GESTSAUDE_ENV_DIR');
if ($pastaAmbiente === false || $pastaAmbiente === '') {
    $pastaAmbiente = is_file($raizPublica . '/login.php')
        ? dirname($raizPublica) . '/ARQUIVOS_SENSIVEIS_AQUI'
        : $raizPublica;
}
try {
    require_once $raizPublica . '/vendor/autoload.php';
    Dotenv\Dotenv::createImmutable($pastaAmbiente)->safeLoad();
} catch (Throwable $e) {
    error_log('Não foi possível carregar a configuração do ambiente.');
    http_response_code(503);
    exit('Configuração indisponível. Contate o responsável pelo sistema.');
}

function configuracaoAmbiente(string $nome, string $padrao = ''): string
{
    $valor = getenv($nome);
    return $valor !== false ? $valor : (string) ($_ENV[$nome] ?? $_SERVER[$nome] ?? $padrao);
}

// Caminhos de URL são distintos dos caminhos físicos usados por require_once.
function calcularBaseUrl(string $arquivo, string $url, string $raiz): string
{
    $pasta = str_replace('\\', '/', dirname($arquivo));
    $raiz = rtrim(str_replace('\\', '/', $raiz), '/');
    if ($pasta !== $raiz && !str_starts_with($pasta, $raiz . '/')) return '/';
    $subpasta = trim(substr($pasta, strlen($raiz)), '/');
    $base = str_replace('\\', '/', dirname($url));
    if ($subpasta !== '') {
        foreach (explode('/', $subpasta) as $_) $base = str_replace('\\', '/', dirname($base));
    }
    $partes = array_filter(explode('/', $base), fn($p) => $p !== '' && $p !== '.');
    return '/' . ($partes ? implode('/', array_map(fn($p) => rawurlencode(rawurldecode($p)), $partes)) . '/' : '');
}

if (!defined('BASE_URL')) {
    define('BASE_URL', calcularBaseUrl($_SERVER['SCRIPT_FILENAME'] ?? '', $_SERVER['SCRIPT_NAME'] ?? '/', $raizPublica));
}
if (!defined('PAGINAS_URL')) {
    define('PAGINAS_URL', BASE_URL . (is_file($raizPublica . '/login.php') ? '' : 'pages/'));
}

const FUSO_HORARIO_SISTEMA = 'America/Bahia';
date_default_timezone_set(FUSO_HORARIO_SISTEMA);

function configurarHorarioBanco(mysqli $conn): void
{
    // Offset do fuso institucional no momento da conexão; dispensa tabelas de fusos do MySQL.
    $offset = (new DateTimeImmutable('now', new DateTimeZone(FUSO_HORARIO_SISTEMA)))->format('P');
    $stmt = $conn->prepare('SET time_zone = ?');
    $stmt->bind_param('s', $offset);
    if (!$stmt->execute()) throw new RuntimeException('Não foi possível configurar o horário do banco.');
    $stmt->close();
}
