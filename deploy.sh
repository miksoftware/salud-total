#!/bin/bash

PROJECT_NAME="salud-total"

# ============================================
# Script de Deploy Automático para Laravel
# ============================================

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
PROJECT_DIR="$SCRIPT_DIR"
SRC_DIR="$PROJECT_DIR/src"

echo -e "${BLUE}=========================================="
echo "  🚀 Deploy Laravel: $PROJECT_NAME"
echo "==========================================${NC}"
echo ""

cd "$SRC_DIR"

# ---- 1. Git Pull ----
echo -e "${YELLOW}[1/6] ⬇️  Descargando cambios desde Git...${NC}"
git stash --quiet 2>/dev/null || true
BRANCH=$(git rev-parse --abbrev-ref HEAD)

if git pull origin "$BRANCH" 2>&1; then
    echo -e "${GREEN}✓ Cambios descargados desde rama '$BRANCH'${NC}"
else
    echo -e "${RED}✗ Error al descargar cambios${NC}"
    exit 1
fi

LAST_COMMIT=$(git log -1 --pretty=format:'%h - %s (%ar) por %an')
echo -e "${BLUE}    📝 Último commit: $LAST_COMMIT${NC}"
echo ""

# ---- 2. Composer Install ----
echo -e "${YELLOW}[2/6] 📦 Composer install...${NC}"
docker exec -w /var/www/html ${PROJECT_NAME}_php composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -5
echo -e "${GREEN}✓ Dependencias actualizadas${NC}"
echo ""

# ---- 3. Base de datos SQLite + Migraciones + Seeder ----
echo -e "${YELLOW}[3/6] 🗄️  Base de datos y migraciones...${NC}"

# Crear archivo SQLite si no existe
docker exec -w /var/www/html ${PROJECT_NAME}_php sh -c '[ -f database/database.sqlite ] || touch database/database.sqlite'

# Ejecutar migraciones
docker exec -w /var/www/html ${PROJECT_NAME}_php php artisan migrate --force 2>&1

# Ejecutar seeder del admin (solo crea si no existe)
docker exec -w /var/www/html ${PROJECT_NAME}_php php artisan db:seed --class=AdminSeeder --force 2>&1

echo -e "${GREEN}✓ Base de datos actualizada${NC}"
echo ""

# ---- 4. Permisos ----
echo -e "${YELLOW}[4/6] 🔐 Ajustando permisos...${NC}"
docker exec ${PROJECT_NAME}_php chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
docker exec ${PROJECT_NAME}_php chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
docker exec ${PROJECT_NAME}_php chmod 664 /var/www/html/database/database.sqlite 2>/dev/null || true
echo -e "${GREEN}✓ Permisos ajustados${NC}"
echo ""

# ---- 5. Cache ----
echo -e "${YELLOW}[5/6] ⚡ Limpiando y recacheando...${NC}"
docker exec -w /var/www/html ${PROJECT_NAME}_php php artisan config:cache
docker exec -w /var/www/html ${PROJECT_NAME}_php php artisan route:cache
docker exec -w /var/www/html ${PROJECT_NAME}_php php artisan view:cache
echo -e "${GREEN}✓ Cache reconstruida${NC}"
echo ""

# ---- 6. Reiniciar servicios ----
echo -e "${YELLOW}[6/6] 🔄 Reiniciando servicios...${NC}"
cd "$PROJECT_DIR"
docker compose restart php nginx
sleep 3
echo -e "${GREEN}✓ Servicios reiniciados${NC}"
echo ""

echo -e "${GREEN}=========================================="
echo "  ✅ Deploy completado exitosamente"
echo "==========================================${NC}"
echo ""
echo -e "${BLUE}📅 Fecha: $(date '+%Y-%m-%d %H:%M:%S')${NC}"
echo -e "${BLUE}🔀 Rama: $BRANCH${NC}"
echo -e "${BLUE}📝 Commit: $LAST_COMMIT${NC}"
echo ""
