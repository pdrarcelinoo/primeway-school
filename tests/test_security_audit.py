#!/usr/bin/env python3
"""Auditoria estática de proteções essenciais, sem acessar dados de estudantes."""
from pathlib import Path
root=Path(__file__).resolve().parents[1]
def get(path):return (root/path).read_text(encoding="utf-8")
auth=get("private/app/api/auth/login.php")
limit=get("private/app/config/login_rate_limit.php")
session=get("private/app/config/session.php")
bootstrap=get("private/app/api/_bootstrap.php")
router=get("public/api/auth/pagina.php")
for required in ("primewayLoginRateStatus($email)","primewayLoginRateFailure($email)",
                 "primewayLoginRateClear($email)","primewayStatusLimiteLogin()",
                 "password_verify(","session_regenerate_id("):
    assert required in auth,required
for required in ("flock($stream, LOCK_EX)","primewayStorageBase()",
                 "hash('sha256', $key)","primewayLoginRateIpKey()",
                 "primewayLoginRateAccountKey(string $email)","filter_var($ip, FILTER_VALIDATE_IP)",
                 "is_link($dir)"):
    assert required in limit,required
assert "pe.ativo AS pessoa_ativa" in auth
assert "LEFT JOIN pessoas pe ON pe.id = u.pessoa_id" in auth
assert "(int) $usuario['pessoa_ativa'] === 1" in auth
assert "HTTP_X_FORWARDED_FOR" not in limit
for required in ("session.use_strict_mode","session.cookie_httponly","samesite",
                 "hash_equals(","random_bytes(32)"):
    assert required in session,required
for required in ("primewayUsuarioAtualSessao()","primewayExigirPerfis(",
                 "primewayExigirCsrf()","u.ativo","pessoa_ativa"):
    assert required in bootstrap,required
for required in ("if ($usuario === null)","if ($perfilAtual !== $perfilNecessario)",
                 "Cache-Control: no-store, private"):
    assert required in router,required
assert "primewayChatExigirDisponivel" in get("private/app/api/chat/upload.php")
assert "INNER JOIN conversa_participantes" in get("private/app/api/chat/arquivo.php")
assert "session_write_close()" in get("private/app/api/chat/arquivo.php")
assert "primewayIdPositivo" in get("private/app/api/chat/arquivo.php")
print("Segurança: login, sessões, rotas, anexos e proteção de perfis auditados.")
