#!/usr/bin/env python3
"""Teste HTTP real: Secretaria cria e edita alunos/professores, cadastra responsáveis, sem exclusão.
Ambiente obrigatoriamente descartável: Docker e MySQL, sem dados de produção.
"""
import json
import os
from http.cookiejar import CookieJar
from urllib.error import HTTPError
from urllib.request import Request, build_opener, HTTPCookieProcessor

BASE=os.environ.get("PRIMEWAY_TEST_URL","")
assert BASE in ("http://127.0.0.1:18080","http://localhost:18080"), "Não executar em produção."

def client():
    return build_opener(HTTPCookieProcessor(CookieJar()))

def call(opener,path,method="GET",payload=None,token=None):
    headers={"Accept":"application/json"}
    body=None
    if payload is not None:
        headers["Content-Type"]="application/json"
        body=json.dumps(payload).encode()
    if token is not None:
        headers["X-CSRF-Token"]=token
    req=Request(BASE+path,data=body,headers=headers,method=method)
    try:
        response=opener.open(req,timeout=20)
    except HTTPError as error:
        response=error
    with response:
        status=response.status
        raw=response.read(160000)
    try:
        data=json.loads(raw)
    except Exception:
        data={"raw":raw[:180].decode("utf8","replace")}
    return status,data

def sign_in(email,password):
    opener=client()
    status,data=call(opener,"/api/auth/login.php","POST",{"email":email,"senha":password})
    assert status==200,(email,status,data)
    return opener,data["csrfToken"],data["usuario"]

password="SenhaCI-2026-Ficticia!"
sec,token,who=sign_in("ci-secretaria1@example.invalid",password)
assert who["perfil"]=="secretaria"
teacher,teacher_token,_=sign_in("ci-professor1@example.invalid",password)

student_api="/api/alunos/salvar.php"
prof_api="/api/professores/salvar.php"
guard_api="/api/responsaveis/salvar.php"
new_password="SenhaNova-CI-2026!"
student={
    "name":"Aluno Criado pela Secretaria CI",
    "registration":"CI-SECRETARIA-ALUNO-001",
    "email":"ci-novo-aluno-secretaria@example.invalid",
    "password":new_password,
    "phone":"11988887777",
    "document":"",
    "birthDate":"2012-06-09",
    "status":"ativo",
    "classId":None
}
professor={
    "name":"Professor Criado pela Secretaria CI",
    "email":"ci-novo-prof-secretaria@example.invalid",
    "password":new_password,
    "phone":"11988886666",
    "document":"",
    "birthDate":"1988-10-25",
    "registration":"CI-PROFESSOR-001",
    "admissionDate":"2026-02-02"
}

guardian={
    "name":"Responsável Criado pela Secretaria CI",
    "email":"ci-novo-resp-secretaria@example.invalid",
    "password":new_password,
    "phone":"11988885555",
    "document":"",
    "birthDate":"1985-09-10",
    "status":"ativo",
    "relationship":"Mãe",
    "studentIds":[],
    "authorizedPickup":True,
    "primaryContact":True,
    "financial":True
}

for endpoint,payload in [(student_api,student),(prof_api,professor),(guard_api,guardian)]:
    status,_=call(teacher,endpoint,"POST",payload,teacher_token)
    assert status==403,("professor não pode cadastrar",endpoint,status)
    status,_=call(sec,endpoint,"POST",payload)
    assert status==403,("CSRF obrigatório",endpoint,status)

status,data=call(sec,student_api,"POST",student,token)
assert status==201 and data.get("success"),("cadastrar aluno",status,data)
new_student_id=int(data["student"]["id"])
assert data["student"]["email"]==student["email"]
assert data["student"]["registration"]==student["registration"]

status,data=call(sec,prof_api,"POST",professor,token)
assert status==201 and data.get("success"),("cadastrar professor",status,data)
new_professor_id=int(data["professorId"])

new_student,_,student_user=sign_in(student["email"],new_password)
new_teacher,_,teacher_user=sign_in(professor["email"],new_password)
assert student_user["perfil"]=="aluno"
assert teacher_user["perfil"]=="professor"

