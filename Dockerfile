FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    libsnmp-dev snmp \
    libicu-dev \
    libonig-dev \
    libzip-dev \
    unzip \
 && docker-php-ext-configure snmp \
 && docker-php-ext-install snmp mysqli pdo pdo_mysql intl \
 && a2enmod rewrite \
 && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/writable
    
RUN echo "date.timezone=America/Sao_Paulo" > /usr/local/etc/php/conf.d/timezone.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader || true

EXPOSE 80
CMD ["apache2-foreground"]

