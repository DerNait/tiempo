FROM php:8.3-fpm

ARG WWWGROUP=1000
ARG WWWUSER=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl unzip libcurl4-openssl-dev libonig-dev libzip-dev libsqlite3-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_sqlite bcmath curl mbstring zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN groupadd --force -g ${WWWGROUP} tiempo \
    && useradd -ms /bin/bash --no-user-group -g ${WWWGROUP} -u ${WWWUSER} tiempo

WORKDIR /var/www
COPY composer.json composer.lock /var/www/
RUN composer install --no-interaction --prefer-dist --no-scripts
COPY --chown=tiempo:tiempo . /var/www
RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/{cache,sessions,views} bootstrap/cache \
    && chown -R tiempo:tiempo storage bootstrap/cache

USER tiempo
EXPOSE 9000
CMD ["php-fpm"]
