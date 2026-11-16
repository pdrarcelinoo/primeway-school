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
    $id = primewayDisciplinaSalvarVinculo($pdo, $data);
    primewayDisciplinaAuditar($pdo, $usuario, empty($data['id']) ? 'VINCULAR_DISCIPLINA' : 'ALTERAR_VINCULO_DISCIPLINA',
        'turma_disciplinas', $id, [
            'disciplinaId' => $data['disciplinaId'] ?? null,
            'turmaId' => $data['turmaId'] ?? null, 'professorId' => $data['professorId'] ?? null,
            'hours' => $data['hours'] ?? null, 'status' => $data['status'] ?? null
        ]);
    $pdo->commit();
    primewayResponderJson(['success' => true, 'id' => $id]);
} catch (Throwable $error) {
    primewayDisciplinaResponderErro($error, $pdo ?? null);
}
