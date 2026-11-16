FROM php:8.3-apache-bookworm
ENV PORT=8080 \
    PRIMEWAY_APP_ENV=production \
    PRIMEWAY_SESSION_SECURE=1 \
    PRIMEWAY_STORAGE_DIR=/var/lib/primeway
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libonig5 \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring opcache \
    && a2enmod headers rewrite setenvif deflate \
    && apt-get purge -y --auto-remove libonig-dev \
    && rm -rf /var/lib/apt/lists/* \
    && printf '%s\n' 'expose_php=Off' > /usr/local/etc/php/conf.d/zz-hardening.ini
COPY private/app/ /opt/primeway/app/
COPY public/ /var/www/html/
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/mpm-prefork.conf /etc/apache2/mods-available/mpm_prefork.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-primeway.ini
COPY docker/entrypoint.sh /usr/local/bin/primeway-entrypoint
COPY docker/install-empty-database.php /opt/primeway/deploy/install-empty-database.php
RUN chmod 0755 /usr/local/bin/primeway-entrypoint \
    && mkdir -p /var/lib/primeway/chat /var/lib/primeway/atividades /var/lib/primeway/sessions /var/lib/primeway/tmp \
    && chown -R www-data:www-data /var/lib/primeway \
    && apachectl -t
EXPOSE 8080
CMD ["/usr/local/bin/primeway-entrypoint"]