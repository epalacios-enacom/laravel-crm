<?php
/**
 * Script de Validación de Entorno para Procesamiento por Lotes
 * 
 * Este script verifica que el entorno esté correctamente configurado
 * antes de ejecutar el procesamiento masivo de organizaciones.
 * 
 * @author Senior Software Engineer
 * @version 1.0
 * @date 2024
 */

class EnvironmentValidator {
    private $results = [];
    private $errors = [];
    private $warnings = [];
    
    public function runAllTests() {
        echo "\n🔍 VALIDADOR DE ENTORNO - KRAYIN CRM BATCH PROCESSOR\n";
        echo str_repeat("=", 60) . "\n\n";
        
        $this->testPHPVersion();
        $this->testPHPExtensions();
        $this->testMemoryLimit();
        $this->testExecutionTime();
        $this->testDatabaseConnection();
        $this->testDatabaseStructure();
        $this->testCSVFile();
        $this->testFilePermissions();
        $this->testKrayinTables();
        
        $this->generateReport();
        
        return empty($this->errors);
    }
    
    private function testPHPVersion() {
        echo "📋 Verificando versión de PHP...\n";
        
        $version = PHP_VERSION;
        $minVersion = '7.4.0';
        
        if (version_compare($version, $minVersion, '>=')) {
            $this->addResult('✅', "PHP Version: $version (OK)");
        } else {
            $this->addError('❌', "PHP Version: $version (Requiere >= $minVersion)");
        }
    }
    
    private function testPHPExtensions() {
        echo "🔌 Verificando extensiones de PHP...\n";
        
        $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
        
        foreach ($requiredExtensions as $ext) {
            if (extension_loaded($ext)) {
                $this->addResult('✅', "Extensión $ext: Disponible");
            } else {
                $this->addError('❌', "Extensión $ext: No disponible (REQUERIDA)");
            }
        }
        
        // Extensiones opcionales pero recomendadas
        $optionalExtensions = ['opcache', 'zip'];
        foreach ($optionalExtensions as $ext) {
            if (extension_loaded($ext)) {
                $this->addResult('✅', "Extensión $ext: Disponible (Recomendada)");
            } else {
                $this->addWarning('⚠️', "Extensión $ext: No disponible (Recomendada)");
            }
        }
    }
    
    private function testMemoryLimit() {
        echo "💾 Verificando límite de memoria...\n";
        
        $memoryLimit = ini_get('memory_limit');
        $memoryBytes = $this->parseMemoryLimit($memoryLimit);
        $recommendedBytes = 512 * 1024 * 1024; // 512MB
        
        if ($memoryBytes >= $recommendedBytes || $memoryLimit === '-1') {
            $this->addResult('✅', "Límite de memoria: $memoryLimit (OK)");
        } else {
            $this->addWarning('⚠️', "Límite de memoria: $memoryLimit (Recomendado: >= 512M)");
        }
        
        $currentUsage = round(memory_get_usage(true) / 1024 / 1024, 2);
        $this->addResult('ℹ️', "Uso actual de memoria: {$currentUsage}MB");
    }
    
    private function testExecutionTime() {
        echo "⏱️ Verificando límite de tiempo de ejecución...\n";
        
        $maxExecutionTime = ini_get('max_execution_time');
        
        if ($maxExecutionTime == 0) {
            $this->addResult('✅', "Tiempo de ejecución: Sin límite (OK)");
        } elseif ($maxExecutionTime >= 300) {
            $this->addResult('✅', "Tiempo de ejecución: {$maxExecutionTime}s (OK)");
        } else {
            $this->addWarning('⚠️', "Tiempo de ejecución: {$maxExecutionTime}s (Recomendado: >= 300s o 0)");
        }
    }
    
