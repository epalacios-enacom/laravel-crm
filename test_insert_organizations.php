<?php
/**
 * Script de Prueba de Concepto - Inserción de Organizaciones en Krayin CRM
 * Organizaciones 2 y 3 con manejo de atributos EAV
 */

// Configuración de la base de datos
$host = 'krayin-db';  // Nombre del contenedor Docker
$dbname = 'krayincrm';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conexión exitosa a la base de datos\n";
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    // ========================================
    // ORGANIZACIÓN 2: Ald Logística
    // ========================================
    
    echo "\n📋 Insertando Organización 2: Ald Logística...\n";
    
    $org2_sql = "INSERT INTO organizations (name, address, created_at, updated_at, user_id) 
                 VALUES (?, ?, NOW(), NOW(), ?)";
    
    $org2_address = json_encode([
        "address" => "",
        "city" => "",
        "state" => "",
        "postcode" => "",
        "country" => "CL"
    ]);
    
    $stmt = $pdo->prepare($org2_sql);
    $stmt->execute(['Ald Logística', $org2_address, 15]);
    $org2_id = $pdo->lastInsertId();
    
    echo "   ✓ Organización insertada con ID: $org2_id\n";
    
    // Insertar atributo RUT para organización 2 (sin fechas)
    $rut2_sql = "INSERT INTO attribute_values (attribute_id, entity_type, entity_id, text_value) 
                 VALUES (?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($rut2_sql);
    $stmt->execute([61, 'organizations', $org2_id, '']);
    
    echo "   ✓ Atributo RUT insertado (vacío)\n";
    
    // ========================================
    // ORGANIZACIÓN 3: Alto S.A.
    // ========================================
    
    echo "\n📋 Insertando Organización 3: Alto S.A....\n";
    
    $org3_sql = "INSERT INTO organizations (name, address, created_at, updated_at, user_id) 
                 VALUES (?, ?, NOW(), NOW(), ?)";
    
    $org3_address = json_encode([
        "address" => "",
        "city" => "",
        "state" => "",
        "postcode" => "",
        "country" => "CL"
    ]);
    
    $stmt = $pdo->prepare($org3_sql);
    $stmt->execute(['Alto S.A.', $org3_address, 15]);
    $org3_id = $pdo->lastInsertId();
    
    echo "   ✓ Organización insertada con ID: $org3_id\n";
    
    // Insertar atributo RUT para organización 3 (sin fechas)
    $stmt = $pdo->prepare($rut2_sql);
    $stmt->execute([61, 'organizations', $org3_id, '']);
    
    echo "   ✓ Atributo RUT insertado (vacío)\n";
    
    // ========================================
    // INSERTAR PERSONAS ASOCIADAS
    // ========================================
    
    echo "\n👥 Insertando personas asociadas...\n";
    
    // Persona para Ald Logística
    $person_sql = "INSERT INTO persons (name, emails, contact_numbers, organization_id, job_title, created_at, updated_at) 
                   VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
    
    $emails_javier = json_encode([['label' => 'work', 'value' => 'jagurto@agurto.cl']]);
    $phones_javier = json_encode([['label' => 'work', 'value' => '56225448620']]);
    
    $stmt = $pdo->prepare($person_sql);
    $stmt->execute(['Javier Agurto', $emails_javier, $phones_javier, $org2_id, 'Compras TI']);
    
    echo "   ✓ Javier Agurto insertado para Ald Logística\n";
    
    // Personas para Alto S.A.
    $emails_mauricio = json_encode([['label' => 'work', 'value' => 'marancibia@grupoalto.com']]);
    $phones_mauricio = json_encode([['label' => 'work', 'value' => '56963109170']]);
    
    $stmt->execute(['Mauricio Arancibia', $emails_mauricio, $phones_mauricio, $org3_id, 'Encargado de compras']);
    
    echo "   ✓ Mauricio Arancibia insertado para Alto S.A.\n";
    
    $emails_cristian = json_encode([['label' => 'work', 'value' => 'cmunoz@grupoalto.com']]);
    $phones_cristian = json_encode([['label' => 'work', 'value' => '56963109170']]);
    
    $stmt->execute(['Cristian Muñoz', $emails_cristian, $phones_cristian, $org3_id, 'Jefe de Compras']);
    
    echo "   ✓ Cristian Muñoz insertado para Alto S.A.\n";
    
    // ========================================
    // VERIFICACIÓN
    // ========================================
    
    echo "\n🔍 Verificando inserción...\n";
    
    // Verificar organizaciones
    $verify_sql = "SELECT o.id, o.name, av.text_value as rut 
                   FROM organizations o 
                   LEFT JOIN attribute_values av ON av.entity_id = o.id AND av.attribute_id = 61 AND av.entity_type = 'organizations'
                   WHERE o.id IN (?, ?)";
    
    $stmt = $pdo->prepare($verify_sql);
    $stmt->execute([$org2_id, $org3_id]);
    $organizations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($organizations as $org) {
        echo "   📊 ID: {$org['id']} | Nombre: {$org['name']} | RUT: {$org['rut']}\n";
    }
    
    // Verificar personas
    $verify_persons_sql = "SELECT name, job_title, organization_id FROM persons WHERE organization_id IN (?, ?)";
    $stmt = $pdo->prepare($verify_persons_sql);
    $stmt->execute([$org2_id, $org3_id]);
    $persons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n👥 Personas insertadas:\n";
    foreach ($persons as $person) {
        echo "   👤 {$person['name']} - {$person['job_title']} (Org ID: {$person['organization_id']})\n";
    }
    
    // Confirmar transacción
    $pdo->commit();
    
    echo "\n🎉 ¡Prueba de concepto completada exitosamente!\n";
    echo "\n📋 Resumen:\n";
    echo "   - 2 organizaciones insertadas\n";
    echo "   - 2 atributos RUT insertados (usando EAV)\n";
    echo "   - 3 personas insertadas\n";
    echo "   - Sin problemas de LAST_INSERT_ID\n";
    
} catch (PDOException $e) {
    // Rollback en caso de error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\n🔧 Verifica:\n";
    echo "   - Credenciales de base de datos\n";
    echo "   - Que el atributo ID 61 existe\n";
    echo "   - Permisos de la base de datos\n";
}

?>
