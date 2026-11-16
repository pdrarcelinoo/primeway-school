#!/usr/bin/env python3
"""Regressão do painel Admin: compatível com os outros quatro perfis."""
from pathlib import Path
from html.parser import HTMLParser
import re

ROOT=Path(__file__).resolve().parents[1]
def read(path):return (ROOT/path).read_text(encoding="utf-8")
public=read("public/pages/dashboard.html")
private=read("private/app/pages/dashboard.html")
assert public==private,"HTML de Admin deve manter a cópia de execução"
assert public.count('id="dashboardTitle"')==1
assert public.count('id="dashboardUserName"')==1
assert public.count('id="adminDashboardDate"')==1
assert public.count('class="pesquisa"')==0
assert 'admin-dashboard-search-row' not in public
assert public.count('class="usuario"')==1
assert public.count('class="admin-quick-card"')==4
assert public.count('class="topo admin-dashboard-header"')==1
assert "class=\"dashboard\"" in public
assert "class=\"app-layout\"" not in public, "Não alterar a estrutura flexível existente"
assert 'href="../css/dashboard.css?v=20261103-admin1"' in public
assert '../js/dashboard.js?v=20261103-welcome1' in public
assert 'id="dashboardRecentList"' in public
assert 'id="dashboardCalendarList"' in public
assert 'id="dashboardNoticeList"' in public
for id in ("dashboardStudents","dashboardProfessors","dashboardClasses",
           "dashboardAverage","dashboardPresent","dashboardAbsent",
           "dashboardAttendancePercentage","dashboardPendingGrades"):
    assert 'id="'+id+'"' in public,id
for link in ("professores.html","alunos.html","turmas.html","notificacoes.html"):
    assert 'class="admin-quick-card" href="'+link+'"' in public,link

# Verifica tags do HTML do painel, em vez de confiar apenas em substrings.
class Balanced(HTMLParser):
    VOID={"area","base","br","col","embed","hr","img","input","link","meta","param","source","track","wbr"}
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.stack=[]
    def handle_starttag(self,tag,attrs):
        if tag not in self.VOID:
            self.stack.append(tag)
    def handle_startendtag(self,tag,attrs):pass
    def handle_endtag(self,tag):
        if tag in self.VOID:return
        assert self.stack, "Tag fechada sem abertura: "+tag
        expected=self.stack.pop()
        assert expected==tag, f"Tag não balanceada: {expected} e {tag}"

parser=Balanced()
parser.feed(public)
assert not parser.stack,parser.stack[-12:]
assert len(re.findall(r'\bid="([^"]+)"',public))==len(set(re.findall(r'\bid="([^"]+)"',public))),"IDs duplicados"

js=read("public/js/dashboard.js")
assert js==read("private/app/js/dashboard.js")
for anchor in ('function tituloAuditoria(acao)',"titulo.textContent = tituloAuditoria(item.action);",
               'document.querySelector("#adminDashboardDate")',
               "new Intl.DateTimeFormat(\"pt-BR\"","titulo.textContent", "renderizarAtividades(data.recentActivity)"):
    assert anchor in js,anchor
assert "pesquisaInput" not in js and "filtrarDashboard" not in js
assert 'PrimeWayGreeting?.render("admin", usuarioSessao.nome)' in js
assert "item.action ||" not in js,"O código bruto não deve voltar para os títulos"
style=read("public/css/dashboard.css")
assert style==read("private/app/css/dashboard.css")
assert ".dashboard .admin-dashboard-header" in style
assert ".dashboard .admin-quick-grid" in style
assert "@media (max-width: 750px)" in style
global_style=read("public/css/visual_polish.css")
assert global_style==read("private/app/css/visual_polish.css")
assert "body :is(.app-layout, .dashboard) .sidebar" in global_style
assert "body .app-layout .sidebar" not in global_style
router=read("public/api/auth/pagina.php")
assert 'visual_polish.css?v=20261103-2' in router
assert "if ($perfilAtual !== $perfilNecessario)" in router
assert "if ($usuario === null)" in router
print("Dashboard admin alinhado: HTML balanceado, indicadores, atalhos, sessão e CSS dos cinco perfis aprovados.")
