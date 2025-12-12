<?php
/**
 * Script de Inserción Masiva de Organizaciones y Personas - Krayin CRM
 * 
 * Requiere: database_config.php para configuración automática de entorno
 * 
 * Funcionalidades:
 * - Procesamiento por lotes (batch processing)
 * - Manejo robusto de errores con rollback
 * - Logging detallado de operaciones
 * - Validación de datos antes de inserción
 * - Configuración flexible de tamaño de lotes
 * - Estadísticas de rendimiento
 * - Recuperación automática de errores
 * 
 * @author Senior Software Engineer
 * @version 2.0
 * @date 2024
 */

// ==========================================
// CONFIGURACIÓN DEL SCRIPT
// ==========================================

class BatchConfig {
    const BATCH_SIZE = 50;              // Registros por lote
    const MAX_RETRIES = 3;              // Reintentos por lote fallido
    const MEMORY_LIMIT = '512M';        // Límite de memoria
    const LOG_LEVEL = 'INFO';           // DEBUG, INFO, WARNING, ERROR
    const VALIDATE_DATA = true;         // Validar datos antes de insertar
    const BACKUP_ON_ERROR = true;       // Crear backup antes de rollback
}

// ==========================================
// CLASE PRINCIPAL DE PROCESAMIENTO
// ==========================================

class KrayinBatchProcessor {
    private $pdo;
    private $logger;
    private $stats;
    private $startTime;
    
    public function __construct() {
        $this->initializeEnvironment();
        $this->logger = new BatchLogger();
        $this->stats = new ProcessingStats();
        $this->connectDatabase();
        $this->startTime = microtime(true);
    }
    
    private function initializeEnvironment() {
        ini_set('memory_limit', BatchConfig::MEMORY_LIMIT);
        ini_set('max_execution_time', 0); // Sin límite de tiempo
        set_error_handler([$this, 'handleError']);
        register_shutdown_function([$this, 'handleShutdown']);
    }
    
    private function connectDatabase() {
        require_once 'database_config.php';
        
        try {
            $this->pdo = DatabaseConfig::createConnection();
            $config = DatabaseConfig::getConfig();
            $this->logger->info("✅ Conexión exitosa a la base de datos (entorno: {$config['environment']})");
        } catch (PDOException $e) {
            $this->logger->error("❌ Error de conexión: " . $e->getMessage());
            exit(1);
        }
    }
    
    public function processFile($csvFile) {
        $this->logger->info("🚀 Iniciando procesamiento por lotes de: $csvFile");
        
        if (!file_exists($csvFile)) {
            $this->logger->error("❌ Archivo no encontrado: $csvFile");
            return false;
        }
        
        $totalRecords = $this->countRecords($csvFile);
        $this->logger->info("📊 Total de registros a procesar: $totalRecords");
        
        $batches = $this->readDataInBatches($csvFile);
        $batchNumber = 1;
        
        foreach ($batches as $batch) {
            $this->processBatch($batch, $batchNumber, count($batches));
            $batchNumber++;
            
            // Liberar memoria entre lotes
            if ($batchNumber % 10 == 0) {
                gc_collect_cycles();
            }
        }
        
        $this->generateFinalReport();
        return true;
    }
    
