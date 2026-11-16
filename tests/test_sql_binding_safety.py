#!/usr/bin/env python3
"""SQLi: verifica vinculos SQL das rotas dinamicas e sinais diretos de interpolacao.

Análise de código, NÃO prova ausência de SQL injection. A integração HTTP
também exercita caracteres SQL e curingas no filtro de contatos de admin.
"""
from pathlib import Path
import re

root=Path(__file__).resolve().parents[1]
api=root/"private/app/api"
files=list(api.rglob("*.php"))
sql_calls=0
suspects=[]
for path in files:
    s=path.read_text(encoding="utf-8")
    sql_calls+=len(re.findall(r"->(?:prepare|query|exec)\s*\(",s))
    # Injeção direta de superglobal dentro da string SQL ou na chamada.
    if re.search(r"->(?:prepare|query|exec)\s*\(\s*\$_(?:GET|POST|REQUEST)",s):
        suspects.append(str(path.relative_to(root))+": argumento SQL vem do request")
    if re.search(r"(?is)(?:SELECT|UPDATE|DELETE|INSERT)\s[^\x60;]{0,150}\$_(?:GET|POST|REQUEST)\s*\[",s):
        suspects.append(str(path.relative_to(root))+": superglobal interpolada em comando SQL")

assert sql_calls>=200,f"Total inesperado de chamadas SQL inspecionadas: {sql_calls}"
assert not suspects,"\n".join(suspects)
db=(root/"private/app/config/database.php").read_text(encoding="utf-8")
assert "PDO::ATTR_EMULATE_PREPARES=>false" in db

contacts=(api/"chat/contatos.php").read_text(encoding="utf-8")
assert "$pdo->prepare($sql)" in contacts
for token in (
    "WHERE $where",":viewer",":role",":nameSearch",":emailSearch",
    "PDO::PARAM_INT","ORDER BY FIELD(u.perfil",
    "'!%'","'!_'",  # curingas escapados do operador LIKE
):
    assert token in contacts,token
assert "array_map(" in contacts
for path in ("aluno/atividades/index.php","professor/notas/index.php",
             "responsavel/alunos/index.php","chat/status.php"):
    source=(api/path).read_text(encoding="utf-8")
    assert "->prepare(" in source,path
    assert "primewayExigirPerfis(" in source,path

print(f"SQL: {len(files)} arquivos PHP, {sql_calls} chamadas a PDO; "
      "verificação de padrões perigosos e consultas críticas aprovada.")
print("Observação: consultas dinâmicas complexas ainda exigem revisão humana.")
