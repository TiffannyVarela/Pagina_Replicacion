<?php
require_once '../config/db.php';

// Pendientes por tabla
$sql1 = "SELECT tabla_afectada, COUNT(*) as total 
         FROM bitacora_replicacion 
         WHERE estado_replicacion = 'PENDIENTE' 
         GROUP BY tabla_afectada 
         ORDER BY total DESC LIMIT 5";
$result1 = $conn->query($sql1);
$tablas = [];
$pendientes = [];
while($row = $result1->fetch_assoc()) {
    $tablas[] = $row['tabla_afectada'];
    $pendientes[] = $row['total'];
}

// Errores por tabla
$sql2 = "SELECT tabla_afectada, COUNT(*) as total 
         FROM bitacora_replicacion 
         WHERE estado_replicacion = 'ERROR' 
         GROUP BY tabla_afectada 
         ORDER BY total DESC LIMIT 5";
$result2 = $conn->query($sql2);
$errores = [];
while($row = $result2->fetch_assoc()) {
    $errores[] = $row['total'];
}

// Porcentaje de éxito
$sql3 = "SELECT 
            ROUND(SUM(CASE WHEN estado_replicacion = 'REPLICADO' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as porcentaje
         FROM bitacora_replicacion 
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
$result3 = $conn->query($sql3);
$porcentaje = $result3->fetch_assoc()['porcentaje'] ?? 0;

// Eventos diarios desde vista_resumen_logs
$sql4 = "SELECT dia, total_eventos 
         FROM vista_resumen_logs 
         ORDER BY dia DESC LIMIT 7";
$result4 = $conn->query($sql4);
$fechas = [];
$cantidades = [];
while($row = $result4->fetch_assoc()) {
    $fechas[] = $row['dia'];
    $cantidades[] = $row['total_eventos'];
}

header('Content-Type: application/json');
echo json_encode([
    'tablas' => $tablas,
    'pendientes_por_tabla' => $pendientes,
    'errores_por_tabla' => $errores,
    'porcentaje_exito' => $porcentaje,
    'eventos_diarios' => [
        'fechas' => array_reverse($fechas),
        'cantidades' => array_reverse($cantidades)
    ]
]);
?>