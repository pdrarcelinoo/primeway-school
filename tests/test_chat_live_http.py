#!/usr/bin/env python3
"""Atualização do chat em 5 perfis: alterações, pin/unpin/exclusão e isolamento real.
Executar somente no MySQL/Apache descartáveis da CI; nunca no site público.
"""
import json
import os
from http.cookiejar import CookieJar
from urllib.error import HTTPError
from urllib.parse import urlencode
from urllib.request import HTTPCookieProcessor, Request, build_opener

BASE=os.environ.get("PRIMEWAY_TEST_URL","")
assert BASE in ("http://127.0.0.1:18080","http://localhost:18080")
PASSWORD="SenhaCI-2026-Ficticia!"
SLUGS={"admin":"admin1","professor":"professor1","aluno":"aluno1",
       "responsavel":"responsavel1","secretaria":"secretaria1"}
MESSAGE_URLS={"admin":"/api/chat/central.php","professor":"/api/professor/chat/mensagens.php",
              "aluno":"/api/aluno/chat/mensagens.php","responsavel":"/api/responsavel/chat/mensagens.php",
              "secretaria":"/api/secretaria/chat/mensagens.php"}

def request(client,path,method="GET",payload=None,token=None):
    headers={"Accept":"application/json"}
    body=None
    if payload is not None:
        headers["Content-Type"]="application/json"
        body=json.dumps(payload).encode()
    if token:
        headers["X-CSRF-Token"]=token
    try:
        response=client.open(Request(BASE+path,headers=headers,data=body,method=method),timeout=25)
    except HTTPError as error:
        response=error
    with response:
        status=response.status
        raw=response.read(100000)
    try:
        data=json.loads(raw)
    except (ValueError,UnicodeDecodeError):
        data={"raw":raw[:120].decode("utf8","replace")}
    return status,data

def signin(slug):
    client=build_opener(HTTPCookieProcessor(CookieJar()))
    status,data=request(client,"/api/auth/login.php","POST",
                        {"email":f"ci-{slug}@example.invalid","senha":PASSWORD})
    assert status==200 and data["csrfToken"],(slug,status,data)
    return client,data["csrfToken"],int(data["usuario"]["id"])

users={role:signin(slug) for role,slug in SLUGS.items()}
admin,csrf,_=users["admin"]
admin2,_,_=signin("admin2")

def version(client,id):
    status,data=request(client,f"/api/chat/sync.php?conversationId={id}")
    assert status==200 and data["success"],(id,status,data)
    assert isinstance(data["version"],str) and len(data["version"])==64
    assert sorted(data)==["success","version"],"A sincronização não deve vazar mensagens nem anexos."
    return data["version"]

status,_=request(build_opener(),"/api/chat/sync.php?conversationId=1")
assert status==401,("acesso anônimo",status)
status,_=request(admin,"/api/chat/sync.php?conversationId=0")
assert status==422,("ID inválido",status)
status,_=request(admin,"/api/chat/sync.php?conversationId=1","POST",{})
assert status==405,("somente leitura",status)

for role in ("professor","aluno","responsavel","secretaria"):
    participant,participant_csrf,person_id=users[role]
    status,data=request(admin,"/api/chat/iniciar.php","POST",{"contactUserId":person_id},csrf)
    assert status in (200,201) and data["success"],(role,status,data)
    cid=int(data["conversationId"])
    initial=version(admin,cid)
    assert version(participant,cid)==initial
    status,_=request(admin2,f"/api/chat/sync.php?conversationId={cid}")
    assert status==404,("outro administrador não pode ver a versão",role,status)
    outsider=users["professor" if role!="professor" else "aluno"][0]
    status,_=request(outsider,f"/api/chat/sync.php?conversationId={cid}")
    assert status==404,("outro perfil não participa",role,status)

    content=f"CI atualização contínua {role} — mensagem recebida"
    status,sent=request(admin,"/api/chat/central.php","POST",
                        {"conversationId":cid,"action":"send","content":content},csrf)
    assert status==201 and sent["success"],(role,status,sent)
    mid=int(sent["messageId"])
    after_send=version(participant,cid)
    assert after_send!=initial,(role,"novo envio sem atualização")
    assert version(admin,cid)==after_send
    status,messages=request(participant,MESSAGE_URLS[role]+f"?conversationId={cid}")
    assert status==200 and any(m["id"]==mid and m["content"]==content
                               for m in messages["messages"]),(role,status,messages)

    status,pinned=request(admin,"/api/chat/mensagem_acao.php","POST",
                          {"messageId":mid,"action":"pin"},csrf)
    assert status==200 and pinned["success"],(role,status,pinned)
    after_pin=version(participant,cid)
    assert after_pin!=after_send,(role,"fixação não detectada")
    status,unpin=request(admin,"/api/chat/mensagem_acao.php","POST",
                         {"messageId":mid,"action":"unpin"},csrf)
    assert status==200 and unpin["success"]
    after_unpin=version(participant,cid)
    assert after_unpin!=after_pin,(role,"desafixar não detectado")

    status,deleted=request(admin,"/api/chat/mensagem_acao.php","POST",
                           {"messageId":mid,"action":"delete"},csrf)
    assert status==200 and deleted["success"]
    after_delete=version(participant,cid)
    assert after_delete!=after_unpin,(role,"exclusão não detectada")
    status,messages=request(participant,MESSAGE_URLS[role]+f"?conversationId={cid}")
    assert status==200 and all(int(m["id"])!=mid for m in messages["messages"])
print("Chat contínuo: cinco perfis, entrega de alterações e isolamento por participante: OK.")
