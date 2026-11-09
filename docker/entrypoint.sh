#!/bin/sh
set -e

# Apache escuta na porta que o Render indica
PORT="${PORT:-10000}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

cd /var/www/html

php artisan storage:link --force || true
php artisan config:cache
php artisan route:cache

# Aplica migrations pendentes (ex.: personal_access_tokens do Sanctum).
# Desative com RUN_MIGRATIONS=false se preferir correr à mão.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

# Painel Super Admin: cria a conta na primeira vez (não altera uma conta existente).
if [ -n "${SUPER_ADMIN_EMAIL}" ] && [ -n "${SUPER_ADMIN_PASSWORD}" ]; then
    php artisan super-admin:create || true
fi

exec apache2-foreground
