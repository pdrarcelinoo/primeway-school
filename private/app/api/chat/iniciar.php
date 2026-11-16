<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../_chat_access.php';

primewayExigirMetodo('POST');
$admin = primewayExigirPerfis(['admin']);
primewayExigirCsrf();
$data = primewayLerJson();
$contactUserId = primewayIdPositivo($data['contactUserId'] ?? null);
$adminId = (int)$admin['id'];

if ($contactUserId === null || $contactUserId === $adminId) {
    primewayResponderJson(['success'=>false,'message'=>'Selecione outro usuário válido.'],422);
}

try {
    $pdo = primewayPdo();
    primewayChatExigirDisponivel($pdo,$adminId);
    $pdo->beginTransaction();

    // Bloqueio ordenado evita duplicações de conversas quando dois admins
    // iniciam simultaneamente um chat com a mesma pessoa.
    $users = $pdo->prepare(
        "SELECT u.id FROM usuarios u
         LEFT JOIN pessoas pe ON pe.id=u.pessoa_id
         WHERE u.id IN (?,?) AND u.ativo=1
           AND (u.pessoa_id IS NULL OR pe.ativo=1)
           AND u.perfil IN ('admin','professor','aluno','responsavel','secretaria')
         ORDER BY u.id FOR UPDATE"
    );
    $users->execute([$adminId,$contactUserId]);
    $allowed = array_map('intval',$users->fetchAll(PDO::FETCH_COLUMN));
    if (count($allowed) !== 2 || !in_array($adminId,$allowed,true)
        || !in_array($contactUserId,$allowed,true)) {
        $pdo->rollBack();
        primewayResponderJson(['success'=>false,'message'=>'Contato indisponível ou inativo.'],404);
    }

    $existing = $pdo->prepare(
        "SELECT c.id FROM conversas c
         INNER JOIN conversa_participantes me ON me.conversa_id=c.id
            AND me.usuario_id=:admin AND me.ativo=1 AND me.saiu_em IS NULL
         INNER JOIN conversa_participantes other ON other.conversa_id=c.id
            AND other.usuario_id=:target AND other.ativo=1 AND other.saiu_em IS NULL
         WHERE c.tipo='individual' AND c.ativo=1
           AND (SELECT COUNT(*) FROM conversa_participantes cp
                WHERE cp.conversa_id=c.id AND cp.ativo=1 AND cp.saiu_em IS NULL)=2
         LIMIT 1 FOR UPDATE"
    );
    $existing->execute([':admin'=>$adminId,':target'=>$contactUserId]);
    $conversationId = (int)($existing->fetchColumn() ?: 0);

    if ($conversationId > 0) {
        // Se este administrador havia arquivado a conversa, reabre-a para ele.
        $pdo->prepare(
            'UPDATE conversa_participantes SET arquivada_em=NULL
             WHERE conversa_id=? AND usuario_id=?'
        )->execute([$conversationId,$adminId]);
        $pdo->commit();
        primewayResponderJson(['success'=>true,'conversationId'=>$conversationId,'created'=>false]);
    }

    $pdo->prepare(
        "INSERT INTO conversas(tipo,criada_por_usuario_id,ativo)
         VALUES ('individual',?,1)"
    )->execute([$adminId]);
    $conversationId = (int)$pdo->lastInsertId();

    $participant = $pdo->prepare(
        "INSERT INTO conversa_participantes(conversa_id,usuario_id,ativo,entrou_em)
         VALUES (?,?,1,CURRENT_TIMESTAMP)"
    );
    $participant->execute([$conversationId,$adminId]);
    $participant->execute([$conversationId,$contactUserId]);
    $pdo->commit();

    primewayResponderJson([
        'success'=>true,'conversationId'=>$conversationId,'created'=>true
    ],201);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('PrimeWay Admin iniciar conversa: '.$error->getMessage());
    primewayResponderJson(['success'=>false,'message'=>'Não foi possível iniciar a conversa.'],500);
}
