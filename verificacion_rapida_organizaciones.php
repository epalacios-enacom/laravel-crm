<?php
/**
 * Script de verificación rápida para organizaciones
 * Permite diagnosticar rápidamente el estado de las organizaciones
 * y confirmar si los problemas han sido resueltos
 */

try {
    // Conexión a la base de datos
    $pdo = new PDO('mysql:host=krayin-db;dbname=krayincrm;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== VERIFICACIÓN RÁPIDA DE ORGANIZACIONES ===\n";
    echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";
    
    // 1. ESTADÍSTICAS GENERALES
    echo "1. ESTADÍSTICAS GENERALES\n";
    echo "========================\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM organizations");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Total de organizaciones: $total\n";
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM organizations 
        WHERE name IS NOT NULL AND address IS NOT NULL AND user_id IS NOT NULL
    ");
    $complete = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Organizaciones completas: $complete\n";
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM organizations 
        WHERE name IS NULL OR address IS NULL OR user_id IS NULL
    ");
    $incomplete = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Organizaciones incompletas: $incomplete\n";
    
    $percentage = $total > 0 ? round(($complete / $total) * 100, 2) : 0;
    echo "Porcentaje completo: $percentage%\n\n";
    
    // 2. VERIFICACIÓN DE ATRIBUTOS
    echo "2. VERIFICACIÓN DE ATRIBUTOS\n";
    echo "============================\n";
    
    $required_attributes = [
        34 => 'name',
        35 => 'address', 
        36 => 'user_id',
        61 => 'rut'
    ];
    
    foreach ($required_attributes as $attr_id => $attr_name) {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT entity_id) as count
            FROM attribute_values 
            WHERE entity_type = 'organizations' AND attribute_id = ?
        ");
        $stmt->execute([$attr_id]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $missing = $total - $count;
        $attr_percentage = $total > 0 ? round(($count / $total) * 100, 2) : 0;
        
        echo "Atributo $attr_name (ID $attr_id): $count/$total ($attr_percentage%)";
        if ($missing > 0) {
            echo " - FALTAN $missing";
        }
        echo "\n";
    }
    
    echo "\n";
    
    // 3. ORGANIZACIONES PROBLEMÁTICAS
    echo "3. ORGANIZACIONES PROBLEMÁTICAS\n";
    echo "===============================\n";
    
    if ($incomplete > 0) {
        echo "Organizaciones con valores NULL:\n";
        $stmt = $pdo->query("
            SELECT id, name, 
                   CASE WHEN name IS NULL THEN 'NULL' ELSE 'OK' END as name_status,
                   CASE WHEN address IS NULL THEN 'NULL' ELSE 'OK' END as address_status,
                   CASE WHEN user_id IS NULL THEN 'NULL' ELSE 'OK' END as user_id_status
            FROM organizations 
            WHERE name IS NULL OR address IS NULL OR user_id IS NULL
            ORDER BY id
            LIMIT 10
        ");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "ID {$row['id']}: Name={$row['name_status']}, Address={$row['address_status']}, UserID={$row['user_id_status']}\n";
        }
        
        if ($incomplete > 10) {
            echo "... y " . ($incomplete - 10) . " más\n";
        }
    } else {
        echo "✓ No se encontraron organizaciones con valores NULL\n";
    }
    
    echo "\n";
    
    // 4. VERIFICACIÓN DE INTEGRIDAD
    echo "4. VERIFICACIÓN DE INTEGRIDAD\n";
    echo "=============================\n";
    
    // Verificar organizaciones sin atributos
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT o.id) as count
        FROM organizations o
        LEFT JOIN attribute_values av ON (o.id = av.entity_id AND av.entity_type = 'organizations')
        WHERE av.id IS NULL
    ");
    $orgs_without_attrs = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "Organizaciones sin atributos: $orgs_without_attrs\n";
    
    // Verificar atributos huérfanos
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM attribute_values av
        LEFT JOIN organizations o ON (av.entity_id = o.id)
        WHERE av.entity_type = 'organizations' AND o.id IS NULL
    ");
    $orphan_attrs = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "Atributos huérfanos: $orphan_attrs\n";
    
    // Verificar duplicados de RUT
    $stmt = $pdo->query("
        SELECT text_value as rut, COUNT(*) as count
        FROM attribute_values 
        WHERE entity_type = 'organizations' 
        AND attribute_id = 61 
        AND text_value IS NOT NULL 
        AND text_value != ''
        GROUP BY text_value
        HAVING COUNT(*) > 1
    ");
    $duplicate_ruts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "RUTs duplicados: " . count($duplicate_ruts) . "\n";
    if (!empty($duplicate_ruts)) {
        foreach ($duplicate_ruts as $dup) {
            echo "  RUT {$dup['rut']}: {$dup['count']} veces\n";
        }
    }
    
    echo "\n";
    
    // 5. EJEMPLOS DE ORGANIZACIONES CORRECTAS
    echo "5. EJEMPLOS DE ORGANIZACIONES CORRECTAS\n";
    echo "=======================================\n";
    
    $stmt = $pdo->query("
        SELECT o.id, o.name, o.user_id,
               (SELECT text_value FROM attribute_values WHERE entity_type = 'organizations' AND entity_id = o.id AND attribute_id = 61) as rut,
               (SELECT text_value FROM attribute_values WHERE entity_type = 'organizations' AND entity_id = o.id AND attribute_id = 34) as attr_name
        FROM organizations o
        WHERE o.name IS NOT NULL AND o.address IS NOT NULL AND o.user_id IS NOT NULL
        ORDER BY o.id
        LIMIT 5
    ");
    
    $correct_examples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($correct_examples)) {
        foreach ($correct_examples as $org) {
            echo "ID {$org['id']}: {$org['name']} (User: {$org['user_id']}, RUT: {$org['rut']})\n";
        }
    } else {
        echo "No se encontraron organizaciones completamente correctas.\n";
    }
    
    echo "\n";
    
    // 6. RESUMEN Y RECOMENDACIONES
    echo "6. RESUMEN Y RECOMENDACIONES\n";
    echo "============================\n";
    
    $total_issues = $incomplete + $orgs_without_attrs + $orphan_attrs + count($duplicate_ruts);
    
    if ($total_issues == 0) {
        echo "✅ ESTADO: EXCELENTE\n";
        echo "Todas las organizaciones están correctamente configuradas.\n";
        echo "\nRecomendaciones:\n";
        echo "- Limpiar caché: docker exec krayin-app php artisan cache:clear\n";
        echo "- Verificar en la interfaz que todo funcione correctamente\n";
    } elseif ($incomplete > 0) {
        echo "❌ ESTADO: REQUIERE CORRECCIÓN\n";
        echo "Se detectaron organizaciones con valores NULL.\n";
        echo "\nRecomendaciones:\n";
        echo "1. Ejecutar: php solucion_completa_organizaciones.php\n";
        echo "2. Limpiar caché: docker exec krayin-app php artisan cache:clear\n";
        echo "3. Reiniciar contenedor: docker restart krayin-app\n";
    } else {
        echo "⚠️ ESTADO: PROBLEMAS MENORES\n";
        echo "Las organizaciones están completas pero hay problemas de integridad.\n";
        echo "\nRecomendaciones:\n";
        echo "- Revisar atributos huérfanos y RUTs duplicados\n";
        echo "- Limpiar caché: docker exec krayin-app php artisan cache:clear\n";
    }
    
    // 7. COMANDOS ÚTILES
    echo "\n7. COMANDOS ÚTILES\n";
    echo "==================\n";
    echo "Diagnóstico completo:\n";
    echo "  php solucion_completa_organizaciones.php\n";
    echo "\nLimpiar caché:\n";
    echo "  docker exec krayin-app php artisan cache:clear\n";
    echo "  docker exec krayin-app php artisan config:clear\n";
    echo "\nReiniciar servicios:\n";
    echo "  docker restart krayin-app\n";
    echo "\nVer logs:\n";
    echo "  docker logs krayin-app\n";
    
} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
    echo "Verificar que el contenedor krayin-db esté ejecutándose.\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== VERIFICACIÓN COMPLETADA ===\n";
?>
