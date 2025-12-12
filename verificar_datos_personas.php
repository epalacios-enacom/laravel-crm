<?php
/**
 * Script para verificar y analizar datos de personas en Krayin CRM
 * 
 * Este script genera un reporte detallado de los problemas en los datos
 * de personas, incluyendo números de teléfono malformados, emails inválidos,
 * y otros problemas de calidad de datos.
 * 
 * @author Senior Software Engineer
 * @version 1.0
 * @date 2024
 */

require_once 'database_config.php';

class PersonDataVerifier {
    private $pdo;
    private $logger;
    private $issues;
    
    public function __construct() {
        $this->initializeLogger();
        $this->connectDatabase();
        $this->initializeIssues();
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
    
    private function initializeIssues() {
        $this->issues = [
            'malformed_phones' => [],
            'missing_phones' => [],
            'invalid_emails' => [],
            'missing_emails' => [],
            'missing_names' => [],
            'duplicate_persons' => [],
            'orphaned_persons' => [],
            'long_phone_numbers' => [],
            'invalid_json' => []
        ];
    }
    
    /**
     * Ejecuta la verificación completa de datos
     */
    public function verifyAllData() {
        $this->logger->info("=== INICIANDO VERIFICACIÓN DE DATOS DE PERSONAS ===");
        
        $this->checkBasicStats();
        $this->checkPhoneNumbers();
        $this->checkEmails();
        $this->checkNames();
        $this->checkDuplicates();
        $this->checkOrphanedPersons();
        $this->checkJSONIntegrity();
        
        $this->generateDetailedReport();
    }
    
    /**
     * Verifica estadísticas básicas
     */
    private function checkBasicStats() {
        $this->logger->info("\n=== ESTADÍSTICAS BÁSICAS ===");
        
        // Total de personas
        $sql = "SELECT COUNT(*) as total FROM persons";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $this->logger->info("Total de personas: $total");
        
        // Personas con organizaciones
        $sql = "
            SELECT COUNT(*) as with_org 
            FROM persons p 
            INNER JOIN organizations o ON p.organization_id = o.id
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $withOrg = $stmt->fetch(PDO::FETCH_ASSOC)['with_org'];
        $this->logger->info("Personas con organización válida: $withOrg");
        
        // Personas con teléfonos
        $sql = "
            SELECT COUNT(*) as with_phones 
            FROM persons 
            WHERE contact_numbers IS NOT NULL 
            AND contact_numbers != '[]' 
            AND contact_numbers != 'null'
            AND contact_numbers != ''
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $withPhones = $stmt->fetch(PDO::FETCH_ASSOC)['with_phones'];
        $this->logger->info("Personas con teléfonos: $withPhones");
        
        // Personas con emails
        $sql = "
            SELECT COUNT(*) as with_emails 
            FROM persons 
            WHERE emails IS NOT NULL 
            AND emails != '[]' 
            AND emails != 'null'
            AND emails != ''
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $withEmails = $stmt->fetch(PDO::FETCH_ASSOC)['with_emails'];
        $this->logger->info("Personas con emails: $withEmails");
    }
    
    /**
     * Verifica números de teléfono
     */
    private function checkPhoneNumbers() {
        $this->logger->info("\n=== VERIFICANDO NÚMEROS DE TELÉFONO ===");
        
        // Obtener todas las personas con teléfonos
        $sql = "
            SELECT id, name, contact_numbers 
            FROM persons 
            WHERE contact_numbers IS NOT NULL 
            AND contact_numbers != '[]' 
            AND contact_numbers != 'null'
            AND contact_numbers != ''
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $persons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($persons as $person) {
            $this->analyzePhoneNumbers($person);
        }
        
        // Personas sin teléfonos
        $sql = "
            SELECT id, name 
            FROM persons 
            WHERE contact_numbers IS NULL 
            OR contact_numbers = '[]' 
            OR contact_numbers = 'null'
            OR contact_numbers = ''
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $noPhones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($noPhones as $person) {
            $this->issues['missing_phones'][] = $person;
        }
        
        $this->logger->info("Teléfonos malformados encontrados: " . count($this->issues['malformed_phones']));
        $this->logger->info("Teléfonos muy largos encontrados: " . count($this->issues['long_phone_numbers']));
        $this->logger->info("Personas sin teléfonos: " . count($this->issues['missing_phones']));
    }
    
    /**
     * Analiza los números de teléfono de una persona
     */
    private function analyzePhoneNumbers($person) {
        try {
            $phones = json_decode($person['contact_numbers'], true);
            
            if (!is_array($phones)) {
                $this->issues['invalid_json'][] = [
                    'person' => $person,
                    'field' => 'contact_numbers',
                    'value' => $person['contact_numbers']
                ];
                return;
            }
            
            foreach ($phones as $phone) {
                if (!isset($phone['value'])) continue;
                
                $phoneNumber = $phone['value'];
                
                // Verificar longitud excesiva
                if (strlen($phoneNumber) > 15) {
                    $this->issues['long_phone_numbers'][] = [
                        'person' => $person,
                        'phone' => $phoneNumber,
                        'length' => strlen($phoneNumber)
                    ];
                }
                
                // Verificar formato
                $cleanPhone = preg_replace('/[^0-9+]/', '', $phoneNumber);
                if (strlen($cleanPhone) < 7 || strlen($cleanPhone) > 15) {
                    $this->issues['malformed_phones'][] = [
                        'person' => $person,
                        'phone' => $phoneNumber,
                        'clean_phone' => $cleanPhone
                    ];
                }
            }
            
        } catch (Exception $e) {
            $this->issues['invalid_json'][] = [
                'person' => $person,
                'field' => 'contact_numbers',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verifica emails
     */
    private function checkEmails() {
        $this->logger->info("\n=== VERIFICANDO EMAILS ===");
        
        // Obtener todas las personas con emails
        $sql = "
            SELECT id, name, emails 
            FROM persons 
            WHERE emails IS NOT NULL 
            AND emails != '[]' 
            AND emails != 'null'
            AND emails != ''
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $persons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($persons as $person) {
            $this->analyzeEmails($person);
        }
        
        // Personas sin emails
        $sql = "
            SELECT id, name 
            FROM persons 
            WHERE emails IS NULL 
            OR emails = '[]' 
            OR emails = 'null'
            OR emails = ''
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $noEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($noEmails as $person) {
            $this->issues['missing_emails'][] = $person;
        }
        
        $this->logger->info("Emails inválidos encontrados: " . count($this->issues['invalid_emails']));
        $this->logger->info("Personas sin emails: " . count($this->issues['missing_emails']));
    }
    
    /**
     * Analiza los emails de una persona
     */
    private function analyzeEmails($person) {
        try {
            $emails = json_decode($person['emails'], true);
            
            if (!is_array($emails)) {
                $this->issues['invalid_json'][] = [
                    'person' => $person,
                    'field' => 'emails',
                    'value' => $person['emails']
                ];
                return;
            }
            
            foreach ($emails as $email) {
                if (!isset($email['value'])) continue;
                
                if (!filter_var($email['value'], FILTER_VALIDATE_EMAIL)) {
                    $this->issues['invalid_emails'][] = [
                        'person' => $person,
                        'email' => $email['value']
                    ];
                }
            }
            
        } catch (Exception $e) {
            $this->issues['invalid_json'][] = [
                'person' => $person,
                'field' => 'emails',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verifica nombres
     */
    private function checkNames() {
        $this->logger->info("\n=== VERIFICANDO NOMBRES ===");
        
        $sql = "
            SELECT id, name, organization_id 
            FROM persons 
            WHERE name IS NULL OR name = '' OR TRIM(name) = ''
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $missingNames = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->issues['missing_names'] = $missingNames;
        $this->logger->info("Personas sin nombre: " . count($missingNames));
    }
    
    /**
     * Verifica duplicados
     */
    private function checkDuplicates() {
        $this->logger->info("\n=== VERIFICANDO DUPLICADOS ===");
        
        $sql = "
            SELECT name, organization_id, COUNT(*) as count, GROUP_CONCAT(id) as ids
            FROM persons 
            WHERE name IS NOT NULL AND name != ''
            GROUP BY name, organization_id 
            HAVING COUNT(*) > 1
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->issues['duplicate_persons'] = $duplicates;
        $this->logger->info("Grupos de personas duplicadas: " . count($duplicates));
    }
    
    /**
     * Verifica personas huérfanas
     */
    private function checkOrphanedPersons() {
        $this->logger->info("\n=== VERIFICANDO PERSONAS HUÉRFANAS ===");
        
        $sql = "
            SELECT p.id, p.name, p.organization_id 
            FROM persons p 
            LEFT JOIN organizations o ON p.organization_id = o.id 
            WHERE o.id IS NULL
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $orphaned = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->issues['orphaned_persons'] = $orphaned;
        $this->logger->info("Personas huérfanas (sin organización): " . count($orphaned));
    }
    
    /**
     * Verifica integridad de JSON
     */
    private function checkJSONIntegrity() {
        $this->logger->info("\n=== VERIFICANDO INTEGRIDAD JSON ===");
        $this->logger->info("Campos con JSON inválido: " . count($this->issues['invalid_json']));
    }
    
    /**
     * Genera reporte detallado
     */
    private function generateDetailedReport() {
        $this->logger->info("\n=== REPORTE DETALLADO DE PROBLEMAS ===");
        
        // Teléfonos muy largos
        if (!empty($this->issues['long_phone_numbers'])) {
            $this->logger->warning("\n--- TELÉFONOS EXCESIVAMENTE LARGOS ---");
            foreach (array_slice($this->issues['long_phone_numbers'], 0, 10) as $issue) {
                $this->logger->warning(
                    "ID: {$issue['person']['id']} - {$issue['person']['name']} - " .
                    "Teléfono: {$issue['phone']} (Longitud: {$issue['length']})"
                );
            }
            if (count($this->issues['long_phone_numbers']) > 10) {
                $remaining = count($this->issues['long_phone_numbers']) - 10;
                $this->logger->warning("... y $remaining más");
            }
        }
        
        // Teléfonos malformados
        if (!empty($this->issues['malformed_phones'])) {
            $this->logger->warning("\n--- TELÉFONOS MALFORMADOS ---");
            foreach (array_slice($this->issues['malformed_phones'], 0, 10) as $issue) {
                $this->logger->warning(
                    "ID: {$issue['person']['id']} - {$issue['person']['name']} - " .
                    "Original: {$issue['phone']} - Limpio: {$issue['clean_phone']}"
                );
            }
            if (count($this->issues['malformed_phones']) > 10) {
                $remaining = count($this->issues['malformed_phones']) - 10;
                $this->logger->warning("... y $remaining más");
            }
        }
        
        // Duplicados
        if (!empty($this->issues['duplicate_persons'])) {
            $this->logger->warning("\n--- PERSONAS DUPLICADAS ---");
            foreach ($this->issues['duplicate_persons'] as $duplicate) {
                $this->logger->warning(
                    "Nombre: {$duplicate['name']} - Organización: {$duplicate['organization_id']} - " .
                    "Cantidad: {$duplicate['count']} - IDs: {$duplicate['ids']}"
                );
            }
        }
        
        // Personas huérfanas
        if (!empty($this->issues['orphaned_persons'])) {
            $this->logger->warning("\n--- PERSONAS HUÉRFANAS ---");
            foreach (array_slice($this->issues['orphaned_persons'], 0, 10) as $orphan) {
                $this->logger->warning(
                    "ID: {$orphan['id']} - {$orphan['name']} - " .
                    "Organización inexistente: {$orphan['organization_id']}"
                );
            }
            if (count($this->issues['orphaned_persons']) > 10) {
                $remaining = count($this->issues['orphaned_persons']) - 10;
                $this->logger->warning("... y $remaining más");
            }
        }
        
        // JSON inválido
        if (!empty($this->issues['invalid_json'])) {
            $this->logger->error("\n--- CAMPOS CON JSON INVÁLIDO ---");
            foreach ($this->issues['invalid_json'] as $issue) {
                $this->logger->error(
                    "ID: {$issue['person']['id']} - {$issue['person']['name']} - " .
                    "Campo: {$issue['field']}"
                );
            }
        }
        
        $this->generateSummary();
    }
    
    /**
     * Genera resumen final
     */
    private function generateSummary() {
        $this->logger->info("\n=== RESUMEN DE PROBLEMAS ===");
        
        $totalIssues = 0;
        foreach ($this->issues as $type => $issues) {
            $count = count($issues);
            $totalIssues += $count;
            if ($count > 0) {
                $this->logger->info(ucfirst(str_replace('_', ' ', $type)) . ": $count");
            }
        }
        
        if ($totalIssues === 0) {
            $this->logger->success("¡No se encontraron problemas en los datos!");
        } else {
            $this->logger->warning("Total de problemas encontrados: $totalIssues");
            $this->logger->info("\nRecomendación: Ejecutar el script de corrección 'corregir_datos_personas.php'");
        }
    }
}

// Ejecución del script
try {
    $verifier = new PersonDataVerifier();
    $verifier->verifyAllData();
    
} catch (Exception $e) {
    echo "Error fatal: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nVerificación completada.\n";
?>
