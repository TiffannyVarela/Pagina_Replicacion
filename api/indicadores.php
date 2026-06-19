<?php

require_once '../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$response = [
    'mysql_online' => isMySQLConnected(),
    'oracle_online' => isOracleConnected(),
    'pendientes' => 0,
    'replicados' => 0,
    'errores' => 0,
    'conflictos' => 0,
    'oracle_tables' => 0,
    'oracle_rows' => 0,
    'timestamp' => date('Y-m-d H:i:s')
];

// DATOS DE MYSQL
if (isMySQLConnected()) {
    $conn_mysql = getMySQLConnection();
    
    $sql = "SELECT 
                SUM(CASE WHEN estado_replicacion = 'PENDIENTE' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado_replicacion = 'REPLICADO' THEN 1 ELSE 0 END) as replicados,
                SUM(CASE WHEN estado_replicacion = 'ERROR' THEN 1 ELSE 0 END) as errores,
                SUM(CASE WHEN estado_replicacion = 'CONFLICTO' THEN 1 ELSE 0 END) as conflictos
            FROM bitacora_replicacion";
    
    $result = $conn_mysql->query($sql);
    if ($result) {
        $data = $result->fetch_assoc();
        $response['pendientes'] = (int)($data['pendientes'] ?? 0);
        $response['replicados'] = (int)($data['replicados'] ?? 0);
        $response['errores'] = (int)($data['errores'] ?? 0);
        $response['conflictos'] = (int)($data['conflictos'] ?? 0);
    }
}

// DATOS DE ORACLE
if (isOracleConnected()) {
    $tables = getOracleTables();
    $response['oracle_tables'] = count($tables);
    
    $stats = getOracleTableStats();
    $response['oracle_rows'] = array_sum($stats);
}

echo json_encode($response);
?>