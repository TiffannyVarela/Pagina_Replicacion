<?php
/*
VERIFICAR DATOS FALTANTES EN MYSQL
*/
require_once '../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isMySQLConnected() || !isOracleConnected()) {
    echo json_encode(['error' => 'Conexiones no disponibles']);
    exit;
}

$resultado = [
    'mysql' => [],
    'oracle' => [],
    'diferencias' => []
];

$conn_mysql = getMySQLConnection();

//VERIFICAR TABLAS PADRE EN MYSQL
$tablasPadre = [
    'tbl_clientes_logisticos' => 'CLIENTE_NAVIERA',
    'centros_logisticos' => 'TERMINAL_PORTUARIA',
    'unidades_transporte' => 'BUQUE_OPERACION',
    'contenedores' => 'CONTENEDOR_NAVIERO',
    'servicios_logisticos' => 'SERVICIO_PORTUARIO'
];

foreach ($tablasPadre as $mysqlTabla => $oracleTabla) {
    // Contar en MySQL
    $sql = "SELECT COUNT(*) as total FROM $mysqlTabla";
    $result = $conn_mysql->query($sql);
    $mysqlCount = $result ? (int)$result->fetch_assoc()['total'] : 0;
    
    // Contar en Oracle
    $oracleCount = countOracleTable($oracleTabla);
    
    $resultado['mysql'][$mysqlTabla] = $mysqlCount;
    $resultado['oracle'][$oracleTabla] = $oracleCount;
    $resultado['diferencias'][$oracleTabla] = $oracleCount - $mysqlCount;
}

//VERIFICAR REGISTROS DE EMBARQUE PENDIENTES
$sql = "SELECT 
            id_registro as ID,
            tabla_afectada as TABLA,
            datos_json as DATOS_JSON
        FROM BITACORA 
        WHERE UPPER(estado_replicacion) = 'PENDIENTE'
          AND UPPER(tabla_afectada) = 'EMBARQUE_MARITIMO'
        ORDER BY fecha_hora ASC
        FETCH FIRST 5 ROWS ONLY";

$result = queryOracle($sql);

if (!isset($result['error']) && !empty($result)) {
    $resultado['embarques_pendientes'] = [];
    foreach ($result as $row) {
        $datos_json_raw = isset($row['DATOS_JSON']) ? leerCLOB($row['DATOS_JSON']) : '{}';
        $datos = json_decode($datos_json_raw, true);
        
        $resultado['embarques_pendientes'][] = [
            'ID' => $row['ID'] ?? null,
            'cliente_id' => $datos['ID_CLIENTE'] ?? $datos['id_cliente'] ?? 'N/A',
            'buque_id' => $datos['ID_BUQUE'] ?? $datos['id_buque'] ?? 'N/A',
            'contenedor_id' => $datos['ID_CONTENEDOR'] ?? $datos['id_contenedor'] ?? 'N/A'
        ];
    }
}

function leerCLOB($clob) {
    if ($clob === null) return '{}';
    if (is_string($clob)) return $clob;
    if (is_object($clob) && method_exists($clob, 'load')) {
        try {
            return $clob->load() ?: '{}';
        } catch (Exception $e) {
            return '{}';
        }
    }
    return '{}';
}

echo json_encode($resultado, JSON_PRETTY_PRINT);
$conn_mysql->close();
?>