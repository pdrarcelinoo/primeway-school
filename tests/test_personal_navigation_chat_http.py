#!/usr/bin/env python3
"""Rotas de Perfil/Configurações/Privacidade mantêm menu da conta autenticada.
Somente no MySQL/Apache sintéticos do CI, jamais em produção.
"""
import json,re,os
from http.cookiejar import CookieJar
from urllib.request import HTTPRedirectHandler,HTTPCookieProcessor,Request,build_opener
from urllib.error import HTTPError
BASE=os.environ.get("PRIMEWAY_TEST_URL","")
assert BASE in ("http://127.0.0.1:18080","http://localhost:18080")
SLUG={"admin":"admin1","professor":"professor1","aluno":"aluno1",
      "responsavel":"responsavel1","secretaria":"secretaria1"}
PASS="SenhaCI-2026-Ficticia!"
class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,url):return None
def request(client,path,method="GET",payload=None):
    data=json.dumps(payload).encode() if payload is not None else None
    try:
        res=client.open(Request(BASE+path,data=data,method=method,
            headers={"Content-Type":"application/json"} if data else {}),timeout=20)
    except HTTPError as err:res=err
    with res:return res.status,res.read(250000).decode("utf8","replace"),res.headers
def signin(role):
    client=build_opener(HTTPCookieProcessor(CookieJar()),NoRedirect())
    status,body,_=request(client,"/api/auth/login.php","POST",{
        "email":"ci-"+SLUG[role]+"@example.invalid","senha":PASS
    })
    assert status==200,(role,status,body[:200])
    return client
def get_menu(html):
    hit=re.search(r'<aside\b[^>]*\bclass\s*=\s*"[^"]*\bsidebar\b[^"]*"[^>]*>.*?</aside>',html,re.I|re.S)
    assert hit,"O menu escolar não foi incluído."
    return hit.group()
client0=build_opener(NoRedirect())
for page in ("perfil","preferencias","privacidade"):
    status,_,headers=request(client0,"/pages/"+page+".html")
    assert status==302 and "login.html" in headers.get("Location",""),(page,status)

allowed={
    "admin":("dashboard.html","professores.html","alunos.html","admin_chat.html","configuracoes.html"),
    "aluno":("aluno_portal.html","aluno_notas.html","aluno_chat.html"),
    "responsavel":("responsavel.html","responsavel_alunos.html","responsavel_chat.html")
}
all_roles=list(SLUG)
for role in all_roles:
    client=signin(role)
    for page in ("perfil","preferencias","privacidade"):
        status,html,headers=request(client,"/pages/"+page+".html")
        assert status==200,(role,page,status)
        assert "navegacao_pessoal.js?v=20261110-nav1" in html,(role,page)
        assert "20261110-nav1" in html
        assert "noindex" in headers.get("X-Robots-Tag",""),(role,page)
        assert "private" in headers.get("Cache-Control","")
        sidebar=get_menu(html)
        if role in allowed:
            assert "sidebar-bottom" in sidebar,(role,page)
            for target in allowed[role]:
                assert 'href="'+target+'"' in sidebar,(role,page,target)
            assert len(re.findall(r'\bhref="[^"]+\.html"',sidebar))>=7
        else:
            assert role+"_common.js?v=20261110-nav1" in html,(role,page)
            assert 'class="sidebar' in sidebar
            assert "sidebar-menu" in client.open(Request(BASE+"/js/"+role+"_common.js")).read().decode()
        assert "navegacao_pessoal.js" in html
    status,_,headers=request(client,"/pages/configuracoes.html")
    if role=="admin":assert status==200
    else:assert status==302 and headers.get("Location","").startswith("/pages/"),role
    status,chat,_=request(client,"/pages/"+("admin_chat.html" if role=="admin" else role+"_chat.html"))
    assert status==200,(role,status)
    if role=="admin":
        assert "admin_chat_voice.css" in chat
        assert "chat_attachments_shared.js" in chat
        assert "chat.js?v=20261110-audio1" in chat
    else:
        assert role+"_chat.js" in chat
print("Menu escolar completo e áudio Admin: cinco contas e três páginas pessoais HTTP OK.")
