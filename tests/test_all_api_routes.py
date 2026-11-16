#!/usr/bin/env python3
"""Auditoria transversal dos endpoints PHP do PrimeWay.

Não substitui pentest, mas impede que novas rotas fiquem sem sessão, autorização,
método HTTP e token CSRF. A fonte da auditoria é o código executado pelo Apache.
"""
from pathlib import Path
import re

ROOT=Path(__file__).resolve().parents[1]
API=ROOT/"private/app/api"
PRIVATE_AUTH=("primewayExigirPerfis(", "primewayExigirAutenticacao(")
EXCEPTIONS={
    "auth/login.php",    # Credenciais; não existe sessão antes do login
    "auth/session.php",  # Descoberta controlada do estado da sessão
    "auth/logout.php",   # Verificado manualmente com sessão + CSRF
}
php_files=sorted(p for p in API.rglob("*.php") if not p.name.startswith("_"))
problems=[]
checks={"endpoints":0,"authenticated":0,"post":0,"uploads":0,"downloads":0}

for path in php_files:
    relative=path.relative_to(API).as_posix()
    content=path.read_text(encoding="utf-8")
    checks["endpoints"]+=1
    if relative in EXCEPTIONS:
        continue

    # Todas as operações fora do login/sessão exigem identidade do banco.
    if not any(fragment in content for fragment in PRIVATE_AUTH):
        problems.append(f"{relative}: autenticação explícita ausente")
    else:
        checks["authenticated"]+=1

    # O método deve ser explicitamente declarado, nunca aceitar todas as
    # formas HTTP por acidente. O Perfil usa GET/POST/DELETE manualmente.
    if "primewayExigirMetodo(" not in content and "REQUEST_METHOD" not in content:
        problems.append(f"{relative}: método HTTP sem restrição explícita")

    post_only=bool(re.search(r"primewayExigirMetodo\(\s*['\"]POST['\"]",content))
    mixed_post=("REQUEST_METHOD" in content and (
        "=== 'POST'" in content or "=== 'DELETE'" in content or
        "=== 'GET'" in content or "=== 'POST'" in content
    ))
    if post_only or mixed_post:
        checks["post"]+=1
        if "primewayExigirCsrf(" not in content and "primewayCsrfValido(" not in content:
            problems.append(f"{relative}: escrita HTTP sem CSRF")

    if "$_FILES" in content or "move_uploaded_file(" in content:
        checks["uploads"]+=1
        if "primewayExigirCsrf(" not in content:
            problems.append(f"{relative}: arquivo enviado sem token CSRF")
        if not any(x in content for x in ("is_uploaded_file(", "primewayChatValidateUpload(")):
            problems.append(f"{relative}: origem do arquivo não é validada")

    if "readfile(" in content:
        checks["downloads"]+=1
        if "no-store" not in content:
            problems.append(f"{relative}: download de arquivo sem cache privado")
        if not any(x in content for x in PRIVATE_AUTH):
            problems.append(f"{relative}: download sem autenticação")

    # Evitar APIs legadas que executem diretamente código ou comandos do
    # sistema operacional a partir de dados externos.
    unsafe=re.search(r"\b(?:eval|unserialize|shell_exec|passthru|proc_open)\s*\(",content)
    if unsafe:
        problems.append(f"{relative}: função de execução/deserialização perigosa")

assert "primewayCsrfValido()" in (API/"auth/logout.php").read_text(encoding="utf-8")
assert "primewayLoginRateStatus($email)" in (API/"auth/login.php").read_text(encoding="utf-8")
assert checks["endpoints"] >= 130,checks
assert checks["authenticated"] >= 125,checks
assert checks["post"] >= 35,checks
assert checks["uploads"] >= 3,checks
assert checks["downloads"] >= 3,checks

# GET não deve alterar recibos de entrega nem criar arquivo em Profile.
assert "primewayExigirMetodo('POST')" in (API/"chat/entregas.php").read_text(encoding="utf-8")
assert "primewayExigirCsrf()" in (API/"chat/entregas.php").read_text(encoding="utf-8")

print(f"Auditados {checks['endpoints']} endpoints; "
      f"{checks['authenticated']} autenticados, {checks['post']} rotas mutáveis, "
      f"{checks['uploads']} uploads, {checks['downloads']} downloads.")
if problems:
    raise AssertionError("\n".join(problems))
print("Autenticação, métodos HTTP, CSRF, upload e download: verificações aprovadas.")
