<?php
/**
 * Script de Prueba para Configuración Automática de Base de Datos
 * 
 * Este script prueba la detección automática de entorno y conexión
 * a la base de datos en diferentes contextos (Docker vs Local).
 * 
 * @author Senior Software Engineer
 * @version 1.0
 * @date 2024
 */

echo "\n🧪 PRUEBA DE CONFIGURACIÓN AUTOMÁTICA DE BASE DE DATOS\n";
echo str_repeat("=", 65) . "\n\n";

// Incluir la configuración automática
require_once 'database_config.php';

// Mostrar información del entorno
echo "🔍 DETECCIÓN DE ENTORNO\n";
echo str_repeat("-", 30) . "\n";

// Verificar archivos y variables de entorno
echo "📁 Archivo /.dockerenv: " . (file_exists('/.dockerenv') ? '✅ Existe' : '❌ No existe') . "\n";
echo "🌍 Variable DOCKER_CONTAINER: " . (getenv('DOCKER_CONTAINER') ? '✅ ' . getenv('DOCKER_CONTAINER') : '❌ No definida') . "\n";
echo "🖥️  Hostname: " . gethostname() . "\n";

// Probar resolución DNS de krayin-db
$krayin_db_ip = gethostbyname('krayin-db');
echo "🔗 Resolución krayin-db: " . ($krayin_db_ip !== 'krayin-db' ? "✅ $krayin_db_ip" : '❌ No resuelve') . "\n";

echo "\n";

// Mostrar configuración detectada
DatabaseConfig::showInfo();

// Probar conexión
echo "🔌 PRUEBA DE CONEXIÓN\n";
echo str_repeat("-", 25) . "\n";

if (DatabaseConfig::testConnection()) {
    echo "🎉 ¡Configuración automática funcionando correctamente!\n\n";
    
    // Probar algunas consultas básicas
    try {
        $pdo = DatabaseConfig::createConnection();
        
        echo "📊 VERIFICACIONES ADICIONALES\n";
        echo str_repeat("-", 30) . "\n";
        
        // Verificar tablas principales
        $tables = ['organizations', 'persons', 'attribute_values', 'users'];
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                $count = $stmt->fetch()['count'];
                echo "📋 Tabla $table: ✅ $count registros\n";
            } catch (PDOException $e) {
                echo "📋 Tabla $table: ❌ Error - " . $e->getMessage() . "\n";
            }
        }
        
        // Verificar atributos específicos
        echo "\n🎯 ATRIBUTOS ESPECÍFICOS\n";
        echo str_repeat("-", 25) . "\n";
        
        $attributes = [34 => 'name', 35 => 'address', 36 => 'user_id', 21 => 'source', 61 => 'rut'];
        foreach ($attributes as $id => $name) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attributes WHERE id = ?");
                $stmt->execute([$id]);
                $count = $stmt->fetchColumn();
                echo "🏷️  Atributo $id ($name): " . ($count > 0 ? '✅ Existe' : '❌ No existe') . "\n";
            } catch (PDOException $e) {
                echo "🏷️  Atributo $id ($name): ❌ Error verificando\n";
            }
        }
        
    } catch (PDOException $e) {
        echo "❌ Error en verificaciones adicionales: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "💥 Error en la configuración automática\n\n";
    
    echo "🔧 SUGERENCIAS DE SOLUCIÓN:\n";
    echo str_repeat("-", 30) . "\n";
    echo "1. Verificar que el servicio MySQL esté ejecutándose\n";
    echo "2. En Docker: Verificar que el servicio se llame 'krayin-db'\n";
    echo "3. En Local: Verificar que MySQL esté en localhost:3306\n";
    echo "4. Verificar credenciales (usuario: root, password: root)\n";
    echo "5. Verificar que la base de datos 'krayincrm' exista\n\n";
}

echo "📝 INFORMACIÓN TÉCNICA\n";
echo str_repeat("-", 25) . "\n";
echo "🐘 PHP Version: " . PHP_VERSION . "\n";
echo "💾 Memory Limit: " . ini_get('memory_limit') . "\n";
echo "⏱️  Max Execution Time: " . (ini_get('max_execution_time') ?: 'Sin límite') . "\n";
echo "📂 Working Directory: " . getcwd() . "\n";
echo "👤 User: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'N/A') . "\n";

echo "\n" . str_repeat("=", 65) . "\n";
echo "✅ Prueba completada\n\n";

?>
