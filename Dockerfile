# Image untuk deploy ke Vercel (Container Runtime). Dibangun oleh Vercel saat `vercel deploy`;
# tidak perlu Docker di laptop. Versi lokal tetap memakai `php -S` seperti biasa.
# Dasar: panduan resmi Vercel "Deploy PHP on Vercel with Docker" (FrankenPHP + Caddy).

FROM dunglas/frankenphp:1-php8.4-alpine AS base
WORKDIR /app
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
# Batas unggah foto menu (aplikasi membatasi 3 MB; batas body Vercel 4,5 MB)
COPY docker/php-kokoro.ini "$PHP_INI_DIR/conf.d/zz-kokoro.ini"

FROM base AS dependencies
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache unzip
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader --no-scripts

FROM base AS runtime
COPY --from=dependencies /app/vendor ./vendor
COPY docker/Caddyfile /etc/caddy/Caddyfile
# Kode aplikasi (tanpa .env: konfigurasi rahasia diisi lewat Environment Variables di Vercel)
COPY app ./app
COPY config ./config
COPY views ./views
COPY public ./public
COPY scripts ./scripts
ENV PORT=80
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
