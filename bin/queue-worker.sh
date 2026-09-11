#!/usr/bin/env bash
#
# Worker de colas de Laravel (app/Listeners/** con ShouldQueue — hoy, los
# correos de hoja de ruta creada/finalizada).
#
# Pensado para correr en primer plano: systemd o el servicio `queue` de
# compose.production.yml se encargan de reiniciarlo si se cae.
#
# Uso:
#   bin/queue-worker.sh [COLA] [CONEXION]
#
# Variables opcionales:
#   QUEUE_NAME        cola(s) separadas por coma    (default: default)
#   QUEUE_CONNECTION  conexion a consumir           (default: la del .env)
#   QUEUE_TRIES       reintentos por job            (default: 3)
#   QUEUE_BACKOFF     espera antes de reintentar    (default: 60)
#   QUEUE_TIMEOUT     segundos maximos por job      (default: 300)
#   QUEUE_SLEEP       espera cuando no hay jobs     (default: 3)
#   QUEUE_MAX_TIME    vida del worker en segundos   (default: 3600)
#   QUEUE_MEMORY      limite de memoria en MB       (default: 512)
#   PHP_BIN           binario de PHP                (default: php)
#
set -euo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

PHP_BIN="${PHP_BIN:-php}"
QUEUE_NAME="${1:-${QUEUE_NAME:-default}}"
QUEUE_CONNECTION="${2:-${QUEUE_CONNECTION:-}}"
QUEUE_TRIES="${QUEUE_TRIES:-3}"
QUEUE_BACKOFF="${QUEUE_BACKOFF:-60}"
QUEUE_TIMEOUT="${QUEUE_TIMEOUT:-300}"
QUEUE_SLEEP="${QUEUE_SLEEP:-3}"
QUEUE_MAX_TIME="${QUEUE_MAX_TIME:-3600}"
QUEUE_MEMORY="${QUEUE_MEMORY:-512}"

args=(
    queue:work
    --queue="${QUEUE_NAME}"
    --tries="${QUEUE_TRIES}"
    --backoff="${QUEUE_BACKOFF}"
    --timeout="${QUEUE_TIMEOUT}"
    --sleep="${QUEUE_SLEEP}"
    --max-time="${QUEUE_MAX_TIME}"
    --memory="${QUEUE_MEMORY}"
    --no-interaction
)

if [ -n "${QUEUE_CONNECTION}" ]; then
    args=(queue:work "${QUEUE_CONNECTION}" "${args[@]:1}")
fi

echo "[queue] iniciando: ${PHP_BIN} artisan ${args[*]}"

exec "${PHP_BIN}" artisan "${args[@]}"
