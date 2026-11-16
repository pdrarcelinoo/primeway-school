#!/bin/sh
set -eu

case "$PORT" in
    ''|*[!0-9]*) echo "PORT precisa ser numérica." >&2; exit 1 ;;
esac
if [ "$PORT" -lt 1 ] || [ "$PORT" -gt 65535 ]; then
    echo "PORT fora do intervalo permitido." >&2
    exit 1
fi

mounted=0
while read -r device mountpoint fstype options rest; do
    if [ "$mountpoint" = "/var/lib/primeway" ]; then
        mounted=1
        break
    fi
done < /proc/self/mounts
if [ "$mounted" -ne 1 ]; then
    echo "Volume privado da aplicação ausente em /var/lib/primeway." >&2
    exit 1
fi



sed -i -E "s/^Listen [0-9]+$/Listen $PORT/" /etc/apache2/ports.conf
sed -i -E "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf

install -d -o www-data -g www-data -m 0750 \
    /var/lib/primeway /var/lib/primeway/chat /var/lib/primeway/atividades
install -d -o www-data -g www-data -m 0700 \
    /var/lib/primeway/sessions /var/lib/primeway/tmp
# A imagem PHP/Apache precisa usar somente o MPM prefork. Remova
# event/worker caso estejam ativados por alguma configuracao de runtime.
rm -f /etc/apache2/mods-enabled/mpm_event.load \
      /etc/apache2/mods-enabled/mpm_event.conf \
      /etc/apache2/mods-enabled/mpm_worker.load \
      /etc/apache2/mods-enabled/mpm_worker.conf
a2enmod mpm_prefork >/dev/null
apachectl -t

umask 077
exec apache2-foreground