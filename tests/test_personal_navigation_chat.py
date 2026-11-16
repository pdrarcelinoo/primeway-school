#!/usr/bin/env python3
"""Navegação escolar completa nas páginas pessoais e áudio consistente nos chats."""
from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]
read=lambda path:(ROOT/path).read_text(encoding="utf8")
php=read("public/api/auth/pagina.php")
for field in (
    "'admin' => 'dashboard'","'professor' => 'professor'",
    "'aluno' => 'aluno_portal'","'responsavel' => 'responsavel'",
    "'secretaria' => 'secretaria'",
    "in_array($pagina, ['perfil', 'preferencias', 'privacidade'], true)",
    "$perfilAtual", "$diretorioPaginas", "file_get_contents($homeFile)",
    "preg_match($sidePattern", "$canonicalSidebar",
    "navegacao_pessoal.js?v=20261110-nav1",
    "professor_common", "secretaria_common", "aluno_common", "responsavel_common",
    "20261110-nav1","X-Robots-Tag: noindex, nofollow",
    "Cache-Control: no-store, private",
):
    assert field in php,field
assert "primewayUsuarioAtualSessao()" in php
assert "if ($usuario === null)" in php
assert "if ($perfilAtual !== $perfilNecessario)" in php
for page in ("perfil","preferencias","privacidade"):
    html=read("public/pages/"+page+".html")
    assert html==read("private/app/pages/"+page+".html"),page
    assert 'class="sidebar"' in html or 'class="sidebar"' in html.replace('class="sidebar ', 'class="sidebar"'),page
assert "app-layout" in read("public/pages/privacidade.html")
assert "core.js" in read("public/pages/privacidade.html")
assert 'perfil.js?v=20261110-nav1' in read("public/pages/perfil.html")
assert "logoutButton" not in read("public/js/perfil.js"),"Logout unificado, sem duplo POST"
navigation=read("public/js/navegacao_pessoal.js")
assert navigation==read("private/app/js/navegacao_pessoal.js")
for item in ("perfil.html","preferencias.html","privacidade.html",
             "querySelector(\"aside.sidebar\")","markCurrent()",
             "MutationObserver","logoutPending",
             "X-CSRF-Token","method:\"POST\"",
             "../api/auth/logout.php","../api/auth/session.php"):
    assert item in navigation,item
assert "fetch(" not in navigation.split("async function logout(){")[0]
assert "innerHTML" not in navigation
for role,home in (
    ("admin","dashboard"),("aluno","aluno_portal"),("responsavel","responsavel")
):
    source=read("public/pages/"+home+".html")
    menu=re.search(r'<aside\b[^>]*\bclass\s*=\s*"[^"]*\bsidebar\b[^"]*"[^>]*>.*?</aside>',source,re.I|re.S)
    assert menu,(role,"Sem sidebar na página oficial")
    anchors=re.findall(r'href="([^"]+\.html)"',menu.group(0))
    assert len(anchors)>=7,(role,len(anchors))
for role in ("professor","secretaria"):
    shared=read("public/js/"+role+"_common.js")
    assert "injectSidebar()" in shared and 'sidebar.innerHTML' in shared
    assert "sidebar-menu" in shared
for role in ("admin","aluno","professor","responsavel","secretaria"):
    path="chat.js" if role=="admin" else role+"_chat.js"
    src=read("public/js/"+path)
    assert src==read("private/app/js/"+path),role
    html=read("public/pages/"+role+"_chat.html")
    assert html==read("private/app/pages/"+role+"_chat.html"),role
    assert "chat_message_experience.js" in html,role
    assert "chat_live_updates.js" in html,role
    if role=="admin":
        for part in (
            "filesUi.createAudioRecorder({","onFileReady:(clip,meta)=>",
            "audio.cancel()","audio.isRecording()","selectedFile",
            "body.set(\"file\",selectedFile,selectedFile.name)",
            "if(active&&!adminSuspended)",
            "onRecordingChange:recording=>",
            "PrimeWayChatLiveUpdates.start",
        ):
            assert part in src,(role,part)
        assert 'admin_chat_voice.css?v=20261110-audio1' in html
        assert 'chat.js?v=20261110-audio1' in html
        assert html.index("chat_attachments_shared.js")<html.index("chat.js?v=")
    else:
        assert "Attachments.createComposer({" in src,role
        assert "attachmentUi.hasFile()" in src,role
        assert 'upload(' in src,role
    assert "renderMessageAttachments" in src
assert read("public/css/admin_chat_voice.css")==read("private/app/css/admin_chat_voice.css")
voice=read("public/css/admin_chat_voice.css")
assert "grid-template-columns:40px 40px minmax(0,1fr) 40px" in voice
assert ".pw-chat-recording-status" in voice and "grid-row:2" in voice
assert "data-primeway-theme=\"dark\"" in voice
shared=read("public/js/chat_attachments_shared.js")
for item in ("createAudioRecorder,","getUserMedia({audio:true})",
             "MediaRecorder", "stopRecording(true)", "MAX_RECORD_SECONDS",
             "async upload({conversationId", "X-CSRF-Token"):
    assert item in shared,item
print("Menus completos e chat com áudio: 5 perfis, permissão e espelhos consistentes.")
