<?php
/**
 * Script simplificado para insertar organizaciones sin atributos adicionales
 * 
 * FUNCIONALIDAD:
 * - Datos directos en el código (sin leer CSV)
 * - Inserta solo organizaciones con user_id correctos
 * - Inserta personas asociadas a las organizaciones
 * - Sin inserción de atributos adicionales (evita errores de columnas)
 */

// Configuración de la base de datos
$host = 'krayin-db';
$dbname = 'krayincrm';
$username = 'root';
$password = 'root';

// DATOS HARDCODEADOS DE LAS ORGANIZACIONES
$organizations_data = [
    [
        'name' => 'AIR Asociación de Industriales',
        'email' => 'contacto@air.cl',
        'phone' => '+56 2 2345 6789',
        'website' => 'www.air.cl',
        'address' => 'Av Jorge Alessandri 50',
        'city' => 'Santiago',
        'state' => 'Santiago',
        'postal_code' => '8320000',
        'country' => 'Chile',
        'rut' => '96.789.123-4',
        'sales_owner' => 'Alexandra',
        'user_id' => 15,
        'source' => 'Website'
    ],
    [
        'name' => 'Ald Logística',
        'email' => 'info@aldlogistica.cl',
        'phone' => '+56 2 2876 5432',
        'website' => 'www.aldlogistica.cl',
        'address' => 'Calle Principal 123',
        'city' => 'Santiago',
        'state' => 'Santiago',
        'postal_code' => '8340000',
        'country' => 'Chile',
        'rut' => '76.543.210-9',
        'sales_owner' => 'Alexandra',
        'user_id' => 15,
        'source' => 'Referral'
    ],
    [
        'name' => 'Alto S.A.',
        'email' => 'contacto@alto.cl',
        'phone' => '+56 2 2987 6543',
        'website' => 'www.alto.cl',
        'address' => 'Avenida Central 456',
        'city' => 'Santiago',
        'state' => 'Santiago',
        'postal_code' => '8350000',
        'country' => 'Chile',
        'rut' => '89.012.345-6',
        'sales_owner' => 'Alexandra',
        'user_id' => 15,
        'source' => 'Cold Call'
    ]
];