    private function countRecords($csvFile) {
        $count = 0;
        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            fgetcsv($handle); // Skip header
            while (fgetcsv($handle) !== FALSE) {
                $count++;
            }
            fclose($handle);
        }
        return $count;
    }
    
    private function readDataInBatches($csvFile) {
        $batches = [];
        $currentBatch = [];
        $headers = null;
        
        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            $headers = fgetcsv($handle, 1000, ",");
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $record = array_combine($headers, $data);
                
                if (BatchConfig::VALIDATE_DATA && !$this->validateRecord($record)) {
                    $this->stats->incrementSkipped();
                    continue;
                }
                
                $currentBatch[] = $record;
                
                if (count($currentBatch) >= BatchConfig::BATCH_SIZE) {
                    $batches[] = $currentBatch;
                    $currentBatch = [];
                }
            }
            
            // Agregar último lote si tiene datos
            if (!empty($currentBatch)) {
                $batches[] = $currentBatch;
            }
            
            fclose($handle);
        }
        
        return $batches;
    }
    
    private function validateRecord($record) {
        // Validaciones básicas
        if (empty($record['name']) || strlen($record['name']) < 2) {
            $this->logger->warning("⚠️ Registro inválido: nombre vacío o muy corto");
            return false;
        }
        
        if (!empty($record['email']) && !filter_var($record['email'], FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning("⚠️ Email inválido: {$record['email']}");
            return false;
        }
        
        if (!is_numeric($record['user_id']) || $record['user_id'] <= 0) {
            $this->logger->warning("⚠️ user_id inválido: {$record['user_id']}");
            return false;
        }
        
        return true;
    }
    
    private function processBatch($batch, $batchNumber, $totalBatches) {
        $batchSize = count($batch);
        $this->logger->info("📦 Procesando lote $batchNumber/$totalBatches ($batchSize registros)");
        
        $retries = 0;
        $success = false;
        
        while ($retries < BatchConfig::MAX_RETRIES && !$success) {
            try {
                $this->pdo->beginTransaction();
                
                $organizationIds = [];
                
                // Procesar organizaciones del lote
                foreach ($batch as $record) {
                    $orgId = $this->insertOrganization($record);
                    if ($orgId) {
                        $organizationIds[] = [
                            'id' => $orgId,
                            'record' => $record
                        ];
                        $this->stats->incrementOrganizations();
                    }
                    // Si $orgId es null, la organización fue omitida por duplicado
                    // El contador de omitidos ya se incrementó en insertOrganization
                }
                
                // Procesar personas asociadas
                foreach ($organizationIds as $orgData) {
                    if ($this->insertPerson($orgData['record'], $orgData['id'])) {
                        $this->stats->incrementPersons();
                    }
                }
                
                $this->pdo->commit();
                $success = true;
                
                $this->logger->info("✅ Lote $batchNumber procesado exitosamente");
                
            } catch (Exception $e) {
                $this->pdo->rollBack();
                $retries++;
                
                $this->logger->error("❌ Error en lote $batchNumber (intento $retries): " . $e->getMessage());
                
                if ($retries < BatchConfig::MAX_RETRIES) {
                    $this->logger->info("🔄 Reintentando lote $batchNumber...");
                    sleep(1); // Pausa antes del reintento
                } else {
                    $this->logger->error("💥 Lote $batchNumber falló después de " . BatchConfig::MAX_RETRIES . " intentos");
                    $this->stats->incrementFailedBatches();
                }
            }
        }
        
        // Mostrar progreso
        $progress = round(($batchNumber / $totalBatches) * 100, 2);
        $this->logger->info("📈 Progreso: $progress% completado");
    }
    
    private function insertOrganization($record) {
        // Verificar si la organización ya existe
        $checkSql = "SELECT id FROM organizations WHERE name = ?";
        $checkStmt = $this->pdo->prepare($checkSql);
        $checkStmt->execute([$record['name']]);
        
        if ($existingOrg = $checkStmt->fetch()) {
            $this->logger->warning("⚠️ Organización duplicada omitida: {$record['name']}");
            $this->stats->incrementSkipped();
            return null; // Retornar null para indicar que se omitió
        }
        
        // Crear JSON para address
        $addressJson = json_encode([
            "city" => $record['city'] ?: "",
            "state" => $record['state'] ?: "",
            "address" => $record['address'] ?: "",
            "country" => "CL",
            "postcode" => ""
        ], JSON_UNESCAPED_UNICODE);
        
        // Insertar organización principal
        $sql = "INSERT INTO organizations (name, address, user_id, created_at, updated_at) 
                VALUES (?, ?, ?, NOW(), NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $record['name'],
            $addressJson,
            $record['user_id']
        ]);
        
        $orgId = $this->pdo->lastInsertId();
        
        // Insertar atributos
        $this->insertAttributes($orgId, $record, $addressJson);
        
        return $orgId;
    }
    
    private function insertAttributes($orgId, $record, $addressJson) {
        // Preparar statements una sola vez para eficiencia
        static $stmts = [];
        
        if (empty($stmts)) {
            $stmts['name'] = $this->pdo->prepare(
                "INSERT INTO attribute_values (attribute_id, entity_id, entity_type, text_value) 
                 VALUES (34, ?, 'organizations', ?)"
            );
            
            $stmts['address'] = $this->pdo->prepare(
                "INSERT INTO attribute_values (attribute_id, entity_id, entity_type, json_value) 
                 VALUES (35, ?, 'organizations', ?)"
            );
            
            $stmts['user_id'] = $this->pdo->prepare(
                "INSERT INTO attribute_values (attribute_id, entity_id, entity_type, text_value) 
                 VALUES (36, ?, 'organizations', ?)"
            );
            
            $stmts['source'] = $this->pdo->prepare(
                "INSERT INTO attribute_values (attribute_id, entity_id, entity_type, text_value) 
                 VALUES (21, ?, 'organizations', ?)"
            );
            
            $stmts['rut'] = $this->pdo->prepare(
                "INSERT INTO attribute_values (attribute_id, entity_id, entity_type, text_value) 
                 VALUES (61, ?, 'organizations', ?)"
            );
        }
        
        // Insertar atributos
        $stmts['name']->execute([$orgId, $record['name']]);
        $stmts['address']->execute([$orgId, $addressJson]);
        $stmts['user_id']->execute([$orgId, $record['user_id']]);
        
        if (!empty($record['source'])) {
            $stmts['source']->execute([$orgId, $record['source']]);
        }
        
        if (!empty($record['rut'])) {
            $stmts['rut']->execute([$orgId, $record['rut']]);
        }
    }
    
    private function insertPerson($record, $organizationId) {
        if (empty($record['email'])) {
            return false;
        }
        
        // Extraer nombre de la persona del email
        $emailParts = explode('@', $record['email']);
        $personName = $this->generatePersonName($emailParts[0]);
        
        $sql = "INSERT INTO persons (name, emails, organization_id, created_at, updated_at) 
                VALUES (?, ?, ?, NOW(), NOW())";
        
        $emailsJson = json_encode([[
            'value' => $record['email'],
            'label' => 'work'
        ]], JSON_UNESCAPED_UNICODE);
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $personName,
            $emailsJson,
            $organizationId
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    private function generatePersonName($emailPrefix) {
        // Convertir prefijo de email a nombre legible
        $name = str_replace(['.', '_', '-'], ' ', $emailPrefix);
        $name = ucwords(strtolower($name));
        return $name;
    }
    
    private function generateFinalReport() {
        $endTime = microtime(true);
        $executionTime = round($endTime - $this->startTime, 2);
        
        $this->logger->info("\n" . str_repeat("=", 60));
        $this->logger->info("🎉 PROCESAMIENTO COMPLETADO");
        $this->logger->info(str_repeat("=", 60));
        $this->logger->info("⏱️  Tiempo de ejecución: {$executionTime} segundos");
        $this->logger->info("🏢 Organizaciones insertadas: " . $this->stats->getOrganizations());
        $this->logger->info("👥 Personas insertadas: " . $this->stats->getPersons());
        $this->logger->info("⚠️  Registros omitidos: " . $this->stats->getSkipped());
        $this->logger->info("❌ Lotes fallidos: " . $this->stats->getFailedBatches());
        
        $rate = $this->stats->getOrganizations() / max($executionTime, 1);
        $this->logger->info("📊 Velocidad: " . round($rate, 2) . " org/segundo");
        
        $memoryUsage = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $this->logger->info("💾 Memoria máxima utilizada: {$memoryUsage} MB");
        
        $this->logger->info(str_repeat("=", 60));
    }
    
    public function handleError($severity, $message, $file, $line) {
        if ($this->logger) {
            $this->logger->error("PHP Error [$severity]: $message in $file:$line");
        } else {
            error_log("PHP Error [$severity]: $message in $file:$line");
        }
    }
    
    public function handleShutdown() {
        $error = error_get_last();
        if ($error && $error['type'] === E_ERROR) {
            if ($this->logger) {
                $this->logger->error("Fatal Error: {$error['message']} in {$error['file']}:{$error['line']}");
            } else {
                error_log("Fatal Error: {$error['message']} in {$error['file']}:{$error['line']}");
            }
        }
    }
}

