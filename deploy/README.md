# Despliegue en Producción - Ubuntu 24.04

## Requisitos del VPS
- Ubuntu 24.04 LTS
- Mínimo 1 GB RAM, 1 vCPU
- Acceso root o sudo

## Pasos

### 1. Subir el proyecto al servidor

Opción A - Git:
```bash
ssh usuario@tu-servidor
cd /var/www
git clone https://tu-repo.git salud-total
cd salud-total
```

Opción B - SCP desde tu máquina local:
```bash
# Desde tu PC (excluir vendor y node_modules)
rsync -avz --exclude='vendor' --exclude='node_modules' --exclude='.env' \
  --exclude='database/database.sqlite' --exclude='storage/logs/*.log' \
  ./ usuario@tu-servidor:/var/www/salud-total/
```

### 2. Instalar dependencias del sistema

```bash
cd /var/www/salud-total
sudo bash deploy/setup.sh
```

Esto instala: PHP 8.3-FPM, Nginx, Composer, extensiones PHP necesarias (zip, gd, sqlite3, curl, xml, mbstring, etc.)

### 3. Instalar la aplicación

```bash
cd /var/www/salud-total
bash deploy/install.sh
```

Esto hace:
- Copia `.env` de producción
- `composer install --no-dev`
- Genera `APP_KEY`
- Crea BD SQLite y ejecuta migraciones
- Configura permisos para `www-data`
- Cachea config/rutas/vistas
- Configura Nginx + PHP-FPM
- Reinicia servicios

### 4. Configurar tu dominio/IP

Editar dos archivos:

```bash
# 1. En .env, cambiar APP_URL
nano .env
# APP_URL=http://tu-dominio.com

# 2. En Nginx, cambiar server_name
sudo nano /etc/nginx/sites-available/salud-total
# server_name tu-dominio.com;

# Aplicar cambios
php artisan config:cache
sudo systemctl restart nginx
```

### 5. Verificar

```bash
# Verificar que PHP-FPM está corriendo
sudo systemctl status php8.3-fpm

# Verificar Nginx
sudo systemctl status nginx

# Probar la app
curl -I http://localhost
```

Abre tu navegador en `http://tu-ip-del-servidor` y deberías ver la app.

---

## SSL con Let's Encrypt (opcional pero recomendado)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d tu-dominio.com
```

Certbot modifica automáticamente la config de Nginx para HTTPS.

Después actualiza `.env`:
```
APP_URL=https://tu-dominio.com
```
```bash
php artisan config:cache
```

---

## Comandos útiles

```bash
# Ver logs de la app
tail -f /var/www/salud-total/storage/logs/laravel.log

# Ver logs de Nginx
tail -f /var/log/nginx/salud-total-error.log

# Ver logs de PHP-FPM
tail -f /var/log/php8.3-fpm-salud-total.log

# Limpiar caché después de cambios
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Limpiar todo el caché
php artisan optimize:clear

# Re-migrar BD (CUIDADO: borra datos)
php artisan migrate:fresh

# Ver estado de servicios
sudo systemctl status php8.3-fpm nginx
```

---

## Solución de problemas

**Error 502 Bad Gateway:**
```bash
sudo systemctl restart php8.3-fpm
# Verificar que el socket existe
ls -la /var/run/php/php8.3-fpm.sock
```

**Error 403 Forbidden:**
```bash
sudo chown -R $USER:www-data /var/www/salud-total
sudo chmod -R 775 storage bootstrap/cache database
```

**Error "Permission denied" en SQLite:**
```bash
sudo chmod 664 database/database.sqlite
sudo chown $USER:www-data database/database.sqlite
sudo chown $USER:www-data database/
```

**La sesión del portal no conecta:**
- Verificar que el VPS puede acceder a `transaccional.saludtotal.com.co`
```bash
curl -I https://transaccional.saludtotal.com.co
```
- Si hay firewall, abrir salida HTTPS (puerto 443)

**Timeout en consultas largas:**
- Ya configurado en `php-fpm-pool.conf` (120s) y `nginx.conf` (120s)
- Si necesitas más, editar ambos archivos y reiniciar servicios

---

## Estructura de archivos de deploy

```
deploy/
├── README.md           ← Esta guía
├── .env.production     ← Template de variables de entorno
├── nginx.conf          ← Config de Nginx
├── php-fpm-pool.conf   ← Pool de PHP-FPM con timeouts
├── setup.sh            ← Instala dependencias del sistema
└── install.sh          ← Instala y configura la app
```
