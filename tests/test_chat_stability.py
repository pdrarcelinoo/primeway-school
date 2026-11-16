#!/usr/bin/env python3
"""Regressão: cinco chats atualizam sem reload, respeitam sessão e evitam reenvios."""
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]

def file(path):
    return (ROOT/path).read_text(encoding="utf8")

shared=file("public/js/chat_live_updates.js")
assert shared==file("private/app/js/chat_live_updates.js")
for pattern in (
    "TICK_MS=2600","INDEX_MS=7000","MAX_RETRY_MS=30000",
    'URL="../api/chat/sync.php"',
    "document.visibilityState===\"visible\"",
    "navigator.onLine!==false",
    "if(stopped||working||!available()||isBusy())return",
    "credentials:\"same-origin\"",
    'cache:"no-store"',
    "data.version",
    "version=data.version",
    "if(previous!==data.version||force)",
    "if(id!==activeId()||isBusy())return",
    "refreshConversations()",
    "window.setTimeout",
    "window.clearTimeout",
    "window.addEventListener(\"online\",wake)",
    "window.addEventListener(\"pagehide\",stop)",
    "document.removeEventListener(\"visibilitychange\",onVisible)"
):
    assert pattern in shared,pattern
assert "window.setInterval" not in shared
assert "location.reload" not in shared

for role in ("admin","professor","aluno","responsavel","secretaria"):
    js="chat.js" if role=="admin" else role+"_chat.js"
    html=file(f"public/pages/{role}_chat.html")
    assert html==file(f"private/app/pages/{role}_chat.html"),role
    assert "chat_live_updates.js?v=20261110-live1" in html,role
    assert f"{js}?v="+("20261110-audio1" if role=="admin" else "20261110-live1") in html,role
    assert html.index("chat_live_updates.js?v=")<html.index(js+"?v="),role
    source=file("public/js/"+js)
    assert source==file("private/app/js/"+js),role
    assert "window.PrimeWayChatLiveUpdates.start({" in source,role
    assert "getActiveConversationId:" in source,role
    assert "refreshConversations:" in source,role
    assert "refreshMessages:" in source,role
    assert "isBusy:" in source,role
    assert "if(sending)return" in source or "if(busy||!active" in source,role
    assert "setInterval(refreshChat" not in source,role
    assert "location.reload" not in source,role
    assert "PrimeWayChatMessageExperience.updateMessageList" in source or "Experience.updateMessageList" in source,role
    if role!="admin":
        for pattern in (
            "let messagesVersion=0",
            "const version=++messagesVersion",
            "return true;",
            "return false;",
            "if(!quiet)",
            "Number(unreadItem.unread||0)<1",
        ):
            assert pattern in source,(role,pattern)

php=file("private/app/api/chat/sync.php")
public=file("public/api/chat/sync.php")
assert "/opt/primeway/app/api/chat/sync.php" in public
for pattern in (
    "primewayExigirMetodo('GET')",
    "primewayExigirPerfis(['admin', 'secretaria', 'professor', 'aluno', 'responsavel'])",
    "cp.usuario_id = :usuario_id",
    "cp.ativo = 1",
    "cp.saiu_em IS NULL",
    "c.ativo = 1",
    "hash('sha256'",
    "soma_visiveis",
    "soma_fixadas",
    "ultima_edicao",
    "ultima_exclusao",
    "Cache-Control: no-store"
):
    assert pattern in php,pattern

exp=file("public/js/chat_message_experience.js")
assert "sameConversation&&previous.signature===signature" in exp
assert "list.scrollTop+=anchor.getBoundingClientRect().top-anchorTop" in exp
fixes=file("public/js/chat_interaction_fixes.js")
assert fixes==file("private/app/js/chat_interaction_fixes.js")
assert "let statusBusy=false" in fixes
assert "let deliveryBusy=false" in fixes
assert "window.addEventListener(\"offline\"" in fixes
assert "window.addEventListener(\"online\"" in fixes
assert "DELIVERY_REFRESH_MS=6000" in fixes
print("Chat contínuo: cinco perfis, atualização sob demanda, rede, foco e autorização OK.")
