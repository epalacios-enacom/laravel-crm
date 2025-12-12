<?php
/**
 * Configuración Automática de Base de Datos para Krayin CRM
 * 
 * Este archivo detecta automáticamente el entorno (Docker vs Local)
 * y configura la conexión a la base de datos apropiadamente.
 * 
 * @author Senior Software Engineer
 * @version 1.0
 * @date 2024
 */

class DatabaseConfig {
    private static $config = null;
    
    /**
     * Obtiene la configuración de base de datos según el entorno
     * @return array Configuración de conexión
     */
    public static function getConfig() {
        if (self::$config === null) {
            self::$config = self::detectEnvironment();
        }
        return self::$config;
    }
    
    /**
     * Detecta automáticamente el entorno y retorna la configuración apropiada
     * @return array Configuración de conexión
     */
    private static function detectEnvironment() {
        // Detectar si estamos en Docker
        $isDocker = self::isDockerEnvironment();
        
        if ($isDocker) {
            return [
                'host' => 'krayin-db',
                'dbname' => 'krayincrm',
                'username' => 'root',
                'password' => 'root',
                'charset' => 'utf8mb4',
                'environment' => 'docker'
            ];
        } else {
            return [
                'host' => 'localhost',
                'dbname' => 'krayincrm',
                'username' => 'root',
                'password' => 'root',
                'charset' => 'utf8mb4',
                'environment' => 'local'
            ];
        }
    }
    
    /**
     * Detecta si el script se está ejecutando en un contenedor Docker
     * @return bool True si está en Docker, false en caso contrario
     */
    private static function isDockerEnvironment() {
        // Método 1: Verificar archivo /.dockerenv
        if (file_exists('/.dockerenv')) {
            return true;
        }
        
        // Método 2: Verificar variable de entorno DOCKER_CONTAINER
        if (getenv('DOCKER_CONTAINER') === 'true') {
            return true;
        }
        
        // Método 3: Verificar hostname típico de Docker
        $hostname = gethostname();
        if (preg_match('/^[a-f0-9]{12}$/', $hostname)) {
            return true;
        }
        
        // Método 4: Verificar si el host krayin-db es resolvible
        $krayin_db_ip = gethostbyname('krayin-db');
        if ($krayin_db_ip !== 'krayin-db') {
            return true;
        }
        
        return false;
    }
    
    /**
     * Crea una conexión PDO usando la configuración detectada
     * @return PDO Instancia de conexión PDO
     * @throws PDOException Si la conexión falla
     */
    public static function createConnection() {
        $config = self::getConfig();
        
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
        
        try {
            $pdo = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']}",
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            
            return $pdo;
            
        } catch (PDOException $e) {
            throw new PDOException(
                "Error conectando a la base de datos en entorno {$config['environment']}: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
    
    /**
     * Muestra información de la configuración detectada
     * @return void
     */
    public static function showInfo() {
        $config = self::getConfig();
        
        echo "🔧 CONFIGURACIÓN DE BASE DE DATOS DETECTADA\n";
        echo str_repeat("=", 50) . "\n";
        echo "🌍 Entorno: " . strtoupper($config['environment']) . "\n";
        echo "🖥️  Host: {$config['host']}\n";
        echo "🗄️  Base de datos: {$config['dbname']}\n";
        echo "👤 Usuario: {$config['username']}\n";
        echo "🔤 Charset: {$config['charset']}\n";
        echo str_repeat("=", 50) . "\n\n";
    }
    
    /**
     * Prueba la conexión y muestra el resultado
     * @return bool True si la conexión es exitosa
     */
    public static function testConnection() {
        try {
            $pdo = self::createConnection();
            
            // Probar la conexión con una consulta simple
            $stmt = $pdo->query("SELECT VERSION() as version, NOW() as current_time");
            $result = $stmt->fetch();
            
            echo "✅ Conexión exitosa\n";
            echo "📊 MySQL Version: {$result['version']}\n";
            echo "🕐 Hora del servidor: {$result['current_time']}\n\n";
            
            return true;
            
        } catch (PDOException $e) {
            echo "❌ Error de conexión: " . $e->getMessage() . "\n\n";
            return false;
        }
    }
}

// Si el archivo se ejecuta directamente, mostrar información
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    echo "\n🔍 DETECTOR DE CONFIGURACIÓN DE BASE DE DATOS\n";
    echo str_repeat("=", 60) . "\n\n";
    
    DatabaseConfig::showInfo();
    DatabaseConfig::testConnection();
    
    echo "💡 Para usar en otros scripts:\n";
    echo "   require_once 'database_config.php';\n";
    echo "   \$pdo = DatabaseConfig::createConnection();\n\n";
}

?>
