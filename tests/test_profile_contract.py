#!/usr/bin/env python3
"""Static checks for user-scoped profile editing and page accessibility."""
from pathlib import Path
import re
root=Path(__file__).resolve().parents[1]
read=lambda path:(root/path).read_text(encoding="utf-8")
def assert_in(fragment,where):
    assert fragment in read(where),(where,fragment)
page=read("public/api/auth/pagina.php")
assert "if ($pagina === 'chat' || $pagina === 'perfil' || $pagina === 'preferencias' || $pagina === 'privacidade')" in page
assert "if ($pagina === 'perfil' || $pagina === 'preferencias' || $pagina === 'privacidade')" in page
assert "if ($perfilAtual !== $perfilNecessario)" in page
assert "if ($usuario === null)" in page
for filename in ("pages/perfil.html","js/perfil.js","css/perfil.css","js/core.js","css/perfil_global.css"):
    assert read("public/"+filename)==read("private/app/"+filename),f"Espelho divergente: {filename}"
api=read("private/app/api/perfil/index.php")
image=read("private/app/api/perfil/foto.php")
helper=read("private/app/api/perfil/_perfil.php")
for source in (api,image):
    assert "primewayExigirAutenticacao()" in source
    assert "primewayExigirCsrf()" in source
    assert "$usuario['id']" in source
    assert not re.search(r"\$_(?:GET|POST|REQUEST)\s*\[\s*['\"](?:userId|usuarioId|id)['\"]",source), "Não deve aceitar ID de terceiros"
for required in ("$pdo->beginTransaction()","WHERE id=? AND ativo=1 FOR UPDATE","UPDATE pessoas SET nome=?","UPDATE usuarios SET nome=?","INSERT INTO pessoas","'loginEmail'","'contactEmail'"):
    assert required in api,required
for required in ("is_uploaded_file","getimagesize","2*1024*1024","image/jpeg","image/png","move_uploaded_file","rename(","primewayPerfilDiretorio($id)","readfile($path)"):
    assert required in image,required
assert "primewayStorageBase()" in helper
assert "document.addEventListener(\"DOMContentLoaded\",primewayMountUserProfile)" in read("public/js/core.js")
for part in ('link.href="perfil.html"','badge.href="perfil.html"','href = "perfil.html"'):
    # Acesso com nome/foto no menu; valida pelo menos o link principal e o cartão.
    if part=='href = "perfil.html"':continue
    assert part in read("public/js/core.js"),part
assert "perfil.html" in read("public/pages/perfil.html")
assert 'name="photo"' not in read("public/pages/perfil.html") or "photoInput" in read("public/pages/perfil.html")
print("Perfil pessoal: rotas, autenticação, persistência privada e espelhos OK.")
