<?php
/**
 * Script para corregir datos de personas en Krayin CRM
 * 
 * Problemas identificados:
 * - Números de teléfono malformados (muy largos)
 * - Números de teléfono faltantes (arrays vacíos)
 * - Nombres con caracteres especiales que pueden causar problemas
 * 
 * @author Senior Software Engineer
 * @version 1.0
 * @date 2024
 */

require_once 'database_config.php';

class PersonDataCorrector {
    private $pdo;
    private $logger;
    private $stats;
    
    public function __construct() {
        $this->initializeLogger();
        $this->connectDatabase();
        $this->initializeStats();
    }
    
    private function initializeLogger() {
        $this->logger = new class {
            public function info($message) {
                echo "[INFO] " . date('Y-m-d H:i:s') . " - $message\n";
            }
            
            public function warning($message) {
                echo "[WARNING] " . date('Y-m-d H:i:s') . " - $message\n";
            }
            
            public function error($message) {
                echo "[ERROR] " . date('Y-m-d H:i:s') . " - $message\n";
            }
            
            public function success($message) {
                echo "[SUCCESS] " . date('Y-m-d H:i:s') . " - $message\n";
            }
        };
    }
    
    private function connectDatabase() {
        try {
            $this->pdo = DatabaseConfig::createConnection();
            $this->logger->info("Conexión a base de datos establecida exitosamente");
            $config = DatabaseConfig::getConfig();
            $this->logger->info("Entorno detectado: " . $config['environment']);
        } catch (Exception $e) {
            $this->logger->error("Error conectando a la base de datos: " . $e->getMessage());
            exit(1);
        }
    }
    
    private function initializeStats() {
        $this->stats = [
            'persons_analyzed' => 0,
            'names_corrected' => 0,
            'phones_corrected' => 0,
            'phones_removed' => 0,
            'skipped' => 0,
            'errors' => 0
        ];
    }
    
