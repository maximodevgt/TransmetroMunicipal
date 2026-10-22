# Imagen de producción: FrankenPHP (Caddy + PHP 8.3) sirviendo public/.

FROM dunglas/frankenphp:1-php8.3 AS base

RUN install-php-extensions pdo_mysql opcache zip \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction

# Recursos de la interfaz. Tailwind también lee las vistas de paginación de Laravel (vendor).
FROM node:20-bookworm-slim AS recursos

WORKDIR /app

COPY package.json package-lock.json vite.config.js ./
RUN npm ci --include=dev

COPY resources ./resources
COPY --from=base /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

FROM base

COPY --from=recursos /app/public/build ./public/build

# Las cachés de configuración y rutas se generan al iniciar, con las variables de Railway.
CMD ["sh", "-c", "php artisan optimize && exec frankenphp run --config /app/deploy/Caddyfile"]
