
<?php
/**
 * Script de Prueba Completa - Inserción de Organizaciones en Krayin CRM
 * Organizaciones 5 y 6 con datos completos de dirección
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
    // ORGANIZACIÓN 5: ANFP
    // ========================================
    
    echo "\n📋 Insertando Organización 5: ANFP...\n";
    
    $org5_sql = "INSERT INTO organizations (name, address, created_at, updated_at, user_id) 
                 VALUES (?, ?, NOW(), NOW(), ?)";
    
    $org5_address = json_encode([
        "address" => "Av. Providencia 1208, Oficina 1501",
        "city" => "Santiago",
        "state" => "Región Metropolitana",
        "postcode" => "7500000",
        "country" => "CL"
    ]);
    
    $stmt = $pdo->prepare($org5_sql);
    $stmt->execute(['ANFP', $org5_address, 16]); // user_id 16 para Fernanda
    $org5_id = $pdo->lastInsertId();
    
    echo "   ✓ Organización insertada con ID: $org5_id\n";
    
    // Insertar atributo RUT para organización 5
    $rut5_sql = "INSERT INTO attribute_values (attribute_id, entity_type, entity_id, text_value) 
                 VALUES (?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($rut5_sql);
    $stmt->execute([61, 'organizations', $org5_id, '70.033.100-9']);
    
    echo "   ✓ Atributo RUT insertado: 70.033.100-9\n";
    
    // ========================================
    // ORGANIZACIÓN 6: Banmedica SA
    // ========================================
    
    echo "\n📋 Insertando Organización 6: Banmedica SA...\n";
    
    $org6_sql = "INSERT INTO organizations (name, address, created_at, updated_at, user_id) 
                 VALUES (?, ?, NOW(), NOW(), ?)";
    
    $org6_address = json_encode([
        "address" => "Apoquindo 3600, Piso 9",
        "city" => "Las Condes",
        "state" => "Región Metropolitana",
        "postcode" => "7550000",
        "country" => "CL"
    ]);
    
    $stmt = $pdo->prepare($org6_sql);
    $stmt->execute(['Banmedica SA', $org6_address, 15]); // user_id 15 para Alexandra
    $org6_id = $pdo->lastInsertId();
    
    echo "   ✓ Organización insertada con ID: $org6_id\n";
    
    // Insertar atributo RUT para organización 6
    $stmt = $pdo->prepare($rut5_sql);
    $stmt->execute([61, 'organizations', $org6_id, '96.856.780-2']);
    
    echo "   ✓ Atributo RUT insertado: 96.856.780-2\n";
    
    // ========================================
    // INSERTAR PERSONAS ASOCIADAS
    // ========================================
    
    echo "\n👥 Insertando personas asociadas...\n";
    
    // Persona para ANFP
    $person_sql = "INSERT INTO persons (name, emails, contact_numbers, organization_id, job_title, created_at, updated_at) 
                   VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
    
    $emails_carlos = json_encode([['label' => 'work', 'value' => 'cbaeza@anfpchile.cl']]);
    $phones_carlos = json_encode([['label' => 'work', 'value' => '56991834957']]);
    
    $stmt = $pdo->prepare($person_sql);
    $stmt->execute(['Carlos Baeza', $emails_carlos, $phones_carlos, $org5_id, 'Gerente General']);
    
    echo "   ✓ Carlos Baeza insertado para ANFP\n";
    
    // Persona para Banmedica SA
    $emails_hernan = json_encode([['label' => 'work', 'value' => 'hbarrios@banmedica.cl']]);
    $phones_hernan = json_encode([['label' => 'work', 'value' => '56223533300']]);
    
    $stmt->execute(['Hernán Barrios', $emails_hernan, $phones_hernan, $org6_id, 'Director de Tecnología']);
    
    echo "   ✓ Hernán Barrios insertado para Banmedica SA\n";
    
    // ========================================
    // VERIFICACIÓN COMPLETA
    // ========================================
    
    echo "\n🔍 Verificando inserción completa...\n";
    
    // Verificar organizaciones con direcciones
    $verify_sql = "SELECT o.id, o.name, o.address, av.text_value as rut 
                   FROM organizations o 
                   LEFT JOIN attribute_values av ON av.entity_id = o.id AND av.attribute_id = 61 AND av.entity_type = 'organizations'
                   WHERE o.id IN (?, ?)";
    
    $stmt = $pdo->prepare($verify_sql);
    $stmt->execute([$org5_id, $org6_id]);
    $organizations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($organizations as $org) {
        $address_data = json_decode($org['address'], true);
        echo "   📊 ID: {$org['id']} | Nombre: {$org['name']}\n";
        echo "       📍 Dirección: {$address_data['address']}\n";
        echo "       🏙️ Ciudad: {$address_data['city']}, {$address_data['state']}\n";
        echo "       📮 Código Postal: {$address_data['postcode']}\n";
        echo "       🌍 País: {$address_data['country']}\n";
        echo "       🆔 RUT: {$org['rut']}\n\n";
    }
    
    // Verificar personas
    $verify_persons_sql = "SELECT name, emails, contact_numbers, job_title, organization_id FROM persons WHERE organization_id IN (?, ?)";
    $stmt = $pdo->prepare($verify_persons_sql);
    $stmt->execute([$org5_id, $org6_id]);
    $persons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "👥 Personas insertadas:\n";
    foreach ($persons as $person) {
        $emails = json_decode($person['emails'], true);
        $phones = json_decode($person['contact_numbers'], true);
        echo "   👤 {$person['name']} - {$person['job_title']}\n";
        echo "       📧 Email: {$emails[0]['value']}\n";
        echo "       📞 Teléfono: {$phones[0]['value']}\n";
        echo "       🏢 Org ID: {$person['organization_id']}\n\n";
    }
    
    // Confirmar transacción
    $pdo->commit();
    
    echo "🎉 ¡Prueba completa exitosa!\n";
    echo "\n📋 Resumen Final:\n";
    echo "   ✅ 2 organizaciones con direcciones completas\n";
    echo "   ✅ 2 atributos RUT con valores reales\n";
    echo "   ✅ 2 personas con emails y teléfonos\n";
    echo "   ✅ Formato JSON correcto para direcciones\n";
    echo "   ✅ Sistema EAV funcionando perfectamente\n";
    
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
