#!/usr/bin/env python3
"""HTTP de integração contra Docker + MySQL DESCARTÁVEIS de CI.

Usa somente contas ci-*@example.invalid; não aceita URL externa ou host de produção.
Verifica login, páginas, perfis, autorizações de objetos, CSRF e SQL parametrizado.
"""
from __future__ import annotations
import json, os
from http.cookiejar import CookieJar
from urllib.error import HTTPError
from urllib.parse import urlencode
from urllib.request import build_opener, HTTPCookieProcessor, HTTPRedirectHandler, Request

BASE = os.environ.get("PRIMEWAY_TEST_URL", "")
assert BASE in ("http://127.0.0.1:18080","http://localhost:18080"), \
    "Os testes NÃO podem executar contra um servidor remoto."
PASSWORD = "SenhaCI-2026-Ficticia!"
ROLES = ("admin","professor","aluno","responsavel","secretaria")
USERS = {
    "admin": "admin1",
    "professor": "professor1",
    "aluno": "aluno1",
    "responsavel": "responsavel1",
    "secretaria": "secretaria1",
}
PAGES = {
    "admin": "dashboard",
    "professor": "professor",
    "aluno": "aluno_portal",
    "responsavel": "responsavel",
    "secretaria": "secretaria",
}
ROLE_APIS = {
    "admin": "/api/chat/contatos.php",
    "professor": "/api/professor/chat/index.php",
    "aluno": "/api/aluno/chat/index.php",
    "responsavel": "/api/responsavel/chat/index.php",
    "secretaria": "/api/secretaria/chat/index.php",
}
class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl):
        return None
def client():
    return build_opener(HTTPCookieProcessor(CookieJar()), NoRedirect())
def request(opener,route,method="GET",payload=None,headers=None):
    request_data=None
    request_headers={"Accept":"application/json"}
    if payload is not None:
        request_data=json.dumps(payload).encode()
        request_headers["Content-Type"]="application/json"
    if headers:
        request_headers.update(headers)
    req=Request(BASE+route,data=request_data,method=method,headers=request_headers)
    try:
        response=opener.open(req,timeout=12)
    except HTTPError as error:
        response=error
    with response:
        body=response.read(100_000)
        code=response.status
        response_headers=response.headers
    try:
        obj=json.loads(body)
    except (ValueError,UnicodeError):
        obj=None
    return code,obj,response_headers
def check_status(client,endpoint,want,**args):
    status,data,_=request(client,endpoint,**args)
    assert status==want, f"{endpoint}: esperado HTTP {want}, recebido {status}; {data}"
    return data
def sign_in(slug):
    opener=client()
    result=check_status(
        opener,"/api/auth/login.php",200,method="POST",
        payload={"email":f"ci-{slug}@example.invalid","senha":PASSWORD}
    )
    token=result["csrfToken"]
    assert len(token)==64 and result["usuario"]["perfil"] in ROLES
    return opener,token,result["usuario"]

anonymous=client()
status,_,headers=request(anonymous,"/pages/dashboard.html")
assert status==302 and "login.html" in headers.get("Location","")
check_status(anonymous,"/api/chat/contatos.php",401)
check_status(anonymous,"/api/chat/entregas.php",405)
check_status(anonymous,"/api/secretaria/chat/suspensao.php",405)
check_status(anonymous,"/api/chat/iniciar.php",405)

accounts={}
for role,slug in USERS.items():
    opener,token,user=sign_in(slug)
    assert user["perfil"]==role,(role,user)
    accounts[role]=(opener,token,user)
    session=check_status(opener,"/api/auth/session.php",200)
    assert session["authenticated"] and session["usuario"]["perfil"]==role
    check_status(opener,f"/pages/{PAGES[role]}.html",200)

    for other_role in ROLES:
        if other_role==role:
            continue
        status,_,response_headers=request(opener,f"/pages/{PAGES[other_role]}.html")
        assert status==302, (role,other_role,status)
        assert response_headers.get("Location","").startswith("/pages/")
        check_status(opener,ROLE_APIS[other_role],403)
    check_status(opener,"/api/chat/central.php",200 if role=="admin" else 403) \
        if role!="admin" else None

# Conta inativa não passa pelo login.
check_status(client(),"/api/auth/login.php",401,method="POST",
             payload={"email":"ci-inativo@example.invalid","senha":PASSWORD})

admin,token,user=accounts["admin"]
contacts=check_status(admin,"/api/chat/contatos.php",200)["contacts"]
assert len(contacts)==5,contacts
assert user["id"] not in [x["userId"] for x in contacts]
assert "ci-inativo@example.invalid" not in [x["email"] for x in contacts]
assert set(x["role"] for x in contacts)==set(ROLES)
check_status(admin,"/api/chat/contatos.php?"+urlencode({"role":"estudante"}),422)
for attempt in ("%' OR 1=1 --","_", "ci-%"):
    matches=check_status(admin,"/api/chat/contatos.php?"+urlencode({"search":attempt}),200)
    assert matches["contacts"]==[],("Busca deve tratar metacaracteres como texto",attempt,matches)

# Bloqueio por CSRF e por perfil independe de clicar no botão da interface.
target=next(x for x in contacts if x["role"]=="professor")
check_status(admin,"/api/chat/iniciar.php",403,method="POST",
             payload={"contactUserId":target["userId"]})
check_status(admin,"/api/chat/iniciar.php",422,method="POST",
             payload={"contactUserId":user["id"]},headers={"X-CSRF-Token":token})
for role in ROLES:
    if role=="admin":continue
    op,csrf,_=accounts[role]
    check_status(op,"/api/chat/iniciar.php",403,method="POST",
                 payload={"contactUserId":target["userId"]},headers={"X-CSRF-Token":csrf})

conversations={}
for person in contacts:
    created=check_status(admin,"/api/chat/iniciar.php",201,method="POST",
                         payload={"contactUserId":person["userId"]},
                         headers={"X-CSRF-Token":token})
    assert created["created"] is True
    key=person["role"]
    conversations[key]=int(created["conversationId"])
    reused=check_status(admin,"/api/chat/iniciar.php",200,method="POST",
                        payload={"contactUserId":person["userId"]},
                        headers={"X-CSRF-Token":token})
    assert reused["created"] is False and reused["conversationId"]==created["conversationId"]
    detail=check_status(admin,f"/api/chat/central.php?conversationId={created['conversationId']}",200)
    assert int(detail["conversation"]["id"])==int(created["conversationId"])

# Nem outro administrador pode ler uma conversa de que não participa.
admin2,admin2_token,second=sign_in("admin2")
check_status(admin2,f"/api/chat/central.php?conversationId={conversations['professor']}",404)
check_status(admin2,"/api/chat/central.php",200)
check_status(accounts["aluno"][0],"/api/chat/central.php",403)

# Recibos agora precisam ser POST com CSRF.
check_status(admin,"/api/chat/entregas.php",405)
check_status(admin,"/api/chat/entregas.php",403,method="POST")
check_status(admin,"/api/chat/entregas.php",200,method="POST",
             headers={"X-CSRF-Token":token})

print("HTTP real isolado: sessões dos 5 perfis, 20 cruzamentos de API e páginas,")
print("contatos do administrador, 5 conversas/reuso, conta inativa, SQL LIKE, CSRF")
print("e leitura privada entre administradores: aprovado.")
