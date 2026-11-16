#!/usr/bin/env python3
"""Teste de regressão: saudações personalizadas e busca retirada do admin."""
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
def read(prefix,path):
    return (ROOT/prefix/path).read_text(encoding="utf-8")
for path in ["pages/dashboard.html","pages/professor.html","pages/aluno_portal.html","pages/responsavel.html","pages/secretaria.html","js/dashboard.js","js/professor.js","js/aluno_portal.js","js/responsavel.js","js/secretaria.js","js/core.js"]:
    assert read("public",path)==read("private/app",path),path
roles={"admin":("dashboard","dashboardTitle","Administrador"),"professor":("professor","professorGreeting","Professor(a)"),"aluno":("aluno_portal","studentGreeting","Aluno(a)"),"responsavel":("responsavel","guardianGreeting","Responsável"),"secretaria":("secretaria","secretaryGreeting","Secretaria")}
core=read("public","js/core.js")
assert "registeredName" in core and "heading.textContent" in core
assert "window.PrimeWayGreeting.render(profile.role, profile.name)" in core
for role,(page,heading,label) in roles.items():
    html=read("public",f"pages/{page}.html")
    assert f'id="{heading}"' in html
    assert f"Olá, {label}!" in html
    assert "../js/core.js?v=20261103-welcome1" in html
    js=read("public",f"js/{page}.js")
    assert f'PrimeWayGreeting?.render("{role}",' in js
assert "admin-dashboard-search-row" not in read("public","pages/dashboard.html")
assert "pesquisaInput" not in read("public","js/dashboard.js")
print("Saudações dos cinco perfis e busca do administrador: OK")