    private function testDatabaseConnection() {
        echo "🔗 Verificando conexión a base de datos...\n";
        
        require_once 'database_config.php';
        
        try {
            // Mostrar configuración detectada
            $config = DatabaseConfig::getConfig();
            $this->addResult('ℹ️', "Entorno detectado: {$config['environment']} (host: {$config['host']})");
            
            $pdo = DatabaseConfig::createConnection();
            $this->addResult('✅', "Conexión a base de datos: Exitosa");
            
            // Verificar versión de MySQL
            $stmt = $pdo->query("SELECT VERSION() as version");
            $version = $stmt->fetch()['version'];
            $this->addResult('ℹ️', "Versión de MySQL: $version");
            
            return $pdo;
            
        } catch (PDOException $e) {
            $this->addError('❌', "Conexión a base de datos: Falló - " . $e->getMessage());
            return null;
        }
    }
    
    private function testDatabaseStructure() {
        echo "🏗️ Verificando estructura de base de datos...\n";
        
        $pdo = $this->testDatabaseConnection();
        if (!$pdo) return;
        
        $requiredTables = [
            'organizations' => ['id', 'name', 'address', 'user_id', 'created_at', 'updated_at'],
            'persons' => ['id', 'name', 'emails', 'organization_id', 'created_at', 'updated_at'],
            'attribute_values' => ['id', 'attribute_id', 'entity_id', 'entity_type', 'text_value', 'json_value'],
            'users' => ['id', 'name', 'email']
        ];
        
        foreach ($requiredTables as $table => $columns) {
            try {
                $stmt = $pdo->query("DESCRIBE $table");
                $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                $this->addResult('✅', "Tabla $table: Existe");
                
                foreach ($columns as $column) {
                    if (in_array($column, $existingColumns)) {
                        $this->addResult('  ✅', "Columna $table.$column: Existe");
                    } else {
                        $this->addError('  ❌', "Columna $table.$column: No existe (REQUERIDA)");
                    }
                }
                
            } catch (PDOException $e) {
                $this->addError('❌', "Tabla $table: No existe o no accesible");
            }
        }
    }
    
