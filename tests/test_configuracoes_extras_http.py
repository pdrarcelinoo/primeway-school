#!/usr/bin/env python3
"""Configuração institucional: HTTP real, 5 perfis e MySQL isolado; nunca produção."""
import json,os,html
from http.cookiejar import CookieJar
from urllib.error import HTTPError
from urllib.request import HTTPCookieProcessor,HTTPRedirectHandler,Request,build_opener
BASE=os.environ.get("PRIMEWAY_TEST_URL","")
assert BASE in ("http://127.0.0.1:18080","http://localhost:18080")
SLUG={"admin":"admin1","secretaria":"secretaria1","professor":"professor1",
      "aluno":"aluno1","responsavel":"responsavel1"}
PASS="SenhaCI-2026-Ficticia!"
class NoRedirect(HTTPRedirectHandler):
 def redirect_request(self,req,fp,code,msg,headers,url):return None
def request(opener,path,method="GET",payload=None,csrf=None):
 headers={"Accept":"application/json"}
 body=None
 if payload is not None:
  headers["Content-Type"]="application/json";body=json.dumps(payload).encode()
 if csrf:headers["X-CSRF-Token"]=csrf
 try:res=opener.open(Request(BASE+path,headers=headers,data=body,method=method),timeout=25)
 except HTTPError as err:res=err
 with res:return res.status,res.read(250000).decode("utf8","replace"),res.headers
def sign(role):
 client=build_opener(HTTPCookieProcessor(CookieJar()),NoRedirect())
 code,raw,_=request(client,"/api/auth/login.php","POST",{
  "email":"ci-"+SLUG[role]+"@example.invalid","senha":PASS
 })
 assert code==200,(role,code,raw[:200])
 value=json.loads(raw)
 return client,value["csrfToken"],value["usuario"]["id"]
users={role:sign(role) for role in SLUG}
public=build_opener(NoRedirect())
api="/api/configuracoes/portal.php"
save="/api/configuracoes/portal_salvar.php"
code,raw,_=request(public,api)
assert code==401,("consulta anônima",code)
code,raw,headers=request(public,"/pages/privacidade.html")
assert code==302 and "login.html" in headers.get("Location",""),("privacidade privada",code)
for role,(client,_,uid) in users.items():
 code,raw,_=request(client,api)
 data=json.loads(raw)
 assert code==200 and data["success"],(role,code,data)
 assert "bannerEnabled" in data["settings"]
 assert "privacyNotice" in data["settings"]
 assert ("seoTitle" in data["settings"])==(role=="admin"),role
 code,page,_=request(client,"/pages/privacidade.html")
 assert code==200 and 'data-primeway-theme-account="'+str(uid)+'"' in page,(role,code)
 assert "configuracoes_extras.js?v=20261110-settings2" in page
 assert "portal_global.js?v=20261110-portal1" in page
 assert "acessibilidade.js?v=20261110-access1" in page
 if role not in ("admin",):
  code,raw,_=request(client,save,"POST",{},users[role][1])
  assert code==403,(role,"não pode editar configurações",code)
client,csrf,_=users["admin"]
payload={
 "bannerEnabled":True,
 "bannerText":"Aulas suspensas nesta sexta-feira - teste CI",
 "privacyEmail":"privacidade-teste@escola.invalid",
 "privacyNotice":"A escola orienta responsáveis sobre acesso e correção de dados.",
 "seoTitle":"PrimeWay Escola de Teste CI",
 "seoDescription":"Instituição educacional fictícia de testes com calendário escolar."
}
code,raw,_=request(client,save,"POST",payload)
assert code==403,("CSRF obrigatório",code,raw)
code,raw,_=request(client,save,"POST",{**payload,"extra":"inválido"},csrf)
assert code==422,("campos imprevistos",code)
code,raw,_=request(client,save,"POST",{**payload,"privacyEmail":"invalido"},csrf)
assert code==422,("email inválido",code)
code,raw,_=request(client,save,"POST",{**payload,"bannerText":""},csrf)
assert code==422,("banner sem texto",code)
code,raw,_=request(client,save,"POST",{**payload,"seoTitle":"x"*71},csrf)
assert code==422,("seo muito longo",code)
code,raw,_=request(client,save,"POST",payload,csrf)
assert code==200 and json.loads(raw)["success"],("admin deve salvar",code,raw)
for role,(sess,_,_) in users.items():
 code,raw,_=request(sess,api)
 data=json.loads(raw)
 assert code==200 and data["success"]
 s=data["settings"]
 assert s["bannerEnabled"] is True and s["bannerText"]==payload["bannerText"],role
 assert s["privacyEmail"]==payload["privacyEmail"],role
 assert ("seoTitle" in s)==(role=="admin"),role
code,site,_=request(public,"/escola.html")
assert code==200,("página pública SEO",code)
assert "<title>"+payload["seoTitle"]+"</title>" in site
assert html.escape(payload["seoDescription"],quote=True) in site
assert '<meta property="og:title"' in site
# SEO não pode alterar páginas privadas, que continuam não indexáveis.
code,protected,headers=request(client,"/pages/dashboard.html")
assert code==200 and "noindex" in headers.get("X-Robots-Tag","")
code,raw,_=request(client,save,"POST",{**payload,"bannerEnabled":False},csrf)
assert code==200 and json.loads(raw)["success"]
code,raw,_=request(users["aluno"][0],api)
assert json.loads(raw)["settings"]["bannerEnabled"] is False
print("Configurações: 5 perfis, CSRF, banner, auditoria, dados privados e SEO público HTTP OK.")
