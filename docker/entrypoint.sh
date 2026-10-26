#!/usr/bin/env bash
set -euo pipefail
cd /app

case "${1:-web}" in
    scan)
        # CLI mode: no web server, no database.
        shift
        exec php artisan mysafecoding:scan "$@"
        ;;

    web)
        # Hosted mode: web server + scan workers + workspace pruning in one container.
        if [ -z "${APP_KEY:-}" ]; then
            # Sessions only hold scan ids, so a per-boot key is fine.
            export APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
        fi
        touch "${DB_DATABASE:-/data/database.sqlite}"
        php artisan migrate --force --no-interaction >/dev/null
        php artisan config:cache >/dev/null
        php artisan view:cache >/dev/null

        for i in $(seq 1 "${MYSAFECODING_WORKERS:-2}"); do
            php artisan queue:work --timeout=1800 --tries=1 --sleep=1 &
        done
        php artisan schedule:work >/dev/null &

        exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}" --no-reload
        ;;

    *)
        exec "$@"
        ;;
esac
