<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

primewayExigirMetodo('POST');
primewayExigirPerfis(['admin']);
primewayExigirCsrf();

$names = [
    'mizael' => ['old' => 'Misael', 'new' => 'Mizael'],
    'ana-julia' => ['old' => 'Dana Júlia', 'new' => 'Ana Júlia'],
    'julia-maniero' => ['old' => 'Júlia Manieiro', 'new' => 'Júlia Maniero']
];
$input = primewayLerJson();
$key = $input['accountId'] ?? null;
if (!is_string($key) || !isset($names[$key])) {
    primewayResponderJson(['success' => false, 'message' => 'Conta não autorizada para correção.'], 422);
}

$old = $names[$key]['old'];
$new = $names[$key]['new'];

try {
    $pdo = primewayPdo();
    $pdo->beginTransaction();
    // Somente uma conta com o nome antigo pode ser corrigida; nunca alterar e-mail ou senha.
    $check = $pdo->prepare(
        "SELECT id, pessoa_id, nome FROM usuarios
         WHERE perfil = 'admin' AND nome IN (?, ?) FOR UPDATE"
    );
    $check->execute([$old, $new]);
    $oldAccount = null;
    $newExists = false;
    foreach ($check->fetchAll() as $row) {
        if ($row['nome'] === $old) {
            $oldAccount = $row;
        } elseif ($row['nome'] === $new) {
            $newExists = true;
        }
    }
    if ($newExists || $oldAccount === null || empty($oldAccount['pessoa_id'])) {
        $pdo->rollBack();
        primewayResponderJson([
            'success' => false,
            'message' => 'Não há uma conta antiga única disponível para essa correção.'
        ], 409);
    }
    $updatePerson = $pdo->prepare('UPDATE pessoas SET nome = ? WHERE id = ?');
    $updatePerson->execute([$new, (int)$oldAccount['pessoa_id']]);
    $updateUser = $pdo->prepare("UPDATE usuarios SET nome = ? WHERE id = ? AND perfil = 'admin'");
    $updateUser->execute([$new, (int)$oldAccount['id']]);
    if ($updatePerson->rowCount() !== 1 || $updateUser->rowCount() !== 1) {
        throw new RuntimeException('Conta ou pessoa não atualizada.');
    }
    $pdo->commit();
    primewayResponderJson([
        'success' => true,
        'message' => 'Nome corrigido sem alterar o e-mail ou a senha.'
    ]);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('PrimeWay admin TCC rename: ' . get_class($error));
    primewayResponderJson(['success' => false, 'message' => 'Não foi possível corrigir esse nome.'], 500);
}
