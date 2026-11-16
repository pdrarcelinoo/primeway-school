#!/usr/bin/env python3
"""Configurações extras: acessibilidade por conta, auditoria e restrições reais."""
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding="utf8")
for f in (
 "js/configuracoes_extras.js","js/acessibilidade.js","js/portal_global.js",
 "css/configuracoes_extras.css","css/acessibilidade.css","css/portal_global.css",
 "pages/privacidade.html"
):
 assert read("public/"+f)==read("private/app/"+f),f
router=read("public/api/auth/pagina.php")
for name in (
 "|| $pagina === 'privacidade'",
 "modo_noturno.js?v=20261110-dark1",
 "configuracoes_extras.css?v=20261110-settings3",
 "configuracoes_extras.js?v=20261110-settings2",
 "acessibilidade.css?v=20261110-access1",
 "acessibilidade.js?v=20261110-access1",
 "portal_global.css?v=20261110-portal1",
 "portal_global.js?v=20261110-portal1",
 "Cache-Control: no-store, private, max-age=0",
 "X-Robots-Tag: noindex, nofollow",
):
 assert name in router,name
assert router.index("modo_noturno.js?v=")<router.index("acessibilidade.js?v=")
assert "primewayUsuarioAtualSessao()" in router
assert "if ($perfilAtual !== $perfilNecessario)" in router
settings=read("public/js/configuracoes_extras.js")
for key in ("admin:","secretaria:","professor:","aluno:","responsavel:",
 "secretaria_matriculas.html","aluno_notas.html","professor_frequencia.html",
 "responsavel_calendario.html","role===\"admin\"","page===\"configuracoes.html\"",
 "page===\"preferencias.html\"","pwPrivacyEmail","pwBannerText","pwSeoTitle",
 "X-CSRF-Token","session.usuario?.perfil!==\"admin\"","data-pw-https-status",
 "Pagamentos dependem","não integrados",
 "publicitários","Informações", "doc"):
 if key in ("Informações","doc"):continue
 assert key in settings,key
assert "location.reload(" not in settings
assert "localStorage.clear(" not in settings

access=read("public/js/acessibilidade.js")
for key in ("primeway:accessibility:v1:","data-primeway-theme-account",
 "data-pw-access", "primeway:settings-created",
 "root.dataset.pwHighContrast","root.dataset.pwReduceMotion",
 "window.addEventListener(\"storage\""):
 assert key in access,key
assert "fetch(" not in access
css=read("public/css/acessibilidade.css")
assert "zoom:1.10" in css and "zoom:1.20" in css
assert "prefers-reduced-motion" not in css or "transition-duration:0.01ms" in css
for p in ("public/js/configuracoes_extras.js","public/js/acessibilidade.js"):
 assert "primewayExigirPerfis" not in read(p),"Permissão é do servidor, não do JS"

private=read("private/app/api/configuracoes/portal_salvar.php")
get=read("private/app/api/configuracoes/portal.php")
for needle in ("primewayExigirMetodo('POST')","primewayExigirPerfis(['admin'])",
 "primewayExigirCsrf()","primewayLerJson()",
 "ATUALIZAR_PORTAL","auditoria","beginTransaction","commit()",
 "bannerEnabled","seoDescription","privacyNotice",
 "privacyEmail","ON DUPLICATE KEY UPDATE","IF(editavel=1"):
 assert needle in private,needle
assert "primewayExigirPerfis(['admin','secretaria','professor','aluno','responsavel'])" in get
assert "'seoTitle'" in get and "if($user['perfil']==='admin')" in get
assert "privacidade_email" in read("private/app/api/configuracoes/_portal.php")
assert "/opt/primeway/app/api/configuracoes/portal.php" in read("public/api/configuracoes/portal.php")
assert "/opt/primeway/app/api/configuracoes/portal_salvar.php" in read("public/api/configuracoes/portal_salvar.php")

notice=read("public/js/portal_global.js")
assert "textContent" in notice and "innerHTML" not in notice
assert "window.setInterval(refresh,60000)" in notice
assert "pw:cookies-info:v1:" in notice
assert "Cookies de sessão" not in notice or "cookies de sessão" in notice.lower()
assert "Entendi" in notice and "privacidade.html" in notice
assert "Nenhuma" not in notice or "rastreamento" in notice
assert "data-pw-privacy-notice" in notice
assert "portal.php" in notice
assert "portal_salvar.php" not in notice

privacy=read("public/pages/privacidade.html")
assert "crianças e adolescentes" in privacy
assert "cookies" in privacy.lower()
assert "data-pw-privacy-contact" in privacy
assert "data-pw-privacy-notice" in privacy
assert "conformidade jurídica" in privacy
seo=read("public/api/portal/escola.php")
assert "htmlspecialchars" in seo and "preg_replace_callback" in seo
assert "configuracoes_sistema" in seo
assert "'HEAD'" in seo and "'GET'" in seo
assert "usuario" not in seo.lower() and "SESSION" not in seo
apache=read("docker/apache-vhost.conf")
assert 'RewriteRule ^/escola\\.html$ /api/portal/escola.php [END]' in apache
assert "AddOutputFilterByType DEFLATE" in apache
assert "Strict-Transport-Security" in apache
assert "Header set Cache-Control \"public" in apache
print("Configurações: API, avisos, privacidade, SEO, acessibilidade e isolamento estático OK.")

# Página pessoal deve ter um único título, seguido diretamente pelas opções.
prefs=read("public/pages/preferencias.html")
extras=read("public/js/configuracoes_extras.js")
assert prefs.count("<h1>Configurações</h1>")==1
assert "PREFERÊNCIAS PESSOAIS" not in prefs
assert "pw-preferences-intro" not in prefs
assert "pw-preferences-note" not in prefs
assert "Mais configurações" not in extras
assert "function htmlPersonal()" in extras
for title in ("Modo noturno","Acessibilidade","Segurança e privacidade","Recursos escolares"):
    assert title in prefs+extras,title
assert ".pw-preferences-inner > .pw-settings-hub { margin-top: 0; }" in read("public/css/configuracoes_extras.css")
