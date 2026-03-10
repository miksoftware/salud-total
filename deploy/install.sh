#!/bin/bash
# =============================================================================
# Script de instalación de la app (ejecutar DESPUÉS de setup.sh)
# Ejecutar desde la raíz del proyecto: bash deploy/install.sh
# =============================================================================
set -e

APP_DIR="/var/www/salud-total"
CURRENT_DIR=$(pwd)

echo "========================================="
echo " Instalando Salud Total"
echo "========================================="

# Verificar que estamos en el directorio correcto
if [ ! -f "artisan" ]; then
    echo "ERROR: Ejecuta este script desde la raíz del proyecto Laravel"
    exit 1
fi

# --- 1. Copiar .env de producción ---
echo "[1/8] Configurando .env..."
if [ ! -f ".env" ]; then
    cp deploy/.env.production .env
    echo "  → .env creado desde template de producción"
    echo "  → IMPORTANTE: Edita .env y configura APP_URL con tu dominio/IP"
else
    echo "  → .env ya existe, no se sobreescribe"
fi

# --- 2. Instalar dependencias PHP ---
echo "[2/8] Instalando dependencias Composer..."
composer install --no-dev --optimize-autoloader --no-interaction

# --- 3. Generar APP_KEY si no existe ---
echo "[3/8] Generando APP_KEY..."
if grep -q "APP_KEY=$" .env || grep -q "APP_KEY=\"\"" .env; then
    php artisan key:generate --force
fi

# --- 4. Crear BD SQLite y migrar ---
echo "[4/8] Configurando base de datos..."
if [ ! -f "database/database.sqlite" ]; then
    touch database/database.sqlite
fi
php artisan migrate --force

# --- 5. Permisos ---
echo "[5/8] Configurando permisos..."
sudo chown -R $USER:www-data .
sudo chmod -R 775 storage bootstrap/cache database
sudo chmod 664 database/database.sqlite

# Asegurar que www-data pueda escribir
sudo setfacl -R -m u:www-data:rwx storage bootstrap/cache database
sudo setfacl -dR -m u:www-data:rwx storage bootstrap/cache database

# --- 6. Optimizar Laravel para producción ---
echo "[6/8] Optimizando para producción..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# --- 7. Configurar PHP-FPM pool ---
echo "[7/8] Configurando PHP-FPM pool..."
sudo cp deploy/php-fpm-pool.conf /etc/php/8.3/fpm/pool.d/salud-total.conf

# Desactivar pool default www si existe (opcional, evita conflicto de socket)
# sudo mv /etc/php/8.3/fpm/pool.d/www.conf /etc/php/8.3/fpm/pool.d/www.conf.bak 2>/dev/null || true

# --- 8. Configurar Nginx ---
echo "[8/8] Configurando Nginx..."
sudo cp deploy/nginx.conf /etc/nginx/sites-available/salud-total
sudo ln -sf /etc/nginx/sites-available/salud-total /etc/nginx/sites-enabled/salud-total

# Desactivar default si existe
if [ -f "/etc/nginx/sites-enabled/default" ]; then
    sudo rm /etc/nginx/sites-enabled/default
fi

# Verificar config de Nginx
sudo nginx -t

# Reiniciar servicios
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx
sudo systemctl enable php8.3-fpm
sudo systemctl enable nginx

echo ""
echo "========================================="
echo " ¡Instalación completada!"
echo "========================================="
echo ""
echo " URL: http://$(hostname -I | awk '{print $1}')"
echo ""
echo " Recuerda:"
echo "  1. Editar .env → APP_URL con tu dominio/IP real"
echo "  2. Editar deploy/nginx.conf → server_name con tu dominio/IP"
echo "  3. Si cambias .env: php artisan config:cache"
echo "  4. Logs: tail -f storage/logs/laravel.log"
echo ""
