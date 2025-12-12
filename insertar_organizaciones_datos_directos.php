<?php
/**
 * Script para insertar organizaciones con datos hardcodeados
 * 
 * FUNCIONALIDAD:
 * - Datos directos en el código (sin leer CSV)
 * - Inserta organizaciones con user_id correctos
 * - Inserta todos los atributos con IDs correctos (34=name, 35=address, 36=sales_owner, 61=rut)
 * - Inserta personas asociadas a las organizaciones
 * - Realiza verificación completa
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
        // Eliminar atributos previos
        $stmt = $pdo->prepare("DELETE FROM attribute_values WHERE entity_type = 'organizations' AND entity_id IN (SELECT id FROM organizations WHERE name = ?)");
        $stmt->execute([$org_data['name']]);
        
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
        $stmt = $pdo->prepare("
            INSERT INTO organizations (name, user_id, created_at, updated_at) 
            VALUES (?, ?, NOW(), NOW())
        ");
        
        if ($stmt->execute([$org_data['name'], $org_data['user_id']])) {
            $org_id = $pdo->lastInsertId();
            $inserted_orgs[] = array_merge($org_data, ['id' => $org_id]);
            
            echo "✅ Insertada: {$org_data['name']} (ID: $org_id, user_id: {$org_data['user_id']})\n";
        } else {
            echo "❌ Error insertando: {$org_data['name']}\n";
        }
    }
    
    // PASO 3: INSERTAR ATRIBUTOS
    echo "\n📝 INSERTANDO ATRIBUTOS\n";
    echo "======================\n";
    
    foreach ($inserted_orgs as $org) {
        echo "\n🏢 Insertando atributos para: {$org['name']} (ID: {$org['id']})\n";
        
        // Atributo NAME (ID 34)
        $stmt = $pdo->prepare("
            INSERT INTO attribute_values (entity_type, entity_id, attribute_id, text_value, created_at, updated_at) 
            VALUES ('organizations', ?, 34, ?, NOW(), NOW())
        ");
        if ($stmt->execute([$org['id'], $org['name']])) {
            echo "  ✅ Name (ID 34): {$org['name']}\n";
        }
        
        // Atributo ADDRESS (ID 35)
        if (!empty($org['address'])) {
            $stmt = $pdo->prepare("
                INSERT INTO attribute_values (entity_type, entity_id, attribute_id, text_value, created_at, updated_at) 
                VALUES ('organizations', ?, 35, ?, NOW(), NOW())
            ");
            if ($stmt->execute([$org['id'], $org['address']])) {
                echo "  ✅ Address (ID 35): {$org['address']}\n";
            }
        }
        
        // Atributo SALES_OWNER (ID 36)
        $stmt = $pdo->prepare("
            INSERT INTO attribute_values (entity_type, entity_id, attribute_id, integer_value, created_at, updated_at) 
            VALUES ('organizations', ?, 36, ?, NOW(), NOW())
        ");
        if ($stmt->execute([$org['id'], $org['user_id']])) {
            echo "  ✅ Sales Owner (ID 36): {$org['user_id']} ({$org['sales_owner']})\n";
        }
        
        // Atributo RUT (ID 61)
        if (!empty($org['rut'])) {
            $stmt = $pdo->prepare("
                INSERT INTO attribute_values (entity_type, entity_id, attribute_id, text_value, created_at, updated_at) 
                VALUES ('organizations', ?, 61, ?, NOW(), NOW())
            ");
            if ($stmt->execute([$org['id'], $org['rut']])) {
                echo "  ✅ RUT (ID 61): {$org['rut']}\n";
            }
        }
        
        // Atributo EMAIL (si existe en la estructura)
        if (!empty($org['email'])) {
            // Buscar ID del atributo email
            $stmt = $pdo->prepare("SELECT id FROM attributes WHERE code = 'email' AND entity_type = 'organizations'");
            $stmt->execute();
            $email_attr = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($email_attr) {
                $stmt = $pdo->prepare("
                    INSERT INTO attribute_values (entity_type, entity_id, attribute_id, text_value, created_at, updated_at) 
                    VALUES ('organizations', ?, ?, ?, NOW(), NOW())
                ");
                if ($stmt->execute([$org['id'], $email_attr['id'], $org['email']])) {
                    echo "  ✅ Email (ID {$email_attr['id']}): {$org['email']}\n";
                }
            }
        }
        
        // Atributo PHONE (si existe en la estructura)
        if (!empty($org['phone'])) {
            // Buscar ID del atributo phone
            $stmt = $pdo->prepare("SELECT id FROM attributes WHERE code = 'phone' AND entity_type = 'organizations'");
            $stmt->execute();
            $phone_attr = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($phone_attr) {
                $stmt = $pdo->prepare("
                    INSERT INTO attribute_values (entity_type, entity_id, attribute_id, text_value, created_at, updated_at) 
                    VALUES ('organizations', ?, ?, ?, NOW(), NOW())
                ");
                if ($stmt->execute([$org['id'], $phone_attr['id'], $org['phone']])) {
                    echo "  ✅ Phone (ID {$phone_attr['id']}): {$org['phone']}\n";
                }
            }
        }
    }
    
    // PASO 4: INSERTAR PERSONAS ASOCIADAS
    echo "\n👥 INSERTANDO PERSONAS ASOCIADAS\n";
    echo "===============================\n";
    
    foreach ($persons_data as $person) {
        // Buscar la organización
        $org = array_filter($inserted_orgs, function($o) use ($person) {
            return $o['name'] === $person['organization'];
        });
        
        if (!empty($org)) {
            $org = array_values($org)[0];
            
            $stmt = $pdo->prepare("
                INSERT INTO persons (name, emails, organization_id, user_id, created_at, updated_at) 
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            
            $emails_json = json_encode([['value' => $person['email'], 'label' => 'work']]);
            
            if ($stmt->execute([$person['name'], $emails_json, $org['id'], $org['user_id']])) {
                $person_id = $pdo->lastInsertId();
                echo "✅ Persona insertada: {$person['name']} → {$org['name']} (ID: $person_id)\n";
                echo "   📧 Email: {$person['email']}\n";
            }
        }
    }
    
    // Confirmar transacción
    $pdo->commit();
    
    // PASO 5: VERIFICACIÓN FINAL
    echo "\n📊 VERIFICACIÓN FINAL\n";
    echo "====================\n";
    
    foreach ($inserted_orgs as $org) {
        echo "\n🏢 {$org['name']} (ID: {$org['id']})\n";
        
        // Verificar tabla organizations
        $stmt = $pdo->prepare("SELECT user_id FROM organizations WHERE id = ?");
        $stmt->execute([$org['id']]);
        $current_org = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "   📋 Tabla organizations: user_id = {$current_org['user_id']}\n";
        
        // Verificar atributos
        $stmt = $pdo->prepare("
            SELECT av.attribute_id, a.code, av.text_value, av.integer_value
            FROM attribute_values av
            LEFT JOIN attributes a ON av.attribute_id = a.id
            WHERE av.entity_type = 'organizations' 
            AND av.entity_id = ?
            ORDER BY av.attribute_id
        ");
        $stmt->execute([$org['id']]);
        $attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($attributes as $attr) {
            $value = $attr['text_value'] ?: $attr['integer_value'];
            $code = $attr['code'] ?: 'unknown';
            echo "   📋 Atributo $code (ID {$attr['attribute_id']}): $value\n";
        }
        
        // Verificar personas asociadas
        $stmt = $pdo->prepare("SELECT name, emails FROM persons WHERE organization_id = ?");
        $stmt->execute([$org['id']]);
        $persons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($persons as $person) {
            $emails = json_decode($person['emails'], true);
            $email = $emails[0]['value'] ?? 'No email';
            echo "   👤 Persona: {$person['name']} ({$email})\n";
        }
    }
    
    echo "\n🎉 INSERCIÓN COMPLETA EXITOSA\n";
    echo "============================\n";
    echo "Organizaciones insertadas con datos correctos:\n";
    foreach ($inserted_orgs as $org) {
        echo "- {$org['name']}: user_id = {$org['user_id']} (Sales Owner: {$org['sales_owner']})\n";
        echo "  RUT: {$org['rut']} | Address: {$org['address']}\n";
    }
    
    echo "\n💡 RECOMENDACIÓN:\n";
    echo "Ejecutar 'php artisan cache:clear' en Krayin para reflejar los cambios.\n";
    
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
