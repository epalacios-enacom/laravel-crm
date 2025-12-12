<?php
/**
 * Script simple para verificar si las organizaciones se insertaron correctamente
 */

try {
    // Conexión a la base de datos
    $pdo = new PDO('mysql:host=krayin-db;dbname=krayincrm;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== VERIFICACIÓN SIMPLE DE ORGANIZACIONES ===\n";
    echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Verificar últimas organizaciones insertadas
    echo "ÚLTIMAS 5 ORGANIZACIONES EN LA TABLA organizations:\n";
    echo "================================================\n";
    
    $stmt = $pdo->query("
        SELECT id, name, address, user_id 
        FROM organizations 
        ORDER BY id DESC 
        LIMIT 5
    ");
    
    $organizations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($organizations)) {
        echo "No se encontraron organizaciones.\n";
    } else {
        foreach ($organizations as $org) {
            echo "ID: {$org['id']} | Nombre: {$org['name']} | User ID: {$org['user_id']}\n";
            echo "Dirección: " . substr($org['address'], 0, 50) . "...\n";
            echo "---\n";
        }
    }
    
    echo "\nVERIFICANDO ATRIBUTOS EN attribute_values:\n";
    echo "========================================\n";
    
    // Verificar atributos para las últimas organizaciones
    foreach ($organizations as $org) {
        echo "\nOrganización ID {$org['id']} - {$org['name']}:\n";
        
        $stmt = $pdo->prepare("
            SELECT attribute_id, text_value, json_value, integer_value, boolean_value
            FROM attribute_values
            WHERE entity_type = 'organizations' AND entity_id = ?
            ORDER BY attribute_id
        ");
        $stmt->execute([$org['id']]);
        $attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($attributes)) {
            echo "  ✗ Sin atributos en attribute_values\n";
        } else {
            foreach ($attributes as $attr) {
                $value = $attr['text_value'] ?? 
                        $attr['json_value'] ?? 
                        $attr['integer_value'] ?? 
                        ($attr['boolean_value'] !== null ? ($attr['boolean_value'] ? 'true' : 'false') : 'NULL');
                
                $attr_name = [
                    26 => 'name',
                    35 => 'address',
                    34 => 'user_id',
                    61 => 'RUT',
                    21 => 'otro'
                ][$attr['attribute_id']] ?? 'desconocido';
                
                echo "  ✓ Atributo {$attr['attribute_id']} ($attr_name): " . substr($value, 0, 30) . "\n";
            }
        }
    }
    
    echo "\nRESUMEN:\n";
    echo "========\n";
    
    // Contar total de organizaciones
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM organizations");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Total de organizaciones: $total\n";
    
    // Contar organizaciones con atributos
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT entity_id) as with_attrs
        FROM attribute_values 
        WHERE entity_type = 'organizations'
    ");
    $with_attrs = $stmt->fetch(PDO::FETCH_ASSOC)['with_attrs'];
    echo "Organizaciones con atributos: $with_attrs\n";
    
    // Verificar atributos específicos
    $required_attrs = [26, 35, 34, 61, 21];
    foreach ($required_attrs as $attr_id) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM attribute_values 
            WHERE entity_type = 'organizations' AND attribute_id = ?
        ");
        $stmt->execute([$attr_id]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $attr_name = [
            26 => 'name',
            35 => 'address',
            34 => 'user_id',
            61 => 'RUT',
            21 => 'otro'
        ][$attr_id];
        
        echo "Atributo $attr_id ($attr_name): $count registros\n";
    }
    
} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
