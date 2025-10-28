# Despliegue con Vercel (estático) + AWS (backend PHP + PostgreSQL)

Este repo usa PHP 8.2 con Apache y PostgreSQL. Vercel no ejecuta PHP, así que el backend debe vivir en AWS y Vercel actuará como CDN/proxy para las rutas `.php`.

## Arquitectura

- Vercel: sirve estáticos (`css/`, `js/`, `img/`) desde `src/` y reescribe (`rewrite`) peticiones a rutas `.php` hacia el backend en AWS.
- AWS: ejecuta el contenedor con Apache+PHP (imagen construida desde `Dockerfile`) y se conecta a una base de datos PostgreSQL (RDS recomendado).

## 1) Preparar la imagen Docker para AWS

El `Dockerfile` ya copia `./src` dentro de la imagen y habilita `pgsql`.

```Dockerfile
FROM php:8.2-apache
RUN apt-get update && \
    apt-get install -y libpq-dev && \
    docker-php-ext-install pgsql pdo_pgsql && \
    a2enmod rewrite && \
    apt-get clean && rm -rf /var/lib/apt/lists/*
COPY ./src /var/www/html
RUN chown -R www-data:www-data /var/www/html && \
    find /var/www/html -type d -exec chmod 755 {} \; && \
    find /var/www/html -type f -exec chmod 644 {} \;
```

> Nota: Si tu PaaS exige escuchar en `$PORT` (en lugar de 80), añade un pequeño entrypoint que edite `ports.conf` para `Listen $PORT` antes de `apache2-foreground`.

## 2) Desplegar el backend en AWS

Opciones sencillas:

- Elastic Beanstalk (Docker Single Container): sube el repo; EB construye la imagen y arranca Apache en el puerto 80 detrás de un Load Balancer.
- ECS Fargate: crea un servicio con tu imagen Docker (subida a ECR) y expone el puerto 80 mediante un ALB.
- Lightsail (contenedores): sube la imagen y expone el puerto 80.

### Variables de entorno (en el servicio de AWS)

- `DB_HOST`
- `DB_PORT` = `5432`
- `DB_USER`
- `DB_PASSWORD`
- `DB_NAME`

`src/conexion.php` ya lee estas variables. Para local usa `docker-compose.yml`.

### Base de datos

- Crea una instancia de Amazon RDS for PostgreSQL (o usa Neon/Supabase).
- Importa `db/init.sql` una vez (por ejemplo con `psql`).
- Seguridad: permite acceso al puerto 5432 solo desde el security group del servicio (no lo abras a Internet si no es necesario).

### Dominio del backend

- Apunta un subdominio, por ejemplo `api.tudominio.com`, al Load Balancer/endpoint del servicio (ALB o EB) usando un registro CNAME/ALIAS.

## 3) Configurar Vercel

Vercel no ejecuta PHP, pero puede proxyar peticiones `.php` a tu backend en AWS.

- Archivo `vercel.json` en la raíz del proyecto:

```json
{
  "version": 2,
  "rewrites": [
    { "source": "/", "destination": "https://YOUR-AWS-BACKEND-DOMAIN/" },
    { "source": "/:path*.php", "destination": "https://YOUR-AWS-BACKEND-DOMAIN/:path*.php" }
  ]
}
```

Reemplaza `YOUR-AWS-BACKEND-DOMAIN` por tu dominio real del backend (por ejemplo `api.tudominio.com`).

### Proyecto en Vercel

- Directorio de salida: `src` (Vercel publicará estáticos desde ahí).
- No requiere build ni framework.
- Subir a Vercel; verificará que los assets (css/js/img) se sirvan estáticamente y que las rutas `/` y `/*.php` se reescriban al backend.

## 4) Consideraciones de subida de archivos

Si tu app escribe archivos en `img/productos/` (p. ej. `subirProducto.php`), en contenedores esa escritura no es persistente entre despliegues. Recomendado:

- Guardar archivos en Amazon S3 y almacenar en la base de datos solo la URL.
- Alternativa: EFS montado en el contenedor (más complejo).

## 5) Comprobación rápida

- Abre el dominio de Vercel: debería cargar `/` desde el backend (por rewrite), y los estáticos desde Vercel.
- Navega a rutas `.php` (login, producto, etc.): deben resolver a tu backend sin problemas de CORS (Vercel actúa como proxy).

## 6) Variables sensibles

- No subas credenciales al repositorio. Usa variables de entorno en AWS. En Vercel no necesitas credenciales del backend si usas rewrites; si decides hacer llamadas directas desde el navegador, entonces trata CORS y evita exponer secretos.

## 7) Desarrollo local

- Usa `docker-compose up` para levantar `web` + `db` localmente en `http://localhost:8080`.

---

Siguientes pasos sugeridos:

- Migrar subida de imágenes a S3.
- Añadir HTTPS (ALB + ACM) en el backend y dominio personalizado en Vercel.
- Health checks y logs centralizados (CloudWatch) para el backend.
