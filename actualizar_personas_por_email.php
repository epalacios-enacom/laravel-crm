<?php
/**
 * Script para actualizar datos específicos de personas en Krayin CRM
 * 
 * Actualiza ÚNICAMENTE:
 * - Name (nombre)
 * - Contact Numbers (números de teléfono)
 * - Job Title (cargo/puesto)
 * 
 * Usa el EMAIL como identificador único para mayor seguridad
 * 
 * @author Senior Software Engineer
 * @version 1.0
 * @date 2024
 */

require_once 'database_config.php';

class PersonUpdaterByEmail {
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
            'total_found' => 0,
            'updated' => 0,
            'no_changes' => 0,
            'errors' => 0,
            'without_email' => 0
        ];
    }
    
    /**
     * Actualiza una persona específica por email
     */
    public function updatePersonByEmail($email, $newName = null, $newPhones = null, $newJobTitle = null) {
        try {
            // Buscar persona por email
            $person = $this->findPersonByEmail($email);
            
            if (!$person) {
                $this->logger->warning("No se encontró persona con email: $email");
                return false;
            }
            
            $this->stats['total_found']++;
            
            // Preparar actualizaciones
            $updates = [];
            $params = ['id' => $person['id']];
            
            if ($newName !== null && $newName !== $person['name']) {
                $updates[] = 'name = :name';
                $params['name'] = $this->cleanName($newName);
                $this->logger->info("Nombre: '{$person['name']}' -> '$newName'");
            }
            
            if ($newPhones !== null) {
                $cleanPhones = $this->formatPhones($newPhones);
                if ($cleanPhones !== $person['contact_numbers']) {
                    $updates[] = 'contact_numbers = :contact_numbers';
                    $params['contact_numbers'] = $cleanPhones;
                    $this->logger->info("Teléfonos actualizados");
                }
            }
            
            if ($newJobTitle !== null && $newJobTitle !== $person['job_title']) {
                $updates[] = 'job_title = :job_title';
                $params['job_title'] = $this->cleanJobTitle($newJobTitle);
                $this->logger->info("Cargo: '{$person['job_title']}' -> '$newJobTitle'");
            }
            
            if (empty($updates)) {
                $this->logger->info("No hay cambios para aplicar en: $email");
                $this->stats['no_changes']++;
                return true;
            }
            
            // Ejecutar actualización
            $sql = "UPDATE persons SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            if ($stmt->rowCount() > 0) {
                $this->logger->success("Persona actualizada exitosamente: $email");
                $this->stats['updated']++;
                return true;
            } else {
                $this->logger->warning("No se pudo actualizar la persona: $email");
                return false;
            }
            
        } catch (Exception $e) {
            $this->logger->error("Error actualizando persona $email: " . $e->getMessage());
            $this->stats['errors']++;
            return false;
        }
    }
    
    /**
     * Actualiza múltiples personas desde un array
     */
    public function updateMultiplePersons($personsData) {
        $this->logger->info("=== INICIANDO ACTUALIZACIÓN MASIVA ===");
        $this->logger->info("Total de personas a procesar: " . count($personsData));
        
        foreach ($personsData as $index => $personData) {
            $this->logger->info("\n--- Procesando persona " . ($index + 1) . " ---");
            
            if (!isset($personData['email'])) {
                $this->logger->error("Email requerido para persona en índice $index");
                $this->stats['errors']++;
                continue;
            }
            
            $this->updatePersonByEmail(
                $personData['email'],
                $personData['name'] ?? null,
                $personData['phones'] ?? null,
                $personData['job_title'] ?? null
            );
        }
        
        $this->showFinalStats();
    }
    
    /**
     * Busca una persona por email
     */
    private function findPersonByEmail($email) {
        $sql = "
            SELECT id, name, contact_numbers, job_title, emails 
            FROM persons 
            WHERE emails LIKE :email
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['email' => "%$email%"]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Verificar coincidencia exacta en el JSON
        foreach ($results as $person) {
            if ($this->emailExistsInPerson($person['emails'], $email)) {
                return $person;
            }
        }
        
        return null;
    }
    
    /**
     * Verifica si un email existe en el JSON de emails de una persona
     */
    private function emailExistsInPerson($emailsJson, $targetEmail) {
        try {
            $emails = json_decode($emailsJson, true);
            if (!is_array($emails)) return false;
            
            foreach ($emails as $emailData) {
                if (isset($emailData['value']) && 
                    strtolower($emailData['value']) === strtolower($targetEmail)) {
                    return true;
                }
            }
        } catch (Exception $e) {
            // JSON inválido
        }
        
        return false;
    }
    
    /**
     * Limpia y normaliza un nombre
     */
    private function cleanName($name) {
        if (empty($name)) return '';
        
        // Limpiar espacios múltiples
        $name = trim($name);
        $name = preg_replace('/\s+/', ' ', $name);
        
        // Capitalizar correctamente
        $name = ucwords(strtolower($name));
        
        return $name;
    }
    
    /**
     * Limpia y normaliza un job title
     */
    private function cleanJobTitle($jobTitle) {
        if (empty($jobTitle)) return '';
        
        // Limpiar espacios múltiples
        $jobTitle = trim($jobTitle);
        $jobTitle = preg_replace('/\s+/', ' ', $jobTitle);
        
        // Capitalizar correctamente
        $jobTitle = ucwords(strtolower($jobTitle));
        
        return $jobTitle;
    }
    
    /**
     * Formatea números de teléfono
     */
    private function formatPhones($phones) {
        if (empty($phones)) {
            return '[]';
        }
        
        // Si ya es un array, procesarlo
        if (is_array($phones)) {
            $formattedPhones = [];
            foreach ($phones as $phone) {
                $cleanPhone = $this->cleanPhoneNumber($phone);
                if (!empty($cleanPhone)) {
                    $formattedPhones[] = [
                        'value' => $cleanPhone,
                        'label' => 'work'
                    ];
                }
            }
            return json_encode($formattedPhones);
        }
        
        // Si es una cadena, intentar limpiarla
        if (is_string($phones)) {
            $cleanPhone = $this->cleanPhoneNumber($phones);
            if (!empty($cleanPhone)) {
                return json_encode([[
                    'value' => $cleanPhone,
                    'label' => 'work'
                ]]);
            }
        }
        
        return '[]';
    }
    
    /**
     * Limpia un número de teléfono individual
     */
    private function cleanPhoneNumber($phone) {
        if (empty($phone)) return '';
        
        // Convertir a string si no lo es
        $phone = (string) $phone;
        
        // Remover caracteres no numéricos excepto + al inicio
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Validar longitud
        if (strlen($phone) < 7 || strlen($phone) > 15) {
            return '';
        }
        
        return $phone;
    }
    
    /**
     * Muestra estadísticas finales
     */
    private function showFinalStats() {
        $this->logger->info("\n=== ESTADÍSTICAS FINALES ===");
        $this->logger->info("Personas encontradas: {$this->stats['total_found']}");
        $this->logger->info("Actualizadas exitosamente: {$this->stats['updated']}");
        $this->logger->info("Sin cambios necesarios: {$this->stats['no_changes']}");
        $this->logger->info("Errores: {$this->stats['errors']}");
        
        if ($this->stats['updated'] > 0) {
            $this->logger->success("Actualización completada exitosamente");
        }
    }
    
    /**
     * Muestra información de una persona por email
     */
    public function showPersonInfo($email) {
        $person = $this->findPersonByEmail($email);
        
        if (!$person) {
            $this->logger->warning("No se encontró persona con email: $email");
            return;
        }
        
        $this->logger->info("=== INFORMACIÓN DE LA PERSONA ===");
        $this->logger->info("ID: {$person['id']}");
        $this->logger->info("Nombre: {$person['name']}");
        $this->logger->info("Cargo: {$person['job_title']}");
        $this->logger->info("Teléfonos: {$person['contact_numbers']}");
        $this->logger->info("Emails: {$person['emails']}");
    }
    
    /**
     * Procesa personas desde archivo CSV
     */
    public function processPersonsFromCSV($csvFile) {
        $this->logger->info("=== PROCESANDO ARCHIVO CSV: $csvFile ===");
        
        if (!file_exists($csvFile)) {
            $this->logger->error("Archivo CSV no encontrado: $csvFile");
            return false;
        }
        
        $handle = fopen($csvFile, 'r');
        if (!$handle) {
            $this->logger->error("No se pudo abrir el archivo CSV: $csvFile");
            return false;
        }
        
        // Leer encabezados
        $headers = fgetcsv($handle);
        if (!$headers) {
            $this->logger->error("No se pudieron leer los encabezados del CSV");
            fclose($handle);
            return false;
        }
        
        $this->logger->info("Encabezados encontrados: " . implode(', ', $headers));
        
        // Mapear índices de columnas
        $emailIndex = array_search('emails', $headers);
        $nameIndex = array_search('name', $headers);
        $phoneIndex = array_search('contact_numbers', $headers);
        $jobTitleIndex = array_search('job_title', $headers);
        
        if ($emailIndex === false) {
            $this->logger->error("Columna 'emails' no encontrada en el CSV");
            fclose($handle);
            return false;
        }
        
        $rowCount = 0;
        $processedCount = 0;
        
        // Procesar cada fila
        while (($row = fgetcsv($handle)) !== false) {
            $rowCount++;
            
            if (count($row) < count($headers)) {
                $this->logger->warning("Fila $rowCount incompleta, saltando...");
                continue;
            }
            
            // Extraer email principal
            $emailsData = $row[$emailIndex];
            $primaryEmail = $this->extractPrimaryEmail($emailsData);
            
            if (empty($primaryEmail)) {
                $this->logger->warning("Fila $rowCount: No se pudo extraer email válido, saltando...");
                continue;
            }
            
            // Preparar datos para actualización
            $name = ($nameIndex !== false) ? trim($row[$nameIndex]) : null;
            $phones = ($phoneIndex !== false) ? $this->parsePhoneNumbers($row[$phoneIndex]) : null;
            $jobTitle = ($jobTitleIndex !== false) ? trim($row[$jobTitleIndex]) : null;
            
            // Actualizar persona
            $this->logger->info("\n--- Procesando fila $rowCount: $primaryEmail ---");
            
            if ($this->updatePersonByEmail($primaryEmail, $name, $phones, $jobTitle)) {
                $processedCount++;
            }
        }
        
        fclose($handle);
        
        $this->logger->info("\n=== PROCESAMIENTO CSV COMPLETADO ===");
        $this->logger->info("Total filas leídas: $rowCount");
        $this->logger->info("Personas procesadas: $processedCount");
        
        $this->showFinalStats();
        
        return true;
    }
    
    /**
     * Extrae el email principal de un campo JSON de emails
     */
    private function extractPrimaryEmail($emailsData) {
        if (empty($emailsData)) return '';
        
        // Si es un JSON, parsearlo
        if (strpos($emailsData, '[') === 0 || strpos($emailsData, '{') === 0) {
            try {
                $emails = json_decode($emailsData, true);
                if (is_array($emails) && !empty($emails)) {
                    // Tomar el primer email válido
                    foreach ($emails as $emailData) {
                        if (isset($emailData['value']) && !empty($emailData['value'])) {
                            return trim($emailData['value']);
                        }
                    }
                }
            } catch (Exception $e) {
                // Si falla el JSON, intentar como texto plano
            }
        }
        
        // Si no es JSON o falló el parsing, tratar como texto plano
        $email = trim($emailsData);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        
        return '';
    }
    
    /**
     * Parsea números de teléfono desde el CSV
     */
    private function parsePhoneNumbers($phoneData) {
        if (empty($phoneData)) return null;
        
        // Si es un JSON, parsearlo
        if (strpos($phoneData, '[') === 0 || strpos($phoneData, '{') === 0) {
            try {
                $phones = json_decode($phoneData, true);
                if (is_array($phones)) {
                    $phoneNumbers = [];
                    foreach ($phones as $phoneItem) {
                        if (isset($phoneItem['value']) && !empty($phoneItem['value'])) {
                            $phoneNumbers[] = $phoneItem['value'];
                        }
                    }
                    return $phoneNumbers;
                }
            } catch (Exception $e) {
                // Si falla el JSON, intentar como texto plano
            }
        }
        
        // Si no es JSON o falló el parsing, tratar como texto plano
        $phone = trim($phoneData);
        if (!empty($phone)) {
            return [$phone];
        }
        
        return null;
    }
}

