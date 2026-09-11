#!/bin/sh
#
# Prepara el contenedor y cede el control al proceso principal.
#
# Lo ejecutan los tres contenedores que usan esta imagen: app (php-fpm),
# queue y scheduler.

set -e

cd /var/www/html

# storage/ es un volumen. La primera vez llega vacío y hay que reconstruir el
# árbol de directorios que Laravel da por hecho.
mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Las cachés se rehacen en cada arranque, no durante la construcción: así
# recogen los cambios del .env con un `docker compose up -d`, sin reconstruir
# la imagen entera.
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

if [ ! -e public/storage ]; then
    php artisan storage:link --quiet || true
fi

chown -R www-data:www-data storage bootstrap/cache

# php-fpm necesita seguir siendo root: es el maestro quien crea los workers
# como www-data (ver www.conf). Los demás comandos no necesitan privilegios,
# así que bajan a www-data antes de ejecutarse.
if [ "$1" = "php-fpm" ]; then
    exec "$@"
fi

exec setpriv --reuid=www-data --regid=www-data --init-groups "$@"