    private function testCSVFile() {
        echo "📄 Verificando archivo CSV...\n";
        
        $csvFile = 'organizations_with_user_id.csv';
        
        if (!file_exists($csvFile)) {
            $this->addError('❌', "Archivo CSV: No encontrado ($csvFile)");
            return;
        }
        
        $this->addResult('✅', "Archivo CSV: Encontrado");
        
        // Verificar permisos de lectura
        if (!is_readable($csvFile)) {
            $this->addError('❌', "Archivo CSV: No legible");
            return;
        }
        
        $this->addResult('✅', "Archivo CSV: Legible");
        
        // Verificar estructura del CSV
        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            $headers = fgetcsv($handle, 1000, ",");
            
            $requiredHeaders = ['name', 'user_id'];
            $optionalHeaders = ['address', 'city', 'state', 'source', 'rut', 'email'];
            
            foreach ($requiredHeaders as $header) {
                if (in_array($header, $headers)) {
                    $this->addResult('  ✅', "Columna requerida '$header': Presente");
                } else {
                    $this->addError('  ❌', "Columna requerida '$header': Ausente");
                }
            }
            
            foreach ($optionalHeaders as $header) {
                if (in_array($header, $headers)) {
                    $this->addResult('  ✅', "Columna opcional '$header': Presente");
                }
            }
            
            // Contar registros
            $recordCount = 0;
            while (fgetcsv($handle) !== FALSE) {
                $recordCount++;
            }
            
            $this->addResult('ℹ️', "Total de registros en CSV: $recordCount");
            
            if ($recordCount > 10000) {
                $this->addWarning('⚠️', "Archivo grande ($recordCount registros) - Considere procesamiento por lotes");
            }
            
            fclose($handle);
        }
    }
    
    private function testFilePermissions() {
        echo "🔐 Verificando permisos de archivos...\n";
        
        $currentDir = getcwd();
        
        if (is_writable($currentDir)) {
            $this->addResult('✅', "Directorio actual: Escribible (para logs)");
        } else {
            $this->addWarning('⚠️', "Directorio actual: No escribible (logs pueden fallar)");
        }
        
        // Verificar si se pueden crear archivos de log
        $testLogFile = 'test_log_' . time() . '.tmp';
        if (file_put_contents($testLogFile, 'test') !== false) {
            $this->addResult('✅', "Creación de archivos de log: OK");
            unlink($testLogFile);
        } else {
            $this->addWarning('⚠️', "Creación de archivos de log: Falló");
        }
    }
    
    private function testKrayinTables() {
        echo "🎯 Verificando atributos específicos de Krayin...\n";
        
        $pdo = $this->testDatabaseConnection();
        if (!$pdo) return;
        
        // Verificar atributos específicos que usa el script
        $requiredAttributes = [
            34 => 'name',
            35 => 'address', 
            36 => 'user_id',
            21 => 'source',
            61 => 'rut'
        ];
        
        try {
            foreach ($requiredAttributes as $id => $name) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM attributes WHERE id = ?");
                $stmt->execute([$id]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    $this->addResult('✅', "Atributo ID $id ($name): Existe");
                } else {
                    $this->addError('❌', "Atributo ID $id ($name): No existe (REQUERIDO)");
                }
            }
            
            // Verificar usuarios existentes
            $stmt = $pdo->query("SELECT COUNT(*) FROM users");
            $userCount = $stmt->fetchColumn();
            $this->addResult('ℹ️', "Usuarios en sistema: $userCount");
            
            if ($userCount == 0) {
                $this->addWarning('⚠️', "No hay usuarios en el sistema");
            }
            
        } catch (PDOException $e) {
            $this->addError('❌', "Error verificando atributos: " . $e->getMessage());
        }
    }
    
    private function parseMemoryLimit($limit) {
        if ($limit === '-1') return PHP_INT_MAX;
        
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit)-1]);
        $limit = (int) $limit;
        
        switch($last) {
            case 'g': $limit *= 1024;
            case 'm': $limit *= 1024;
            case 'k': $limit *= 1024;
        }
        
        return $limit;
    }
    
    private function addResult($icon, $message) {
        $this->results[] = "$icon $message";
        echo "$icon $message\n";
    }
    
    private function addError($icon, $message) {
        $this->errors[] = "$icon $message";
        echo "$icon $message\n";
    }
    
    private function addWarning($icon, $message) {
        $this->warnings[] = "$icon $message";
        echo "$icon $message\n";
    }
    
    private function generateReport() {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📊 RESUMEN DE VALIDACIÓN\n";
        echo str_repeat("=", 60) . "\n";
        
        $totalTests = count($this->results);
        $errorCount = count($this->errors);
        $warningCount = count($this->warnings);
        $successCount = $totalTests - $errorCount - $warningCount;
        
        echo "✅ Pruebas exitosas: $successCount\n";
        echo "⚠️  Advertencias: $warningCount\n";
        echo "❌ Errores críticos: $errorCount\n";
        echo "📋 Total de pruebas: $totalTests\n\n";
        
        if ($errorCount == 0) {
            echo "🎉 ENTORNO LISTO PARA PROCESAMIENTO POR LOTES\n";
            echo "💡 Puede ejecutar: php insertar_organizaciones_lotes.php\n";
        } else {
            echo "🚨 ERRORES CRÍTICOS ENCONTRADOS\n";
            echo "⚠️  Corrija los errores antes de ejecutar el procesamiento\n";
            
            echo "\n🔧 ERRORES A CORREGIR:\n";
            foreach ($this->errors as $error) {
                echo "   $error\n";
            }
        }
        
        if ($warningCount > 0) {
            echo "\n💡 RECOMENDACIONES:\n";
            foreach ($this->warnings as $warning) {
                echo "   $warning\n";
            }
        }
        
        echo "\n" . str_repeat("=", 60) . "\n";
    }
}

// ==========================================
// EJECUCIÓN PRINCIPAL
// ==========================================

if (php_sapi_name() === 'cli' || isset($_GET['test'])) {
    $validator = new EnvironmentValidator();
    $success = $validator->runAllTests();
    
    exit($success ? 0 : 1);
} else {
    echo "Este script debe ejecutarse desde línea de comandos o con ?test=1\n";
}

?>
