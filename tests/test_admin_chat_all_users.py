#!/usr/bin/env python3
"""Regressão da permissão de chat Admin -> qualquer usuário ativo."""
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
get=lambda p:(ROOT/p).read_text(encoding="utf-8")
roles=("admin","professor","aluno","responsavel","secretaria")
contacts=get("private/app/api/chat/contatos.php")
start=get("private/app/api/chat/iniciar.php")
central=get("private/app/api/chat/central.php")
ui=get("public/js/chat.js")
page=get("public/pages/admin_chat.html")
for p,src in [("contatos",contacts),("iniciar",start)]:
    assert "primewayExigirPerfis(['admin'])" in src, p+" precisa exigir perfil Admin"
    assert "primewayExigirAutenticacao" not in src, p+" não pode aceitar qualquer perfil"
    assert "u.ativo=1" in src, p+" deve excluir contas inativas"
    assert "pe.ativo=1" in src, p+" deve excluir pessoas inativas"
    for role in roles:
        assert "'"+role+"'" in src,p+" não lista "+role
assert "primewayExigirCsrf()" in start
assert "primewayExigirMetodo('POST')" in start
assert "primewayExigirMetodo('GET')" in contacts
assert "primewayIdPositivo" in start
assert "$contactUserId === $adminId" in start
assert "FOR UPDATE" in start
assert "SELECT c.id FROM conversas" in start
assert "c.tipo='individual'" in start
assert "COUNT(*) FROM conversa_participantes cp" in start
assert "criada_por_usuario_id" in start
assert "if ($conversationId > 0)" in start
assert "primewayChatExigirDisponivel($pdo,$adminId)" in start
assert "primewayResponderJson(['success'=>true,'conversationId'=>$conversationId,'created'=>false])" in start
assert "primewayResponderJson([" in start
assert "limit + 1" in contacts or "$limit + 1" in contacts
assert "'hasMore'=>$hasMore" in contacts
assert "LIMIT :limit OFFSET :offset" in contacts
assert "u.id<>:viewer" in contacts
assert "primewayExigirPerfis(['admin'])" in central
assert "cp.usuario_id=:viewer AND cp.ativo=1 AND cp.saiu_em IS NULL" in central
assert "primewaySecretariaChatConversa($pdo,$userId,$id)" in central
assert "'chatSuspended'=>primewayChatStatus" in central
assert "primewayChatExigirDisponivel($pdo,$userId)" in central
assert "primewayChatExigirDisponivel($pdo,$conversation['userId'])" not in central
for key in ('adminContactsDialog','adminContactSearch','adminContactRole','adminContactsPrevious','adminContactsNext'):
    assert key in page and key in ui, key
assert "startConversation(contact.userId)" in ui
assert 'request("../api/chat/contatos.php?"' in ui
assert 'request("../api/chat/iniciar.php",{contactUserId})' in ui
assert 'q("#newChatButton").hidden=false' in ui
assert "adminSuspended=Boolean(data.chatSuspended)" in ui
for prefix in ("pages/admin_chat.html","js/chat.js","css/admin_chat_contacts.css"):
    assert get("public/"+prefix)==get("private/app/"+prefix),prefix+" divergente"
for role in roles:
    assert '<option value="'+role+'">' in page,role
for role in ("professor","aluno","responsavel","secretaria"):
    endpoint=get("private/app/api/"+role+"/chat/iniciar.php")
    assert "primewayExigirPerfis([" in endpoint
    assert "'"+role+"'" in endpoint
print("Chat admin: contatos universais com segurança, reuso de conversas e espelhos OK.")
