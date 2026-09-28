<?php
declare(strict_types=1);
require_once __DIR__ . '/ambiente.php';
ambienteSistema();

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
