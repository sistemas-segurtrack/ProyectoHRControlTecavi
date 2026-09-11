#!/usr/bin/env bash
#
# Scheduler de Laravel (tareas declaradas en routes/console.php).
#
# Hoy ejecuta:
#   - wialon:sync --solo=unidades   cada minuto (placas + contador de kilometraje)
#   - wialon:sync                   cada hora (conductores, carretas, geocercas)
#
# `schedule:work` se queda en primer plano y dispara `schedule:run` cada
# minuto, así que no hace falta cron dentro de contenedores. En un servidor
# tradicional se puede usar cron en vez de este script:
#   * * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
#
set -euo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

PHP_BIN="${PHP_BIN:-php}"

echo "[schedule] iniciando: ${PHP_BIN} artisan schedule:work"

exec "${PHP_BIN}" artisan schedule:work --no-interaction
