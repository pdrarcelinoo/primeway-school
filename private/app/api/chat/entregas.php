<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/_message_status.php';

primewayExigirMetodo('POST');

$usuario = primewayExigirPerfis([
    'aluno',
    'professor',
    'responsavel',
    'secretaria',
    'admin'
]);

primewayExigirCsrf();

try {
    $pdo = primewayPdo();
    $usuarioId = (int) $usuario['id'];

    // Recibo idempotente de sincronização. Não altera conteúdo de mensagem.
    primewayChatMarcarEntregues($pdo, $usuarioId);

    primewayResponderJson([
        'success' => true
    ]);

} catch (Throwable $erro) {
    error_log(
        'PrimeWay Chat Entregas POST: ' . $erro->getMessage()
    );

    primewayResponderJson([
        'success' => false,
        'message' => 'Não foi possível sincronizar a entrega das mensagens.'
    ], 500);
}
