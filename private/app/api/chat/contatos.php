<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

primewayExigirMetodo('GET');
$admin = primewayExigirPerfis(['admin']);

$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$pageInput = $_GET['page'] ?? '1';
$roles = ['admin','professor','aluno','responsavel','secretaria'];

if (!is_string($search) || mb_strlen(trim($search)) > 120 ||
    !is_string($role) || ($role !== '' && !in_array($role,$roles,true)) ||
    (!is_string($pageInput) && !is_int($pageInput)) ||
    !preg_match('/^[1-9][0-9]{0,4}$/D',(string)$pageInput)) {
    primewayResponderJson(['success'=>false,'message'=>'Filtros de contatos inválidos.'],422);
}
$search = trim($search);
$page = (int)$pageInput;
$limit = 30;
$offset = ($page - 1) * $limit;

try {
    $pdo = primewayPdo();
    $where = "u.ativo=1 AND u.id<>:viewer
        AND (u.pessoa_id IS NULL OR pe.ativo=1)
        AND u.perfil IN ('admin','professor','aluno','responsavel','secretaria')";
    if ($role !== '') $where .= ' AND u.perfil=:role';
    if ($search !== '') {
        $where .= " AND (
            COALESCE(NULLIF(pe.nome,''),NULLIF(u.nome,''),u.email) LIKE :nameSearch ESCAPE '!'
            OR u.email LIKE :emailSearch ESCAPE '!'
        )";
    }
    $sql = "SELECT u.id,u.perfil,u.email,
        COALESCE(NULLIF(pe.nome,''),NULLIF(u.nome,''),u.email) AS nome
        FROM usuarios u LEFT JOIN pessoas pe ON pe.id=u.pessoa_id
        WHERE $where
        ORDER BY FIELD(u.perfil,'admin','secretaria','professor','aluno','responsavel'), nome,u.id
        LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':viewer',(int)$admin['id'],PDO::PARAM_INT);
    if ($role !== '') $stmt->bindValue(':role',$role);
    if ($search !== '') {
        $pattern = '%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';
        $stmt->bindValue(':nameSearch',$pattern);
        $stmt->bindValue(':emailSearch',$pattern);
    }
    $stmt->bindValue(':limit',$limit + 1,PDO::PARAM_INT);
    $stmt->bindValue(':offset',$offset,PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();
    $hasMore = count($rows) > $limit;
    $rows = array_slice($rows,0,$limit);
    primewayResponderJson([
        'success'=>true,
        'contacts'=>array_map(static fn(array $r):array=>[
            'userId'=>(int)$r['id'],
            'name'=>(string)$r['nome'],
            'email'=>(string)$r['email'],
            'role'=>(string)$r['perfil']
        ],$rows),
        'page'=>$page,
        'hasMore'=>$hasMore
    ]);
} catch (Throwable $error) {
    error_log('PrimeWay Admin contatos: '.$error->getMessage());
    primewayResponderJson(['success'=>false,'message'=>'Não foi possível carregar os contatos.'],500);
}
