#!/usr/bin/env python3
"""Tipografia consistente em páginas de Administrador, Secretaria, Professor, Aluno e Responsável."""
from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]
def read(path):
    return (root / path).read_text(encoding="utf-8")

css = read("public/css/tipografia_global.css")
assert css == read("private/app/css/tipografia_global.css"), "CSS público e privado divergentes"
assert 5000 < len(css) < 15000, "Folha incompleta ou excessiva"
assert css.count("{") == css.count("}"), "Regras CSS desbalanceadas"
assert "html[data-primeway-theme-role]" in css, "Escopo autenticado ausente"
assert "body:not(.pw-admin-chat)" in css, "Chat Admin aprovado deve manter estilo específico"
assert "body.pw-login-typography" in css, "A página de login também deve usar fonte legível"
assert "Segoe UI" in css and "Roboto" in css, "Fonte sans-serif não definida"
assert 'data-primeway-theme="dark"' in css, "O tema escuro não pode ser ignorado"
assert "prefers-reduced-motion" in css, "Respeitar redução de movimento"
assert "position: absolute" not in css and "position: fixed" not in css, "Sem novos overlays"
assert "display: none" not in css and "overflow: hidden" not in css, "Não esconder funcionalidades"
assert not re.search(r"(?m)^\s*zoom\s*:", css), "Não substituir ampliação de acessibilidade"
for selector in (
    ".student-page-header", ".guardian-page-header", ".professor-module-header",
    ".secretary-header", ".dashboard-header", ".settings-header",
    ".perfil-top", ".portal-header",
    ".chat-message", ".teacher-message", ".guardian-message", ".secretary-message",
    ".conversation-info", ".teacher-conversation-info",
    ".guardian-conversation-info", ".secretary-conversation-info",
    "table td", "input:not([type=\"checkbox\"])", "time",
):
    assert selector in css, "Componente não coberto: " + selector

router = read("public/api/auth/pagina.php")
assert "primewayUsuarioAtualSessao()" in router
assert "if ($perfilAtual !== $perfilNecessario)" in router
assert 'tipografia_global.css?v=20261110-1' in router
assert "if (stripos($html, 'tipografia_global.css') === false)" in router
assert router.index("tipografia_global.css?v=20261110-1") > router.index("modo_noturno.css?v="), "CSS deve ser injetado após tema"
assert router.count("tipografia_global.css?v=20261110-1") == 1, "Não injetar CSS duplicado"
assert "echo $html;" in router, "Roteador não deve alterar resposta"
for role,home in (
    ("admin","dashboard"), ("professor","professor"),
    ("aluno","aluno_portal"), ("responsavel","responsavel"),
    ("secretaria","secretaria"),
):
    html = read(f"public/pages/{home}.html")
    assert html == read(f"private/app/pages/{home}.html"), "Espelho divergente " + role
    assert "<head" in html and "</head>" in html, "Página sem head: " + role
    assert 'href="../css/components.css"' in html or 'href="../css/components.css"' in html.replace("\n",""), role

login = read("public/pages/login.html")
assert login == read("private/app/pages/login.html"), "Login não espelhado"
assert '<body class="pw-login-typography">' in login, "Login não usa classe de tipografia"
assert '../css/tipografia_global.css?v=20261110-1' in login, "Login não carrega CSS"
assert 'id="senha"' in login and 'id="mostrarSenha"' in login, "Campos essenciais removidos"

print("Tipografia global: 5 perfis, login, quatro chats, tema escuro e acessibilidade OK.")
