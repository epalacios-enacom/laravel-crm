#!/bin/bash
# Script para inicializar Krayin CRM para un nuevo cliente
# Uso: ./init-krayin.sh nombre_cliente email_admin password_admin

# Verificar argumentos
if [ $# -lt 3 ]; then
  echo "Uso: $0 nombre_cliente email_admin password_admin"
  exit 1
fi

CLIENTE=$1
EMAIL_ADMIN=$2
PASSWORD_ADMIN=$3
HASHED_PASSWORD=$(php -r "echo password_hash('$PASSWORD_ADMIN', PASSWORD_BCRYPT);")

# Configurar .env para el cliente
sed -i "s/APP_NAME=.*/APP_NAME=\"$CLIENTE CRM\"/" /opt/krayin-crm/src/.env
sed -i "s/APP_URL=.*/APP_URL=http:\/\/${HOSTNAME}/" /opt/krayin-crm/src/.env

cd /opt/krayin-crm

# Iniciar los contenedores
docker-compose up -d

# Esperar a que MySQL esté listo
echo "Esperando a que la base de datos esté lista..."
sleep 15

# Instalar dependencias y configurar la aplicación
docker exec krayin-app bash -c "cd /var/www/html && composer install --no-dev --optimize-autoloader"
docker exec krayin-app bash -c "cd /var/www/html && php artisan key:generate"
docker exec krayin-app bash -c "cd /var/www/html && php artisan migrate --force"
docker exec krayin-app bash -c "cd /var/www/html && php artisan db:seed --force"
docker exec krayin-app bash -c "cd /var/www/html && php artisan optimize:clear"
docker exec krayin-app bash -c "cd /var/www/html && php artisan storage:link"

# Crear usuario administrador
docker exec -i krayin-db mysql -uroot -proot krayincrm << EOSQL
INSERT INTO admins (name, email, password, status, created_at, updated_at)
VALUES ('$CLIENTE Admin', '$EMAIL_ADMIN', '$HASHED_PASSWORD', 1, NOW(), NOW());

INSERT INTO admin_roles (role_id, admin_id, created_at, updated_at)
VALUES (1, LAST_INSERT_ID(), NOW(), NOW());
EOSQL

echo "=========================================================="
echo "Krayin CRM ha sido inicializado para: $CLIENTE"
echo "URL de acceso: http://$HOSTNAME/admin/login"
echo "Usuario: $EMAIL_ADMIN"
echo "Contraseña: $PASSWORD_ADMIN"
echo "=========================================================="
