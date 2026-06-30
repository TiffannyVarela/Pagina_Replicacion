<?php
require_once '../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isOracleConnected()) {
    echo json_encode(['error' => 'Oracle no disponible']);
    exit;
}

$sql = "SELECT estado_replicacion, COUNT(*) as total 
        FROM BITACORA 
        GROUP BY estado_replicacion
        ORDER BY estado_replicacion";

$result = queryOracle($sql);

echo json_encode([
    'estados' => $result,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>