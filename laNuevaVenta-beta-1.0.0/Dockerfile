FROM php:8.2-apache

# Instala las librerías necesarias para compilar extensiones de PostgreSQL
RUN apt-get update && \
    apt-get install -y libpq-dev && \
    docker-php-ext-install pgsql pdo_pgsql

# Habilita mod_rewrite si lo necesitas (opcional)
RUN a2enmod rewrite