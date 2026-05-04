FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader

FROM php:8.3-cli-alpine AS app

WORKDIR /var/www

RUN apk add --no-cache curl

COPY --from=vendor /app/vendor ./vendor
COPY . .

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD curl -fsS http://127.0.0.1:8000/up || exit 1

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
