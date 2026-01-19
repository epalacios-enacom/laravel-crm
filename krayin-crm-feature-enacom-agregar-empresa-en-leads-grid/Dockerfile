# Imagen base de PHP con Apache (ajusta la versión si es distinta)
FROM php:8.1-apache

# Instalar dependencias necesarias
RUN apt-get update && \
    apt-get install -y \
        git \
        unzip \
        zip \
        libicu-dev \
        libzip-dev \
        libonig-dev && \
    docker-php-ext-configure intl && \
    docker-php-ext-install intl pdo pdo_mysql zip && \
    docker-php-ext-enable intl

# Habilitar mod_rewrite para Laravel
RUN a2enmod rewrite

# Copiar archivos del proyecto
COPY ./src /var/www/html

# Establecer permisos correctos
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html/storage && \
    chmod -R 755 /var/www/html/bootstrap/cache

# Configurar el directorio de trabajo
WORKDIR /var/www/html

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Instalar dependencias PHP de Laravel
RUN composer install --no-interaction --optimize-autoloader

# Exponer el puerto web
EXPOSE 80
