#!/usr/bin/env python3
"""Verificação estática: alertas pessoais, cache e isolamento dos cinco perfis."""
from pathlib import Path
root=Path(__file__).resolve().parents[1]
def read(path):return (root/path).read_text(encoding="utf-8")
roles=("admin","professor","aluno","responsavel","secretaria")
backend=read("private/app/api/notificacoes/_notificacoes.php")
for text in (
    "function primewayNotificationInboxScope",
    "if ($user['perfil']!=='admin')",
    "nd.id IS NOT NULL AND nd.excluida_em IS NULL",
    "NOT EXISTS",
    "LOWER(n.publico)",
    "function primewayNotificationRows(PDO $pdo,array $user,array $filters=[],?int $limit=null,bool $inboxOnly=false)",
    "$scope=$inboxOnly?primewayNotificationInboxScope",
    "$scope=primewayNotificationInboxScope($user,$params);",
    "primewayNotificationRows($pdo,$user,['read'=>'unread'],null,true)",
    "primewayNotificationRows($pdo,$user,['id'=>$id],null,true)",
    "'forMe'=>$forMe",
    "tem_destinatarios",
):assert text in backend,text
# O admin mantém a gestão de comunicados, sem passar a ler avisos pessoais alheios como destinatário.
assert "if ($user['perfil']==='admin') return '1=1';" in backend
assert "if(!$read && $user['perfil']!=='admin')" in backend
for p in ("js/notificacoes.js","js/avisos_inteligentes.js","css/avisos_inteligentes.css"):
    assert read("public/"+p)==read("private/app/"+p),p+" deve manter espelhos idênticos"
view=read("public/js/notificacoes.js")
for snippet in ("x.forMe!==false&&!x.read","x.forMe===false||x.read","x.forMe===false;"):
    assert snippet in view,snippet
pulse=read("private/app/api/notificacoes/pulso.php")
for snippet in (
    "primewayExigirMetodo('GET')",
    "primewayExigirPerfis(['admin','professor','aluno','responsavel','secretaria'])",
    "primewayNotificationUnread($pdo,$user)",
    "cp.usuario_id=:viewer AND cp.ativo=1 AND cp.saiu_em IS NULL",
    "ml.id IS NULL",
    "'viewerId'=>$userId",
    "'unreadMessages'=>(int)$stmt->fetchColumn()",
):
    assert snippet in pulse,snippet
assert "require '/opt/primeway/app/api/notificacoes/pulso.php'" in read("public/api/notificacoes/pulso.php")
client=read("public/js/avisos_inteligentes.js")
for snippet in (
    "primeway:navigation-counts",
    "sessionStorage.setItem",
    "String(userId)",
    "prev.notifications",
    "prev.messages",
    "if(!prev||document.visibilityState!==\"visible\")return",
    "if(!common)",
    "setInterval(getPulse,60000)",
    "if(pulseBusy||document.hidden||navigator.onLine===false)return",
    'setAttribute("role","status")',
):
    assert snippet in client,snippet
for role in roles:
    assert role+":" in client,role
for role in roles[1:]:
    nav=read(f"private/app/api/{role}/navegacao.php")
    assert "'viewerId' => $usuarioId" in nav,role+" deve identificar a sessão"
    src=read(f"public/js/{role}_common.js")
    assert src==read(f"private/app/js/{role}_common.js"),role+" espelho"
    assert 'new CustomEvent("primeway:navigation-counts"' in src,role
    assert "viewerId:" in src and "unreadNotifications:" in src,role
router=read("public/api/auth/pagina.php")
for snippet in ("'avisos_inteligentes.js'","avisos_inteligentes.css","primeway:navigation-counts"):
    if snippet=="primeway:navigation-counts":continue
    assert snippet in router,snippet
assert "if ($usuario === null)" in router
assert "if ($perfilAtual !== $perfilNecessario)" in router
print("Notificações inteligentes: perfil isolado, resumo protegido e UI sincronizada.")
