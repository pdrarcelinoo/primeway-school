#!/usr/bin/env python3
"""Anexos reais: envia, visualiza e baixa nos 5 perfis, em Docker/MySQL isolados."""
from __future__ import annotations
import base64
import io
import json
import os
import uuid
import wave
import zipfile
from http.cookiejar import CookieJar
from urllib.error import HTTPError
from urllib.request import Request, build_opener, HTTPCookieProcessor

BASE=os.environ.get("PRIMEWAY_TEST_URL","")
assert BASE in ("http://127.0.0.1:18080","http://localhost:18080"), "Não executar em servidor público."
PASSWORD="SenhaCI-2026-Ficticia!"
ROLES=("admin","professor","aluno","responsavel","secretaria")
ENDPOINT={"admin":"/api/chat/central.php","professor":"/api/professor/chat/mensagens.php",
          "aluno":"/api/aluno/chat/mensagens.php","responsavel":"/api/responsavel/chat/mensagens.php",
          "secretaria":"/api/secretaria/chat/mensagens.php"}
SLUG={"admin":"admin1","professor":"professor1","aluno":"aluno1",
      "responsavel":"responsavel1","secretaria":"secretaria1"}

def client():
    return build_opener(HTTPCookieProcessor(CookieJar()))

def request(opener,path,method="GET",body=None,headers=None):
    head={"Accept":"application/json",**(headers or {})}
    req=Request(BASE+path,data=body,method=method,headers=head)
    try:
        result=opener.open(req,timeout=25)
    except HTTPError as exc:
        result=exc
    with result:
        return result.status,result.read(24*1024*1024),result.headers

def json_req(opener,path,method="GET",payload=None,headers=None):
    body=None
    if payload is not None:
        body=json.dumps(payload).encode()
        headers={"Content-Type":"application/json",**(headers or {})}
    code,raw,head=request(opener,path,method,body,headers)
    try:
        data=json.loads(raw)
    except Exception:
        data=None
    return code,data,head

def signin(slug):
    opener=client()
    status,data,_=json_req(opener,"/api/auth/login.php","POST",
                           {"email":"ci-"+slug+"@example.invalid","senha":PASSWORD})
    assert status==200 and data["csrfToken"],(slug,status,data)
    return opener,data["csrfToken"]

def multipart(conversation_id,name,payload,mime,content=""):
    boundary="primeway-"+uuid.uuid4().hex
    form=[]
    for key,value in (("conversationId",str(conversation_id)),("content",content)):
        form.extend([("--"+boundary+"\r\n").encode(),
                     ('Content-Disposition: form-data; name="'+key+'"\r\n\r\n').encode(),
                     value.encode(),b"\r\n"])
    form.extend([("--"+boundary+"\r\n").encode(),
                 ('Content-Disposition: form-data; name="file"; filename="'+name+'"\r\n').encode(),
                 ("Content-Type: "+mime+"\r\n\r\n").encode(),payload,b"\r\n",
                 ("--"+boundary+"--\r\n").encode()])
    return b"".join(form),"multipart/form-data; boundary="+boundary

def upload(opener,token,conv,name,payload,mime,csrf=True):
    body,content_type=multipart(conv,name,payload,mime)
    headers={"Content-Type":content_type}
    if csrf:
        headers["X-CSRF-Token"]=token
    return json_req_raw(opener,"/api/chat/upload.php",body,headers)

def json_req_raw(opener,path,body,headers):
    status,raw,response_headers=request(opener,path,"POST",body,headers)
    try:
        data=json.loads(raw)
    except Exception:
        data={"raw":raw[:500].decode("utf-8","replace")}
    return status,data,response_headers

def messages(opener,role,conversation_id):
    path=ENDPOINT[role]+"?conversationId="+str(conversation_id)
    code,data,_=json_req(opener,path)
    assert code==200 and data["success"],(role,code,data)
    return data["messages"]

def path_of(relative):
    assert relative.startswith("../api/chat/arquivo.php?"),relative
    return relative[2:]

def received_attachment(opener,role,conv,message_id):
    matches=[msg for msg in messages(opener,role,conv) if int(msg["id"])==message_id]
    assert len(matches)==1,(role,message_id)
    items=matches[0].get("attachments",[])
    assert len(items)==1,(role,items)
    att=items[0]
    assert att["name"] and att["url"] and att["downloadUrl"]
    return att

