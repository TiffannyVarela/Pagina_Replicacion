<?php
/*
VER DATOS DE ORACLE - BITACORA
*/
require_once '../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isOracleConnected()) {
    echo json_encode(['error' => 'Oracle no disponible']);
    exit;
}

$resultado = [
    'timestamp' => date('Y-m-d H:i:s'),
    'bitacora' => [],
    'logs' => [],
    'tablas' => []
];

// ============================================================
// 1. VER BITACORA - REGISTROS PENDIENTES
// ============================================================
$sql = "SELECT 
            id_registro as ID,
            tabla_afectada as TABLA,
            tipo_operacion as TIPO,
            estado_replicacion as ESTADO,
            origen_evento as ORIGEN,
            SUBSTR(datos_json, 1, 500) as DATOS_JSON,
            fecha_hora as FECHA,
            version_registro as VERSION,
            mensaje_error as ERROR
        FROM BITACORA 
        WHERE ROWNUM <= 30
        ORDER BY fecha_hora DESC";

$result = queryOracle($sql);

if (!isset($result['error']) && !empty($result)) {
    $resultado['bitacora'] = $result;
} else {
    $resultado['bitacora_error'] = $result['error'] ?? 'Sin datos';
}

// ============================================================
// 2. CONTAR POR ESTADO
// ============================================================
$sql = "SELECT 
            estado_replicacion as ESTADO,
            COUNT(*) as TOTAL
        FROM BITACORA 
        GROUP BY estado_replicacion
        ORDER BY estado_replicacion";

$result = queryOracle($sql);

if (!isset($result['error']) && !empty($result)) {
    $resultado['estados'] = $result;
}

// ============================================================
// 3. VER LOGS_REPLICACION_ORACLE
// ============================================================
$checkLogs = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'LOGS_REPLICACION_ORACLE'");

if (!isset($checkLogs['error']) && !empty($checkLogs)) {
    $sql = "SELECT 
                id,
                evento,
                SUBSTR(descripcion, 1, 200) as descripcion,
                fecha
            FROM LOGS_REPLICACION_ORACLE 
            WHERE ROWNUM <= 20
            ORDER BY fecha DESC";
    
    $result = queryOracle($sql);
    
    if (!isset($result['error']) && !empty($result)) {
        $resultado['logs'] = $result;
    }
}

// ============================================================
// 4. LISTAR TABLAS Y CONTAR REGISTROS
// ============================================================
$tables = getOracleTables();
foreach ($tables as $table) {
    if (in_array($table, ['BITACORA', 'LOGS_REPLICACION_ORACLE', 'LAMBDA_EXECUTION_LOGS'])) {
        continue;
    }
    $count = countOracleTable($table);
    $resultado['tablas'][$table] = $count;
}

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>