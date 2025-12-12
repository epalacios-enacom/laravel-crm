<?php
/**
 * Script para verificar los atributos reales de organizations en Krayin
 * y diagnosticar por qué el nombre no se inserta correctamente en attribute_values
 * 
 * PROBLEMA IDENTIFICADO:
 * - El usuario muestra que los atributos reales son diferentes a los que estamos usando
 * - Necesitamos verificar los IDs correctos de los atributos
 */

try {
    // Conexión a la base de datos
    $pdo = new PDO('mysql:host=krayin-db;dbname=krayincrm;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== VERIFICACIÓN DE ATRIBUTOS REALES DE ORGANIZATIONS ===\n";
    echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";
    
    // PASO 1: VERIFICAR TODOS LOS ATRIBUTOS DE ORGANIZATIONS
    echo "PASO 1: ATRIBUTOS DISPONIBLES PARA ORGANIZATIONS\n";
    echo "================================================\n";
    
    $stmt = $pdo->query("
        SELECT id, code, name, type, lookup_type, entity_type, sort_order, validation, is_required, is_unique
        FROM attributes 
        WHERE entity_type = 'organizations'
        ORDER BY sort_order, id
    ");
    
    $attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Atributos encontrados: " . count($attributes) . "\n\n";
    
    foreach ($attributes as $attr) {
        echo "ID: {$attr['id']} | Código: {$attr['code']} | Nombre: {$attr['name']} | Tipo: {$attr['type']}\n";
        echo "   Requerido: " . ($attr['is_required'] ? 'Sí' : 'No') . " | Único: " . ($attr['is_unique'] ? 'Sí' : 'No') . "\n";
        echo "   Orden: {$attr['sort_order']} | Validación: {$attr['validation']}\n\n";
    }
    
    // PASO 2: VERIFICAR ATRIBUTOS ESPECÍFICOS QUE ESTAMOS USANDO
    echo "PASO 2: VERIFICACIÓN DE ATRIBUTOS ESPECÍFICOS\n";
    echo "=============================================\n";
    
    $atributos_buscados = ['name', 'address', 'user_id', 'rut'];
    
    foreach ($atributos_buscados as $codigo) {
        $stmt = $pdo->prepare("
            SELECT id, code, name, type 
            FROM attributes 
            WHERE entity_type = 'organizations' AND code = ?
        ");
        $stmt->execute([$codigo]);
        $attr = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($attr) {
            echo "✅ Atributo '$codigo' encontrado:\n";
            echo "   ID: {$attr['id']} | Nombre: {$attr['name']} | Tipo: {$attr['type']}\n\n";
        } else {
            echo "❌ Atributo '$codigo' NO encontrado\n\n";
        }
    }
    
    // PASO 3: VERIFICAR LAS ORGANIZACIONES INSERTADAS RECIENTEMENTE
    echo "PASO 3: VERIFICACIÓN DE ORGANIZACIONES RECIENTES\n";
    echo "================================================\n";
    
    // Buscar las últimas 3 organizaciones insertadas
    $stmt = $pdo->query("
        SELECT id, name, address, user_id, created_at
        FROM organizations 
        ORDER BY id DESC 
        LIMIT 3
    ");
    
    $recent_orgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($recent_orgs as $org) {
        echo "\n🏢 Organización ID {$org['id']}: {$org['name']}\n";
        echo "   Creada: {$org['created_at']}\n";
        
        // Verificar atributos en attribute_values
        $stmt = $pdo->prepare("
            SELECT av.attribute_id, a.code, a.name as attr_name, a.type,
                   av.text_value, av.json_value, av.integer_value, av.boolean_value
            FROM attribute_values av
            JOIN attributes a ON av.attribute_id = a.id
            WHERE av.entity_type = 'organizations' AND av.entity_id = ?
            ORDER BY av.attribute_id
        ");
        $stmt->execute([$org['id']]);
        $attrs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($attrs)) {
            echo "   ❌ SIN ATRIBUTOS EN attribute_values\n";
        } else {
            echo "   ✅ Atributos en attribute_values:\n";
            foreach ($attrs as $attr) {
                $value = $attr['text_value'] ?? $attr['json_value'] ?? $attr['integer_value'] ?? $attr['boolean_value'] ?? 'NULL';
                echo "     - ID {$attr['attribute_id']} ({$attr['code']}): " . substr($value, 0, 50) . "\n";
            }
        }
    }
    
    // PASO 4: BUSCAR ESPECÍFICAMENTE EL ATRIBUTO DE NOMBRE
    echo "\nPASO 4: ANÁLISIS ESPECÍFICO DEL ATRIBUTO NOMBRE\n";
    echo "===============================================\n";
    
    // Buscar todos los posibles atributos de nombre
    $stmt = $pdo->query("
        SELECT id, code, name, type
        FROM attributes 
        WHERE entity_type = 'organizations' 
        AND (code LIKE '%name%' OR name LIKE '%name%' OR name LIKE '%nombre%')
        ORDER BY id
    ");
    
    $name_attrs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Atributos relacionados con 'name':\n";
    foreach ($name_attrs as $attr) {
        echo "   ID: {$attr['id']} | Código: {$attr['code']} | Nombre: {$attr['name']} | Tipo: {$attr['type']}\n";
    }
    
    // PASO 5: RECOMENDACIONES
    echo "\nPASO 5: RECOMENDACIONES\n";
    echo "=======================\n";
    
    // Encontrar el atributo correcto para el nombre
    $stmt = $pdo->prepare("
        SELECT id FROM attributes 
        WHERE entity_type = 'organizations' AND code = 'name'
    ");
    $stmt->execute();
    $name_attr = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($name_attr) {
        $correct_name_id = $name_attr['id'];
        echo "✅ El ID correcto para el atributo 'name' es: $correct_name_id\n";
        
        // Verificar si estamos usando el ID correcto en nuestro script
        if ($correct_name_id != 26) {
            echo "⚠️ PROBLEMA ENCONTRADO: Estamos usando ID 26, pero el correcto es $correct_name_id\n";
            echo "\n📝 SOLUCIÓN:\n";
            echo "Actualizar el script para usar el ID correcto: $correct_name_id\n";
        } else {
            echo "✅ Estamos usando el ID correcto (26) para el atributo name\n";
        }
    } else {
        echo "❌ No se encontró el atributo 'name' para organizations\n";
    }
    
    // Verificar otros atributos importantes
    $important_attrs = ['address', 'user_id'];
    foreach ($important_attrs as $code) {
        $stmt = $pdo->prepare("
            SELECT id FROM attributes 
            WHERE entity_type = 'organizations' AND code = ?
        ");
        $stmt->execute([$code]);
        $attr = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($attr) {
            echo "✅ Atributo '$code' tiene ID: {$attr['id']}\n";
        }
    }
    
    echo "\n=== SCRIPT DE VERIFICACIÓN COMPLETADO ===\n";
    
} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DEL ANÁLISIS ===\n";
?>
