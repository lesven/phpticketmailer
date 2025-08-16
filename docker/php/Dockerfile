FROM php:8.3-fpm-bullseye

# Installiere Abhängigkeiten
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    git \
    curl \
    wget

# PHP-Erweiterungen installieren
RUN docker-php-ext-install pdo pdo_mysql
RUN docker-php-ext-install zip
RUN docker-php-ext-install intl
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install gd

# Install Xdebug for code coverage (PECL)
RUN pecl update-channels && pecl install xdebug && docker-php-ext-enable xdebug

# OPCache für verbesserte Leistung
RUN docker-php-ext-install opcache
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# PHP-Konfiguration anpassen
COPY docker/php/php.ini /usr/local/etc/php/conf.d/php.ini

# Xdebug configuration (enabled for development/coverage)
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Composer installieren
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Arbeitsverzeichnis festlegen
WORKDIR /var/www/html

# Symfony-Cache-Berechtigung setzen
RUN mkdir -p var/cache var/log && \
    chmod -R 777 var

CMD ["php-fpm"]

EXPOSE 9000