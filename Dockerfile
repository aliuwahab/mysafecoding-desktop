# MySafeCoding: one image for the CLI and the hosted web scanner.
#
#   CLI:  docker run --rm -v "$PWD:/src" ghcr.io/aliuwahab/mysafecoding scan /src
#   Web:  docker run --rm -p 8080:8080 ghcr.io/aliuwahab/mysafecoding web

# ── Frontend assets ─────────────────────────────────────────────────────────
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN npm ci
COPY resources ./resources
RUN npm run build

# ── Runtime ─────────────────────────────────────────────────────────────────
FROM php:8.4-cli-bookworm

ARG TARGETARCH
ARG TRIVY_VERSION=0.69.3
ARG GITLEAKS_VERSION=8.30.0
ARG SEMGREP_VERSION=1.154.0

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl git unzip libzip-dev libsqlite3-dev python3 python3-venv \
    && docker-php-ext-install zip pcntl \
    && rm -rf /var/lib/apt/lists/* \
    # Scanned folders are mounted from the host and owned by another uid.
    && git config --system --add safe.directory '*'

# Scanner toolchain, pinned for reproducible results.
RUN curl -sfL https://raw.githubusercontent.com/aquasecurity/trivy/main/contrib/install.sh | sh -s -- -b /usr/local/bin "v${TRIVY_VERSION}" \
    && GL_ARCH=$([ "$TARGETARCH" = "arm64" ] && echo arm64 || echo x64) \
    && curl -sSfL "https://github.com/gitleaks/gitleaks/releases/download/v${GITLEAKS_VERSION}/gitleaks_${GITLEAKS_VERSION}_linux_${GL_ARCH}.tar.gz" | tar -xz -C /usr/local/bin gitleaks \
    && python3 -m venv /opt/semgrep \
    && /opt/semgrep/bin/pip install --no-cache-dir "semgrep==${SEMGREP_VERSION}" \
    && ln -s /opt/semgrep/bin/semgrep /usr/local/bin/semgrep

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/mysafecoding.ini

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && mkdir -p /data/cache /data/workspaces /data/trivy \
    && chmod +x docker/entrypoint.sh bin/mysafecoding

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    MYSAFECODING_MODE=web \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    CACHE_STORE=file \
    CACHE_FILE_PATH=/data/cache \
    SESSION_DRIVER=file \
    QUEUE_CONNECTION=database \
    MYSAFECODING_WEB_WORKSPACE=/data/workspaces \
    TRIVY_CACHE_DIR=/data/trivy \
    SEMGREP_SEND_METRICS=off \
    PHP_CLI_SERVER_WORKERS=8

EXPOSE 8080
ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["web"]
