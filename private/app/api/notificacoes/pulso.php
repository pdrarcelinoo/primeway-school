<?php
declare(strict_types=1);
require_once __DIR__.'/_notificacoes.php';

// Resumo privado, sem mensagens nem dados pessoais de outros usuários.
primewayExigirMetodo('GET');
$user=primewayExigirPerfis(['admin','professor','aluno','responsavel','secretaria']);

try {
    $pdo=primewayPdo();
    $userId=(int)$user['id'];
    $unreadNotifications=primewayNotificationUnread($pdo,$user);

    $stmt=$pdo->prepare(
        "SELECT COUNT(DISTINCT m.id)
         FROM conversa_participantes cp
         INNER JOIN conversas c ON c.id=cp.conversa_id AND c.ativo=1
         INNER JOIN mensagens m ON m.conversa_id=c.id
           AND m.excluida_em IS NULL AND m.remetente_usuario_id<>:sender
         LEFT JOIN mensagem_leituras ml ON ml.mensagem_id=m.id
           AND ml.usuario_id=:reader
         WHERE cp.usuario_id=:viewer AND cp.ativo=1 AND cp.saiu_em IS NULL
           AND cp.arquivada_em IS NULL AND ml.id IS NULL"
    );
    $stmt->execute(['sender'=>$userId,'reader'=>$userId,'viewer'=>$userId]);

    primewayResponderJson([
        'success'=>true,
        'viewerId'=>$userId,
        'role'=>(string)$user['perfil'],
        'unreadNotifications'=>$unreadNotifications,
        'unreadMessages'=>(int)$stmt->fetchColumn()
    ]);
} catch (Throwable $error) {
    error_log('PrimeWay Resumo de avisos: '.$error->getMessage());
    primewayResponderJson(['success'=>false,'message'=>'Não foi possível atualizar os avisos.'],500);
}