// DATOS DE PERSONAS ASOCIADAS
$persons_data = [
    [
        'name' => 'Juan Pérez',
        'email' => 'juan.perez@air.cl',
        'phone' => '+56 9 8765 4321',
        'organization' => 'AIR Asociación de Industriales'
    ],
    [
        'name' => 'María González',
        'email' => 'maria.gonzalez@aldlogistica.cl',
        'phone' => '+56 9 7654 3210',
        'organization' => 'Ald Logística'
    ],
    [
        'name' => 'Carlos Silva',
        'email' => 'carlos.silva@alto.cl',
        'phone' => '+56 9 6543 2109',
        'organization' => 'Alto S.A.'
    ]
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conexión exitosa a la base de datos\n";
    
    // MOSTRAR DATOS A INSERTAR
    echo "\n📋 DATOS A INSERTAR\n";
    echo "==================\n";
    
    foreach ($organizations_data as $index => $org) {
        echo "  " . ($index + 1) . ". {$org['name']} - user_id: {$org['user_id']} - sales_owner: {$org['sales_owner']}\n";
        echo "     RUT: {$org['rut']} - Address: {$org['address']}\n";
    }
    
    // PASO 1: LIMPIAR DATOS PREVIOS
    echo "\n🧹 LIMPIANDO DATOS PREVIOS\n";
    echo "=========================\n";
    
    $pdo->beginTransaction();
    
    foreach ($organizations_data as $org_data) {
        // Eliminar personas asociadas
        $stmt = $pdo->prepare("DELETE FROM persons WHERE organization_id IN (SELECT id FROM organizations WHERE name = ?)");
        $stmt->execute([$org_data['name']]);
        
        // Eliminar organización
        $stmt = $pdo->prepare("DELETE FROM organizations WHERE name = ?");
        $stmt->execute([$org_data['name']]);
        
        echo "🗑️ Limpiados datos previos de: {$org_data['name']}\n";
    }
    
    // PASO 2: INSERTAR ORGANIZACIONES
    echo "\n🏢 INSERTANDO ORGANIZACIONES\n";
    echo "============================\n";
    
    $inserted_orgs = [];
    
    foreach ($organizations_data as $org_data) {
        // Verificar qué columnas existen en la tabla organizations
        $stmt = $pdo->prepare("SHOW COLUMNS FROM organizations");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Construir query dinámicamente basado en columnas existentes
        $insert_fields = ['name', 'user_id'];
        $insert_values = [$org_data['name'], $org_data['user_id']];
        $placeholders = ['?', '?'];
        
        // Agregar campos opcionales si existen
        if (in_array('created_at', $columns)) {
            $insert_fields[] = 'created_at';
            $insert_values[] = date('Y-m-d H:i:s');
            $placeholders[] = '?';
        }
        
        if (in_array('updated_at', $columns)) {
            $insert_fields[] = 'updated_at';
            $insert_values[] = date('Y-m-d H:i:s');
            $placeholders[] = '?';
        }
        
        $sql = "INSERT INTO organizations (" . implode(', ', $insert_fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute($insert_values)) {
            $org_id = $pdo->lastInsertId();
            $inserted_orgs[] = array_merge($org_data, ['id' => $org_id]);
            
            echo "✅ Insertada: {$org_data['name']} (ID: $org_id, user_id: {$org_data['user_id']})\n";
        } else {
            echo "❌ Error insertando: {$org_data['name']}\n";
        }
    }
    
    // PASO 3: INSERTAR PERSONAS ASOCIADAS
    echo "\n👥 INSERTANDO PERSONAS ASOCIADAS\n";
    echo "===============================\n";
    
    foreach ($persons_data as $person) {
        // Buscar la organización
        $org = array_filter($inserted_orgs, function($o) use ($person) {
            return $o['name'] === $person['organization'];
        });
        
        if (!empty($org)) {
            $org = array_values($org)[0];
            
            // Verificar qué columnas existen en la tabla persons
            $stmt = $pdo->prepare("SHOW COLUMNS FROM persons");
            $stmt->execute();
            $person_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Construir query dinámicamente
            $person_fields = ['name', 'organization_id', 'user_id'];
            $person_values = [$person['name'], $org['id'], $org['user_id']];
            $person_placeholders = ['?', '?', '?'];
            
            // Agregar email si la columna existe
            if (in_array('emails', $person_columns)) {
                $person_fields[] = 'emails';
                $emails_json = json_encode([['value' => $person['email'], 'label' => 'work']]);
                $person_values[] = $emails_json;
                $person_placeholders[] = '?';
            }
            
            // Agregar timestamps si existen
            if (in_array('created_at', $person_columns)) {
                $person_fields[] = 'created_at';
                $person_values[] = date('Y-m-d H:i:s');
                $person_placeholders[] = '?';
            }
            
            if (in_array('updated_at', $person_columns)) {
                $person_fields[] = 'updated_at';
                $person_values[] = date('Y-m-d H:i:s');
                $person_placeholders[] = '?';
            }
            
            $person_sql = "INSERT INTO persons (" . implode(', ', $person_fields) . ") VALUES (" . implode(', ', $person_placeholders) . ")";
            
            $stmt = $pdo->prepare($person_sql);
            
            if ($stmt->execute($person_values)) {
                $person_id = $pdo->lastInsertId();
                echo "✅ Persona insertada: {$person['name']} → {$org['name']} (ID: $person_id)\n";
                echo "   📧 Email: {$person['email']}\n";
            } else {
                echo "❌ Error insertando persona: {$person['name']}\n";
            }
        }
    }
    
    // Confirmar transacción
    $pdo->commit();
    
    // PASO 4: VERIFICACIÓN FINAL
    echo "\n📊 VERIFICACIÓN FINAL\n";
    echo "====================\n";
    
    foreach ($inserted_orgs as $org) {
        echo "\n🏢 {$org['name']} (ID: {$org['id']})\n";
        
        // Verificar tabla organizations
        $stmt = $pdo->prepare("SELECT user_id FROM organizations WHERE id = ?");
        $stmt->execute([$org['id']]);
        $current_org = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "   📋 Tabla organizations: user_id = {$current_org['user_id']}\n";
        
        // Verificar personas asociadas
        $stmt = $pdo->prepare("SELECT name, emails FROM persons WHERE organization_id = ?");
        $stmt->execute([$org['id']]);
        $persons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($persons as $person) {
            if ($person['emails']) {
                $emails = json_decode($person['emails'], true);
                $email = $emails[0]['value'] ?? 'No email';
            } else {
                $email = 'No email';
            }
            echo "   👤 Persona: {$person['name']} ({$email})\n";
        }
    }
    
    echo "\n🎉 INSERCIÓN SIMPLE EXITOSA\n";
    echo "===========================\n";
    echo "Organizaciones insertadas:\n";
    foreach ($inserted_orgs as $org) {
        echo "- {$org['name']}: user_id = {$org['user_id']} (Sales Owner: {$org['sales_owner']})\n";
    }
    
    echo "\n💡 NOTA:\n";
    echo "Este script solo inserta organizaciones y personas básicas.\n";
    echo "Los atributos adicionales se pueden agregar desde la interfaz de Krayin.\n";
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Error de base de datos: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== SCRIPT COMPLETADO ===\n";
?>
