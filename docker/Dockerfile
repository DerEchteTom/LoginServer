FROM php:8.2-fpm

# Systempakete + LDAP + ZIP + SQLite-Erweiterung
RUN apt-get update && apt-get install -y \
    unzip zip curl git libzip-dev libldap2-dev libsasl2-dev libkrb5-dev \
    && docker-php-ext-install zip ldap pdo pdo_sqlite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer bereitstellen
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
