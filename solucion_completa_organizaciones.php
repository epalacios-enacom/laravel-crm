<?php
/**
 * Script maestro para solucionar completamente el problema de organizaciones
 * que no aparecen correctamente en la interfaz de Krayin
 * 
 * PROBLEMA IDENTIFICADO:
 * - Algunas organizaciones tienen valores NULL en campos básicos (name, address, user_id)
 * - Faltan registros en attribute_values para algunos atributos requeridos
 * - La interfaz de Krayin requiere tanto los datos en la tabla organizations 
 *   como en attribute_values para mostrar correctamente la información
 * 
 * SOLUCIÓN:
 * 1. Verificar y crear atributos faltantes en attribute_values
 * 2. Corregir valores NULL en la tabla organizations
 * 3. Sincronizar datos entre ambas tablas
 * 4. Verificar integridad final
 */

try {
    // Conexión a la base de datos
    $pdo = new PDO('mysql:host=krayin-db;dbname=krayincrm;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== SOLUCIÓN COMPLETA PARA ORGANIZACIONES ===\n";
    echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";
    
    // PASO 1: DIAGNÓSTICO INICIAL
    echo "PASO 1: DIAGNÓSTICO INICIAL\n";
    echo "==============================\n";
    
    // Contar organizaciones totales
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM organizations");
    $total_orgs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Total de organizaciones: $total_orgs\n";
    
    // Contar organizaciones con problemas en tabla organizations
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM organizations 
        WHERE name IS NULL OR address IS NULL OR user_id IS NULL
    ");
    $orgs_with_null = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Organizaciones con valores NULL: $orgs_with_null\n";
    
    // Verificar atributos faltantes
    $required_attributes = [34, 35, 36, 61]; // name, address, user_id, rut
    $missing_attrs_count = 0;
    
    foreach ($required_attributes as $attr_id) {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT o.id) as missing_count
            FROM organizations o
            LEFT JOIN attribute_values av ON (o.id = av.entity_id AND av.entity_type = 'organizations' AND av.attribute_id = ?)
            WHERE av.id IS NULL
        ");
        $stmt->execute([$attr_id]);
        $missing = $stmt->fetch(PDO::FETCH_ASSOC)['missing_count'];
        $missing_attrs_count += $missing;
        echo "Organizaciones sin atributo ID $attr_id: $missing\n";
    }
    
    echo "\nProblemas detectados:\n";
    echo "- Valores NULL en organizations: $orgs_with_null\n";
    echo "- Atributos faltantes: $missing_attrs_count\n";
    
    if ($orgs_with_null == 0 && $missing_attrs_count == 0) {
        echo "\n✓ No se detectaron problemas. Todas las organizaciones están correctas.\n";
        exit(0);
    }
    
    echo "\n";
    
    // PASO 2: CREAR ATRIBUTOS FALTANTES
    echo "PASO 2: CREANDO ATRIBUTOS FALTANTES\n";
    echo "====================================\n";
    
    // Obtener datos del CSV si existe
    $csv_data = [];
    $csv_file = 'organizations.csv';
    
    if (file_exists($csv_file)) {
        echo "Cargando datos desde $csv_file...\n";
        $handle = fopen($csv_file, 'r');
        $headers = fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            $data = array_combine($headers, $row);
            $csv_data[$data['id']] = $data;
        }
        fclose($handle);
        echo "Datos CSV cargados: " . count($csv_data) . " registros\n";
    } else {
        echo "Archivo CSV no encontrado. Usando valores por defecto.\n";
    }
    
    // Obtener user_id por defecto
    $stmt = $pdo->query("SELECT id FROM users WHERE role_id = 1 LIMIT 1");
    $default_user = $stmt->fetch(PDO::FETCH_ASSOC);
    $default_user_id = $default_user ? $default_user['id'] : 1;
    echo "User ID por defecto: $default_user_id\n\n";
    
    // Crear atributos faltantes para cada organización
    $stmt = $pdo->query("SELECT id FROM organizations ORDER BY id");
    $organizations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $created_attrs = 0;
    
    foreach ($organizations as $org_id) {
        foreach ($required_attributes as $attr_id) {
            // Verificar si el atributo existe
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM attribute_values 
                WHERE entity_type = 'organizations' 
                AND entity_id = ? 
                AND attribute_id = ?
            ");
            $stmt->execute([$org_id, $attr_id]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
            
            if (!$exists) {
                // Crear el atributo faltante
                $text_value = null;
                $json_value = null;
                $integer_value = null;
                
                switch ($attr_id) {
                    case 34: // name
                        $text_value = isset($csv_data[$org_id]) ? 
                            ($csv_data[$org_id]['org_name'] ?? "Organización $org_id") : 
                            "Organización $org_id";
                        break;
                        
                    case 35: // address
                        if (isset($csv_data[$org_id])) {
                            $address_data = [
                                'city' => $csv_data[$org_id]['city'] ?? '',
                                'state' => $csv_data[$org_id]['state'] ?? '',
                                'address' => $csv_data[$org_id]['address'] ?? '',
                                'country' => 'CL',
                                'postcode' => ''
                            ];
                            $json_value = json_encode($address_data);
                        } else {
                            $json_value = '{"city":"","state":"","address":"","country":"CL","postcode":""}';
                        }
                        break;
                        
                    case 36: // user_id
                        $integer_value = $default_user_id;
                        break;
                        
                    case 61: // rut
                        $text_value = isset($csv_data[$org_id]) ? 
                            ($csv_data[$org_id]['rut'] ?? "") : "";
                        break;
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO attribute_values (
                        entity_type, entity_id, attribute_id, 
                        text_value, json_value, integer_value,
                        created_at, updated_at
                    ) VALUES (
                        'organizations', ?, ?,
                        ?, ?, ?,
                        NOW(), NOW()
                    )
                ");
                
                if ($stmt->execute([$org_id, $attr_id, $text_value, $json_value, $integer_value])) {
                    $created_attrs++;
                    if ($created_attrs % 10 == 0) {
                        echo "Creados $created_attrs atributos...\n";
                    }
                }
            }
        }
    }
    
    echo "Atributos creados: $created_attrs\n\n";
    
    // PASO 3: CORREGIR VALORES NULL EN ORGANIZATIONS
    echo "PASO 3: CORRIGIENDO VALORES NULL EN ORGANIZATIONS\n";
    echo "================================================\n";
    
    $stmt = $pdo->query("
        SELECT id FROM organizations 
        WHERE name IS NULL OR address IS NULL OR user_id IS NULL
        ORDER BY id
    ");
    $orgs_to_fix = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $fixed_orgs = 0;
    
    foreach ($orgs_to_fix as $org_id) {
        // Obtener datos de attribute_values
        $name = null;
        $address = null;
        $user_id = null;
        
        // Obtener name (attr_id = 34)
        $stmt = $pdo->prepare("
            SELECT text_value FROM attribute_values 
            WHERE entity_type = 'organizations' AND entity_id = ? AND attribute_id = 34
        ");
        $stmt->execute([$org_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) $name = $result['text_value'];
        
        // Obtener address (attr_id = 35)
        $stmt = $pdo->prepare("
            SELECT json_value FROM attribute_values 
            WHERE entity_type = 'organizations' AND entity_id = ? AND attribute_id = 35
        ");
        $stmt->execute([$org_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $address = is_string($result['json_value']) ? 
                $result['json_value'] : 
                json_encode($result['json_value']);
        }
        
        // Obtener user_id (attr_id = 36)
        $stmt = $pdo->prepare("
            SELECT integer_value FROM attribute_values 
            WHERE entity_type = 'organizations' AND entity_id = ? AND attribute_id = 36
        ");
        $stmt->execute([$org_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) $user_id = $result['integer_value'];
        
        // Valores por defecto si no se encontraron
        $name = $name ?: "Organización $org_id";
        $address = $address ?: '{"city":"","state":"","address":"","country":"CL","postcode":""}';
        $user_id = $user_id ?: $default_user_id;
        
        // Actualizar organizations
        $stmt = $pdo->prepare("
            UPDATE organizations 
            SET name = ?, address = ?, user_id = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        if ($stmt->execute([$name, $address, $user_id, $org_id])) {
            $fixed_orgs++;
        }
    }
    
    echo "Organizaciones corregidas: $fixed_orgs\n\n";
    
    // PASO 4: VERIFICACIÓN FINAL
    echo "PASO 4: VERIFICACIÓN FINAL\n";
    echo "==========================\n";
    
    // Verificar valores NULL restantes
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM organizations 
        WHERE name IS NULL OR address IS NULL OR user_id IS NULL
    ");
    $remaining_nulls = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Verificar atributos faltantes restantes
    $remaining_missing = 0;
    foreach ($required_attributes as $attr_id) {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT o.id) as missing_count
            FROM organizations o
            LEFT JOIN attribute_values av ON (o.id = av.entity_id AND av.entity_type = 'organizations' AND av.attribute_id = ?)
            WHERE av.id IS NULL
        ");
        $stmt->execute([$attr_id]);
        $missing = $stmt->fetch(PDO::FETCH_ASSOC)['missing_count'];
        $remaining_missing += $missing;
    }
    
    echo "Verificación final:\n";
    echo "- Valores NULL restantes: $remaining_nulls\n";
    echo "- Atributos faltantes restantes: $remaining_missing\n";
    
    if ($remaining_nulls == 0 && $remaining_missing == 0) {
        echo "\n✓ CORRECCIÓN COMPLETADA EXITOSAMENTE\n";
        echo "Todas las organizaciones han sido corregidas.\n";
    } else {
        echo "\n⚠ CORRECCIÓN PARCIAL\n";
        echo "Algunos problemas persisten. Revisar manualmente.\n";
    }
    
    // PASO 5: MOSTRAR EJEMPLOS
    echo "\nEjemplos de organizaciones corregidas:\n";
    $stmt = $pdo->query("
        SELECT o.id, o.name, o.user_id,
               (SELECT text_value FROM attribute_values WHERE entity_type = 'organizations' AND entity_id = o.id AND attribute_id = 61) as rut
        FROM organizations o
        ORDER BY o.id
        LIMIT 5
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']}, Name: {$row['name']}, User ID: {$row['user_id']}, RUT: {$row['rut']}\n";
    }
    
    echo "\n=== PASOS FINALES RECOMENDADOS ===\n";
    echo "1. Limpiar caché de Krayin:\n";
    echo "   docker exec krayin-app php artisan cache:clear\n";
    echo "2. Limpiar caché de configuración:\n";
    echo "   docker exec krayin-app php artisan config:clear\n";
    echo "3. Reiniciar el contenedor:\n";
    echo "   docker restart krayin-app\n";
    echo "4. Verificar en la interfaz que las organizaciones aparezcan correctamente\n";
    echo "5. Si persisten problemas, revisar logs:\n";
    echo "   docker logs krayin-app\n";
    
} catch (PDOException $e) {
    echo "Error de conexión a la base de datos: " . $e->getMessage() . "\n";
    echo "Verificar que el contenedor krayin-db esté ejecutándose.\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== SCRIPT COMPLETADO ===\n";
echo "Fecha de finalización: " . date('Y-m-d H:i:s') . "\n";
?>
