#!/usr/bin/env python3
"""Cinco perfis visualizam as preferências pessoais, sem abrir Configurações admin.
Somente servidor localhost da CI, nunca produção.
"""
import json,os,re
from http.cookiejar import CookieJar
from urllib.error import HTTPError
from urllib.request import HTTPCookieProcessor,HTTPRedirectHandler,Request,build_opener
BASE=os.environ.get("PRIMEWAY_TEST_URL","")
assert BASE in ("http://127.0.0.1:18080","http://localhost:18080")
SLUG={"admin":"admin1","professor":"professor1","aluno":"aluno1",
      "responsavel":"responsavel1","secretaria":"secretaria1"}
class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,url):return None
def request(opener,path,method="GET",data=None):
    body=json.dumps(data).encode() if data is not None else None
    headers={"Content-Type":"application/json"} if data is not None else {}
    try:response=opener.open(Request(BASE+path,headers=headers,data=body,method=method),timeout=20)
    except HTTPError as err:response=err
    with response:return response.status,response.read(200000).decode("utf8","replace"),response.headers
def login(role):
    opener=build_opener(HTTPCookieProcessor(CookieJar()),NoRedirect())
    code,raw,_=request(opener,"/api/auth/login.php","POST",{
        "email":"ci-"+SLUG[role]+"@example.invalid","senha":"SenhaCI-2026-Ficticia!"
    })
    assert code==200,(role,code,raw[:250])
    return opener,json.loads(raw)["usuario"]
anon=build_opener(HTTPCookieProcessor(CookieJar()),NoRedirect())
code,_,headers=request(anon,"/pages/preferencias.html")
assert code==302 and "login.html" in headers.get("Location",""),(code,headers)
seen={}
for role in SLUG:
    session,user=login(role)
    seen[role]=str(user["id"])
    for page in ("preferencias.html",{"admin":"dashboard.html","professor":"professor.html",
        "aluno":"aluno_portal.html","responsavel":"responsavel.html","secretaria":"secretaria.html"}[role]):
        code,html,headers=request(session,"/pages/"+page)
        assert code==200,(role,page,code)
        assert 'data-primeway-theme-account="'+str(user["id"])+'"' in html,(role,page)
        assert 'data-primeway-theme-role="'+role+'"' in html,(role,page)
        assert html.index("modo_noturno.js?v=20261110-dark1")<html.index("components.css"),(role,page)
        assert "modo_noturno.css?v=20261110-dark2" in html
        assert "Cache-Control" in headers
        assert "private" in headers["Cache-Control"]
    code,html,_=request(session,"/pages/preferencias.html")
    assert 'data-primeway-theme-toggle' in html
    code,_,headers=request(session,"/pages/configuracoes.html")
    if role=="admin":
        assert code==200,(role,code)
    else:
        assert code==302,(role,code)
        assert headers.get("Location","").startswith("/pages/")
assert len(set(seen.values()))==5
code,raw,_=request(login("admin")[0],"/pages/configuracoes.html")
assert code==200 and 'data-primeway-theme-toggle' in raw
print("Modo noturno em 5 perfis: autenticado, isolado por conta e Admin protegido: OK.")
