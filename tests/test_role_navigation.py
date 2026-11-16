#!/usr/bin/env python3
"""Static regression: PrimeWay pages must never navigate into another profile."""
from pathlib import Path
from html.parser import HTMLParser
import re

ROOT=Path(__file__).resolve().parents[1]
PUBLIC=ROOT/"public/pages"
PRIVATE=ROOT/"private/app/pages"
ADMIN={"dashboard","professores","alunos","responsaveis","turmas","disciplinas","calendario","notificacoes","configuracoes","administradores_tcc","admin_chat","chat-professor"}
PORTALS={"admin":"admin_chat","professor":"professor_chat","aluno":"aluno_chat","responsavel":"responsavel_chat","secretaria":"secretaria_chat"}
class Links(HTMLParser):
    def __init__(self):
        super().__init__();self.links=[]
    def handle_starttag(self,tag,attrs):
        if tag!="a":return
        data=dict(attrs);url=data.get("href","")
        if isinstance(url,str) and url.endswith(".html"):self.links.append(url.split("/")[-1].removesuffix(".html"))
def role_of(name):
    if name in ADMIN:return "admin"
    if name in {"chat","login","perfil","preferencias","privacidade"}:return None
    if name=="professor" or name.startswith("professor_"):return "professor"
    if name=="aluno_portal" or name.startswith("aluno_"):return "aluno"
    if name=="responsavel" or name.startswith("responsavel_"):return "responsavel"
    if name=="secretaria" or name.startswith("secretaria_"):return "secretaria"
    raise AssertionError("Página sem classificação: "+name)
pages=sorted(PUBLIC.glob("*.html"))
assert len(pages)>=52, len(pages)
roles={p.stem:role_of(p.stem) for p in pages}
errors=[]
for page in pages:
    content=page.read_text(encoding="utf-8")
    private=PRIVATE/page.name
    if not private.is_file() or content!=private.read_text(encoding="utf-8"):
        errors.append(page.name+": espelho privado divergente")
    parser=Links();parser.feed(content)
    src=roles[page.stem]
    for target in parser.links:
        if target not in roles:
            errors.append(f"{page.name}: link para página inexistente {target}.html")
            continue
        dest=roles[target]
        if src and dest and dest!=src:
            errors.append(f"{page.name}: {src} aponta para {dest} ({target}.html)")
    if src=="admin" and re.search(r"\b(Chat do Professor|Área do professor|Área do aluno|Área do responsável)\b",content,re.I):
        errors.append(page.name+": título de perfil incompatível")
if "Chat da Administração" not in (PUBLIC/"admin_chat.html").read_text(encoding="utf-8"):
    errors.append("Chat admin sem título próprio")
if "Chat do Professor" in (PUBLIC/"admin_chat.html").read_text(encoding="utf-8"):
    errors.append("Chat admin tem título errado")
router=(ROOT/"public/api/auth/pagina.php").read_text(encoding="utf-8")
for alias in ("'admin_chat'","'chat-professor'","'chat'","'professor'","'aluno'","'responsavel'","'secretaria'"):
    if alias not in router:errors.append("Roteamento ausente: "+alias)
for role, page in PORTALS.items():
    if not (PUBLIC/f"{page}.html").is_file():errors.append("Chat de perfil ausente: "+role)
for role in ("professor","aluno","responsavel","secretaria"):
    index=(ROOT/f"private/app/api/{role}/chat/index.php").read_text(encoding="utf-8")
    if f"'{role}'" not in index or "primewayExigirPerfis" not in index:
        errors.append("API Chat sem escopo claro: "+role)
central=(ROOT/"private/app/api/chat/central.php").read_text(encoding="utf-8")
if "primewayExigirPerfis(['admin'])" not in central:errors.append("Chat Admin sem checagem de perfil")
print(f"Auditados {len(pages)} HTML, {len(PORTALS)} portais Chat e seus limites de API.")
if errors:
    raise AssertionError("\n".join(errors))

# Toda API específica de um perfil precisa de guarda de autorização no servidor.
# Exceções compartilhadas intencionais: cadastro/edição acadêmicos, Saída Segura e
# leitura de calendário/notificações Administração/Professor.
API_ROOT=ROOT/"private/app/api"
roles=("professor","aluno","responsavel","secretaria")
api_checked=0
for role in roles:
    for endpoint in (API_ROOT/role).rglob("*.php"):
        if endpoint.name.startswith("_"):continue
        api_checked+=1
        code=endpoint.read_text(encoding="utf-8")
        match=re.search(r"primewayExigirPerfis\s*\(\s*\[([^\]]+)\]",code)
        found=set(re.findall(r"'(admin|professor|aluno|responsavel|secretaria)'",match.group(1))) if match else set()
        expected={role}
        if role=="secretaria" and "/saida_segura/" in endpoint.as_posix():
            expected={"secretaria","admin"}
        if not found or found!=expected:
            errors.append(f"{endpoint.relative_to(ROOT)}: esperado {expected}, encontrado {found}")
        if re.search(r"primewayExigirMetodo\s*\(\s*['\"]POST['\"]",code) and "primewayExigirCsrf" not in code:
            errors.append(f"{endpoint.relative_to(ROOT)}: POST sem CSRF")
admin_roots=("dashboard","professores","alunos","responsaveis",
             "turmas","disciplinas","configuracoes","administradores_tcc",
             "calendario","notificacoes")
for folder in admin_roots:
    for endpoint in (API_ROOT/folder).rglob("*.php"):
        if endpoint.name.startswith("_"):continue
        api_checked+=1
        code=endpoint.read_text(encoding="utf-8")
        match=re.search(r"primewayExigirPerfis\s*\(\s*\[([^\]]+)\]",code)
        found=set(re.findall(r"'(admin|professor|aluno|responsavel|secretaria)'",match.group(1))) if match else set()
        expected={"admin"}
        # API de gestão escolar compartilhada; exclusões e configurações continuam Admin.
        shared={
            "alunos":{"salvar.php"},
            "professores":{"salvar.php","index.php"},
            "responsaveis":{"salvar.php"},
            "turmas":{"salvar.php","index.php"},
            "disciplinas":{"index.php","salvar.php","salvar_vinculo.php","status.php"},
        }
        if endpoint.name in shared.get(folder,set()):
            expected={"admin","secretaria"}
        if folder in {"calendario","notificacoes"} and endpoint.name=="index.php":
            expected={"admin","professor"}
        if folder=="configuracoes" and endpoint.name=="portal.php":
            expected={"admin","secretaria","professor","aluno","responsavel"}
        if folder=="notificacoes" and endpoint.name=="pulso.php":
            expected={"admin","professor","aluno","responsavel","secretaria"}
        if not found or found!=expected:
            errors.append(f"{endpoint.relative_to(ROOT)}: esperado {expected}, encontrado {found}")
        if re.search(r"primewayExigirMetodo\s*\(\s*['\"]POST['\"]",code) and "primewayExigirCsrf" not in code:
            errors.append(f"{endpoint.relative_to(ROOT)}: POST sem CSRF")
print(f"Auditados {api_checked} endpoints PHP com controle explícito de perfil.")
if errors:
    raise AssertionError("\n".join(errors))
