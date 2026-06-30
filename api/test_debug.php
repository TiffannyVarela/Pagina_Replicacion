<?php
/*
ARCHIVO DE DEPURACIÓN - Verificar conexiones y datos
*/
require_once '../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$resultado = [
    'mysql' => [
        'online' => false,
        'bitacora_count' => 0,
        'tablas' => []
    ],
    'oracle' => [
        'online' => false,
        'bitacora_exists' => false,
        'bitacora_count' => 0,
        'logs_count' => 0,
        'tablas' => []
    ],
    'timestamp' => date('Y-m-d H:i:s')
];

// ============================================================
// VERIFICAR MYSQL
// ============================================================
$resultado['mysql']['online'] = isMySQLConnected();

if ($resultado['mysql']['online']) {
    try {
        $conn = getMySQLConnection(false);
        
        if ($conn) {
            // Contar bitacora por estados
            $sql = "SELECT COUNT(*) as total, estado_replicacion 
                    FROM bitacora_replicacion 
                    GROUP BY estado_replicacion";
            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $resultado['mysql']['bitacora_count'] += (int)$row['total'];
                    $resultado['mysql']['estados'][$row['estado_replicacion']] = (int)$row['total'];
                }
            }
            
            // Contar total de la bitacora
            $sql = "SELECT COUNT(*) as total FROM bitacora_replicacion";
            $result = $conn->query($sql);
            if ($result) {
                $row = $result->fetch_assoc();
                $resultado['mysql']['bitacora_total'] = (int)($row['total'] ?? 0);
            }
            
            // Listar tablas
            $result = $conn->query("SHOW TABLES");
            if ($result) {
                while ($row = $result->fetch_array()) {
                    $table = $row[0];
                    $countResult = $conn->query("SELECT COUNT(*) as total FROM $table");
                    if ($countResult) {
                        $countRow = $countResult->fetch_assoc();
                        $resultado['mysql']['tablas'][$table] = (int)$countRow['total'];
                    }
                }
            }
        }
    } catch (Exception $e) {
        $resultado['mysql']['error'] = $e->getMessage();
    }
} else {
    $resultado['mysql']['error'] = 'No se pudo conectar a MySQL';
}

// ============================================================
// VERIFICAR ORACLE
// ============================================================
$resultado['oracle']['online'] = isOracleConnected();

if ($resultado['oracle']['online']) {
    try {
        // Verificar BITACORA
        $checkBitacora = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'BITACORA'");
        if (!isset($checkBitacora['error']) && !empty($checkBitacora)) {
            $resultado['oracle']['bitacora_exists'] = true;
            
            // Contar BITACORA por estado
            $sql = "SELECT COUNT(*) as total, estado_replicacion 
                    FROM BITACORA 
                    GROUP BY estado_replicacion";
            $result = queryOracle($sql);
            if (!isset($result['error']) && !empty($result)) {
                foreach ($result as $row) {
                    $resultado['oracle']['bitacora_count'] += (int)($row['TOTAL'] ?? 0);
                    $resultado['oracle']['estados'][$row['ESTADO_REPLICACION'] ?? 'DESCONOCIDO'] = (int)($row['TOTAL'] ?? 0);
                }
            }
            
            // Contar total BITACORA
            $sql = "SELECT COUNT(*) as total FROM BITACORA";
            $result = queryOracle($sql);
            if (!isset($result['error']) && !empty($result) && isset($result[0]['TOTAL'])) {
                $resultado['oracle']['bitacora_total'] = (int)$result[0]['TOTAL'];
            }
            
            // Obtener últimos 5 registros de BITACORA
            $sql = "SELECT id_registro, tabla_afectada, tipo_operacion, estado_replicacion, fecha_hora 
                    FROM BITACORA 
                    WHERE ROWNUM <= 5 
                    ORDER BY fecha_hora DESC";
            $result = queryOracle($sql);
            if (!isset($result['error']) && !empty($result)) {
                $resultado['oracle']['ultimos_registros'] = $result;
            }
        } else {
            $resultado['oracle']['bitacora_exists'] = false;
        }
        
        // Verificar LOGS_REPLICACION_ORACLE
        $checkLogs = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'LOGS_REPLICACION_ORACLE'");
        if (!isset($checkLogs['error']) && !empty($checkLogs)) {
            $sql = "SELECT COUNT(*) as total FROM LOGS_REPLICACION_ORACLE";
            $result = queryOracle($sql);
            if (!isset($result['error']) && !empty($result) && isset($result[0]['TOTAL'])) {
                $resultado['oracle']['logs_count'] = (int)$result[0]['TOTAL'];
            }
        }
        
        // Listar tablas Oracle
        $tables = getOracleTables();
        foreach ($tables as $table) {
            $count = countOracleTable($table);
            $resultado['oracle']['tablas'][$table] = $count;
        }
        
    } catch (Exception $e) {
        $resultado['oracle']['error'] = $e->getMessage();
    }
} else {
    $resultado['oracle']['error'] = 'No se pudo conectar a Oracle';
}

echo json_encode($resultado, JSON_PRETTY_PRINT);
?>