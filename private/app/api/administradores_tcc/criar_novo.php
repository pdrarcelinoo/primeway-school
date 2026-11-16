<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
primewayExigirMetodo('POST');
$actor = primewayExigirPerfis(['admin']);
primewayExigirCsrf();

// Somente administradores autenticados podem cadastrar outro administrador.
$data = primewayLerJson();
$name = $data['name'] ?? null;
$email = $data['email'] ?? null;
$password = $data['password'] ?? null;
$adminPassword = $data['adminPassword'] ?? null;
if (!is_string($name) || !is_string($email) ||
    !is_string($password) || !is_string($adminPassword) || $adminPassword === '') {
    primewayResponderJson(['success'=>false,'message'=>'Preencha nome, e-mail, senha e sua senha de Administrador.'],422);
}
$name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
$email = mb_strtolower(trim($email), 'UTF-8');
if (mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 120 ||
    preg_match('/[\x00-\x1F\x7F]/u', $name)) {
    primewayResponderJson(['success'=>false,'message'=>'O nome deve ter de 2 a 120 caracteres e não conter caracteres de controle.'],422);
}
if (mb_strlen($email, 'UTF-8') > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    primewayResponderJson(['success'=>false,'message'=>'Informe um e-mail válido.'],422);
}
if (mb_strlen($password,'UTF-8') < 12 || strlen($password) > 72) {
    primewayResponderJson(['success'=>false,'message'=>'A senha deve ter no mínimo 12 caracteres e no máximo 72 bytes.'],422);
}
if (hash_equals($adminPassword, $password)) {
    primewayResponderJson(['success'=>false,'message'=>'A nova conta precisa usar uma senha diferente da sua.'],422);
}

try {
    $pdo = primewayPdo();
    $pdo->beginTransaction();

    // Exigir senha atual de quem está autorizando a criação,
    // mesmo quando a sessão já estiver autenticada.
    $current = $pdo->prepare(
        "SELECT senha_hash FROM usuarios WHERE id = ? AND perfil = 'admin' AND ativo = 1 FOR UPDATE"
    );
    $current->execute([(int)$actor['id']]);
    $currentHash = $current->fetchColumn();
    if (!is_string($currentHash) || !password_verify($adminPassword, $currentHash)) {
        $pdo->rollBack();
        primewayResponderJson(['success'=>false,'message'=>'Sua senha de Administrador está incorreta. Nenhuma conta foi criada.'],403);
    }

    // Evitar duplicação de nomes de administradores do TCC e de endereços de login.
    $existing = $pdo->prepare(
        "SELECT id FROM usuarios
         WHERE email = ? OR (perfil = 'admin' AND nome = ?)
         LIMIT 1 FOR UPDATE"
    );
    $existing->execute([$email, $name]);
    if ($existing->fetch()) {
        $pdo->rollBack();
        primewayResponderJson(['success'=>false,'message'=>'Já existe um usuário com esse e-mail ou um administrador com esse nome.'],409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($hash)) throw new RuntimeException('Não foi possível proteger a senha.');

    $person = $pdo->prepare('INSERT INTO pessoas (nome,email_contato,ativo) VALUES (?,?,1)');
    $person->execute([$name, $email]);
    $personId = (int)$pdo->lastInsertId();

    $new = $pdo->prepare(
        "INSERT INTO usuarios (pessoa_id,nome,email,senha_hash,perfil,ativo)
         VALUES (?,?,?,?,'admin',1)"
    );
    $new->execute([$personId, $name, $email, $hash]);
    $newId = (int)$pdo->lastInsertId();
    $pdo->commit();

    primewayResponderJson([
        'success'=>true,
        'message'=>'Novo administrador cadastrado com sucesso.',
        'account'=>['id'=>$newId,'name'=>$name,'email'=>$email]
    ],201);
} catch (PDOException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    if ((string)$error->getCode() === '23000') {
        primewayResponderJson(['success'=>false,'message'=>'E-mail já utilizado. Nenhuma conta foi criada.'],409);
    }
    error_log('PrimeWay admin create: '.$error->getCode());
    primewayResponderJson(['success'=>false,'message'=>'Não foi possível criar o administrador.'],500);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('PrimeWay admin create: '.get_class($error));
    primewayResponderJson(['success'=>false,'message'=>'Não foi possível criar o administrador.'],500);
}
