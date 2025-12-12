<?php
/**
 * Script para verificar y corregir atributos faltantes en organizaciones
 * Problema: Algunas organizaciones no tienen todos los atributos necesarios en attribute_values
 * Solución: Crear los registros faltantes en attribute_values
 */

try {
    // Conexión a la base de datos
    $pdo = new PDO('mysql:host=krayin-db;dbname=krayincrm;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== VERIFICACIÓN DE ATRIBUTOS FALTANTES ===\n";
    
    // Atributos requeridos para organizaciones
    $required_attributes = [
        34 => 'name',
        35 => 'address', 
        36 => 'user_id',
        61 => 'rut'
    ];
    
    // 1. Obtener todas las organizaciones
    $stmt = $pdo->query("SELECT id FROM organizations ORDER BY id");
    $organizations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Total de organizaciones: " . count($organizations) . "\n";
    
    $missing_attributes = [];
    $organizations_with_issues = [];
    
    // 2. Verificar cada organización
    foreach ($organizations as $org_id) {
        echo "Verificando organización ID: $org_id\n";
        
        $org_missing = [];
        
        foreach ($required_attributes as $attr_id => $attr_name) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM attribute_values 
                WHERE entity_type = 'organizations' 
                AND entity_id = ? 
                AND attribute_id = ?
            ");
            $stmt->execute([$org_id, $attr_id]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count == 0) {
                $org_missing[] = $attr_name;
                $missing_attributes[] = [
                    'org_id' => $org_id,
                    'attr_id' => $attr_id,
                    'attr_name' => $attr_name
                ];
            }
        }
        
        if (!empty($org_missing)) {
            $organizations_with_issues[$org_id] = $org_missing;
            echo "  ⚠ Faltan atributos: " . implode(', ', $org_missing) . "\n";
        } else {
            echo "  ✓ Todos los atributos presentes\n";
        }
    }
    
    echo "\n=== RESUMEN ===\n";
    echo "Organizaciones con atributos faltantes: " . count($organizations_with_issues) . "\n";
    echo "Total de atributos faltantes: " . count($missing_attributes) . "\n";
    
    if (empty($missing_attributes)) {
        echo "✓ Todas las organizaciones tienen todos los atributos requeridos.\n";
        exit(0);
    }
    
    // 3. Mostrar detalles de organizaciones problemáticas
    echo "\nOrganizaciones con problemas:\n";
    foreach ($organizations_with_issues as $org_id => $missing) {
        echo "ID $org_id: faltan " . implode(', ', $missing) . "\n";
    }
    
    echo "\n=== INICIANDO CORRECCIÓN ===\n";
    
    // 4. Obtener datos de organizaciones desde CSV para completar información
    $csv_file = 'organizations.csv';
    $csv_data = [];
    
    if (file_exists($csv_file)) {
        echo "Leyendo datos desde $csv_file...\n";
        $handle = fopen($csv_file, 'r');
        $headers = fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            $data = array_combine($headers, $row);
            $csv_data[$data['id']] = $data;
        }
        fclose($handle);
        echo "Datos CSV cargados: " . count($csv_data) . " registros\n";
    }
    
    // 5. Obtener user_id por defecto
    $stmt = $pdo->query("SELECT id FROM users WHERE role_id = 1 LIMIT 1");
    $default_user = $stmt->fetch(PDO::FETCH_ASSOC);
    $default_user_id = $default_user ? $default_user['id'] : 1;
    
    // 6. Crear atributos faltantes
    foreach ($missing_attributes as $missing) {
        $org_id = $missing['org_id'];
        $attr_id = $missing['attr_id'];
        $attr_name = $missing['attr_name'];
        
        echo "Creando atributo '$attr_name' para organización $org_id...\n";
        
        $value = null;
        $text_value = null;
        $json_value = null;
        $integer_value = null;
        
        // Determinar el valor según el tipo de atributo
        switch ($attr_id) {
            case 34: // name
                if (isset($csv_data[$org_id])) {
                    $text_value = $csv_data[$org_id]['org_name'] ?? "Organización $org_id";
                } else {
                    $text_value = "Organización $org_id";
                }
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
                if (isset($csv_data[$org_id])) {
                    $text_value = $csv_data[$org_id]['rut'] ?? "";
                } else {
                    $text_value = "";
                }
                break;
        }
        
        // Insertar el atributo faltante
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
        
        $result = $stmt->execute([
            $org_id, $attr_id,
            $text_value, $json_value, $integer_value
        ]);
        
        if ($result) {
            echo "  ✓ Atributo '$attr_name' creado exitosamente\n";
        } else {
            echo "  ✗ Error al crear atributo '$attr_name'\n";
        }
    }
    
    echo "\n=== ACTUALIZANDO TABLA ORGANIZATIONS ===\n";
    
    // 7. Actualizar la tabla organizations con los datos de los atributos
    foreach ($organizations_with_issues as $org_id => $missing_attrs) {
        echo "Actualizando organización $org_id...\n";
        
        // Obtener datos actualizados de attribute_values
        $name = null;
        $address = null;
        $user_id = null;
        
        // Obtener name
        $stmt = $pdo->prepare("
            SELECT text_value FROM attribute_values 
            WHERE entity_type = 'organizations' AND entity_id = ? AND attribute_id = 34
        ");
        $stmt->execute([$org_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) $name = $result['text_value'];
        
        // Obtener address
        $stmt = $pdo->prepare("
            SELECT json_value FROM attribute_values 
            WHERE entity_type = 'organizations' AND entity_id = ? AND attribute_id = 35
        ");
        $stmt->execute([$org_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) $address = $result['json_value'];
        
        // Obtener user_id
        $stmt = $pdo->prepare("
            SELECT integer_value FROM attribute_values 
            WHERE entity_type = 'organizations' AND entity_id = ? AND attribute_id = 36
        ");
        $stmt->execute([$org_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) $user_id = $result['integer_value'];
        
        // Actualizar organizations
        $stmt = $pdo->prepare("
            UPDATE organizations 
            SET name = ?, address = ?, user_id = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $result = $stmt->execute([$name, $address, $user_id, $org_id]);
        
        if ($result) {
            echo "  ✓ Organización $org_id actualizada\n";
        } else {
            echo "  ✗ Error al actualizar organización $org_id\n";
        }
    }
    
    echo "\n=== VERIFICACIÓN FINAL ===\n";
    
    // 8. Verificación final
    $final_issues = 0;
    foreach ($organizations as $org_id) {
        foreach ($required_attributes as $attr_id => $attr_name) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM attribute_values 
                WHERE entity_type = 'organizations' 
                AND entity_id = ? 
                AND attribute_id = ?
            ");
            $stmt->execute([$org_id, $attr_id]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count == 0) {
                $final_issues++;
            }
        }
    }
    
    if ($final_issues == 0) {
        echo "✓ Todos los atributos han sido creados exitosamente.\n";
    } else {
        echo "⚠ Aún faltan $final_issues atributos.\n";
    }
    
    echo "\n=== RECOMENDACIONES ===\n";
    echo "1. Ejecutar el script de corrección de organizaciones NULL:\n";
    echo "   php corregir_organizaciones_null.php\n";
    echo "2. Limpiar caché de Krayin:\n";
    echo "   docker exec krayin-app php artisan cache:clear\n";
    echo "3. Reiniciar el contenedor:\n";
    echo "   docker restart krayin-app\n";
    
} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== SCRIPT COMPLETADO ===\n";
?>