png=base64.b64decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO/+l9UAAAAASUVORK5CYII=")
pdf=b"%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n"
csv=b"aluno,nota\nTeste,9\n"
info=b'{"escola":"PrimeWay","ok":true}\n'
with io.BytesIO() as store:
    with zipfile.ZipFile(store,"w",zipfile.ZIP_DEFLATED) as z:
        z.writestr("[Content_Types].xml",'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>')
        z.writestr("word/document.xml","<w:document/>")
    docx=store.getvalue()
audio_stream=io.BytesIO()
with wave.open(audio_stream,"wb") as wav:
    wav.setnchannels(1)
    wav.setsampwidth(2)
    wav.setframerate(8000)
    wav.writeframes(b"\0\0"*8000)
audio=audio_stream.getvalue()

users={role:signin(slug) for role,slug in SLUG.items()}
admin,admin_token=users["admin"]
admin2,admin2_token=signin("admin2")
code,contacts,_=json_req(admin,"/api/chat/contatos.php")
assert code==200 and contacts["success"],contacts
contact_by_role={item["role"]:item for item in contacts["contacts"]}
convs={}
for role in ROLES[1:]:
    contact=contact_by_role[role]
    code,created,_=json_req(admin,"/api/chat/iniciar.php","POST",
                            {"contactUserId":contact["userId"]},
                            {"X-CSRF-Token":admin_token})
    assert code in (200,201) and created["success"],(role,code,created)
    convs[role]=int(created["conversationId"])

fixtures={
    "admin":("resumo.txt",b"PrimeWay teste de anexo.\n","text/plain","text"),
    "professor":("atividade.docx",docx,"application/vnd.openxmlformats-officedocument.wordprocessingml.document","document"),
    "aluno":("foto.png",png,"image/png","image"),
    "responsavel":("registro.csv",csv,"text/csv","text"),
    "secretaria":("informacoes.json",info,"application/json","text"),
}
for role,(name,payload,mime,kind) in fixtures.items():
    sender,token=users[role]
    conv=convs["professor" if role=="admin" else role]
    code,data,_=upload(sender,token,conv,name,payload,mime)
    assert code==201 and data.get("success"),(role,name,code,data)
    msg=int(data["messageId"])
    att_sender=received_attachment(sender,role,conv,msg)
    receiver_role="professor" if role=="admin" else "admin"
    receiver=users[receiver_role][0]
    att_receiver=received_attachment(receiver,receiver_role,conv,msg)
    assert att_sender["id"]==att_receiver["id"]
    assert att_sender["kind"]==att_receiver["kind"]==kind,(role,att_sender)
    for actor in (sender,receiver):
        code,body,headers=request(actor,path_of(att_sender["downloadUrl"]))
        assert code==200 and body==payload,(role,name,code,len(body),len(payload))
        assert headers.get("Content-Disposition","").startswith("attachment;"),headers
    if kind in ("text","image"):
        code,body,headers=request(receiver,path_of(att_sender["url"]))
        assert code==200 and body==payload,(role,name,code)
        assert headers.get("Content-Disposition","").startswith("inline;")
    code,_,_=request(admin2,path_of(att_sender["url"]))
    assert code==404,("outro administrador não pode abrir anexo",role,code)

# Também cobre o documento PDF e o áudio gravado no navegador.
for name,payload,mime,expected in [
    ("apresentacao.pdf",pdf,"application/pdf","pdf"),
    ("audio.wav",audio,"audio/wav","audio"),
]:
    code,data,_=upload(admin,admin_token,convs["aluno"],name,payload,mime)
    assert code==201 and data.get("success"),(name,code,data)
    att=received_attachment(users["aluno"][0],"aluno",convs["aluno"],int(data["messageId"]))
    assert att["kind"]==expected,(name,att)
    code,body,headers=request(users["aluno"][0],path_of(att["url"]),headers={"Range":"bytes=0-15"})
    assert code==206 and body==payload[:16],(name,code,len(body))
    code,body,_=request(admin,path_of(att["downloadUrl"]))
    assert code==200 and body==payload

# CSRF e formatos potencialmente executáveis continuam bloqueados.
code,data,_=upload(admin,admin_token,convs["professor"],"bloqueado.html",b"<script>alert(1)</script>","text/html")
assert code==422,(code,data)
code,data,_=upload(admin,admin_token,convs["professor"],"falha.txt",b"teste","text/plain",csrf=False)
assert code==403,(code,data)
print("Anexos: uploads reais nos 5 perfis, Office, PDF, texto, imagem, áudio, download dos dois lados, Range, CSRF e IDOR aprovados.")
