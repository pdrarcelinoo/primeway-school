<?php
declare(strict_types=1);

// Avisos automáticos internos: somente destinatários relacionados à turma.
require_once __DIR__ . '/_notificacoes.php';

function primewayAvisosDestinatarios(PDO $pdo, ?int $turmaId, bool $incluirProfessores = true): array
{
    if ($turmaId === null) {
        $roles = $incluirProfessores ? ['professor','aluno','responsavel'] : ['aluno','responsavel'];
        $sql = "SELECT u.id FROM usuarios u INNER JOIN pessoas pe ON pe.id=u.pessoa_id
            WHERE u.ativo=1 AND pe.ativo=1 AND u.perfil IN (" .
            implode(',',array_fill(0,count($roles),'?')) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($roles);
        return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    $consultas = [
        "SELECT DISTINCT u.id FROM usuarios u
         JOIN pessoas pe ON pe.id=u.pessoa_id AND pe.ativo=1
         JOIN alunos a ON a.pessoa_id=pe.id AND a.status='ativo'
         JOIN matriculas m ON m.aluno_id=a.id AND m.situacao='Ativa'
         WHERE u.ativo=1 AND u.perfil='aluno' AND m.turma_id=?",
        "SELECT DISTINCT u.id FROM usuarios u
         JOIN pessoas pe ON pe.id=u.pessoa_id AND pe.ativo=1
         JOIN responsaveis r ON r.pessoa_id=pe.id AND r.status='ativo'
         JOIN aluno_responsavel ar ON ar.responsavel_id=r.id AND ar.ativo=1
         JOIN alunos a ON a.id=ar.aluno_id AND a.status='ativo'
         JOIN matriculas m ON m.aluno_id=a.id AND m.situacao='Ativa'
         WHERE u.ativo=1 AND u.perfil='responsavel' AND m.turma_id=?"
    ];
    if ($incluirProfessores) {
        $consultas[] = "SELECT DISTINCT u.id FROM usuarios u
            JOIN pessoas pe ON pe.id=u.pessoa_id AND pe.ativo=1
            JOIN professores p ON p.pessoa_id=pe.id AND p.status='ativo'
            WHERE u.ativo=1 AND u.perfil='professor'
            AND (EXISTS (SELECT 1 FROM turmas t WHERE t.id=? AND t.professor_id=p.id)
              OR EXISTS (SELECT 1 FROM turma_disciplinas td
                  WHERE td.turma_id=? AND td.professor_id=p.id))";
    }
    $ids=[];
    foreach ($consultas as $i=>$sql) {
        $stmt=$pdo->prepare($sql);
        $stmt->execute($i===2?[$turmaId,$turmaId]:[$turmaId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) $ids[(int)$id]=(int)$id;
    }
    return array_values($ids);
}

function primewayAvisoAutomatico(
    PDO $pdo, array $autor, ?int $turmaId, string $titulo, string $mensagem,
    ?int $eventoId = null, bool $professores = true
): void {
    primewayNotificationTransaction($pdo);
    $ids = array_values(array_diff(
        primewayAvisosDestinatarios($pdo,$turmaId,$professores),
        [(int)$autor['id']]
    ));
    if (!$ids) return;
    $titulo=mb_substr($titulo,0,190);
    $mensagem=mb_substr($mensagem,0,500);
    $stmt=$pdo->prepare(
        "INSERT INTO notificacoes
        (criado_por_usuario_id,evento_calendario_id,titulo,tipo,publico,mensagem,origem,status,publicada_em)
        VALUES (?,?,?,?,?,?,'Sistema','Publicada',CURRENT_TIMESTAMP)"
    );
    $stmt->execute([
        (int)$autor['id'],$eventoId,$titulo,'Aviso','vinculados',$mensagem
    ]);
    primewayNotificationDeliver($pdo,(int)$pdo->lastInsertId(),$ids);
}

/** A correção pertence exclusivamente ao aluno da matrícula e aos seus responsáveis. */
function primewayAvisoDaMatricula(PDO $pdo, array $autor, int $matriculaId, string $titulo): void
{
    primewayNotificationTransaction($pdo);
    $stmt=$pdo->prepare(
        "SELECT DISTINCT u.id FROM usuarios u JOIN pessoas pe ON pe.id=u.pessoa_id AND pe.ativo=1
         JOIN alunos a ON a.pessoa_id=pe.id AND a.status='ativo'
         JOIN matriculas m ON m.aluno_id=a.id WHERE m.id=? AND u.ativo=1 AND u.perfil='aluno'
         UNION
         SELECT DISTINCT u.id FROM usuarios u JOIN pessoas pe ON pe.id=u.pessoa_id AND pe.ativo=1
         JOIN responsaveis r ON r.pessoa_id=pe.id AND r.status='ativo'
         JOIN aluno_responsavel ar ON ar.responsavel_id=r.id AND ar.ativo=1
         JOIN matriculas m ON m.aluno_id=ar.aluno_id
         WHERE m.id=? AND u.ativo=1 AND u.perfil='responsavel'"
    );
    $stmt->execute([$matriculaId,$matriculaId]);
    $recipients=array_values(array_diff(array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN)),[(int)$autor['id']]));
    if (!$recipients) return;
    $sql="INSERT INTO notificacoes
       (criado_por_usuario_id,titulo,tipo,publico,mensagem,origem,status,publicada_em)
       VALUES (?,?,'Aviso','vinculados',?,'Sistema','Publicada',CURRENT_TIMESTAMP)";
    $pdo->prepare($sql)->execute([
       (int)$autor['id'],
       mb_substr('Atividade corrigida: '.$titulo,0,190),
       mb_substr('O professor publicou uma correção para '.$titulo.'. Consulte suas notas e atividades.',0,500)
    ]);
    primewayNotificationDeliver($pdo,(int)$pdo->lastInsertId(),$recipients);
}
