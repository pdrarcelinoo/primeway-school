<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

primewayExigirMetodo('POST');
primewayExigirCsrf();
$usuario = primewayExigirPerfis(['admin', 'secretaria']);
$dados = primewayLerJson();
$idInformado = $dados['id'] ?? null;
$id = primewayIdPositivo($idInformado);
if ($idInformado !== null && $idInformado !== '' && $id === null) {
    primewayResponderJson(['success' => false, 'message' => 'Identificador do professor inválido.'], 422);
}
$status = trim((string) ($dados['status'] ?? 'ativo'));
if (!in_array($status, ['ativo', 'inativo'], true)) {
    primewayResponderJson(['success' => false, 'message' => 'Status do professor inválido.'], 422);
}

$nome = trim((string) ($dados['name'] ?? ''));
$email = mb_strtolower(trim((string) ($dados['email'] ?? '')));
$senha = (string) ($dados['password'] ?? '');
$telefone = trim((string) ($dados['phone'] ?? ''));
$documento = trim((string) ($dados['document'] ?? ''));
$nascimento = trim((string) ($dados['birthDate'] ?? ''));
$registro = trim((string) ($dados['registration'] ?? ''));
$admissao = trim((string) ($dados['admissionDate'] ?? ''));

if ($nome === '' || mb_strlen($nome) > 120) {
    primewayResponderJson(['success' => false, 'message' => 'Informe um nome válido.'], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    primewayResponderJson(['success' => false, 'message' => 'Informe um e-mail válido.'], 422);
}

if (($id === null && (mb_strlen($senha) < 8 || mb_strlen($senha) > 200))
    || ($id !== null && $senha !== '' && (mb_strlen($senha) < 8 || mb_strlen($senha) > 200))) {
    primewayResponderJson(['success' => false, 'message' => 'A senha deve ter entre 8 e 200 caracteres (na edição, pode ficar vazia).'], 422);
}

$dataValida = static function (string $valor): ?string {
    if ($valor === '') {
        return null;
    }
    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    return $data && $data->format('Y-m-d') === $valor ? $valor : null;
};

$nascimentoFinal = $dataValida($nascimento);
$admissaoFinal = $dataValida($admissao);

if (($nascimento !== '' && $nascimentoFinal === null) || ($admissao !== '' && $admissaoFinal === null)) {
    primewayResponderJson(['success' => false, 'message' => 'Informe datas válidas.'], 422);
}

try {
    $pdo = primewayPdo();
    $pdo->beginTransaction();


    if ($id !== null) {
        // Usa a PK interna do professor, sem permitir alterar outras categorias de usuário.
        $current = $pdo->prepare(
            "SELECT p.pessoa_id, pe.nome, u.id AS usuario_id
             FROM professores p
             INNER JOIN pessoas pe ON pe.id = p.pessoa_id
             LEFT JOIN usuarios u ON u.pessoa_id = pe.id AND u.perfil = 'professor'
             WHERE p.id = :id LIMIT 1 FOR UPDATE"
        );
        $current->execute(['id' => $id]);
        $previous = $current->fetch(PDO::FETCH_ASSOC);
        if (!$previous) {
            $pdo->rollBack();
            primewayResponderJson(['success' => false, 'message' => 'Professor não encontrado.'], 404);
        }
        if ($previous['usuario_id'] === null) {
            $pdo->rollBack();
            primewayResponderJson(['success' => false, 'message' => 'Conta de professor indisponível para edição.'], 409);
        }
        $pessoaId = (int) $previous['pessoa_id'];
        $pdo->prepare(
            'UPDATE pessoas SET nome=:nome, email_contato=:email, telefone=:telefone,
             documento=:documento, data_nascimento=:nascimento, ativo=:ativo WHERE id=:id'
        )->execute([
            'nome' => $nome, 'email' => $email,
            'telefone' => $telefone !== '' ? $telefone : null,
            'documento' => $documento !== '' ? $documento : null,
            'nascimento' => $nascimentoFinal, 'ativo' => $status === 'ativo' ? 1 : 0,
            'id' => $pessoaId
        ]);
        $pdo->prepare(
            'UPDATE professores SET registro_funcional=:registro, admissao_em=:admissao,
             status=:status WHERE id=:id'
        )->execute([
            'registro' => $registro !== '' ? $registro : null,
            'admissao' => $admissaoFinal,
            'status' => $status, 'id' => $id
        ]);
        $accountSql = 'UPDATE usuarios SET nome=:nome, email=:email, ativo=:ativo';
        $accountParams = [
            'nome' => $nome, 'email' => $email, 'ativo' => $status === 'ativo' ? 1 : 0,
            'pessoa_id' => $pessoaId
        ];
        if ($senha !== '') {
            $accountSql .= ', senha_hash=:senha_hash';
            $accountParams['senha_hash'] = password_hash($senha, PASSWORD_DEFAULT);
        }
        $accountSql .= " WHERE pessoa_id=:pessoa_id AND perfil='professor'";
        $pdo->prepare($accountSql)->execute($accountParams);

        $pdo->prepare(
            "INSERT INTO auditoria (usuario_id,acao,entidade,registro_id,descricao,
             dados_anteriores,dados_novos,endereco_ip,user_agent)
             VALUES (:usuario_id,'ATUALIZAR_PROFESSOR','professores',:registro_id,
             'Cadastro do professor atualizado.',:antigo,:novo,:endereco_ip,:user_agent)"
        )->execute([
            'usuario_id' => $usuario['id'], 'registro_id' => $id,
            'antigo' => json_encode(['name' => (string)$previous['nome']], JSON_UNESCAPED_UNICODE),
            'novo' => json_encode([
                'name' => $nome, 'email' => $email, 'registration' => $registro,
                'status' => $status
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'endereco_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT'])
                ? mb_substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 500) : null
        ]);
        $pdo->commit();
        primewayResponderJson([
            'success' => true, 'message' => 'Cadastro do professor atualizado com sucesso.',
            'professorId' => $id
        ], 200);
    }

    $pessoaStmt = $pdo->prepare(
        "
            INSERT INTO pessoas (nome, email_contato, telefone, documento, data_nascimento, ativo)
            VALUES (:nome, :email, :telefone, :documento, :nascimento, 1)
        "
    );
    $pessoaStmt->execute([
        'nome' => $nome,
        'email' => $email,
        'telefone' => $telefone !== '' ? $telefone : null,
        'documento' => $documento !== '' ? $documento : null,
        'nascimento' => $nascimentoFinal
    ]);
    $pessoaId = (int) $pdo->lastInsertId();

    $professorStmt = $pdo->prepare(
        "
            INSERT INTO professores (pessoa_id, registro_funcional, status, admissao_em)
            VALUES (:pessoa_id, :registro, 'ativo', :admissao)
        "
    );
    $professorStmt->execute([
        'pessoa_id' => $pessoaId,
        'registro' => $registro !== '' ? $registro : null,
        'admissao' => $admissaoFinal
    ]);
    $professorId = (int) $pdo->lastInsertId();

    $contaStmt = $pdo->prepare(
        "
            INSERT INTO usuarios (pessoa_id, nome, email, senha_hash, perfil, ativo)
            VALUES (:pessoa_id, :nome, :email, :senha_hash, 'professor', 1)
        "
    );
    $contaStmt->execute([
        'pessoa_id' => $pessoaId,
        'nome' => $nome,
        'email' => $email,
        'senha_hash' => password_hash($senha, PASSWORD_DEFAULT)
    ]);

    $auditoriaStmt = $pdo->prepare(
        "
            INSERT INTO auditoria (
                usuario_id, acao, entidade, registro_id, descricao,
                dados_novos, endereco_ip, user_agent
            ) VALUES (
                :usuario_id, 'CRIAR_PROFESSOR', 'professores', :registro_id,
                'Professor e conta de acesso cadastrados.', :dados_novos,
                :endereco_ip, :user_agent
            )
        "
    );
    $auditoriaStmt->execute([
        'usuario_id' => $usuario['id'],
        'registro_id' => $professorId,
        'dados_novos' => json_encode([
            'nome' => $nome,
            'email' => $email,
            'registro_funcional' => $registro !== '' ? $registro : null
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'endereco_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT'])
            ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500)
            : null
    ]);

    $pdo->commit();

    primewayResponderJson([
        'success' => true,
        'message' => 'Professor cadastrado com sucesso.',
        'professorId' => $professorId
    ], 201);
} catch (PDOException $erro) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ((string) $erro->getCode() === '23000') {
        primewayResponderJson([
            'success' => false,
            'message' => 'E-mail, documento ou registro funcional já cadastrado.'
        ], 409);
    }

    error_log('PrimeWay Professor POST: ' . $erro->getMessage());
    primewayResponderJson(['success' => false, 'message' => 'Não foi possível cadastrar o professor.'], 500);
} catch (Throwable $erro) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('PrimeWay Professor POST: ' . $erro->getMessage());
    primewayResponderJson(['success' => false, 'message' => 'Não foi possível cadastrar o professor.'], 500);
}
