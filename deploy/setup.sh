#!/bin/bash
# =============================================================================
# Script de despliegue - Salud Total Consultor Automático
# Ubuntu 24.04 LTS + Nginx + PHP 8.3-FPM + SQLite
# =============================================================================
set -e

echo "========================================="
echo " Salud Total - Setup Producción"
echo "========================================="

# --- 1. Actualizar sistema ---
echo "[1/8] Actualizando sistema..."
sudo apt update && sudo apt upgrade -y

# --- 2. Instalar PHP 8.3 + extensiones necesarias ---
echo "[2/8] Instalando PHP 8.3 y extensiones..."
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

sudo apt install -y \
    php8.3-fpm \
    php8.3-cli \
    php8.3-common \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-curl \
    php8.3-zip \
    php8.3-gd \
    php8.3-sqlite3 \
    php8.3-intl \
    php8.3-bcmath \
    php8.3-tokenizer \
    php8.3-fileinfo

# --- 3. Instalar Nginx ---
echo "[3/8] Instalando Nginx..."
sudo apt install -y nginx

# --- 4. Instalar Composer ---
echo "[4/8] Instalando Composer..."
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
fi

# --- 5. Instalar Git y Unzip ---
echo "[5/8] Instalando utilidades..."
sudo apt install -y git unzip acl

# --- 6. Crear directorio del proyecto ---
echo "[6/8] Configurando directorio del proyecto..."
APP_DIR="/var/www/salud-total"

if [ ! -d "$APP_DIR" ]; then
    sudo mkdir -p "$APP_DIR"
fi
sudo chown -R $USER:www-data "$APP_DIR"

echo ""
echo "========================================="
echo " Dependencias instaladas correctamente"
echo "========================================="
echo ""
echo "Siguiente paso: copiar el proyecto a $APP_DIR"
echo "Puedes usar git clone o scp/rsync."
echo ""
echo "Luego ejecuta: bash deploy/install.sh"
