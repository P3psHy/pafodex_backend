FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

# IMPORTANT : on désactive les scripts Symfony Flex
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

RUN php bin/console cache:clear --env=prod --no-warmup

EXPOSE 10000

CMD php bin/console doctrine:migrations:migrate --no-interaction \
 && php bin/console SeedDatabaseCommand --no-interaction \
 && php -S 0.0.0.0:${PORT:-10000} -t public
