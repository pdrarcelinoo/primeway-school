<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

primewayExigirMetodo('GET');
$usuario = primewayExigirPerfis(['admin', 'secretaria', 'professor', 'aluno', 'responsavel']);
$conversaId = primewayIdPositivo($_GET['conversationId'] ?? null);

if ($conversaId === null) {
    primewayResponderJson(['success' => false, 'message' => 'Conversa inválida.'], 422);
}

try {
    $pdo = primewayPdo();

    // Checagem explícita de participação, sem expor sequer a versão de conversas alheias.
    $acesso = $pdo->prepare(
        "SELECT c.atualizado_em
         FROM conversas c
         INNER JOIN conversa_participantes cp ON cp.conversa_id = c.id
         WHERE c.id = :conversa_id
           AND c.ativo = 1
           AND cp.usuario_id = :usuario_id
           AND cp.ativo = 1
           AND cp.saiu_em IS NULL
         LIMIT 1"
    );
    $acesso->execute([
        'conversa_id' => $conversaId,
        'usuario_id' => (int)$usuario['id']
    ]);
    $conversa = $acesso->fetch(PDO::FETCH_ASSOC);

    if (!$conversa) {
        primewayResponderJson(['success' => false, 'message' => 'Conversa não encontrada.'], 404);
    }

    // Uma consulta compacta: nunca transfere mensagens ou anexos quando nada mudou.
    // Inclui exclusão e fixação (não apenas o maior ID), para sincronizar todas as ações.
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS quantidade,
                COALESCE(MAX(id), 0) AS ultimo_id,
                COALESCE(SUM(CASE WHEN excluida_em IS NULL THEN id ELSE 0 END), 0) AS soma_visiveis,
                COALESCE(SUM(CASE WHEN fixada_em IS NOT NULL AND excluida_em IS NULL THEN id ELSE 0 END), 0) AS soma_fixadas,
                COALESCE(MAX(editada_em), '') AS ultima_edicao,
                COALESCE(MAX(excluida_em), '') AS ultima_exclusao
         FROM mensagens
         WHERE conversa_id = :conversa_id"
    );
    $stmt->execute(['conversa_id' => $conversaId]);
    $snapshot = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $version = hash('sha256', json_encode([
        $conversa['atualizado_em'],
        $snapshot['quantidade'] ?? '0',
        $snapshot['ultimo_id'] ?? '0',
        $snapshot['soma_visiveis'] ?? '0',
        $snapshot['soma_fixadas'] ?? '0',
        $snapshot['ultima_edicao'] ?? '',
        $snapshot['ultima_exclusao'] ?? ''
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

    header('Cache-Control: no-store, private, max-age=0');
    primewayResponderJson(['success' => true, 'version' => $version]);
} catch (Throwable $erro) {
    error_log('PrimeWay Chat Live Sync GET: ' . $erro->getMessage());
    primewayResponderJson([
        'success' => false,
        'message' => 'Não foi possível sincronizar esta conversa.'
    ], 500);
}
