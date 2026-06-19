<?php

require_once '../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Verificar conexión a Oracle
if (!isOracleConnected()) {
    echo json_encode([
        'error' => 'Oracle no disponible',
        'oracle_online' => false,
        'tables' => [],
        'table_stats' => [],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Obtener acción solicitada
$action = $_GET['action'] ?? 'summary';

switch ($action) {
    case 'tables':
        // Listar tablas de Oracle
        $tables = getOracleTables();
        echo json_encode([
            'success' => true,
            'tables' => $tables,
            'count' => count($tables),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
        
    case 'stats':
        // Estadísticas de tablas
        $stats = getOracleTableStats();
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
        
    case 'table_data':
        // Datos de una tabla específica
        $table = $_GET['table'] ?? '';
        $limit = (int)($_GET['limit'] ?? 10);
        
        if (empty($table)) {
            echo json_encode(['error' => 'Nombre de tabla requerido']);
            exit;
        }
        
        // Validar que la tabla existe
        $tables = getOracleTables();
        if (!in_array(strtoupper($table), array_map('strtoupper', $tables))) {
            echo json_encode(['error' => 'Tabla no encontrada: ' . $table]);
            exit;
        }
        
        $sql = "SELECT * FROM $table WHERE ROWNUM <= $limit";
        $data = queryOracle($sql);
        
        if (isset($data['error'])) {
            echo json_encode(['error' => $data['error']]);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'table' => $table,
            'data' => $data,
            'count' => count($data),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
        
    case 'summary':
    default:
        // Resumen general de Oracle
        $tables = getOracleTables();
        $stats = getOracleTableStats();
        
        $table_info = [];
        foreach ($tables as $table) {
            $table_info[] = [
                'name' => $table,
                'rows' => $stats[$table] ?? 0
            ];
        }
        
        echo json_encode([
            'success' => true,
            'oracle_online' => true,
            'total_tables' => count($tables),
            'total_rows' => array_sum($stats),
            'tables' => $table_info,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
}
?>