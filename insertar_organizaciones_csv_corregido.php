<?php
/**
 * Script corregido para insertar las primeras 3 organizaciones del CSV
 * Basado en el script que funcionaba bien (solucion_completa_organizaciones.php)
 * 
 * PROBLEMAS IDENTIFICADOS EN EL SCRIPT ANTERIOR:
 * 1. Los atributos en attribute_values no se insertaban con los tipos correctos
 * 2. El atributo de dirección debe ser JSON en json_value, no text_value
 * 3. El user_id debe ser integer_value, no text_value
 * 4. Faltaba sincronización entre organizations y attribute_values
 * 
 * SOLUCIÓN:
 * - Insertar correctamente en organizations
 * - Insertar atributos en attribute_values con tipos de datos correctos
 * - Usar los IDs de atributos correctos: name(26), address(35), user_id(34), rut(61)
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
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    // Datos de las primeras 3 organizaciones del CSV
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
    
    echo "🏢 INSERTANDO ORGANIZACIONES:\n";
    echo "================================\n";
    
    foreach ($organizaciones as $index => $org) {
        // 1. INSERTAR EN TABLA ORGANIZATIONS
        // Crear JSON para address según el formato de Krayin
        $address_data = [
            'address' => $org['address'] ?: '',
            'city' => $org['city'] ?: '',
            'state' => $org['state'] ?: '',
            'postcode' => '',
            'country' => 'CL'
        ];
        
        $address_json = json_encode($address_data, JSON_UNESCAPED_UNICODE);
        
        // Insertar organización
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
        
        // 2. INSERTAR ATRIBUTOS EN ATTRIBUTE_VALUES CON TIPOS CORRECTOS
        
        // Atributo 26: name (text_value)
        $stmt = $pdo->prepare("
            INSERT INTO attribute_values (entity_type, entity_id, attribute_id, text_value) 
            VALUES ('organizations', ?, 26, ?)
        ");
        $stmt->execute([$org_id, $org['name']]);
        
        // Atributo 35: address (json_value)
        $stmt = $pdo->prepare("
            INSERT INTO attribute_values (entity_type, entity_id, attribute_id, json_value) 
            VALUES ('organizations', ?, 35, ?)
        ");
        $stmt->execute([$org_id, $address_json]);
        
        // Atributo 34: user_id (integer_value)
        $stmt = $pdo->prepare("
            INSERT INTO attribute_values (entity_type, entity_id, attribute_id, integer_value) 
            VALUES ('organizations', ?, 34, ?)
        ");
        $stmt->execute([$org_id, $user_id]);
        
        // Atributo 61: RUT (text_value) - solo si no está vacío
        if (!empty($org['rut'])) {
            $stmt = $pdo->prepare("
                INSERT INTO attribute_values (entity_type, entity_id, attribute_id, text_value) 
                VALUES ('organizations', ?, 61, ?)
            ");
            $stmt->execute([$org_id, $org['rut']]);
        }
        
        echo "   📝 Atributos insertados correctamente para {$org['name']}\n";
    }
    
    echo "\n👥 INSERTANDO PERSONAS ASOCIADAS:\n";
    echo "==================================\n";
    
    // Insertar personas asociadas
    foreach ($organizaciones as $index => $org) {
        $org_id = $organization_ids[$index];
        
        // Crear JSON para emails y teléfonos
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
        echo "✅ Persona insertada: {$org['contact_name']} (ID: $person_id) - Organización: {$org['name']} (ID: $org_id)\n";
    }
    
    // Confirmar transacción
    $pdo->commit();
    
    echo "\n🎉 INSERCIÓN COMPLETADA EXITOSAMENTE\n";
    echo "=====================================\n";
    
    // VERIFICACIÓN FINAL
    echo "\n📊 VERIFICACIÓN FINAL:\n";
    echo "======================\n";
    
    // Verificar organizaciones con sus atributos
    foreach ($organization_ids as $org_id) {
        // Obtener datos de organizations
        $stmt = $pdo->prepare("SELECT name, address, user_id FROM organizations WHERE id = ?");
        $stmt->execute([$org_id]);
        $org_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "\n🏢 Organización ID $org_id:\n";
        echo "   Nombre: {$org_data['name']}\n";
        echo "   User ID: {$org_data['user_id']}\n";
        
        // Verificar atributos en attribute_values
        $stmt = $pdo->prepare("
            SELECT attribute_id, text_value, json_value, integer_value 
            FROM attribute_values 
            WHERE entity_type = 'organizations' AND entity_id = ? 
            ORDER BY attribute_id
        ");
        $stmt->execute([$org_id]);
        $attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   Atributos en attribute_values:\n";
        foreach ($attributes as $attr) {
            $attr_name = [
                26 => 'name',
                35 => 'address', 
                34 => 'user_id',
                61 => 'RUT'
            ][$attr['attribute_id']] ?? 'desconocido';
            
            $value = $attr['text_value'] ?? $attr['json_value'] ?? $attr['integer_value'] ?? 'NULL';
            echo "     * ID {$attr['attribute_id']} ($attr_name): " . substr($value, 0, 50) . "\n";
        }
        
        // Verificar personas asociadas
        $stmt = $pdo->prepare("SELECT name, job_title FROM persons WHERE organization_id = ?");
        $stmt->execute([$org_id]);
        $persons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   Personas asociadas:\n";
        foreach ($persons as $person) {
            echo "     👤 {$person['name']} - {$person['job_title']}\n";
        }
    }
    
    echo "\n📈 ESTADÍSTICAS FINALES:\n";
    echo "========================\n";
    
    // Contar totales
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM organizations");
    $total_orgs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM persons");
    $total_persons = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM attribute_values WHERE entity_type = 'organizations'");
    $total_attrs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo "Total organizaciones: $total_orgs\n";
    echo "Total personas: $total_persons\n";
    echo "Total atributos de organizaciones: $total_attrs\n";
    
    echo "\n✅ PROCESO COMPLETADO CORRECTAMENTE\n";
    echo "Las organizaciones ahora deberían aparecer correctamente en la interfaz de Krayin.\n";
    
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

?>
