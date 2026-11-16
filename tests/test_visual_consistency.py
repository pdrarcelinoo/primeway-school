#!/usr/bin/env python3
"""A camada de acabamento não pode quebrar identidade, rotas ou acesso por perfil."""
from pathlib import Path
import re

ROOT=Path(__file__).resolve().parents[1]
public=ROOT/"public"
mirror=ROOT/"private/app"
css=(public/"css/visual_polish.css").read_text(encoding="utf-8")
assert css==(mirror/"css/visual_polish.css").read_text(encoding="utf-8"), \
    "Espelhos de CSS divergentes"
assert 2500<len(css)<14000
assert css.count("{")==css.count("}"), "Blocos CSS inválidos"
assert "prefers-reduced-motion" in css
assert ":focus-visible" in css
assert "overflow: hidden" not in css, "O acabamento não deve cortar conteúdo"
assert "display: none" not in css, "Não esconder elementos funcionais"
assert not re.search(r"(?<!-)\bposition\s*:\s*(?:fixed|absolute)",css), \
    "Não sobrepor elementos essenciais"
assert ":root {" in css and "#f1c963" in css and "#224493" in css
for role in ("student","guardian","professor","secretary"):
    assert role in css,role

router=(public/"api/auth/pagina.php").read_text(encoding="utf-8")
for required in (
    "if ($usuario === null)",
    "if ($perfilAtual !== $perfilNecessario)",
    "Cache-Control: no-store, private, max-age=0",
    "visual_polish.css?v=20261103-2",
    "if (stripos($html, 'visual_polish.css') === false)",
    "preg_replace('~</head>~i', $visualCss.",
):
    assert required in router,required
assert router.count("visual_polish.css?v=20261103-2")==1
assert "login.html" not in css

pages=sorted((public/"pages").glob("*.html"))
assert len(pages)>=50
for role,home in (
    ("admin","dashboard.html"),("professor","professor.html"),
    ("aluno","aluno_portal.html"),("responsavel","responsavel.html"),
    ("secretaria","secretaria.html")
):
    html=(public/"pages"/home).read_text(encoding="utf-8")
    assert re.search(r'class="sidebar(?:\s|")', html), role+" precisa de menu lateral"
    assert 'href="../css/components.css"' in html or 'href="../css/components.css"' in html.replace("\n",""),role
    assert html==(mirror/"pages"/home).read_text(encoding="utf-8"), role+" HTML divergente"
print(f"Acabamento: {len(pages)} páginas preservadas, cinco perfis e CSS privado/público consistentes.")
