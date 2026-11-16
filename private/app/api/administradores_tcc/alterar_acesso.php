<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

primewayExigirMetodo('POST');
$actor = primewayExigirPerfis(['admin']);
primewayExigirCsrf();

// Apenas contas existentes do perfil admin podem ser editadas; protege-se a operação com reautenticação.
$allowed = [
    'mizael' => ['current' => 'Mizael', 'legacy' => 'Misael'],
    'ana-julia' => ['current' => 'Ana Júlia', 'legacy' => 'Dana Júlia'],
    'julia-maniero' => ['current' => 'Júlia Maniero', 'legacy' => 'Júlia Manieiro']
];

$input = primewayLerJson();
$key = $input['accountId'] ?? null;
$targetId = primewayIdPositivo($input['targetId'] ?? null);
$email = $input['email'] ?? null;
$newPassword = $input['newPassword'] ?? '';
$adminPassword = $input['adminPassword'] ?? null;

if (($targetId === null && (!is_string($key) || !isset($allowed[$key]))) ||
    ($targetId !== null && $key !== null) ||
    !is_string($email) || !is_string($newPassword) ||
    !is_string($adminPassword) || $adminPassword === '') {
    primewayResponderJson(['success' => false, 'message' => 'Informe os dados e confirme com sua senha de Administrador.'], 422);
}

$email = mb_strtolower(trim($email), 'UTF-8');
if (mb_strlen($email, 'UTF-8') > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    primewayResponderJson(['success' => false, 'message' => 'Informe um e-mail válido.'], 422);
}
if ($newPassword !== '' &&
    (mb_strlen($newPassword, 'UTF-8') < 12 || strlen($newPassword) > 72)) {
    primewayResponderJson(['success' => false, 'message' => 'A nova senha precisa de pelo menos 12 caracteres e no máximo 72 bytes.'], 422);
}

try {
    $pdo = primewayPdo();
    $pdo->beginTransaction();

    // Reautenticação obrigatória com a senha do Admin que faz a alteração.
    // Não depender apenas de uma sessão já aberta no navegador.
    $current = $pdo->prepare(
        "SELECT senha_hash FROM usuarios WHERE id = ? AND perfil = 'admin' AND ativo = 1 FOR UPDATE"
    );
    $current->execute([(int)$actor['id']]);
    $currentHash = $current->fetchColumn();

    if (!is_string($currentHash) || !password_verify($adminPassword, $currentHash)) {
        $pdo->rollBack();
        primewayResponderJson(['success' => false, 'message' => 'Sua senha de Administrador está incorreta. Nenhum dado foi alterado.'], 403);
    }

    if ($targetId !== null) {
        $target = $pdo->prepare(
            "SELECT id, pessoa_id, nome, email
             FROM usuarios WHERE id = ? AND perfil = 'admin' AND ativo = 1 FOR UPDATE"
        );
        $target->execute([$targetId]);
    } else {
        $target = $pdo->prepare(
            "SELECT id, pessoa_id, nome, email
             FROM usuarios
             WHERE perfil = 'admin' AND nome IN (?, ?)
             FOR UPDATE"
        );
        $target->execute([$allowed[$key]['current'], $allowed[$key]['legacy']]);
    }
    $matches = $target->fetchAll();

    if (count($matches) !== 1) {
        $pdo->rollBack();
        primewayResponderJson([
            'success' => false,
            'message' => count($matches) === 0
                ? 'Essa conta ainda não foi cadastrada.'
                : 'Foram encontrados cadastros duplicados. A alteração foi bloqueada.'
        ], 409);
    }

    $row = $matches[0];
    $emailChanged = strcasecmp($email, (string)$row['email']) !== 0;
    $passwordChanged = $newPassword !== '';

    if (!$emailChanged && !$passwordChanged) {
        $pdo->rollBack();
        primewayResponderJson(['success' => false, 'message' => 'Altere o e-mail ou informe uma nova senha.'], 422);
    }

    $passwordHash = $passwordChanged ? password_hash($newPassword, PASSWORD_DEFAULT) : null;
    if ($passwordChanged && !is_string($passwordHash)) {
        throw new RuntimeException('Hash não gerado.');
    }

    $stmt = $pdo->prepare(
        "UPDATE usuarios
         SET email = CASE WHEN :change_email = 1 THEN :new_email ELSE email END,
             senha_hash = COALESCE(:new_hash, senha_hash)
         WHERE id = :id AND perfil = 'admin'"
    );
    $stmt->bindValue(':change_email', $emailChanged ? 1 : 0, PDO::PARAM_INT);
    $stmt->bindValue(':new_email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':new_hash', $passwordHash, $passwordHash === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':id', (int)$row['id'], PDO::PARAM_INT);
    $stmt->execute();

    if ($emailChanged && $row['pessoa_id'] !== null) {
        $updatePerson = $pdo->prepare('UPDATE pessoas SET email_contato = ? WHERE id = ?');
        $updatePerson->execute([$email, (int)$row['pessoa_id']]);
    }

    $pdo->commit();
    primewayResponderJson([
        'success' => true,
        'message' => $emailChanged && $passwordChanged
            ? 'E-mail e senha alterados com sucesso.'
            : ($emailChanged ? 'E-mail alterado com sucesso.' : 'Senha alterada com sucesso.'),
        'account' => [
            'id' => $targetId ?? $key,
            'name' => (string)$row['nome'],
            'email' => $email
        ]
    ]);
} catch (PDOException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    if ((string)$error->getCode() === '23000') {
        primewayResponderJson(['success' => false, 'message' => 'O e-mail informado já está em uso. Nenhum dado foi alterado.'], 409);
    }
    error_log('PrimeWay admin TCC alter credentials: ' . $error->getCode());
    primewayResponderJson(['success' => false, 'message' => 'Não foi possível atualizar os dados de acesso.'], 500);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('PrimeWay admin TCC alter credentials: ' . get_class($error));
    primewayResponderJson(['success' => false, 'message' => 'Não foi possível atualizar os dados de acesso.'], 500);
}
