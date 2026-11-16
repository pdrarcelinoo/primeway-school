<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/_perfil.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? ''));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    primewayResponderJson(['success'=>false,'message'=>'Método não permitido.'],405);
}

$usuario = primewayExigirAutenticacao();
if ($method === 'POST') primewayExigirCsrf();

try {
    $pdo = primewayPdo();
    $id = (int)$usuario['id'];
    if ($method === 'POST') {
        $data = primewayLerJson();
        $name = trim((string)($data['name'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));
        $contact = trim((string)($data['contactEmail'] ?? ''));

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
            primewayResponderJson(['success'=>false,'message'=>'Informe um nome válido de 2 a 120 caracteres.'],422);
        }
        if (strlen($phone) > 30 || !preg_match('/^[+0-9(). \-]*$/D', $phone)) {
            primewayResponderJson(['success'=>false,'message'=>'Informe um telefone válido de até 30 caracteres.'],422);
        }
        if (strlen($contact) > 190 || ($contact !== '' && !filter_var($contact, FILTER_VALIDATE_EMAIL))) {
            primewayResponderJson(['success'=>false,'message'=>'E-mail de contato inválido.'],422);
        }

        $pdo->beginTransaction();
        $lock = $pdo->prepare('SELECT id, pessoa_id FROM usuarios WHERE id=? AND ativo=1 FOR UPDATE');
        $lock->execute([$id]);
        $current = $lock->fetch();
        if (!$current) throw new RuntimeException('Usuário indisponível.',403);

        $personId = (int)($current['pessoa_id'] ?? 0);
        if ($personId > 0) {
            $update = $pdo->prepare('UPDATE pessoas SET nome=?,telefone=?,email_contato=? WHERE id=? AND ativo=1');
            $update->execute([$name,$phone?:null,$contact?:null,$personId]);
            // ROW_COUNT também é zero quando não houve alteração; validar existência separadamente.
            $active = $pdo->prepare('SELECT id FROM pessoas WHERE id=? AND ativo=1');
            $active->execute([$personId]);
            if (!$active->fetchColumn()) throw new RuntimeException('Cadastro pessoal indisponível.',403);
            $pdo->prepare('UPDATE usuarios SET nome=? WHERE id=?')->execute([$name,$id]);
        } else {
            // Contas administrativas antigas podem existir sem pessoa vinculada.
            // Criar esse vínculo uma única vez, sob lock da conta.
            $pdo->prepare('INSERT INTO pessoas(nome,telefone,email_contato,ativo) VALUES (?,?,?,1)')
                ->execute([$name,$phone?:null,$contact?:null]);
            $personId = (int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE usuarios SET nome=?,pessoa_id=? WHERE id=?')
                ->execute([$name,$personId,$id]);
        }
        $pdo->commit();
        $_SESSION['usuario_nome']=$name;
    }

    $stmt = $pdo->prepare(
        'SELECT u.email,u.perfil,COALESCE(NULLIF(TRIM(pe.nome),""),NULLIF(TRIM(u.nome),""),u.email) AS nome,
                pe.telefone,pe.email_contato
         FROM usuarios u LEFT JOIN pessoas pe ON pe.id=u.pessoa_id
         WHERE u.id=? AND u.ativo=1 LIMIT 1'
    );
    $stmt->execute([$id]);
    $profile=$stmt->fetch();
    if (!$profile) primewayResponderJson(['success'=>false,'message'=>'Perfil indisponível.'],404);
    primewayResponderJson([
        'success'=>true,
        'profile'=>[
            'name'=>(string)$profile['nome'],
            'role'=>(string)$profile['perfil'],
            'loginEmail'=>(string)$profile['email'],
            'phone'=>(string)($profile['telefone']??''),
            'contactEmail'=>(string)($profile['email_contato']??''),
            'hasPhoto'=>primewayPerfilFotoExistente($id)
        ],
        'csrfToken'=>primewayTokenCsrf()
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $status=(int)$e->getCode();
    if (!in_array($status,[403,404,422],true)) {
        error_log('PrimeWay perfil: '.$e->getMessage());
        $status=500;
    }
    primewayResponderJson(['success'=>false,'message'=>$status===500?'Não foi possível atualizar o perfil.':$e->getMessage()],$status);
}
