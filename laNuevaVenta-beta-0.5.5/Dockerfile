FROM php:8.2-apache

# Instala las librerías necesarias para compilar extensiones de PostgreSQL
RUN apt-get update && \
    apt-get install -y libpq-dev && \
    docker-php-ext-install pgsql pdo_pgsql && \
    a2enmod rewrite && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Copia el código de la app dentro de la imagen (útil para despliegues en PaaS)
# En desarrollo local, docker-compose monta ./src como volumen; en producción la imagen ya incluye el código.
COPY ./src /var/www/html

# Asegura permisos de lectura para Apache (opcional, según tu entorno)
RUN chown -R www-data:www-data /var/www/html && \
    find /var/www/html -type d -exec chmod 755 {} \; && \
    find /var/www/html -type f -exec chmod 644 {} \;

# Si tu plataforma exige escuchar en un puerto dinámico ($PORT), puedes descomentar este bloque
# y usar un entrypoint simple que reemplace el puerto antes de arrancar Apache.
#
# ENV PORT=80
# COPY ./ops/docker/entrypoint.sh /entrypoint.sh
# RUN chmod +x /entrypoint.sh
# CMD ["/entrypoint.sh"]