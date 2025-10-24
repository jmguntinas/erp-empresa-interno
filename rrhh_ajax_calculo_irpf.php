<?php
// Lógica pura AJAX: Usamos auth y db del ERP
require_once __DIR__ . '/auth.php'; 
require_login(); 
require_role(['Admin General', 'Admin RRHH']);
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$salario_bruto = $_POST['salario'] ?? 0;
$comunidad = $_POST['ca'] ?? '';

if ($salario_bruto <= 0 || empty($comunidad)) {
    echo json_encode(['error' => 'Datos insuficientes. Introduce Salario Bruto y Comunidad Autónoma.']);
    exit;
}

$resultado = [
    'bruto_anual' => (float)$salario_bruto,
    'irpf_estatal' => 0.0,
    'irpf_autonomico' => 0.0,
    'irpf_total' => 0.0,
    'porcentaje_total' => 0.0,
    'neto_anual' => 0.0,
    'neto_mensual' => 0.0
];

try {
    // --- Calcular IRPF Estatal ---
    // (Usar tabla 'hr_tramos_irpf')
    $stmt_est = $pdo->prepare("SELECT * FROM hr_tramos_irpf ORDER BY limite_inferior ASC");
    $stmt_est->execute();
    $tramos_estatales = $stmt_est->fetchAll();
    
    $resultado['irpf_estatal'] = calcular_retencion($salario_bruto, $tramos_estatales);

    // --- Calcular IRPF Autonómico ---
    // (Usar tabla 'hr_tramos_irpf_autonomico')
    $stmt_auto = $pdo->prepare("SELECT * FROM hr_tramos_irpf_autonomico WHERE comunidad = ? ORDER BY limite_inferior ASC");
    $stmt_auto->execute([$comunidad]);
    $tramos_autonomicos = $stmt_auto->fetchAll();
    
    if (empty($tramos_autonomicos)) {
         echo json_encode(['error' => 'No se encontraron tramos autonómicos para la comunidad: ' . htmlspecialchars($comunidad) . '. Revisa la BBDD.']);
         exit;
    } else {
         $resultado['irpf_autonomico'] = calcular_retencion($salario_bruto, $tramos_autonomicos);
    }
    
    // --- Calcular Totales ---
    $resultado['irpf_total'] = $resultado['irpf_estatal'] + $resultado['irpf_autonomico'];
    $resultado['neto_anual'] = $resultado['bruto_anual'] - $resultado['irpf_total'];
    $resultado['neto_mensual'] = $resultado['neto_anual'] / 12;
    if ($resultado['bruto_anual'] > 0) {
        $resultado['porcentaje_total'] = ($resultado['irpf_total'] / $resultado['bruto_anual']) * 100;
    }

    echo json_encode($resultado);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Función auxiliar para calcular IRPF progresivo
 * (Simplificación: calcula la cuota, no el tipo de retención real)
 */
function calcular_retencion($base_imponible, $tramos) {
    $retencion_total = 0;
    $base_calculada = 0;

    foreach ($tramos as $tramo) {
        $limite_inf = (float)$tramo['limite_inferior'];
        $limite_sup = (float)($tramo['limite_superior'] ?? PHP_FLOAT_MAX);
        $porcentaje = (float)$tramo['porcentaje'] / 100;

        if ($base_imponible > $limite_inf) {
            $base_en_tramo = 0;
            if ($base_imponible >= $limite_sup) {
                // Tramo completo
                $base_en_tramo = $limite_sup - $limite_inf;
            } else {
                // Último tramo aplicable
                $base_en_tramo = $base_imponible - $limite_inf;
            }
            
            $retencion_total += $base_en_tramo * $porcentaje;
        }
    }
    return $retencion_total;
}
?>