FROM php:8.3-fpm-bookworm

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

# OPCache für verbesserte Leistung
RUN docker-php-ext-install opcache
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# PHP-Konfiguration anpassen
COPY docker/php/php.ini /usr/local/etc/php/conf.d/php.ini

# Composer installieren
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Arbeitsverzeichnis festlegen
WORKDIR /var/www/html

## Xdebug für Code-Coverage installieren
RUN pecl channel-update pecl.php.net && pecl install xdebug && docker-php-ext-enable xdebug
# Copy xdebug configuration (mode off, trigger start)
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
# Ensure the zend_extension is explicitly set to the installed xdebug.so
RUN php -r "echo ini_get('extension_dir');" > /tmp/extension_dir && \
    EXT_DIR=$(cat /tmp/extension_dir) && \
    printf 'zend_extension=%s/xdebug.so\n' "$EXT_DIR" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini || true


# Symfony-Cache-Berechtigung setzen
RUN mkdir -p var/cache var/log && \
    chmod -R 777 var

CMD ["php-fpm"]

EXPOSE 9000