<?php
/**
 * Script completo para insertar organizaciones desde CSV con user_id correctos
 * 
 * FUNCIONALIDAD:
 * - Lee datos del CSV organizations_with_user_id.csv
 * - Inserta organizaciones con user_id correctos desde el principio
 * - Inserta todos los atributos con IDs correctos (34=name, 35=address, 36=sales_owner, 61=rut)
 * - Inserta personas asociadas a las organizaciones
 * - Realiza verificación completa
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
    
    // PASO 1: LEER DATOS DEL CSV
    echo "\n📋 LEYENDO DATOS DEL CSV\n";
    echo "========================\n";
    
    $csv_file = 'organizations_with_user_id.csv';
    
    if (!file_exists($csv_file)) {
        throw new Exception("Archivo CSV no encontrado: $csv_file");
    }
    
    $csv_data = [];
    $handle = fopen($csv_file, 'r');
    
    // Leer encabezados
    $headers = fgetcsv($handle);
    echo "Encabezados CSV: " . implode(', ', $headers) . "\n\n";
    
    // Leer las primeras 3 filas (nuestras organizaciones de prueba)
    for ($i = 0; $i < 3; $i++) {
        $row = fgetcsv($handle);
        if ($row) {
            $csv_data[] = array_combine($headers, $row);
        }
    }
    
    fclose($handle);
    
    echo "Organizaciones a insertar:\n";
    foreach ($csv_data as $index => $org) {
        echo "  " . ($index + 1) . ". {$org['name']} - user_id: {$org['user_id']} - sales_owner: {$org['sales_owner']}\n";
    }
    
    // PASO 2: LIMPIAR DATOS PREVIOS (OPCIONAL)
    echo "\n🧹 LIMPIANDO DATOS PREVIOS\n";
    echo "=========================\n";
    
    $pdo->beginTransaction();
    
    foreach ($csv_data as $org_data) {
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
    
    // PASO 3: INSERTAR ORGANIZACIONES
    echo "\n🏢 INSERTANDO ORGANIZACIONES\n";
    echo "============================\n";
    
    $inserted_orgs = [];
    
    foreach ($csv_data as $org_data) {
        $stmt = $pdo->prepare("
            INSERT INTO organizations (name, user_id, created_at, updated_at) 
            VALUES (?, ?, NOW(), NOW())
        ");
        
        if ($stmt->execute([$org_data['name'], $org_data['user_id']])) {
            $org_id = $pdo->lastInsertId();
            $inserted_orgs[] = [
                'id' => $org_id,
                'name' => $org_data['name'],
                'user_id' => $org_data['user_id'],
                'address' => $org_data['address'],
                'rut' => $org_data['rut'],
                'sales_owner' => $org_data['sales_owner']
            ];
            
            echo "✅ Insertada: {$org_data['name']} (ID: $org_id, user_id: {$org_data['user_id']})\n";
        } else {
            echo "❌ Error insertando: {$org_data['name']}\n";
        }
    }
    
    // PASO 4: INSERTAR ATRIBUTOS
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
            echo "  ✅ Sales Owner (ID 36): {$org['user_id']}\n";
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
    }
    
    // PASO 5: INSERTAR PERSONAS ASOCIADAS
    echo "\n👥 INSERTANDO PERSONAS ASOCIADAS\n";
    echo "===============================\n";
    
    $persons_data = [
        ['name' => 'Juan Pérez', 'email' => 'juan.perez@air.cl', 'organization' => 'AIR Asociación de Industriales'],
        ['name' => 'María González', 'email' => 'maria.gonzalez@ald.cl', 'organization' => 'Ald Logística'],
        ['name' => 'Carlos Silva', 'email' => 'carlos.silva@alto.cl', 'organization' => 'Alto S.A.']
    ];
    
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
            }
        }
    }
    
    // Confirmar transacción
    $pdo->commit();
    
    // PASO 6: VERIFICACIÓN FINAL
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
            echo "   📋 Atributo {$attr['code']} (ID {$attr['attribute_id']}): $value\n";
        }
        
        // Verificar personas asociadas
        $stmt = $pdo->prepare("SELECT name, emails FROM persons WHERE organization_id = ?");
        $stmt->execute([$org['id']]);
        $persons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($persons as $person) {
            echo "   👤 Persona: {$person['name']}\n";
        }
    }
    
    echo "\n🎉 INSERCIÓN COMPLETA EXITOSA\n";
    echo "============================\n";
    echo "Organizaciones insertadas con user_id correctos:\n";
    foreach ($inserted_orgs as $org) {
        echo "- {$org['name']}: user_id = {$org['user_id']} (Sales Owner: {$org['sales_owner']})\n";
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
