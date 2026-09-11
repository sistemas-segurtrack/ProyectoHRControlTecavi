# syntax=docker/dockerfile:1
#
# Tres etapas, dos imágenes finales:
#
#   builder → instala dependencias y compila los assets. Se descarta.
#   app     → php-fpm con el código y las extensiones. Es la que corre.
#   web     → nginx con los archivos públicos ya compilados.
#
# `builder` lleva PHP y Node a la vez porque el plugin de Wayfinder ejecuta
# `php artisan wayfinder:generate` durante `vp build`: sin PHP y sin vendor/
# instalado, la compilación de assets falla.
#
# PHP 8.4, la misma versión que usa DDEV en desarrollo y la CI.

# ---------------------------------------------------------------------------
# Etapa 1 · builder
# ---------------------------------------------------------------------------
FROM php:8.4-cli-bookworm AS builder

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    NODE_MAJOR=24

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        gnupg \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libsqlite3-dev \
        libzip-dev \
        unzip; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" gd intl pdo_mysql pdo_sqlite zip; \
    rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    install -d -m 0755 /etc/apt/keyrings; \
    curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
        | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg; \
    echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODE_MAJOR}.x nodistro main" \
        > /etc/apt/sources.list.d/nodesource.list; \
    apt-get update; \
    apt-get install -y --no-install-recommends nodejs; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Los manifiestos van antes que el código: mientras no cambien, Docker reutiliza
# estas dos capas y se salta la descarga entera de dependencias.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-progress

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .

# El build hornea estos valores dentro del JavaScript: VITE_APP_NAME es
# cosmético; VITE_PWA_BASE_PATH es el prefijo con el que se sirve la PWA
# (Conductor) detrás del proxy (vacío si va en la raíz o en un subdominio,
# "/hrcontrol" para el subpath tools.segurtrack.com/hrcontrol). APP_URL es
# necesario por otra razón: `vp build` corre `php artisan wayfinder:generate`,
# que arma cada helper de ruta (`login()`, `dashboard()`, etc. en
# `resources/js/routes/`) llamando a `route()` del lado del servidor, y eso
# produce una URL ABSOLUTA usando el `APP_URL` que vea Laravel en ese momento.
# Sin este ARG, `config('app.url')` cae al default de Laravel
# (`http://localhost`) y todos los <Link> de la app (Log in, Register,
# Dashboard, ...) navegaban a `http://localhost/...` en vez de a
# `https://tools.segurtrack.com/hrcontrol/...` — no es un placeholder inocuo,
# queda grabado dentro del bundle.
ARG VITE_APP_NAME="Proyecto Tecavi - HRControl"
ARG VITE_PWA_BASE_PATH=""
ARG APP_URL="http://localhost"

# `vp build` arranca Laravel para generar las rutas tipadas de Wayfinder, así
# que necesita un .env con clave válida. Es de usar y tirar: apunta a SQLite en
# memoria para no depender de MySQL durante la construcción, y se borra al
# terminar para que no acabe dentro de ninguna capa de la imagen final.
RUN set -eux; \
    printf '%s\n' \
        'APP_ENV=production' \
        'APP_DEBUG=false' \
        'APP_KEY=' \
        'DB_CONNECTION=sqlite' \
        'DB_DATABASE=:memory:' \
        'CACHE_STORE=array' \
        'SESSION_DRIVER=array' \
        'QUEUE_CONNECTION=sync' \
        > .env; \
    printf 'APP_URL="%s"\nVITE_APP_NAME="%s"\nVITE_PWA_BASE_PATH="%s"\n' \
        "${APP_URL}" \
        "${VITE_APP_NAME}" \
        "${VITE_PWA_BASE_PATH}" \
        >> .env; \
    php artisan key:generate --force --no-interaction; \
    composer dump-autoload --optimize --no-dev; \
    npm run build; \
    rm -f .env


# ---------------------------------------------------------------------------
# Etapa 2 · app (php-fpm)
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-bookworm AS app

ENV TZ=America/Lima

# Primero las bibliotecas de runtime, después las de desarrollo. Al purgar las
# segundas, apt respeta las primeras porque están marcadas como instaladas a
# mano: la imagen se queda con lo justo para ejecutar, sin el compilador.
#
# `pcntl` no es opcional aquí: lo necesita `queue:work` para atender señales y
# `schedule:work` para cerrar limpio con SIGTERM.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        libfreetype6 \
        libicu72 \
        libjpeg62-turbo \
        libpng16-16 \
        libzip4 \
        tzdata; \
    apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j1 gd intl pcntl pdo_mysql zip; \
    apt-get purge -y --auto-remove \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev; \
    rm -rf /var/lib/apt/lists/*; \
    ln -snf "/usr/share/zoneinfo/${TZ}" /etc/localtime; \
    printf '%s\n' "${TZ}" > /etc/timezone

COPY docker/php/php.ini  /usr/local/etc/php/conf.d/zz-hrcontrol.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-hrcontrol.conf

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=builder --chown=www-data:www-data /app/vendor       ./vendor
COPY --from=builder --chown=www-data:www-data /app/public/build ./public/build

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 9000

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]


# ---------------------------------------------------------------------------
# Etapa 3 · web (nginx)
# ---------------------------------------------------------------------------
#
# nginx sirve los estáticos por su cuenta, así que necesita public/ con los
# assets ya compilados. Se copian dentro de la imagen en vez de compartir un
# volumen con `app`: un volumen conserva los assets viejos entre despliegues y
# acabas depurando un caché que no era tal.
FROM nginx:1.27-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=builder /app/public /var/www/html/public

EXPOSE 80
