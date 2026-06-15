<?php
require_once '../config/db.php';

header('Content-Type: application/json');

// Verificar conexión MySQL
if (!isMySQLConnected()) {
    echo json_encode([
        'pendientes' => 0,
        'replicados' => 0,
        'errores' => 0,
        'conflictos' => 0,
        'error' => 'MySQL no disponible',
        'mysql_error' => $mysql_error ?? 'Conexión fallida'
    ]);
    exit;
}

$sql = "SELECT 
            SUM(CASE WHEN estado_replicacion = 'PENDIENTE' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado_replicacion = 'REPLICADO' THEN 1 ELSE 0 END) as replicados,
            SUM(CASE WHEN estado_replicacion = 'ERROR' THEN 1 ELSE 0 END) as errores,
            SUM(CASE WHEN estado_replicacion = 'CONFLICTO' THEN 1 ELSE 0 END) as conflictos
        FROM bitacora_replicacion";

$result = $conn_mysql->query($sql);

if (!$result) {
    echo json_encode([
        'pendientes' => 0,
        'replicados' => 0,
        'errores' => 0,
        'conflictos' => 0,
        'error' => 'Error en consulta: ' . $conn_mysql->error
    ]);
    exit;
}

$data = $result->fetch_assoc();

// Asegurar valores numéricos
foreach($data as $key => $value) {
    if($value === null) $data[$key] = 0;
}

echo json_encode($data);
?>