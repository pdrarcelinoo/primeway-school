#!/usr/bin/env python3
"""Regressões de segurança: dados externos nunca são interpolados em HTML da Secretaria."""
from pathlib import Path
import re
root=Path(__file__).resolve().parents[1]
read=lambda rel: (root/rel).read_text(encoding="utf-8")
enroll=read("public/js/secretaria_matriculas.js")
professor=read("public/js/professor_entregas.js")
assert enroll==read("private/app/js/secretaria_matriculas.js"),"Espelho de Secretaria divergente"
assert professor==read("private/app/js/professor_entregas.js"),"Espelho de Professor divergente"

start=enroll.index('row.innerHTML='+chr(96),enroll.index("for(const student of list){"))
end=enroll.index("tbody.append(row);",start)
row=enroll[start:end]
for expression in ("student.name","student.email","student.registration","student.id",
                   "enrollment.className","enrollment.series","enrollment.shift",
                   "enrollment?.callNumber"):
    assert re.search(r"escapeHtml\(\s*"+re.escape(expression),row),expression+" precisa de HTML escaping"
    assert not re.search(r"\$\{\s*"+re.escape(expression),row),expression+" não pode ir ao HTML sem escaping"
assert "escapeHtml(window.PrimeWaySecretaria.formatDate(" in row
assert "function escapeHtml(value)" in enroll
assert "replace(/[&<>\"']/g" in enroll

assert 'submissionsList.querySelector(".loading-state span").textContent' in professor
assert not re.search(r"submissionsList\.innerHTML\s*=\s*\x60[\s\S]*?\$\{\s*error\.message",professor)
for page,script in (("secretaria_matriculas","secretaria_matriculas"),("professor_entregas","professor_entregas")):
    html=read("public/pages/"+page+".html")
    assert html==read("private/app/pages/"+page+".html")
    assert f"../js/{script}.js?v=20261103-xss1" in html

delivery=read("private/app/api/chat/entregas.php")
assert "primewayExigirMetodo('POST')" in delivery
assert "primewayExigirCsrf()" in delivery
assert "primewayExigirPerfis([" in delivery
assert "primewayChatMarcarEntregues($pdo, $usuarioId)" in delivery
script=read("public/js/chat_interaction_fixes.js")
assert script==read("private/app/js/chat_interaction_fixes.js")
part=script[script.index("async function syncDeliveries()"):script.index("function updateNetworkStatus()")]
assert 'method:"POST"' in part and 'method:"GET"' not in part
assert 'credentials:"same-origin"' in part
for role in ("admin","professor","aluno","responsavel","secretaria"):
    html=read("public/pages/"+role+"_chat.html")
    assert html==read("private/app/pages/"+role+"_chat.html")
    assert "../js/chat_interaction_fixes.js?v=20261103-2" in html
print("XSS de matrículas/entregas e CSRF do recibo: regressões estáticas aprovadas.")


# Secretaria pode conversar com admin, mas não pode suspender um administrador.
secretaria=get_role_src=None
suspension=read("private/app/api/secretaria/chat/suspensao.php")
assert "primewayExigirPerfis([" in suspension and "'secretaria'" in suspension
allowed=suspension.split("AND u.perfil IN (",1)[1].split(")",1)[0]
assert "'admin'" not in allowed
for role in ("professor","aluno","responsavel"):
    assert "'"+role+"'" in allowed
secretaria_js=read("public/js/secretaria_chat.js")
assert secretaria_js==read("private/app/js/secretaria_chat.js")
assert secretaria_js.count('activeConversation.role==="admin"')>=3
assert "../js/secretaria_chat.js?v=20261110-live1" in read("public/pages/secretaria_chat.html")
