<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

primewayExigirMetodo('GET');
primewayExigirPerfis(['admin']);

// Lista fixa e restrita a tres contas autorizadas pelo grupo do TCC.
$allowed = [
    'mizael' => 'Mizael',
    'ana-julia' => 'Ana Júlia',
    'julia-maniero' => 'Júlia Maniero'
];
// Detectar cadastros criados antes da correção dos nomes, sem duplicá-los.
$legacy = [
    'mizael' => 'Misael',
    'ana-julia' => 'Dana Júlia',
    'julia-maniero' => 'Júlia Manieiro'
];

try {
    $pdo = primewayPdo();
    $stmt = $pdo->prepare(
        "SELECT nome, email, ativo
         FROM usuarios
         WHERE perfil = 'admin' AND nome IN (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute(array_merge(array_values($allowed), array_values($legacy)));
    $status = [];
    foreach ($stmt->fetchAll() as $row) {
        $status[(string)$row['nome']] = [
            'email' => (string)$row['email'],
            'ativo' => (int)$row['ativo'] === 1
        ];
    }

    $allStmt = $pdo->query(
        "SELECT u.id, COALESCE(NULLIF(TRIM(pe.nome), ''), NULLIF(TRIM(u.nome), ''), u.email) AS nome,
                u.email, u.ativo
         FROM usuarios u
         LEFT JOIN pessoas pe ON pe.id = u.pessoa_id
         WHERE u.perfil = 'admin'
         ORDER BY nome, u.id"
    );
    $administrators = [];
    foreach ($allStmt->fetchAll() as $row) {
        $administrators[] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['nome'],
            'email' => (string)$row['email'],
            'active' => (int)$row['ativo'] === 1
        ];
    }

    $accounts = [];
    foreach ($allowed as $id => $name) {
        $legacyName = $legacy[$id];
        $existing = $status[$name] ?? $status[$legacyName] ?? null;
        $needsRename = !isset($status[$name]) && isset($status[$legacyName]);
        $accounts[] = [
            'id' => $id,
            'name' => $name,
            'created' => $existing !== null,
            'email' => $existing['email'] ?? null,
            'active' => $existing['ativo'] ?? false,
            'needsRename' => $needsRename
        ];
    }
    primewayResponderJson(['success' => true, 'accounts' => $accounts, 'administrators' => $administrators]);
} catch (Throwable $error) {
    error_log('PrimeWay admin TCC list: ' . $error->getMessage());
    primewayResponderJson(['success' => false, 'message' => 'Não foi possível consultar os administradores.'], 500);
}
