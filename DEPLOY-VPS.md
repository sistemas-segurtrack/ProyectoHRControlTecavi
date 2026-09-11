# Despliegue en VPS

VPS Rocky Linux 9.7. Las credenciales (SSH, base de datos, SMTP, Wialon) viven
**solo** en el `.env` del servidor y en el gestor de contraseñas del equipo —
nunca en este repositorio.

Dominio: **`tools.segurtrack.com/hrcontrol`** — subpath sobre un Apache que ya
sirve otro proyecto en ese dominio. Esto exige que la app entienda el prefijo
`/hrcontrol`; se resuelve con la variable `PWA_BASE_PATH` / `VITE_PWA_BASE_PATH`
(ver `.env.example` y `config/pwa.php`) y con Apache **quitando** el prefijo
antes de reenviar a los contenedores (`ProxyPass /hrcontrol http://127.0.0.1:8090/`,
con la barra final). Así, dentro del contenedor Laravel nunca ve `/hrcontrol` —
solo genera URLs con ese prefijo hacia afuera (`APP_URL`/`ASSET_URL`).

## Rutas

- Código: `/opt/proyectos/segurtrack/tecavi/HRControl`
- Datos persistentes: `/var/opt/proyectos/segurtrack/tecavi/HRControl/{mysql,storage,backups}`

## Preparación del host (Rocky Linux 9.7)

```bash
sudo dnf upgrade -y
sudo dnf install -y git curl ca-certificates policycoreutils-python-utils

# Docker CE (no viene en los repos de Rocky)
sudo dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
sudo dnf install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"
```

Cerrar la sesión SSH y volver a entrar para aplicar el grupo `docker`.

Apache ya está instalado en este VPS (sirve `tools.segurtrack.com`). Si hiciera falta:

```bash
sudo dnf install -y httpd mod_ssl certbot python3-certbot-apache
sudo systemctl enable --now httpd
```

### Firewall (firewalld, no ufw)

```bash
sudo systemctl enable --now firewalld
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

El puerto `8090` (donde escucha el contenedor `web`) queda solo en loopback —
no se abre en el firewall.

### Directorios + SELinux

```bash
sudo mkdir -p /opt/proyectos/segurtrack/tecavi
sudo mkdir -p /var/opt/proyectos/segurtrack/tecavi/HRControl/{mysql,storage,backups}
sudo chown -R "$USER":"$USER" /opt/proyectos/segurtrack /var/opt/proyectos/segurtrack

# SELinux (enforcing por defecto en Rocky): permitir que los contenedores
# escriban en los bind mounts. compose.production.yml ya usa los flags :z/:Z,
# pero si algo diera "Permission denied" igual:
sudo semanage fcontext -a -t container_file_t "/var/opt/proyectos/segurtrack/tecavi/HRControl(/.*)?"
sudo restorecon -Rv /var/opt/proyectos/segurtrack/tecavi/HRControl

# Apache -> proxy hacia el contenedor
sudo setsebool -P httpd_can_network_connect 1
```

## Instalación

```bash
git clone https://github.com/sistemas-segurtrack/ProyectoHRControlTecavi.git \
  /opt/proyectos/segurtrack/tecavi/HRControl
cd /opt/proyectos/segurtrack/tecavi/HRControl
cp .env.example .env
```

Editar `.env` con los valores reales de producción (ver el bloque comentado
"Producción (VPS)" al final de `.env.example` como referencia): `APP_URL`,
`ASSET_URL`, `PWA_BASE_PATH=/hrcontrol`, `VITE_PWA_BASE_PATH=/hrcontrol`,
`DB_DATABASE=hrcontrol_db`, `DB_USERNAME=sistemas-segurtrack`, `DB_PASSWORD`,
`DB_ROOT_PASSWORD`, `MAIL_*`, `WIALON_STK_TOKEN`.

```bash
# Contraseñas de la base de datos
openssl rand -base64 24   # -> DB_PASSWORD
openssl rand -base64 24   # -> DB_ROOT_PASSWORD (un valor distinto)
```

`APP_KEY`: **generarla antes de `up -d`**, porque `.env` no es un volumen
montado (solo `storage/` lo es) — si se genera después de que el contenedor
ya sirve tráfico, invalida sesiones y datos cifrados.

```bash
docker compose -f compose.production.yml build
docker run --rm hrcontrol-php php artisan key:generate --show
# pegar el resultado (base64:...) en APP_KEY del .env

