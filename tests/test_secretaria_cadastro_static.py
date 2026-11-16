#!/usr/bin/env python3
"""Interface e segurança das criações e edições acadêmicas da Secretaria."""
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def file(path):
    return (ROOT/path).read_text(encoding="utf-8")
for path in (
    "pages/secretaria_alunos.html","pages/secretaria_professores.html",
    "pages/secretaria_responsaveis.html","pages/secretaria_turmas.html",
    "pages/secretaria_disciplinas.html",
    "js/secretaria_cadastro.js","js/secretaria_alunos.js","js/secretaria_professores.js",
    "js/secretaria_responsaveis.js","js/secretaria_turmas.js",
    "js/secretaria_gestao_turmas.js","js/secretaria_common.js",
    "js/disciplinas.js","css/secretaria_cadastro.css"
):
    assert file("private/app/"+path)==file("public/"+path),path
for page,role in [
    ("secretaria_alunos","aluno"),("secretaria_professores","professor"),
    ("secretaria_responsaveis","responsavel")
]:
    html=file("public/pages/"+page+".html")
    assert 'data-secretaria-cadastro="'+role+'"' in html
    assert "secretaria_cadastro.js?v=20261110-roles1" in html
    assert "secretaria_cadastro.css" in html
    script=file("public/js/"+page+".js")
    assert "primeway:secretaria-cadastro-criado" in script
for page,role in [("secretaria_alunos","aluno"),("secretaria_professores","professor"),("secretaria_responsaveis","responsavel")]:
    code=file("public/js/"+page+".js")
    assert "primeway:secretaria-editar" in code
    assert "Editar cadastro" in code
code=file("public/js/secretaria_cadastro.js")
assert "ensureSecretary()" in code
assert "api.requestJson(endpoint,payload)" in code
assert "studentIds" in code and "secretaria/alunos/index.php" in code
assert "classId" in code and "secretaria/turmas/index.php" in code
assert "payload.id=Number(editing.id)" in code
assert "const wasEdit=Boolean(editing)" in code
assert "form.reset();" in code
assert "new FormData(form)" in code
assert "loadingOptions" in code and "form.reportValidity()" in code
assert "primeway:secretaria-cadastro-criado" in code
for path in ("alunos","professores","responsaveis"):
    src=file(f"private/app/api/{path}/salvar.php")
    assert "'secretaria'" in src and "'admin'" in src
    assert "primewayExigirCsrf()" in src
    assert "password_hash($senha, PASSWORD_DEFAULT)" in src
assert "'ALTERAR_ALUNO'" in file("private/app/api/alunos/salvar.php")
assert "'ATUALIZAR_PROFESSOR'" in file("private/app/api/professores/salvar.php")
assert "'CRIAR_RESPONSAVEL'" in file("private/app/api/responsaveis/salvar.php")
assert "$usuario['perfil'] === 'secretaria' && $id !== null" not in file("private/app/api/responsaveis/salvar.php")
assert "'admin'" in file("private/app/api/alunos/excluir.php")
assert "'admin'" in file("private/app/api/responsaveis/excluir.php")
print("Secretaria: edição de aluno/professor e cadastro de responsável com auditoria e sem exclusão: OK")

# Operações escolares adicionais não devem conceder acesso a exclusões ou a configurações técnicas.
assert "data-guardian-edit-id" in file("public/js/secretaria_responsaveis.js")
assert "primeway:secretaria-editar-turma" in file("public/js/secretaria_turmas.js")
assert "newClassSecretaryButton" in file("public/pages/secretaria_turmas.html")
assert "secretaria_gestao_turmas.js?v=20261110-class1" in file("public/pages/secretaria_turmas.html")
assert "api.requestJson(\"../api/turmas/salvar.php\",payload)" in file("public/js/secretaria_gestao_turmas.js")
assert "secretaria_disciplinas.html" in file("public/js/secretaria_common.js")
assert '["admin","secretaria"]' in file("public/js/disciplinas.js")
assert "secretaria_common.js?v=20261110-academic1" in file("public/pages/secretaria_disciplinas.html")
for folder in ("turmas","disciplinas"):
    source=file(f"private/app/api/{folder}/salvar.php")
    assert "secretaria" in source and "admin" in source
assert "secretaria" not in file("private/app/api/turmas/excluir.php").split("primewayExigirPerfis",1)[1].split("]",1)[0]
