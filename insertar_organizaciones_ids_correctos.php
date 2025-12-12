<?php
/**
 * Script CORREGIDO con los IDs de atributos REALES de Krayin
 * 
 * PROBLEMA IDENTIFICADO:
 * - Estábamos usando IDs incorrectos para los atributos
 * - Según la imagen mostrada por el usuario:
 *   * ID 34 = Name (no 26)
 *   * ID 35 = Address 
 *   * ID 36 = Sales Owner (user_id)
 *   * ID 61 = RUT
 * 
 * SOLUCIÓN:
 * - Usar los IDs correctos según la estructura real de Krayin
 */

// Configuración de la base de datos
$host = 'krayin-db';
$dbname = 'krayincrm';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conexión exitosa a la base de datos\n";
    
    // Obtener un user_id válido
    $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_id = $user ? $user['id'] : 1;
    
    echo "📋 Usando user_id: $user_id\n\n";
    
    // PASO 1: LIMPIAR DATOS ANTERIORES DE LAS 3 ORGANIZACIONES
    echo "🧹 LIMPIANDO DATOS ANTERIORES\n";
    echo "=============================\n";
    
    $org_names = ['AIR Asociación de Industriales', 'Ald Logística', 'Alto S.A.'];
    
    foreach ($org_names as $name) {
        // Buscar organización existente
        $stmt = $pdo->prepare("SELECT id FROM organizations WHERE name = ?");
        $stmt->execute([$name]);
        $org = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($org) {
            $org_id = $org['id'];
            echo "🗑️ Eliminando datos anteriores de: $name (ID: $org_id)\n";
            
            // Eliminar personas asociadas
            $stmt = $pdo->prepare("DELETE FROM persons WHERE organization_id = ?");
            $stmt->execute([$org_id]);
            
            // Eliminar atributos
            $stmt = $pdo->prepare("DELETE FROM attribute_values WHERE entity_type = 'organizations' AND entity_id = ?");
            $stmt->execute([$org_id]);
            
            // Eliminar organización
            $stmt = $pdo->prepare("DELETE FROM organizations WHERE id = ?");
            $stmt->execute([$org_id]);
        }
    }
    
    echo "\n🏢 INSERTANDO ORGANIZACIONES CON IDs CORRECTOS\n";
    echo "==============================================\n";
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    // Datos de las organizaciones
    $organizaciones = [
        [
            'name' => 'AIR Asociación de Industriales',
            'email' => 'dsepulveda@air.cl',
            'phone' => '56222751068',
            'address' => 'Av Jorge Alessandri 50',
            'city' => 'Santiago',
            'state' => 'Santiago',
            'country' => 'Chile',
            'rut' => '',
            'contact_name' => 'David Sepúlveda'
        ],
        [
            'name' => 'Ald Logística',
            'email' => 'jagurto@agurto.cl',
            'phone' => '56225448620',
            'address' => '',
            'city' => '',
            'state' => '',
            'country' => 'Chile',
            'rut' => '',
            'contact_name' => 'Jorge Agurto'
        ],
        [
            'name' => 'Alto S.A.',
            'email' => 'marancibia@grupoalto.com',
            'phone' => '56963109170',
            'address' => '',
            'city' => '',
            'state' => '',
            'country' => 'Chile',
            'rut' => '',
            'contact_name' => 'Miguel Arancibia'
        ]
    ];
    
    $organization_ids = [];
    
    foreach ($organizaciones as $index => $org) {
        // 1. INSERTAR EN TABLA ORGANIZATIONS
        $address_data = [
            'address' => $org['address'] ?: '',
            'city' => $org['city'] ?: '',
            'state' => $org['state'] ?: '',
            'postcode' => '',
            'country' => 'CL'
        ];
        
        $address_json = json_encode($address_data, JSON_UNESCAPED_UNICODE);
        
        $sql = "INSERT INTO organizations (name, address, user_id, created_at, updated_at) 
                VALUES (?, ?, ?, NOW(), NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $org['name'],
            $address_json,
            $user_id
        ]);
        
        $org_id = $pdo->lastInsertId();
        $organization_ids[] = $org_id;
        
        echo "✅ Organización insertada: {$org['name']} (ID: $org_id)\n";
        
        // 2. INSERTAR ATRIBUTOS CON IDs CORRECTOS
        
        // Atributo 34: name (text_value) - ID CORRECTO SEGÚN LA IMAGEN
        $stmt = $pdo->prepare("
            INSERT INTO attribute_values (entity_type, entity_id, attribute_id, text_value) 
            VALUES ('organizations', ?, 34, ?)
        ");
        if ($stmt->execute([$org_id, $org['name']])) {
            echo "   ✓ Atributo name (ID 34) insertado\n";
        } else {
            echo "   ✗ Error insertando atributo name (ID 34)\n";
        }
        
        // Atributo 35: address (json_value)
        $stmt = $pdo->prepare("
            INSERT INTO attribute_values (entity_type, entity_id, attribute_id, json_value) 
            VALUES ('organizations', ?, 35, ?)
        ");
        if ($stmt->execute([$org_id, $address_json])) {
            echo "   ✓ Atributo address (ID 35) insertado\n";
        } else {
            echo "   ✗ Error insertando atributo address (ID 35)\n";
        }
        
        // Atributo 36: user_id/sales_owner (integer_value) - ID CORRECTO SEGÚN LA IMAGEN
        $stmt = $pdo->prepare("
            INSERT INTO attribute_values (entity_type, entity_id, attribute_id, integer_value) 
            VALUES ('organizations', ?, 36, ?)
        ");
        if ($stmt->execute([$org_id, $user_id])) {
            echo "   ✓ Atributo sales_owner (ID 36) insertado\n";
        } else {
            echo "   ✗ Error insertando atributo sales_owner (ID 36)\n";
        }
        
        // Atributo 61: RUT (text_value) - solo si no está vacío
        if (!empty($org['rut'])) {
            $stmt = $pdo->prepare("
                INSERT INTO attribute_values (entity_type, entity_id, attribute_id, text_value) 
                VALUES ('organizations', ?, 61, ?)
            ");
            if ($stmt->execute([$org_id, $org['rut']])) {
                echo "   ✓ Atributo RUT (ID 61) insertado\n";
            }
        } else {
            echo "   - Atributo RUT omitido (vacío)\n";
        }
        
        echo "\n";
    }
    
    echo "👥 INSERTANDO PERSONAS ASOCIADAS\n";
    echo "================================\n";
    
    // Insertar personas asociadas
    foreach ($organizaciones as $index => $org) {
        $org_id = $organization_ids[$index];
        
        $emails_json = json_encode([
            ['label' => 'work', 'value' => $org['email']]
        ], JSON_UNESCAPED_UNICODE);
        
        $phones_json = json_encode([
            ['label' => 'work', 'value' => $org['phone']]
        ], JSON_UNESCAPED_UNICODE);
        
        $sql_person = "INSERT INTO persons (name, emails, contact_numbers, organization_id, job_title, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, 'Contacto Principal', NOW(), NOW())";
        
        $stmt_person = $pdo->prepare($sql_person);
        $stmt_person->execute([
            $org['contact_name'],
            $emails_json,
            $phones_json,
            $org_id
        ]);
        
        $person_id = $pdo->lastInsertId();
        echo "✅ Persona: {$org['contact_name']} → Organización: {$org['name']}\n";
    }
    
    // Confirmar transacción
    $pdo->commit();
    
    echo "\n🎉 INSERCIÓN COMPLETADA CON IDs CORRECTOS\n";
    echo "=========================================\n";
    
    // VERIFICACIÓN FINAL DETALLADA
    echo "\n📊 VERIFICACIÓN FINAL\n";
    echo "====================\n";
    
    foreach ($organization_ids as $org_id) {
        $stmt = $pdo->prepare("SELECT name FROM organizations WHERE id = ?");
        $stmt->execute([$org_id]);
        $org_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "\n🏢 {$org_data['name']} (ID: $org_id)\n";
        
        // Verificar TODOS los atributos
        $stmt = $pdo->prepare("
            SELECT av.attribute_id, a.code, a.name as attr_name, 
                   av.text_value, av.json_value, av.integer_value
            FROM attribute_values av
            LEFT JOIN attributes a ON av.attribute_id = a.id
            WHERE av.entity_type = 'organizations' AND av.entity_id = ?
            ORDER BY av.attribute_id
        ");
        $stmt->execute([$org_id]);
        $attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($attributes)) {
            echo "   ❌ SIN ATRIBUTOS\n";
        } else {
            echo "   ✅ Atributos encontrados:\n";
            foreach ($attributes as $attr) {
                $value = $attr['text_value'] ?? $attr['json_value'] ?? $attr['integer_value'] ?? 'NULL';
                $attr_name = $attr['attr_name'] ?? 'Desconocido';
                echo "     - ID {$attr['attribute_id']} ({$attr['code']}): $attr_name = " . substr($value, 0, 30) . "\n";
            }
        }
        
        // Verificar personas
        $stmt = $pdo->prepare("SELECT name FROM persons WHERE organization_id = ?");
        $stmt->execute([$org_id]);
        $persons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   👥 Personas: " . count($persons) . "\n";
        foreach ($persons as $person) {
            echo "     - {$person['name']}\n";
        }
    }
    
    echo "\n✅ PROCESO COMPLETADO CON IDs CORRECTOS\n";
    echo "Ahora las organizaciones deberían mostrarse correctamente en Krayin.\n";
    
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
