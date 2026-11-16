#!/usr/bin/env python3
"""Regressão: texto legível no chat administrativo sem alterar os outros perfis."""
from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]

def read(path):
    return (root / path).read_text(encoding="utf-8")

for path in ("css/admin_chat_readability.css", "pages/admin_chat.html", "js/chat.js"):
    assert read("public/" + path) == read("private/app/" + path), path + ": espelhos divergentes"

css = read("public/css/admin_chat_readability.css")
page = read("public/pages/admin_chat.html")
chat = read("public/js/chat.js")

assert css.count("{") == css.count("}"), "Blocos CSS desbalanceados"
assert 'class="pw-admin-chat"' in page
assert 'admin_chat_readability.css?v=20261110-1' in page
assert page.index('admin_chat_voice.css') < page.index('admin_chat_readability.css')
assert '.pw-admin-chat .conversation-info :is(p, span)' in css
assert '.pw-admin-chat .conversation-info strong' in css
assert '.pw-admin-chat .conversation-person h2' in css
assert '.pw-admin-chat .message .message-bubble > .message-time' in css
assert '.pw-admin-chat .chat-input-area input[type="text"]' in css
assert 'data-primeway-theme="dark"' in css
assert '@media (max-width: 680px)' in css
assert re.search(r'font-size:\s*1[345](?:\.\d+)?px', css)
assert 'const displayRole=' in chat
assert 'displayRole(item.role)' in chat
assert 'displayRole(data.conversation.role,' in chat
for token in ('admin:"Administrador"', 'responsavel:"Responsável"', 'secretaria:"Secretaria"'):
    assert token in chat, token
# A correção é visual: a autorização e o envio originais permanecem intocados.
for token in (
    'role!=="admin"', 'createAudioRecorder({', 'renderMessageAttachments',
    'syncComposer()', 'request(api', 'sendMessage()',
):
    assert token in chat, token
assert ':focus-visible' not in css or 'display: none' not in css
print("Tipografia Chat Admin: espelhos, nomes, áudio e layout responsivo OK.")
