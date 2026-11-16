<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

primewayExigirMetodo('POST');
primewayExigirPerfis(['admin']);
primewayExigirCsrf();

$allowed = [
    'mizael' => 'Mizael',
    'ana-julia' => 'Ana Júlia',
    'julia-maniero' => 'Júlia Maniero'
];
$legacy = [
    'mizael' => 'Misael',
    'ana-julia' => 'Dana Júlia',
    'julia-maniero' => 'Júlia Manieiro'
];
$input = primewayLerJson();
$key = $input['accountId'] ?? null;
$email = $input['email'] ?? null;
$password = $input['password'] ?? null;

if (!is_string($key) || !isset($allowed[$key]) ||
    !is_string($email) || !is_string($password)) {
    primewayResponderJson(['success' => false, 'message' => 'Informe a pessoa, o e-mail e a senha.'], 422);
}

$name = $allowed[$key];
$email = mb_strtolower(trim($email), 'UTF-8');
if (mb_strlen($email, 'UTF-8') > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    primewayResponderJson(['success' => false, 'message' => 'Informe um e-mail válido.'], 422);
}
if (mb_strlen($password, 'UTF-8') < 12 || strlen($password) > 72) {
    primewayResponderJson(['success' => false, 'message' => 'A senha precisa ter pelo menos 12 caracteres e no máximo 72 bytes.'], 422);
}
// Uma conta para cada pessoa; nunca redefinir senha de uma conta existente.
try {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($hash)) throw new RuntimeException('Não foi possível proteger a senha.');
    $pdo = primewayPdo();
    $pdo->beginTransaction();

    $find = $pdo->prepare(
        "SELECT id FROM usuarios
         WHERE (perfil = 'admin' AND nome IN (:nome, :legacy)) OR email = :email
         LIMIT 1 FOR UPDATE"
    );
    $find->execute([':nome' => $name, ':legacy' => $legacy[$key], ':email' => $email]);
    if ($find->fetch()) {
        $pdo->rollBack();
        primewayResponderJson([
            'success' => false,
            'message' => 'Essa pessoa ou esse e-mail já possui uma conta. Nenhuma senha foi alterada.'
        ], 409);
    }

    $person = $pdo->prepare(
        'INSERT INTO pessoas (nome, email_contato, ativo) VALUES (:nome, :email, 1)'
    );
    $person->execute([':nome' => $name, ':email' => $email]);
    $personId = (int)$pdo->lastInsertId();

    $user = $pdo->prepare(
        "INSERT INTO usuarios (pessoa_id, nome, email, senha_hash, perfil, ativo)
         VALUES (:pessoa_id, :nome, :email, :senha_hash, 'admin', 1)"
    );
    $user->execute([
        ':pessoa_id' => $personId,
        ':nome' => $name,
        ':email' => $email,
        ':senha_hash' => $hash
    ]);
    $pdo->commit();
    primewayResponderJson([
        'success' => true,
        'message' => 'Conta administrativa criada com sucesso.',
        'account' => ['id' => $key, 'name' => $name, 'email' => $email]
    ], 201);
} catch (PDOException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    if ((string)$error->getCode() === '23000') {
        primewayResponderJson(['success' => false, 'message' => 'E-mail já utilizado. Nenhuma conta nova foi criada.'], 409);
    }
    error_log('PrimeWay admin TCC create: ' . $error->getCode());
    primewayResponderJson(['success' => false, 'message' => 'Não foi possível criar a conta.'], 500);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('PrimeWay admin TCC create: ' . get_class($error));
    primewayResponderJson(['success' => false, 'message' => 'Não foi possível criar a conta.'], 500);
}
