<?php
/**
 * Script para insertar las primeras 3 organizaciones del CSV con personas asociadas
 * Krayin CRM - Inserción completa con atributos correctos
 * 
 * Organizaciones del CSV:
 * 1. AIR Asociación de Industriales
 * 2. Ald Logística  
 * 3. Alto S.A.
 */

// Configuración automática de la base de datos
require_once 'database_config.php';

try {
    $pdo = DatabaseConfig::createConnection();
    $config = DatabaseConfig::getConfig();
    echo "✅ Conexión exitosa a la base de datos (entorno: {$config['environment']})\n";
    
    echo "📋 Leyendo user_id desde CSV para cada organización\n\n";
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    // Leer datos del CSV
    $csv_file = 'organizations_with_user_id.csv';
    $organizaciones = [];
    
    if (($handle = fopen($csv_file, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ","); // Leer encabezados
        $count = 0;
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE && $count < 3) {
            $org = array_combine($headers, $data);
            $organizaciones[] = $org;
            $count++;
        }
        fclose($handle);
    } else {
        die("❌ Error: No se pudo abrir el archivo CSV\n");
    }
    
    $organization_ids = [];
    
    echo "🏢 INSERTANDO ORGANIZACIONES:\n";
    echo "================================\n";
    
    foreach ($organizaciones as $index => $org) {
        // Crear JSON para address con formato correcto según Krayin
        $address_json = json_encode([
            "city" => $org['city'] ?: "",
            "state" => $org['state'] ?: "", 
            "address" => $org['address'] ?: "",
            "country" => "CL",
            "postcode" => ""
        ], JSON_UNESCAPED_UNICODE);
        
        // Insertar organización
        $sql = "INSERT INTO organizations (name, address, user_id, created_at, updated_at) 
                VALUES (?, ?, ?, NOW(), NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $org['name'],
            $address_json,
            $org['user_id']
        ]);
        
        $org_id = $pdo->lastInsertId();
        $organization_ids[] = $org_id;
        
        echo "✅ Organización insertada: {$org['name']} (ID: $org_id)\n";
        
        // Insertar atributos en attribute_values con IDs CORRECTOS según base de datos real
        
        // ID 34 = name (text_value)
        $stmt_name = $pdo->prepare("
            INSERT INTO attribute_values (attribute_id, entity_id, entity_type, text_value) 
            VALUES (34, ?, 'organizations', ?)
        ");
        $stmt_name->execute([$org_id, $org['name']]);
        echo "   ✅ Name insertado: ID 34 = {$org['name']}\n";
        
        // ID 35 = address (json_value) - formato correcto
        $stmt_address = $pdo->prepare("
            INSERT INTO attribute_values (attribute_id, entity_id, entity_type, json_value) 
            VALUES (35, ?, 'organizations', ?)
        ");
        $stmt_address->execute([$org_id, $address_json]);
        echo "   ✅ Address insertado: ID 35 = $address_json\n";
        
        // ID 36 = user_id (lookup_value - según tipo en DB es lookup)
         $stmt_user = $pdo->prepare("
             INSERT INTO attribute_values (attribute_id, entity_id, entity_type, text_value) 
             VALUES (36, ?, 'organizations', ?)
         ");
         $stmt_user->execute([$org_id, $org['user_id']]);
         echo "   ✅ User_id insertado: ID 36 = {$org['user_id']}\n";
         
         // Insertar source como atributo adicional si es necesario
         if (!empty($org['source'])) {
             $stmt_source = $pdo->prepare("
                 INSERT INTO attribute_values (attribute_id, entity_id, entity_type, text_value) 
                 VALUES (21, ?, 'organizations', ?)
             ");
             $stmt_source->execute([$org_id, $org['source']]);
             echo "   ✅ Source insertado: ID 21 = {$org['source']}\n";
         }
        
        // ID 61 = RUT (text_value) - solo si existe
        if (!empty($org['rut'])) {
            $stmt_rut = $pdo->prepare("
                INSERT INTO attribute_values (attribute_id, entity_id, entity_type, text_value) 
                VALUES (61, ?, 'organizations', ?)
            ");
            $stmt_rut->execute([$org_id, $org['rut']]);
            echo "   ✅ RUT insertado: ID 61 = {$org['rut']}\n";
        }
        
        echo "   📝 Atributos insertados para {$org['name']}\n";
    }
    
    echo "\n👥 INSERTANDO PERSONAS ASOCIADAS:\n";
    echo "==================================\n";
    
    // Datos de personas asociadas (basado en los emails de contacto)
    $personas = [
        [
            'name' => 'David Sepúlveda',
            'emails' => json_encode([['label' => 'work', 'value' => 'dsepulveda@air.cl']], JSON_UNESCAPED_UNICODE),
            'contact_numbers' => json_encode([['label' => 'work', 'value' => '56222751068']], JSON_UNESCAPED_UNICODE),
            'organization_id' => $organization_ids[0],
            'job_title' => 'Contacto Principal'
        ],
        [
            'name' => 'Jorge Agurto',
            'emails' => json_encode([['label' => 'work', 'value' => 'jagurto@agurto.cl']], JSON_UNESCAPED_UNICODE),
            'contact_numbers' => json_encode([['label' => 'work', 'value' => '56225448620']], JSON_UNESCAPED_UNICODE),
            'organization_id' => $organization_ids[1],
            'job_title' => 'Contacto Principal'
        ],
        [
            'name' => 'Miguel Arancibia',
            'emails' => json_encode([['label' => 'work', 'value' => 'marancibia@grupoalto.com']], JSON_UNESCAPED_UNICODE),
            'contact_numbers' => json_encode([['label' => 'work', 'value' => '56963109170']], JSON_UNESCAPED_UNICODE),
            'organization_id' => $organization_ids[2],
            'job_title' => 'Contacto Principal'
        ]
    ];
    
    foreach ($personas as $persona) {
        $sql_person = "INSERT INTO persons (name, emails, contact_numbers, organization_id, job_title, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt_person = $pdo->prepare($sql_person);
        $stmt_person->execute([
            $persona['name'],
            $persona['emails'],
            $persona['contact_numbers'],
            $persona['organization_id'],
            $persona['job_title']
        ]);
        
        $person_id = $pdo->lastInsertId();
        echo "✅ Persona insertada: {$persona['name']} (ID: $person_id) - Organización ID: {$persona['organization_id']}\n";
    }
    
    // Confirmar transacción
    $pdo->commit();
    
    echo "\n🎉 INSERCIÓN COMPLETADA EXITOSAMENTE\n";
    echo "=====================================\n";
    
    // Verificación final
    echo "\n📊 VERIFICACIÓN FINAL:\n";
    echo "======================\n";
    
    // Mostrar organizaciones insertadas
    $sql_verify = "SELECT id, name, address FROM organizations WHERE id IN (" . implode(',', $organization_ids) . ") ORDER BY id";
    $stmt_verify = $pdo->query($sql_verify);
    
    echo "\n🏢 Organizaciones insertadas:\n";
    while ($row = $stmt_verify->fetch(PDO::FETCH_ASSOC)) {
        $address_data = json_decode($row['address'], true);
        $email = $address_data['email'] ?? 'Sin email';
        $phone = $address_data['phone'] ?? 'Sin teléfono';
        
        echo "   ID: {$row['id']} | {$row['name']} | $email | $phone\n";
    }
    
    // Mostrar personas insertadas
    $sql_persons = "SELECT p.id, p.name, p.emails, p.organization_id, o.name as org_name 
                   FROM persons p 
                   JOIN organizations o ON p.organization_id = o.id 
                   WHERE p.organization_id IN (" . implode(',', $organization_ids) . ") 
                   ORDER BY p.organization_id";
    
    $stmt_persons = $pdo->query($sql_persons);
    
    echo "\n👥 Personas insertadas:\n";
    while ($row = $stmt_persons->fetch(PDO::FETCH_ASSOC)) {
        $emails_data = json_decode($row['emails'], true);
        $email = $emails_data[0]['value'] ?? 'Sin email';
        
        echo "   ID: {$row['id']} | {$row['name']} | $email | Org: {$row['org_name']}\n";
    }
    
    // Consulta de verificación con IDs correctos
    echo "\n=== VERIFICACIÓN DE DATOS INSERTADOS ===\n";
    $verification_query = "SELECT 
                            o.id as org_id,
                            o.name as org_name,
                            av.attribute_id,
                            a.code as attribute_name,
                            a.type as attribute_type,
                            CASE 
                                WHEN av.text_value IS NOT NULL THEN av.text_value
                                WHEN av.integer_value IS NOT NULL THEN CAST(av.integer_value AS CHAR)
                                WHEN av.json_value IS NOT NULL THEN av.json_value
                                ELSE 'NULL'
                            END as value
                         FROM organizations o
                         LEFT JOIN attribute_values av ON o.id = av.entity_id AND av.entity_type = 'organizations'
                         LEFT JOIN attributes a ON av.attribute_id = a.id
                         WHERE o.id >= (SELECT MAX(id) - 2 FROM organizations)
                         ORDER BY o.id, av.attribute_id";

    $stmt_verify = $pdo->prepare($verification_query);
    $stmt_verify->execute();
    $results = $stmt_verify->fetchAll(PDO::FETCH_ASSOC);

    echo "\nResultados de verificación:\n";
    echo "ID 34 = name (text) | ID 35 = address (json) | ID 36 = user_id (lookup) | ID 61 = rut (text)\n\n";

    foreach ($results as $row) {
        echo "Org ID: {$row['org_id']} | Org Name: {$row['org_name']} | Attr ID: {$row['attribute_id']} | Attr Name: {$row['attribute_name']} | Type: {$row['attribute_type']} | Value: {$row['value']}\n";
    }
    
    // Mostrar atributos insertados
    echo "\n📝 Atributos insertados:\n";
    $sql_attrs = "SELECT av.entity_id, a.code, av.text_value, av.integer_value, av.json_value 
                 FROM attribute_values av 
                 JOIN attributes a ON av.attribute_id = a.id 
                 WHERE av.entity_type = 'organizations' 
                 AND av.entity_id IN (" . implode(',', $organization_ids) . ") 
                 ORDER BY av.entity_id, a.code";
    
    $stmt_attrs = $pdo->query($sql_attrs);
    
    $current_org = null;
    while ($row = $stmt_attrs->fetch(PDO::FETCH_ASSOC)) {
        if ($current_org !== $row['entity_id']) {
            $current_org = $row['entity_id'];
            echo "   Organización ID {$row['entity_id']}:\n";
        }
        
        // Determinar qué valor mostrar según el tipo
        $value = '';
        if (!empty($row['text_value'])) {
            $value = $row['text_value'];
        } elseif (!empty($row['integer_value'])) {
            $value = $row['integer_value'];
        } elseif (!empty($row['json_value'])) {
            $value = $row['json_value'];
        }
        
        echo "     - {$row['code']}: {$value}\n";
    }
    
    echo "\n✅ PROCESO COMPLETADO - Las organizaciones y personas están listas para usar en Krayin CRM\n";
    echo "\n💡 RECOMENDACIONES POST-INSERCIÓN:\n";
    echo "1. Limpiar caché de Krayin: php artisan cache:clear\n";
    echo "2. Reiniciar contenedor si es necesario\n";
    echo "3. Verificar en la interfaz web de Krayin\n";
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\n🔧 SOLUCIÓN SUGERIDA:\n";
    echo "1. Verificar que la base de datos 'krayincrm' existe\n";
    echo "2. Verificar credenciales de conexión\n";
    echo "3. Verificar que las tablas 'organizations', 'persons', 'attributes' y 'attribute_values' existen\n";
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    echo "❌ Error general: " . $e->getMessage() . "\n";
}

echo "\n🏁 Script finalizado.\n";
?>