docker compose -f compose.production.yml up -d
docker compose -f compose.production.yml exec app php artisan migrate --force
docker compose -f compose.production.yml exec app php artisan storage:link
docker compose -f compose.production.yml ps
curl -I http://127.0.0.1:8090/up
```

Solo se construyen dos imágenes: `hrcontrol-php` (la usan `app`, `queue` y
`scheduler` — el mismo contenedor con distinto comando) y la de `web`. Por eso
`build` va siempre antes de `up -d`.

## Apache — proxy en subpath

`/etc/httpd/conf.d/tools.segurtrack.com.conf` (bloque añadido al vhost existente,
dentro del `<VirtualHost *:443>` de `tools.segurtrack.com`):

```apache
<Location /hrcontrol>
    ProxyPreserveHost On
    RequestHeader set X-Forwarded-Proto "https"
</Location>

ProxyPass        /hrcontrol http://127.0.0.1:8090/ retry=0
ProxyPassReverse /hrcontrol http://127.0.0.1:8090/

# Subidas de la PWA (fotos de documentos, hasta ~300 MB)
<Location /hrcontrol>
    LimitRequestBody 367001600
</Location>
ProxyTimeout 600
```

Confirmar que `mod_proxy`, `mod_proxy_http`, `mod_headers` y `mod_ssl` están
cargados (`httpd -M | grep -E 'proxy|headers|ssl'`) y recargar:

```bash
sudo apachectl configtest
sudo systemctl reload httpd
```

No hace falta certbot nuevo: el certificado de `tools.segurtrack.com` ya cubre
cualquier subpath del mismo dominio.

Probar:

```bash
curl -I https://tools.segurtrack.com/hrcontrol/up
```

## Actualización

```bash
cd /opt/proyectos/segurtrack/tecavi/HRControl
git pull --ff-only origin main
docker compose -f compose.production.yml build
docker compose -f compose.production.yml up -d   # añadir --force-recreate si cambió el .env
docker compose -f compose.production.yml exec app php artisan migrate --force
docker compose -f compose.production.yml logs --tail=100
```

**Un cambio en `.env` no se aplica con `restart`.** Las variables de
`env_file` se fijan al crear el contenedor, así que hay que recrearlos con
`docker compose -f compose.production.yml up -d --force-recreate`.

## Mantenimiento

```bash
docker compose -f compose.production.yml exec app php artisan down --render=errors::503 --retry=60
# ... trabajo ...
docker compose -f compose.production.yml exec app php artisan up
```

## Respaldo

```bash
docker compose -f compose.production.yml exec db \
  mysqldump -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  > /var/opt/proyectos/segurtrack/tecavi/HRControl/backups/hrcontrol_$(date +%Y%m%d).sql
```

Cron diario en el host (fuera de los contenedores) para rotar backups y, si
aplica, empaquetar `storage/app/public/docruta` (fotos de documentos).

## Notas / cosas que ya pasaron en el proyecto hermano (shalom)

- Nunca exponer MySQL ni el puerto `8090` fuera de `127.0.0.1`.
- Las migraciones son manuales — no hay auto-migrate al desplegar.
- El servicio `queue` (`docker compose ... exec queue ...` o
  `logs queue`) es el que efectivamente manda los correos de "hoja de ruta
  creada/finalizada" — sin él, los `Mail::to()` se quedan en la tabla `jobs` y
  nunca salen.
