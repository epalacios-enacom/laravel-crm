#!/bin/bash
set -euo pipefail

# Configuración
KRAYIN_ROOT_CONTAINER="/var/www/html" # Ruta DENTRO del contenedor
CONTAINER=${1:-krayin-app} # Nombre del contenedor
HOST_PACKAGES_DIR="./packages/Webkul/EnacomLeadOrg" # Ruta local del paquete en el host (relativa a donde se corre el script)

echo "=== DESPLIEGUE ENACOM (V2.1 - Docker Native) ==="

# 1. Copiar archivos al contenedor
echo "1. Copiando archivos al contenedor..."
if [ -d "$HOST_PACKAGES_DIR" ]; then
    # Crear directorio destino en contenedor
    docker exec "$CONTAINER" mkdir -p "$KRAYIN_ROOT_CONTAINER/packages/Webkul"
    
    # Limpiar instalación previa en contenedor
    docker exec "$CONTAINER" rm -rf "$KRAYIN_ROOT_CONTAINER/packages/Webkul/EnacomLeadOrg"
    
    # Copiar desde host al contenedor
    docker cp "$HOST_PACKAGES_DIR" "$CONTAINER:$KRAYIN_ROOT_CONTAINER/packages/Webkul/"
else
    echo "(!) Error: No encuentro la carpeta local '$HOST_PACKAGES_DIR'."
    echo "    Ejecuta este script desde la raíz del proyecto (donde está la carpeta 'packages')."
    exit 1
fi

# 2. Configurar Autoload PSR-4 (PHP dentro del contenedor)
echo "2. Configurando Autoload PSR-4..."
docker exec "$CONTAINER" php -r "
\$file = '$KRAYIN_ROOT_CONTAINER/composer.json';
\$json = json_decode(file_get_contents(\$file), true);
\$json['autoload']['psr-4']['Webkul\\\\EnacomLeadOrg\\\\'] = 'packages/Webkul/EnacomLeadOrg/src';
file_put_contents(\$file, json_encode(\$json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
"

# 3. Configurar Provider con Prioridad (PHP dentro del contenedor)
echo "3. Configurando Provider (Prioridad Alta)..."
docker exec "$CONTAINER" php -r "
\$file = '$KRAYIN_ROOT_CONTAINER/config/app.php';
\$content = file_get_contents(\$file);
\$provider = 'Webkul\\\\EnacomLeadOrg\\\\Providers\\\\EnacomLeadOrgServiceProvider::class';

// 1. Limpiar referencia existente para evitar duplicados
\$content = preg_replace('/\\s*'.preg_quote(\$provider, '/').',/', '', \$content);

// 2. Insertar al PRINCIPIO del array 'providers'
\$content = preg_replace('/(\047providers\047\\s*=>\\s*\\[)/', \"\$1\\n        \$provider,\", \$content, 1);

file_put_contents(\$file, \$content);
"

# 4. Regenerar Autoload y Cache
echo "4. Regenerando Autoload y Cache..."
docker exec "$CONTAINER" bash -c "cd $KRAYIN_ROOT_CONTAINER && composer dump-autoload && php artisan optimize:clear"

# 5. Verificación
echo "=== VERIFICACION ==="
echo "Ruta de prueba (debe decir ENACOM PACKAGE IS ACTIVE):"
echo "  http://crm-enacom.onak.cl/admin/enacom-test"
echo ""
echo "Controlador activo para 'admin/leads':"
docker exec "$CONTAINER" bash -c "cd $KRAYIN_ROOT_CONTAINER && php artisan route:list | grep 'admin/leads' | head -n 1"
echo ""
echo "Si ves 'Webkul\EnacomLeadOrg...' arriba, la instalación fue EXITOSA."
