# eOil ERP — local development runtime (PHP 8.4 + Composer).
#
# Build context is this directory only (see compose.yaml), so the repository,
# `.local/` analysis data and any `.env` never reach the Docker daemon.
# Application code is bind-mounted at /app at runtime.

FROM composer/composer:2-bin AS composer

FROM mlocati/php-extension-installer:2 AS ext-installer

FROM php:8.4-cli-bookworm AS dev

ARG USER_ID=1000
ARG GROUP_ID=1000

COPY --from=ext-installer /usr/bin/install-php-extensions /usr/local/bin/install-php-extensions
COPY --from=composer /composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip git curl \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions intl opcache

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && printf 'expose_php=Off\nmemory_limit=512M\n' > "$PHP_INI_DIR/conf.d/zz-erp.ini"

RUN groupadd --gid "${GROUP_ID}" app \
    && useradd --uid "${USER_ID}" --gid "${GROUP_ID}" --create-home app

ENV LC_ALL=C.UTF-8 \
    COMPOSER_HOME=/home/app/.composer \
    COMPOSER_CACHE_DIR=/app/runtime/cache/composer

WORKDIR /app
USER app

EXPOSE 8080

# PHP built-in server with the Yii3 front controller as router (same mechanism
# as upstream `./yii serve`). Listens on all container interfaces; compose
# publishes it only on host loopback.
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
