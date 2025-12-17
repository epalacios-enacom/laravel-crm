#!/bin/bash
set -euo pipefail

# =============================================================================
# deploy-empresa-column.sh
# Despliega el cambio de columna "Empresa" en el grid de Leads
# =============================================================================
# Uso: ./deploy-empresa-column.sh
# Ejecutar en el servidor como root o con sudo
# =============================================================================

SRC_DIR="/opt/krayin-crm/src"
ENACOM_PKG="$SRC_DIR/packages/Webkul/EnacomLeadOrg"

echo "=============================================="
echo " Desplegando columna Empresa en LeadDataGrid"
echo "=============================================="

# Verificar que estamos en el servidor correcto
if [ ! -d "$SRC_DIR" ]; then
    echo "[ERROR] No se encontró $SRC_DIR"
    echo "        ¿Estás ejecutando esto en el servidor correcto?"
    exit 1
fi

cd "$SRC_DIR"

# 1. Actualizar el código desde el fork
echo ""
echo "[1/5] Actualizando código desde el repositorio..."
git fetch origin
git pull origin 2.1 || {
    echo "[WARN] Hubo conflictos. Intentando reset..."
    git reset --hard origin/2.1
}

# 2. Remover el paquete EnacomLeadOrg (ya no es necesario)
echo ""
echo "[2/5] Removiendo paquete EnacomLeadOrg (ya no es necesario)..."
if [ -d "$ENACOM_PKG" ]; then
    rm -rf "$ENACOM_PKG"
    echo "       ✓ Paquete eliminado"
else
    echo "       ✓ El paquete no existía (OK)"
fi

# 3. Remover el provider de config/app.php si existe
echo ""
echo "[3/5] Limpiando config/app.php..."
if grep -q "EnacomLeadOrgServiceProvider" "$SRC_DIR/config/app.php" 2>/dev/null; then
    sed -i '/EnacomLeadOrgServiceProvider/d' "$SRC_DIR/config/app.php"
    echo "       ✓ Provider removido de config/app.php"
else
    echo "       ✓ Provider no estaba registrado (OK)"
fi

# 4. Regenerar autoload y limpiar cachés
echo ""
echo "[4/5] Limpiando cachés y regenerando autoload..."
composer dump-autoload --no-interaction
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# 5. Verificar que el cambio está aplicado
echo ""
echo "[5/5] Verificando que el cambio está aplicado..."
if grep -q "organization_name" "$SRC_DIR/packages/Webkul/Admin/src/DataGrids/Lead/LeadDataGrid.php"; then
    echo "       ✓ Columna 'Empresa' encontrada en LeadDataGrid.php"
else
    echo "[ERROR] No se encontró la columna organization_name en LeadDataGrid.php"
    echo "        Verifica que el commit está en la rama 2.1"
    exit 1
fi

echo ""
echo "=============================================="
echo " ✓ Despliegue completado exitosamente"
echo "=============================================="
echo ""
echo "La columna 'Empresa' debería estar visible en:"
echo "  → Grid de Leads (vista tabla)"
echo "  → Filtros del DataGrid"
echo ""
echo "Si no ves los cambios, recarga la página con Ctrl+Shift+R"
echo ""
