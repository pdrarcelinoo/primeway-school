#!/usr/bin/env python3
"""Tema pessoal global: mesmo comportamento nos 5 perfis sem afetar o sistema claro."""
from pathlib import Path
import re
R=Path(__file__).resolve().parents[1]
def read(path):return (R/path).read_text(encoding="utf8")
for f in ("css/modo_noturno.css","js/modo_noturno.js",
          "pages/preferencias.html","pages/configuracoes.html"):
    assert read("private/app/"+f)==read("public/"+f),f
router=read("public/api/auth/pagina.php")
assert "primewayUsuarioAtualSessao()" in router
assert "if ($usuario === null)" in router
assert "if ($perfilAtual !== $perfilNecessario)" in router
assert "|| $pagina === 'preferencias'" in router
assert "data-primeway-theme-account" in router
assert "data-primeway-theme-role" in router
assert "$usuario['id']" in router
assert "htmlspecialchars($perfilAtual, ENT_QUOTES, 'UTF-8')" in router
assert "modo_noturno.js?v=20261110-dark1" in router
assert "modo_noturno.css?v=20261110-dark2" in router
assert router.index("modo_noturno.js?v=")<router.index("modo_noturno.css?v=")
assert "Cache-Control: no-store, private, max-age=0" in router
assert "'configuracoes'" in router
assert "(int)$usuario['id']" in router
code=read("public/js/modo_noturno.js")
for feature in (
    "primeway:appearance:v1:",
    "data-primeway-theme-account",
    "data-primeway-theme-role",
    "doc.dataset.primewayTheme",
    "doc.style.colorScheme",
    "setDark(read(),false)",
    "window.localStorage.setItem(key",
    "window.addEventListener(\"storage\"",
    "data-primeway-theme-toggle",
    "aria-checked",
    "document.addEventListener(\"DOMContentLoaded\",mount",
    "role===\"admin\"?\"configuracoes.html\":\"preferencias.html\"",
    "MutationObserver",
    "professor:\"professor.html\"",
    "secretaria:\"secretaria.html\""
):
    assert feature in code,feature
assert "fetch(" not in code,"Mudança de tema não deve chamar API nem alterar dados escolares"
assert "location.reload(" not in code
assert "window.localStorage.clear()" not in code
css=read("public/css/modo_noturno.css")
assert 20000<len(css)<42000
assert css.count("{")==css.count("}")
assert 'html[data-primeway-theme="dark"]' in css
assert "color-scheme:dark" in css
assert "background:#0b1423" in css
assert "prefers-reduced-motion:reduce" in css
assert "filter:none!important" in css
assert ".pw-appearance-panel" in css
assert ".pw-theme-switch input:focus-visible" in css
assert "overflow:hidden" not in css
settings=read("public/pages/configuracoes.html")
preferences=read("public/pages/preferencias.html")
for html in (settings,preferences):
    assert 'data-primeway-theme-toggle' in html
    assert 'role="switch"' in html
    assert 'aria-checked="false"' in html
    assert 'data-primeway-theme-state' in html
    assert 'Modo noturno' in html
    assert 'Modo claro ativado' in html
assert settings.index("pw-appearance-panel")<settings.index("settings-summary")
assert "settingsForm" in settings and "saveSettingsButton" in settings
assert "configuracoes.js" in settings
assert 'href="perfil.html"' in preferences
assert 'data-primeway-preferences-back' in preferences
assert "configuracoes.js" not in preferences
for role in ("admin","professor","aluno","responsavel","secretaria"):
    home={"admin":"dashboard","professor":"professor","aluno":"aluno_portal",
          "responsavel":"responsavel","secretaria":"secretaria"}[role]
    assert "core.js" in read("public/pages/"+home+".html")
print("Tema noturno: 5 perfis, preferência individual, aparência clara, autorização e espelhos OK.")
