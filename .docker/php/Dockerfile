FROM dunglas/frankenphp:php8.5-alpine AS base

RUN apk add --no-cache \
    icu-dev \
    oniguruma-dev \
    linux-headers

RUN docker-php-ext-install \
    intl \
    mbstring

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY .docker/caddy/Caddyfile /etc/caddy/Caddyfile

WORKDIR /var/www

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

FROM base AS development

RUN apk add --no-cache sqlite

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .build-deps

COPY .docker/php/config/dev.ini /usr/local/etc/php/conf.d/zz-dev.ini

ARG UID=1000
ARG GID=1000
RUN deluser --remove-home www-data 2>/dev/null || true; \
    delgroup www-data 2>/dev/null || true; \
    addgroup -g ${GID} -S www-data \
    && adduser -u ${UID} -S -G www-data -h /home/www-data www-data \
    && chown -R www-data:www-data /var/www /data /config

USER www-data

FROM base AS production

COPY .docker/php/config/prod.ini /usr/local/etc/php/conf.d/zz-prod.ini

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts

COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && chown -R www-data:www-data /var/www

USER www-data