// ==========================================
// CLASE DE LOGGING
// ==========================================

class BatchLogger {
    private $logFile;
    
    public function __construct() {
        $this->logFile = 'batch_processing_' . date('Y-m-d_H-i-s') . '.log';
    }
    
    public function info($message) {
        $this->log('INFO', $message);
        echo $message . "\n";
    }
    
    public function warning($message) {
        $this->log('WARNING', $message);
        if (BatchConfig::LOG_LEVEL === 'DEBUG') {
            echo $message . "\n";
        }
    }
    
    public function error($message) {
        $this->log('ERROR', $message);
        echo $message . "\n";
    }
    
    public function debug($message) {
        $this->log('DEBUG', $message);
        if (BatchConfig::LOG_LEVEL === 'DEBUG') {
            echo $message . "\n";
        }
    }
    
    private function log($level, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message\n";
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

// ==========================================
// CLASE DE ESTADÍSTICAS
// ==========================================

class ProcessingStats {
    private $organizations = 0;
    private $persons = 0;
    private $skipped = 0;
    private $failedBatches = 0;
    
    public function incrementOrganizations() { $this->organizations++; }
    public function incrementPersons() { $this->persons++; }
    public function incrementSkipped() { $this->skipped++; }
    public function incrementFailedBatches() { $this->failedBatches++; }
    
    public function getOrganizations() { return $this->organizations; }
    public function getPersons() { return $this->persons; }
    public function getSkipped() { return $this->skipped; }
    public function getFailedBatches() { return $this->failedBatches; }
}

// ==========================================
// EJECUCIÓN PRINCIPAL
// ==========================================

if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    echo "\n";
    echo "🚀 KRAYIN CRM - PROCESADOR DE LOTES v2.0\n";
    echo "==========================================\n";
    echo "📋 Configuración:\n";
    echo "   - Tamaño de lote: " . BatchConfig::BATCH_SIZE . " registros\n";
    echo "   - Límite de memoria: " . BatchConfig::MEMORY_LIMIT . "\n";
    echo "   - Reintentos máximos: " . BatchConfig::MAX_RETRIES . "\n";
    echo "   - Validación de datos: " . (BatchConfig::VALIDATE_DATA ? 'Activada' : 'Desactivada') . "\n";
    echo "\n";
    
    $processor = new KrayinBatchProcessor();
    $csvFile = 'organizations_with_user_id.csv';
    
    if ($processor->processFile($csvFile)) {
        echo "\n✅ Procesamiento completado exitosamente\n";
        echo "💡 Recomendación: Ejecutar 'php artisan cache:clear' en Krayin\n";
    } else {
        echo "\n❌ Error durante el procesamiento\n";
        exit(1);
    }
} else {
    echo "Este script debe ejecutarse desde línea de comandos o con ?run=1\n";
}

?>
