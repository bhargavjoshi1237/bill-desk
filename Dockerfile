# syntax=docker/dockerfile:1

# ---- Base: PHP + Apache with the extensions Laravel, Filament and dompdf need ----
FROM php:8.4-apache AS base

COPY --from=mlocati/php-extension-installer:2.7 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions intl zip gd bcmath opcache pdo_sqlite pdo_pgsql pdo_mysql \
    && a2enmod rewrite headers \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
RUN sed -i 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# ---- Composer dependencies (cached separately from app code) ----
FROM base AS vendor
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

# ---- Front-end assets (the Filament theme imports CSS from vendor/) ----
FROM node:24-slim AS assets
WORKDIR /app
COPY package.json ./
RUN npm install --ignore-scripts
COPY --from=vendor /var/www/html/vendor ./vendor
COPY vite.config.js ./
COPY resources ./resources
COPY app ./app
COPY public ./public
RUN npm run build

# ---- Runtime image ----
FROM base
ENV PORT=8080 \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

# Runs package:discover and filament:upgrade, which publishes Filament's JS/CSS into public/.
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8080
ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