status,data=call(sec,"/api/secretaria/alunos/index.php")
assert status==200 and data["success"],(status,data)
assert any(int(item["id"])==new_student_id for item in data["students"])
status,data=call(sec,"/api/secretaria/professores/index.php")
assert status==200 and data["success"],(status,data)
assert any(int(item["id"])==new_professor_id for item in data["teachers"])

# Edita dados com senha em branco: senha anterior deve continuar válida.
updated_student={**student,"id":new_student_id,"name":"Aluno Atualizado pela Secretaria CI",
                 "phone":"11977776666","password":""}
status,data=call(sec,student_api,"POST",updated_student,token)
assert status==200 and data.get("success"),("Secretaria edita aluno",status,data)
updated_teacher={**professor,"id":new_professor_id,"name":"Professor Atualizado pela Secretaria CI",
                 "phone":"11977775555","password":"","status":"ativo"}
status,data=call(sec,prof_api,"POST",updated_teacher,token)
assert status==200 and data.get("success"),("Secretaria edita professor",status,data)

status,data=call(sec,"/api/secretaria/alunos/index.php")
assert status==200 and data.get("success"),(status,data)
assert next(item for item in data["students"] if int(item["id"])==new_student_id)["name"]==updated_student["name"]
status,data=call(sec,"/api/secretaria/professores/index.php")
assert status==200 and data.get("success"),(status,data)
assert next(item for item in data["teachers"] if int(item["id"])==new_professor_id)["name"]==updated_teacher["name"]

# Cadastro de responsável exige vínculo, cria conta e aparece para a Secretaria.
guardian["studentIds"]=[new_student_id]
status,data=call(sec,guard_api,"POST",guardian,token)
assert status==201 and data.get("success"),("Secretaria cadastra responsável",status,data)
new_guardian_id=int(data["guardianId"])
status,data=call(sec,"/api/secretaria/responsaveis/index.php")
assert status==200 and data.get("success"),(status,data)
created=next(item for item in data["guardians"] if int(item["id"])==new_guardian_id)
assert any(int(link["studentId"])==new_student_id and link["active"] for link in created["links"])
_,_,guardian_user=sign_in(guardian["email"],new_password)
assert guardian_user["perfil"]=="responsavel"

# Secretaria pode atualizar o responsável sem trocar a senha ou perder seus vínculos.
updated_guardian={**guardian,"id":new_guardian_id,"name":"Responsável Atualizado pela Secretaria CI",
                  "password":"","phone":"11977774444"}
status,data=call(sec,guard_api,"POST",updated_guardian,token)
assert status==200 and data.get("success"),("Secretaria edita responsável",status,data)
status,data=call(sec,"/api/secretaria/responsaveis/index.php")
assert status==200 and data.get("success")
updated=next(x for x in data["guardians"] if int(x["id"])==new_guardian_id)
assert updated["name"]==updated_guardian["name"]
assert any(int(link["studentId"])==new_student_id and link["active"] for link in updated["links"])
_,_,guardian_user=sign_in(guardian["email"],new_password)
assert guardian_user["perfil"]=="responsavel"

# Edição sem nova senha não deve invalidar as senhas anteriores.
_,_,student_user=sign_in(student["email"],new_password)
_,_,teacher_user=sign_in(professor["email"],new_password)
assert student_user["perfil"]=="aluno" and teacher_user["perfil"]=="professor"


# Secretária compartilha a gestão acadêmica com o Administrador: turma e disciplina.
status,classes_data=call(sec,"/api/secretaria/turmas/index.php")
assert status==200 and classes_data["success"],(status,classes_data)
year=classes_data["schoolYear"]["year"]
class_payload={"name":"Turma Gestão Compartilhada CI","grade":"1º ano",
               "shift":"Manhã","room":"Lab 1","schoolYear":year,
               "capacity":25,"status":"Ativa","teacherId":new_professor_id}
status,class_result=call(sec,"/api/turmas/salvar.php","POST",class_payload,token)
assert status==201 and class_result.get("success"),("criar turma",status,class_result)
class_id=int(class_result["class"]["id"])
status,class_result=call(sec,"/api/turmas/salvar.php","POST",{
    **class_payload,"id":class_id,"name":"Turma Gestão Editada CI","capacity":28
},token)
assert status==200 and class_result.get("success"),("editar turma",status,class_result)
status,classes_data=call(sec,"/api/secretaria/turmas/index.php")
assert status==200 and classes_data.get("success")
assert any(int(x["id"])==class_id and x["name"]=="Turma Gestão Editada CI" for x in classes_data["classes"])

