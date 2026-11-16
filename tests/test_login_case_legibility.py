#!/usr/bin/env python3
"""O login deve exibir fielmente a caixa digitada e avisar sobre Caps Lock."""
from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]
def read(path):
    return (root / path).read_text(encoding="utf-8")

for name in ("css/login.css", "js/login.js", "pages/login.html"):
    assert read("private/app/" + name) == read("public/" + name), name

css = read("public/css/login.css")
field = re.search(r"\.campo input\s*\{([^}]+)\}", css)
assert field, "O seletor do campo de login não existe"
for style in ("font-family:", '"Segoe UI"', "font-variant: normal", "text-transform: none", "letter-spacing: normal"):
    assert style in field.group(1), f"Campo não garante legibilidade: {style}"
assert "font-family: \"Cinzel\"" in css, "A identidade visual original deve ser mantida"
assert ".caps-lock-notice[hidden]" in css

page = read("public/pages/login.html")
assert 'type="password"' in page and 'id="senha"' in page
assert 'id="mostrarSenha"' in page, "Mostrar/ocultar senha deve continuar disponível"
assert 'id="capsLockNotice"' in page and 'aria-live="polite"' in page
assert "../css/login.css?v=20261109-case1" in page
assert "../js/login.js?v=20261109-case1" in page

script = read("public/js/login.js")
assert 'senhaInput.type =' in script and '? "text"' in script
assert 'getModifierState("CapsLock")' in script
assert 'senhaInput.addEventListener("keydown", atualizarCapsLock)' in script
assert 'senhaInput.addEventListener("keyup", atualizarCapsLock)' in script
assert 'capsLockNotice.hidden = true' in script
print("Login: maiúsculas/minúsculas legíveis, Caps Lock e mostrar senha preservados.")
