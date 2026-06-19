<?php

require_once '../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// MAPEO DE TABLAS: MySQL ↔ Oracle
$tableMapping = [
    'tbl_clientes_logisticos' => 'CLIENTE_NAVIERA',
    'centros_logisticos' => 'TERMINAL_PORTUARIA',
    'unidades_transporte' => 'BUQUE_OPERACION',
    'contenedores' => 'CONTENEDOR_NAVIERO',
    'stock_carga' => 'INVENTARIO_CARGA',
    'ordenes_envio' => 'EMBARQUE_MARITIMO',
    'tbl_facturas_logisticas' => 'FACTURACION_EMBARQUE',
    'servicios_logisticos' => 'SERVICIO_PORTUARIO',
    'factura_servicios' => 'DETALLE_FACTURA_SERVICIO',
    'movimientos_carga' => 'TRANSFERENCIA_CARGA'
];

$response = [
    'mysql_online' => isMySQLConnected(),
    'oracle_online' => isOracleConnected(),
    'comparison' => [],
    'timestamp' => date('Y-m-d H:i:s')
];

// Si ambos están online, hacer la comparación
if ($response['mysql_online'] && $response['oracle_online']) {
    $conn_mysql = getMySQLConnection();
    
    foreach ($tableMapping as $mysqlTable => $oracleTable) {
        // Contar en MySQL
        $mysqlCount = 0;
        try {
            $result = $conn_mysql->query("SELECT COUNT(*) as total FROM $mysqlTable");
            if ($result) {
                $row = $result->fetch_assoc();
                $mysqlCount = (int)($row['total'] ?? 0);
            }
        } catch (Exception $e) {
            $mysqlCount = -1;
        }
        
        // Contar en Oracle
        $oracleCount = countOracleTable($oracleTable);
        
        $response['comparison'][] = [
            'mysql_table' => $mysqlTable,
            'oracle_table' => $oracleTable,
            'mysql_count' => $mysqlCount,
            'oracle_count' => $oracleCount,
            'match' => ($mysqlCount === $oracleCount && $mysqlCount >= 0),
            'difference' => $oracleCount - $mysqlCount
        ];
    }
}

echo json_encode($response);
?>