// Ejemplo de uso
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $updater = new PersonUpdaterByEmail();
        
        echo "\n=== ACTUALIZADOR DE PERSONAS POR EMAIL ===\n";
        echo "Este script actualiza Name, Contact Numbers y Job Title usando email como identificador.\n\n";
        
        // Ejemplo de uso individual
        echo "Ejemplo de uso:\n";
        echo "\$updater->updatePersonByEmail('email@ejemplo.com', 'Nuevo Nombre', ['123456789'], 'Nuevo Cargo');\n\n";
        
        // Ejemplo de uso masivo
        echo "Ejemplo de uso masivo:\n";
        echo "\$personsData = [\n";
        echo "    ['email' => 'persona1@ejemplo.com', 'name' => 'Nombre 1', 'job_title' => 'Cargo 1'],\n";
        echo "    ['email' => 'persona2@ejemplo.com', 'phones' => ['987654321']]\n";
        echo "];\n";
        echo "\$updater->updateMultiplePersons(\$personsData);\n\n";
        
        echo "Para usar este script, descomenta las líneas de ejemplo al final del archivo.\n";
        
        // Leer y procesar archivo persons.csv
        $csvFile = 'persons.csv';
        if (file_exists($csvFile)) {
            echo "Procesando archivo: $csvFile\n\n";
            $updater->processPersonsFromCSV($csvFile);
        } else {
            echo "Error: No se encontró el archivo $csvFile\n";
            echo "Asegúrate de que el archivo persons.csv esté en el mismo directorio.\n";
        }
        
    } catch (Exception $e) {
        echo "Error fatal: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\nScript cargado correctamente.\n";
?>
