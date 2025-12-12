<?php
/**
 * Script para corregir organizaciones con valores NULL
 * Problema: Algunas organizaciones tienen name, address y user_id como NULL
 * Solución: Actualizar estos campos con valores apropiados
 */

try {
    // Conexión a la base de datos
    $pdo = new PDO('mysql:host=krayin-db;dbname=krayincrm;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== DIAGNÓSTICO DE ORGANIZACIONES CON VALORES NULL ===\n";
    
    // 1. Identificar organizaciones con problemas
    $stmt = $pdo->query("
        SELECT id, name, address, user_id, created_at, updated_at
        FROM organizations 
        WHERE name IS NULL OR address IS NULL OR user_id IS NULL
        ORDER BY id
    ");
    
    $organizaciones_problematicas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Organizaciones con valores NULL encontradas: " . count($organizaciones_problematicas) . "\n";
    
    if (empty($organizaciones_problematicas)) {
        echo "No se encontraron organizaciones con valores NULL.\n";
        exit(0);
    }
    
    // Mostrar organizaciones problemáticas
    foreach ($organizaciones_problematicas as $org) {
        echo "ID: {$org['id']}, Name: " . ($org['name'] ?? 'NULL') . 
             ", Address: " . ($org['address'] ?? 'NULL') . 
             ", User ID: " . ($org['user_id'] ?? 'NULL') . "\n";
    }
    
    echo "\n=== INICIANDO CORRECCIÓN ===\n";
    
    // 2. Obtener un user_id válido por defecto
    $stmt = $pdo->query("SELECT id FROM users WHERE role_id = 1 LIMIT 1");
    $default_user = $stmt->fetch(PDO::FETCH_ASSOC);
    $default_user_id = $default_user ? $default_user['id'] : 1;
    
    echo "User ID por defecto a usar: $default_user_id\n";
    
    // 3. Corregir cada organización
    foreach ($organizaciones_problematicas as $org) {
        $org_id = $org['id'];
        
        // Obtener el RUT desde los atributos para usar como nombre si no existe
        $stmt = $pdo->prepare("
            SELECT text_value 
            FROM attribute_values 
            WHERE entity_type = 'organizations' 
            AND entity_id = ? 
            AND attribute_id = 61
        ");
        $stmt->execute([$org_id]);
        $rut_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $rut = $rut_data ? $rut_data['text_value'] : "Organización $org_id";
        
        // Obtener nombre desde atributos (attribute_id = 34)
        $stmt = $pdo->prepare("
            SELECT text_value 
            FROM attribute_values 
            WHERE entity_type = 'organizations' 
            AND entity_id = ? 
            AND attribute_id = 34
        ");
        $stmt->execute([$org_id]);
        $name_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $name = $name_data ? $name_data['text_value'] : $rut;
        
        // Obtener dirección desde atributos (attribute_id = 35)
        $stmt = $pdo->prepare("
            SELECT json_value 
            FROM attribute_values 
            WHERE entity_type = 'organizations' 
            AND entity_id = ? 
            AND attribute_id = 35
        ");
        $stmt->execute([$org_id]);
        $address_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $address = '{"city":"","state":"","address":"","country":"CL","postcode":""}';
        if ($address_data && $address_data['json_value']) {
            $address = json_encode($address_data['json_value']);
        }
        
        // Obtener user_id desde atributos (attribute_id = 36)
        $stmt = $pdo->prepare("
            SELECT integer_value 
            FROM attribute_values 
            WHERE entity_type = 'organizations' 
            AND entity_id = ? 
            AND attribute_id = 36
        ");
        $stmt->execute([$org_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_id = $user_data ? $user_data['integer_value'] : $default_user_id;
        
        // Actualizar la organización
        $stmt = $pdo->prepare("
            UPDATE organizations 
            SET 
                name = COALESCE(name, ?),
                address = COALESCE(address, ?),
                user_id = COALESCE(user_id, ?),
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $result = $stmt->execute([$name, $address, $user_id, $org_id]);
        
        if ($result) {
            echo "✓ Organización ID $org_id corregida:\n";
            echo "  - Name: $name\n";
            echo "  - Address: $address\n";
            echo "  - User ID: $user_id\n";
        } else {
            echo "✗ Error al corregir organización ID $org_id\n";
        }
    }
    
    echo "\n=== VERIFICACIÓN FINAL ===\n";
    
    // 4. Verificar que se corrigieron los problemas
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM organizations 
        WHERE name IS NULL OR address IS NULL OR user_id IS NULL
    ");
    
    $remaining_issues = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($remaining_issues == 0) {
        echo "✓ Todas las organizaciones han sido corregidas exitosamente.\n";
    } else {
        echo "⚠ Aún quedan $remaining_issues organizaciones con valores NULL.\n";
    }
    
    // 5. Mostrar algunas organizaciones corregidas
    echo "\nEjemplos de organizaciones corregidas:\n";
    $stmt = $pdo->query("
        SELECT id, name, address, user_id 
        FROM organizations 
        WHERE id IN (" . implode(',', array_column($organizaciones_problematicas, 'id')) . ")
        LIMIT 5
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']}, Name: {$row['name']}, User ID: {$row['user_id']}\n";
    }
    
    echo "\n=== RECOMENDACIONES ===\n";
    echo "1. Limpiar caché de Krayin:\n";
    echo "   docker exec krayin-app php artisan cache:clear\n";
    echo "2. Reiniciar el contenedor si es necesario:\n";
    echo "   docker restart krayin-app\n";
    echo "3. Verificar que las organizaciones ahora aparezcan correctamente en la UI\n";
    
} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== SCRIPT COMPLETADO ===\n";
?>