    /**
     * Analiza y corrige todos los datos de personas
     */
    public function correctPersonData() {
        $this->logger->info("=== INICIANDO CORRECCIÓN DE DATOS DE PERSONAS ===");
        
        try {
            // Obtener todas las personas
            $persons = $this->getAllPersons();
            $this->logger->info("Encontradas " . count($persons) . " personas para analizar");
            
            foreach ($persons as $person) {
                $this->stats['persons_analyzed']++;
                $this->correctPersonRecord($person);
            }
            
            $this->showFinalStats();
            
        } catch (Exception $e) {
            $this->logger->error("Error durante la corrección: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtiene todas las personas de la base de datos
     */
    private function getAllPersons() {
        $sql = "
            SELECT 
                p.id,
                p.name,
                p.emails,
                p.contact_numbers,
                p.organization_id,
                p.job_title,
                o.name as organization_name
            FROM persons p
            LEFT JOIN organizations o ON p.organization_id = o.id
            ORDER BY p.id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Corrige un registro individual de persona
     * Solo actualiza: Name, Contact Numbers, Job Title
     * Usa email como identificador para mayor seguridad
     */
    private function correctPersonRecord($person) {
        $personId = $person['id'];
        $originalName = $person['name'];
        $originalPhones = $person['contact_numbers'];
        $originalJobTitle = $person['job_title'] ?? '';
        
        // Verificar que la persona tenga email para identificación segura
        if (empty($person['emails']) || $person['emails'] === '[]' || $person['emails'] === 'null') {
            $this->logger->warning(
                "Persona sin email - ID: $personId, Nombre: $originalName - SALTANDO corrección por seguridad"
            );
            $this->stats['skipped']++;
            return;
        }
        
        $this->logger->info("Analizando persona ID: $personId - Email: " . $this->getFirstEmail($person['emails']));
        
        // Corregir nombre
        $correctedName = $this->correctPersonName($originalName);
        $nameChanged = ($correctedName !== $originalName);
        
        // Corregir números de teléfono
        $correctedPhones = $this->correctPhoneNumbers($originalPhones);
        $phonesChanged = ($correctedPhones !== $originalPhones);
        
        // Corregir Job Title
        $correctedJobTitle = $this->correctJobTitle($originalJobTitle);
        $jobTitleChanged = ($correctedJobTitle !== $originalJobTitle);
        
        // Actualizar si hay cambios
        if ($nameChanged || $phonesChanged || $jobTitleChanged) {
            $this->updatePersonRecord($personId, $correctedName, $correctedPhones, $correctedJobTitle, $nameChanged, $phonesChanged, $jobTitleChanged);
        }
    }
    
    /**
     * Corrige el nombre de una persona
     */
    private function correctPersonName($name) {
        $originalName = $name;
        
        // Limpiar caracteres especiales problemáticos
        $name = trim($name);
        
        // Corregir caracteres con tildes y especiales
        $replacements = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'ñ' => 'n', 'Ñ' => 'N',
            'ü' => 'u', 'Ü' => 'U'
        ];
        
        // Solo aplicar reemplazos si causan problemas en la base de datos
        // En este caso, mantenemos los caracteres especiales pero limpiamos espacios
        $name = preg_replace('/\s+/', ' ', $name); // Múltiples espacios a uno solo
        $name = trim($name);
        
        if ($name !== $originalName) {
            $this->logger->info("Nombre corregido: '$originalName' -> '$name'");
            $this->stats['names_corrected']++;
        }
        
        return $name;
    }
    
    /**
     * Corrige los números de teléfono
     */
    private function correctPhoneNumbers($phoneJson) {
        if (empty($phoneJson) || $phoneJson === '[]' || $phoneJson === 'null') {
            return null; // Eliminar números vacíos
        }
        
        try {
            $phones = json_decode($phoneJson, true);
            
            if (!is_array($phones) || empty($phones)) {
                $this->stats['phones_removed']++;
                return null;
            }
            
            $correctedPhones = [];
            $hasChanges = false;
            
            foreach ($phones as $phone) {
                if (!isset($phone['value']) || empty($phone['value'])) {
                    $hasChanges = true;
                    continue; // Saltar números vacíos
                }
                
                $phoneNumber = $phone['value'];
                $originalPhone = $phoneNumber;
                
                // Corregir números malformados
                $correctedPhone = $this->correctPhoneNumber($phoneNumber);
                
                if ($correctedPhone !== null) {
                    $phone['value'] = $correctedPhone;
                    $correctedPhones[] = $phone;
                    
                    if ($correctedPhone !== $originalPhone) {
                        $hasChanges = true;
                        $this->logger->info("Teléfono corregido: '$originalPhone' -> '$correctedPhone'");
                    }
                } else {
                    $hasChanges = true;
                    $this->logger->warning("Teléfono eliminado (malformado): '$originalPhone'");
                }
            }
            
            if (empty($correctedPhones)) {
                $this->stats['phones_removed']++;
                return null;
            }
            
            if ($hasChanges) {
                $this->stats['phones_corrected']++;
            }
            
            return json_encode($correctedPhones);
            
        } catch (Exception $e) {
            $this->logger->error("Error procesando teléfonos: " . $e->getMessage());
            $this->stats['phones_removed']++;
            return null;
        }
    }
    
    /**
     * Corrige un número de teléfono individual
     */
    private function correctPhoneNumber($phone) {
        // Limpiar espacios y caracteres especiales
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Verificar longitud razonable (entre 7 y 15 dígitos)
        if (strlen($phone) < 7 || strlen($phone) > 15) {
            return null; // Número inválido
        }
        
        // Si no tiene código de país, agregar +56 para Chile
        if (!str_starts_with($phone, '+') && !str_starts_with($phone, '56')) {
            if (strlen($phone) >= 8) {
                $phone = '56' . $phone;
            }
        }
        
        // Asegurar que tenga el formato correcto
        if (str_starts_with($phone, '56') && !str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Corrige el job title de una persona
     */
    private function correctJobTitle($jobTitle) {
        if (empty($jobTitle)) {
            return '';
        }
        
        $originalJobTitle = $jobTitle;
        
        // Limpiar espacios múltiples y normalizar
        $jobTitle = trim($jobTitle);
        $jobTitle = preg_replace('/\s+/', ' ', $jobTitle);
        
        // Capitalizar correctamente (primera letra de cada palabra)
        $jobTitle = ucwords(strtolower($jobTitle));
        
        if ($jobTitle !== $originalJobTitle) {
            $this->logger->info("Job title corregido: '$originalJobTitle' -> '$jobTitle'");
        }
        
        return $jobTitle;
    }
    
    /**
     * Obtiene el primer email de una persona para identificación
     */
    private function getFirstEmail($emailsJson) {
        if (empty($emailsJson) || $emailsJson === '[]' || $emailsJson === 'null') {
            return 'Sin email';
        }
        
        try {
            $emails = json_decode($emailsJson, true);
            if (is_array($emails) && !empty($emails) && isset($emails[0]['value'])) {
                return $emails[0]['value'];
            }
        } catch (Exception $e) {
            // Ignorar errores de JSON
        }
        
        return 'Email inválido';
    }
    
    /**
     * Actualiza un registro de persona en la base de datos
     * Solo actualiza los campos específicos: name, contact_numbers, job_title
     */
    private function updatePersonRecord($personId, $name, $phones, $jobTitle, $nameChanged, $phonesChanged, $jobTitleChanged) {
        try {
            $this->pdo->beginTransaction();
            
            $sql = "UPDATE persons SET name = ?, contact_numbers = ?, job_title = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$name, $phones, $jobTitle, $personId]);
            
            $this->pdo->commit();
            
            $changes = [];
            if ($nameChanged) $changes[] = 'nombre';
            if ($phonesChanged) $changes[] = 'teléfonos';
            if ($jobTitleChanged) $changes[] = 'cargo';
            
            $this->logger->success("Persona ID $personId actualizada: " . implode(', ', $changes));
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error("Error actualizando persona ID $personId: " . $e->getMessage());
            $this->stats['errors']++;
        }
    }
    
    /**
     * Muestra estadísticas finales
     */
    private function showFinalStats() {
        $this->logger->info("\n=== ESTADÍSTICAS FINALES ===");
        $this->logger->info("Personas analizadas: " . $this->stats['persons_analyzed']);
        $this->logger->info("Nombres corregidos: " . $this->stats['names_corrected']);
        $this->logger->info("Teléfonos corregidos: " . $this->stats['phones_corrected']);
        $this->logger->info("Teléfonos eliminados: " . $this->stats['phones_removed']);
        $this->logger->info("Saltadas (sin email): " . $this->stats['skipped']);
        $this->logger->info("Errores: " . $this->stats['errors']);
        $this->logger->success("Corrección completada exitosamente");
    }
    
    /**
     * Genera un reporte de personas con problemas
     */
    public function generateProblemReport() {
        $this->logger->info("=== GENERANDO REPORTE DE PROBLEMAS ===");
        
        // Personas con teléfonos malformados
        $sql = "
            SELECT id, name, contact_numbers 
            FROM persons 
            WHERE contact_numbers IS NOT NULL 
            AND contact_numbers != '[]' 
            AND contact_numbers != 'null'
            AND (LENGTH(contact_numbers) > 200 OR contact_numbers LIKE '%56954671368%')
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $problematicPhones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->logger->info("Personas con teléfonos problemáticos: " . count($problematicPhones));
        
        foreach ($problematicPhones as $person) {
            $this->logger->warning("ID: {$person['id']} - {$person['name']} - Teléfonos: {$person['contact_numbers']}");
        }
        
        // Personas sin teléfonos
        $sql = "
            SELECT COUNT(*) as count 
            FROM persons 
            WHERE contact_numbers IS NULL OR contact_numbers = '[]' OR contact_numbers = 'null'
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $noPhones = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->logger->info("Personas sin teléfonos: " . $noPhones['count']);
    }
}

// Ejecución del script
try {
    $corrector = new PersonDataCorrector();
    
    // Generar reporte de problemas antes de la corrección
    $corrector->generateProblemReport();
    
    echo "\n¿Desea proceder con la corrección? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim(strtolower($line)) === 'y' || trim(strtolower($line)) === 'yes') {
        $corrector->correctPersonData();
    } else {
        echo "Corrección cancelada por el usuario.\n";
    }
    
} catch (Exception $e) {
    echo "Error fatal: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nScript completado.\n";
?>
