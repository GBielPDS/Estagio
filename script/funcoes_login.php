<?php

declare(strict_types=1);

function buscarTentativaLogin(
    mysqli $conn,
    string $identificador,
    string $tipo
): array {

    $sql = "SELECT tentativas, bloqueado_ate
            FROM tentativa_login
            WHERE identificador = ?
              AND tipo = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [
            'tentativas' => 0,
            'bloqueado_ate' => null
        ];
    }

    $stmt->bind_param(
        'ss',
        $identificador,
        $tipo
    );

    $stmt->execute();

    $resultado = $stmt->get_result();
    $dados = $resultado->fetch_assoc();

    $stmt->close();

    if (!$dados) {
        return [
            'tentativas' => 0,
            'bloqueado_ate' => null
        ];
    }

    return [
        'tentativas' => (int) $dados['tentativas'],
        'bloqueado_ate' => $dados['bloqueado_ate']
    ];
}

function registrarTentativaLogin(
    mysqli $conn,
    string $identificador,
    string $tipo,
    int $limite,
    int $tempoBloqueio
): bool {

    $tentativa = buscarTentativaLogin(
        $conn,
        $identificador,
        $tipo
    );

    $tentativas = $tentativa['tentativas'] + 1;

    $bloqueadoAte = null;

    if ($tentativas >= $limite) {

        $bloqueadoAte = date(
            'Y-m-d H:i:s',
            time() + $tempoBloqueio
        );
    }

    $sql = "INSERT INTO tentativa_login
            (
                identificador,
                tipo,
                tentativas,
                bloqueado_ate,
                ultima_tentativa
            )
            VALUES (?, ?, ?, ?, NOW())

            ON DUPLICATE KEY UPDATE

                tentativas = VALUES(tentativas),
                bloqueado_ate = VALUES(bloqueado_ate),
                ultima_tentativa = NOW()";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        'ssis',
        $identificador,
        $tipo,
        $tentativas,
        $bloqueadoAte
    );

    $sucesso = $stmt->execute();

    $stmt->close();

    return $sucesso;
}


function limparTentativaLogin(
    mysqli $conn,
    string $identificador,
    string $tipo
): void {

    $sql = "DELETE FROM tentativa_login
            WHERE identificador = ?
              AND tipo = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return;
    }

    $stmt->bind_param(
        'ss',
        $identificador,
        $tipo
    );

    $stmt->execute();

    $stmt->close();
}


function estaBloqueado(
    mysqli $conn,
    string $identificador,
    string $tipo
): bool {

    $tentativa = buscarTentativaLogin(
        $conn,
        $identificador,
        $tipo
    );

    if ($tentativa['bloqueado_ate'] === null) {
        return false;
    }

    $bloqueadoAte = strtotime(
        (string) $tentativa['bloqueado_ate']
    );

    if ($bloqueadoAte > time()) {
        return true;
    }

    limparTentativaLogin(
        $conn,
        $identificador,
        $tipo
    );

    return false;
}


function minutosRestantes(
    mysqli $conn,
    string $identificador,
    string $tipo
): int {

    $tentativa = buscarTentativaLogin(
        $conn,
        $identificador,
        $tipo
    );

    if ($tentativa['bloqueado_ate'] === null) {
        return 0;
    }

    $bloqueadoAte = strtotime(
        (string) $tentativa['bloqueado_ate']
    );

    $segundos = $bloqueadoAte - time();

    if ($segundos <= 0) {
        return 0;
    }

    return max(
        1,
        (int) ceil($segundos / 60)
    );
}
