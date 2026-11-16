#!/usr/bin/env python3
"""Evita regressões de cache de dados privados e de limite de Apache."""
from pathlib import Path
root=Path(__file__).resolve().parents[1]
read=lambda p:(root/p).read_text(encoding="utf-8")
conf=read("docker/apache-vhost.conf")
docker=read("Dockerfile")
mpm=read("docker/mpm-prefork.conf")
assert "a2enmod headers rewrite setenvif deflate" in docker
for mime in ["text/html","text/css","application/javascript","application/json"]:
    assert mime in conf,mime
assert "Header always set Content-Security-Policy" in conf
assert "RewriteRule ^/pages/([a-z][a-z0-9_-]*)\\.html$ /api/auth/pagina.php [END]" in conf
assert "Header always set X-Content-Type-Options" in conf
assert "Header always set X-Frame-Options" in conf
for endpoint in ("api/auth","api/perfil","api/chat"):
    assert not any(endpoint in x for x in conf.splitlines() if "max-age=" in x), endpoint
assert conf.count("Header set Cache-Control") == 2
assert "max-age=120, must-revalidate" in conf
assert "max-age=3600, must-revalidate" in conf
assert "MaxRequestWorkers        6" in mpm
assert "ServerLimit              6" in mpm
assert "opcache.enable=1" in read("docker/php.ini")
assert "Cache-Control: no-store" in read("private/app/api/_bootstrap.php") or "'Cache-Control: no-store" in read("private/app/api/_bootstrap.php")
print("Cache privado preservado, compressão e concorrência Apache configurados.")
