FROM php:8.3-cli

# Dépendances système
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    zip

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Dossier de travail
WORKDIR /app

# Copier le projet
COPY . .

# Installer dépendances SANS scripts (important sur Render)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Exposer le port Render
EXPOSE 10000

# Lancer Symfony
CMD php -S 0.0.0.0:${PORT:-10000} -t public
