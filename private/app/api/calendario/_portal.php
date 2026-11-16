<?php
declare(strict_types=1);

// Retorno somente-leitura para calendários dos portais; a própria sessão decide o escopo.
require_once __DIR__ . '/../secretaria/calendario/_helpers.php';
require_once __DIR__ . '/../aluno/_contexto.php';
require_once __DIR__ . '/../responsavel/_contexto.php';

function primewayCalendarioPortal(PDO $pdo, array $usuario, string $mes): array
{
    if (!preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/D', $mes)) {
        primewayResponderJson(['success'=>false,'message'=>'Mês inválido.'],400);
    }
    $inicio=$mes.'-01';
    $fim=(new DateTimeImmutable($inicio))->modify('last day of this month')->format('Y-m-d');
    $turmas=[];
    if ($usuario['perfil']==='aluno') {
        $contexto=primewayAlunoContexto($pdo,$usuario);
        if (!empty($contexto['enrollment']['classId'])) {
            $turmas[]=(int)$contexto['enrollment']['classId'];
        }
    } elseif ($usuario['perfil']==='responsavel') {
        $contexto=primewayResponsavelContexto($pdo,$usuario);
        foreach ($contexto['students'] as $student) {
            if (!empty($student['enrollment']['classId'])) {
                $turmas[]=(int)$student['enrollment']['classId'];
            }
        }
    } else {
        primewayResponderJson(['success'=>false,'message'=>'Acesso não autorizado.'],403);
    }
    $turmas=array_values(array_unique($turmas));
    $filtro=$turmas ? '(ec.turma_id IS NULL OR ec.turma_id IN ('
        .implode(',',array_fill(0,count($turmas),'?')).'))' : 'ec.turma_id IS NULL';
    $sql="SELECT ec.*, t.nome AS turma_nome, t.status AS turma_status
        FROM eventos_calendario ec LEFT JOIN turmas t ON t.id=ec.turma_id
        WHERE ec.status IN ('Agendado','Concluído')
          AND ec.data_evento BETWEEN ? AND ? AND ".$filtro."
        ORDER BY ec.data_evento ASC,ec.horario_inicio ASC,ec.id ASC";
    $stmt=$pdo->prepare($sql);
    $stmt->execute(array_merge([$inicio,$fim],$turmas));
    return [
        'success'=>true,
        'month'=>$mes,
        'events'=>array_map('primewayCalendarioMapearEvento',$stmt->fetchAll(PDO::FETCH_ASSOC)),
        'classCount'=>count($turmas)
    ];
}
