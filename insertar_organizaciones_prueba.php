<?php
/**
 * Script para insertar 3 organizaciones de prueba con los atributos correctos
 * IDs correctos según las imágenes de la BD:
 * - ID 26: name
 * - ID 35: address 
 * - ID 34: user_id
 * - ID 61: RUT
 * - ID 21: otro atributo
 */

try {
    // Conexión a la base de datos
    $pdo = new PDO('mysql:host=krayin-db;dbname=krayincrm;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== INSERCIÓN DE ORGANIZACIONES DE PRUEBA ===\n";
    echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Obtener user_id por defecto
    $stmt = $pdo->query("SELECT id FROM users WHERE role_id = 1 LIMIT 1");
    $default_user = $stmt->fetch(PDO::FETCH_ASSOC);
    $default_user_id = $default_user ? $default_user['id'] : 1;
    echo "User ID por defecto: $default_user_id\n\n";
    
    // Datos de las 3 organizaciones de prueba
    $test_organizations = [
        [
            'name' => 'EMPRESA PRUEBA 1 LTDA',
            'address' => [
                'address' => 'Av. Providencia 1234',
                'city' => 'Santiago',
                'state' => 'Región Metropolitana',
                'country' => 'CL',
                'postcode' => '7500000'
            ],
            'rut' => '76.123.456-7',
            'user_id' => $default_user_id
        ],
        [
            'name' => 'COMERCIAL PRUEBA 2 SPA',
            'address' => [
                'address' => 'Calle Las Condes 5678',
                'city' => 'Las Condes',
                'state' => 'Región Metropolitana', 
                'country' => 'CL',
                'postcode' => '7550000'
            ],
            'rut' => '77.987.654-3',
            'user_id' => $default_user_id
        ],
        [
            'name' => 'SERVICIOS PRUEBA 3 EIRL',
            'address' => [
                'address' => 'Av. Vitacura 9012',
                'city' => 'Vitacura',
                'state' => 'Región Metropolitana',
                'country' => 'CL', 
                'postcode' => '7630000'
            ],
            'rut' => '78.555.444-1',
            'user_id' => $default_user_id
        ]
    ];
    
    $pdo->beginTransaction();
    
    foreach ($test_organizations as $index => $org_data) {
        $org_number = $index + 1;
        echo "Insertando Organización $org_number: {$org_data['name']}\n";
        
        // 1. Insertar en tabla organizations
        $stmt = $pdo->prepare("
            INSERT INTO organizations (name, address, user_id, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        
        $address_json = json_encode($org_data['address']);
        $stmt->execute([
            $org_data['name'],
            $address_json,
            $org_data['user_id']
        ]);
        
        $org_id = $pdo->lastInsertId();
        echo "  ✓ Insertado en organizations con ID: $org_id\n";
        
        // 2. Insertar atributos en attribute_values
        $attributes_to_insert = [
            26 => ['text_value' => $org_data['name']], // name
            35 => ['json_value' => $address_json], // address
            34 => ['integer_value' => $org_data['user_id']], // user_id
            61 => ['text_value' => $org_data['rut']], // RUT
            21 => ['text_value' => 'Valor adicional'] // otro atributo
        ];
        
        foreach ($attributes_to_insert as $attr_id => $values) {
            $stmt = $pdo->prepare("
                INSERT INTO attribute_values (
                    entity_type, entity_id, attribute_id,
                    text_value, json_value, integer_value, boolean_value
                ) VALUES (
                    'organizations', ?, ?,
                    ?, ?, ?, NULL
                )
            ");
            
            $text_value = $values['text_value'] ?? NULL;
            $json_value = $values['json_value'] ?? NULL;
            $integer_value = $values['integer_value'] ?? NULL;
            
            $stmt->execute([
                $org_id,
                $attr_id,
                $text_value,
                $json_value,
                $integer_value
            ]);
            
            $attr_name = [
                26 => 'name',
                35 => 'address',
                34 => 'user_id', 
                61 => 'RUT',
                21 => 'otro'
            ][$attr_id];
            
            echo "  ✓ Atributo $attr_id ($attr_name) insertado\n";
        }
        
        echo "\n";
    }
    
    $pdo->commit();
    
    echo "=== INSERCIÓN COMPLETADA ===\n";
    echo "Se insertaron 3 organizaciones de prueba con todos sus atributos.\n\n";
    
    // Verificar las organizaciones insertadas
    echo "VERIFICACIÓN DE LAS ORGANIZACIONES INSERTADAS:\n";
    echo "============================================\n";
    
    $stmt = $pdo->query("
        SELECT id, name, address, user_id 
        FROM organizations 
        ORDER BY id DESC 
        LIMIT 3
    ");
    
    $inserted_orgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($inserted_orgs as $org) {
        echo "\nOrganización ID {$org['id']}:\n";
        echo "- Nombre: {$org['name']}\n";
        echo "- Dirección: {$org['address']}\n";
        echo "- User ID: {$org['user_id']}\n";
        
        // Verificar atributos
        $stmt = $pdo->prepare("
            SELECT attribute_id, text_value, json_value, integer_value
            FROM attribute_values
            WHERE entity_type = 'organizations' AND entity_id = ?
            ORDER BY attribute_id
        ");
        $stmt->execute([$org['id']]);
        $attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "- Atributos en attribute_values:\n";
        foreach ($attributes as $attr) {
            $value = $attr['text_value'] ?? $attr['json_value'] ?? $attr['integer_value'] ?? 'NULL';
            echo "  * ID {$attr['attribute_id']}: $value\n";
        }
    }
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "Error de base de datos: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
?>