# Matrícula real pela Secretaria na turma que acabou de criar.
status,enroll=call(sec,"/api/secretaria/matriculas/salvar.php","POST",{
    "action":"assign","studentId":new_student_id,"classId":class_id
},token)
assert status in (200,201) and enroll.get("success"),("matricular aluno",status,enroll)
status,classes_data=call(sec,"/api/secretaria/turmas/index.php")
assert any(int(x["id"])==class_id and x["studentCount"]>=1 for x in classes_data["classes"])

subject_api="/api/disciplinas/salvar.php"
link_api="/api/disciplinas/salvar_vinculo.php"
subject_payload={"name":"Tecnologia e Sociedade CI","code":"CI-SHARED-01",
                 "area":"Tecnologia","status":"Ativa"}
status,data=call(sec,subject_api,"POST",subject_payload,token)
assert status==200 and data.get("success"),("criar disciplina",status,data)
subject_id=int(data["id"])
status,data=call(sec,subject_api,"POST",{
    **subject_payload,"id":subject_id,"name":"Tecnologia Aplicada CI"
},token)
assert status==200 and data.get("success"),("editar disciplina",status,data)
link_payload={"disciplinaId":subject_id,"turmaId":class_id,
              "professorId":new_professor_id,"hours":60,"status":"Ativa"}
status,data=call(sec,link_api,"POST",link_payload,token)
assert status==200 and data.get("success"),("vincular disciplina",status,data)
link_id=int(data["id"])
status,data=call(sec,link_api,"POST",{**link_payload,"id":link_id,"hours":80},token)
assert status==200 and data.get("success"),("editar vínculo",status,data)
status,data=call(sec,"/api/disciplinas/index.php")
assert status==200 and data.get("success"),("consultar disciplina",status,data)
saved=next(x for x in data["subjects"] if int(x["id"])==subject_id)
assert saved["name"]=="Tecnologia Aplicada CI"
assert any(int(link["id"])==link_id and int(link["hours"])==80 for link in saved["links"])
status,data=call(sec,"/api/disciplinas/status.php","POST",{
    "target":"vinculo","id":link_id,"status":"Inativa"
},token)
assert status==200 and data.get("success"),("inativar vínculo",status,data)

# Admin também lê e altera os mesmos módulos; professor não pode usá-los.
admin,admin_token,_=sign_in("ci-admin1@example.invalid",password)
for path in ("/api/disciplinas/index.php","/api/turmas/index.php","/api/professores/index.php"):
    status,data=call(admin,path)
    assert status==200 and data.get("success"),("admin consulta",path,status,data)
    status,_=call(teacher,path)
    assert status==403,("professor não administra",path,status)
status,data=call(admin,"/api/turmas/salvar.php","POST",{
    **class_payload,"id":class_id,"name":"Turma Ajustada pelo Admin CI"
},admin_token)
assert status==200 and data.get("success"),("admin edita turma",status,data)
status,_=call(teacher,subject_api,"POST",subject_payload,teacher_token)
assert status==403,("professor não cria disciplina",status)
status,_=call(sec,"/api/turmas/excluir.php","POST",{"id":class_id},token)
assert status==403,("secretaria não exclui turma",status)

status,data=call(sec,student_api,"POST",student,token)
assert status==409,("Matrícula duplicada bloqueada",status,data)
status,data=call(sec,prof_api,"POST",professor,token)
assert status==409,("Professor duplicado bloqueado",status,data)

status,_=call(sec,"/api/alunos/excluir.php","POST",{"id":new_student_id},token)
assert status==403,("Secretaria não pode excluir aluno",status)
status,_=call(sec,"/api/configuracoes/salvar.php","POST",{},token)
assert status in (403,404),("Secretaria não pode alterar configurações",status)

for page in ("secretaria_alunos.html","secretaria_professores.html","secretaria_responsaveis.html","secretaria_turmas.html","secretaria_disciplinas.html"):
    status,_=call(sec,"/pages/"+page)
    assert status==200,(page,status)

print("Gestão escolar: Secretaria e Admin editam pessoas/turmas/disciplinas, vinculam e matriculam; perfis e exclusões protegidos.")