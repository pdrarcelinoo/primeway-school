<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/_disciplinas.php';

primewayExigirMetodo('POST');
$usuario=primewayExigirPerfis(['admin', 'secretaria']);
primewayExigirCsrf();
$data = primewayLerJson();
try {
    $pdo = primewayPdo();
    $pdo->beginTransaction();
    $id = primewayDisciplinaAlterarStatus($pdo, $data);
    primewayDisciplinaAuditar($pdo, $usuario, 'ALTERAR_STATUS_DISCIPLINA',
        ($data['target'] ?? '') === 'vinculo' ? 'turma_disciplinas' : 'disciplinas',
        $id, ['status' => $data['status'] ?? null, 'target' => $data['target'] ?? null]);
    $pdo->commit();
    primewayResponderJson(['success' => true, 'id' => $id]);
} catch (Throwable $error) {
    primewayDisciplinaResponderErro($error, $pdo ?? null);
}
