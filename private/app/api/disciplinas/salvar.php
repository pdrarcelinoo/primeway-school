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
    $id = primewayDisciplinaSalvar($pdo, $data);
    primewayDisciplinaAuditar($pdo, $usuario, empty($data['id']) ? 'CRIAR_DISCIPLINA' : 'ALTERAR_DISCIPLINA',
        'disciplinas', $id, [
            'nome' => $data['name'] ?? null, 'codigo' => $data['code'] ?? null,
            'area' => $data['area'] ?? null, 'status' => $data['status'] ?? null
        ]);
    $pdo->commit();
    primewayResponderJson(['success' => true, 'id' => $id]);
} catch (Throwable $error) {
    primewayDisciplinaResponderErro($error, $pdo ?? null);
}
