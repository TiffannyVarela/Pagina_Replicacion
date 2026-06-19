<?php

require_once '../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (!isOracleConnected()) {
    echo json_encode([
        'error' => 'Oracle no disponible',
        'oracle_online' => false,
        'tables' => [],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        $tables = getOracleTables();
        $stats = getOracleTableStats();
        
        $result = [];
        foreach ($tables as $table) {
            $result[] = [
                'name' => $table,
                'rows' => $stats[$table] ?? 0
            ];
        }
        
        echo json_encode([
            'success' => true,
            'tables' => $result,
            'total' => count($result),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
        
    case 'data':
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
        
        // Obtener nombres de columnas
        $columns = [];
        if (!empty($data)) {
            $columns = array_keys($data[0]);
        }
        
        echo json_encode([
            'success' => true,
            'table' => $table,
            'columns' => $columns,
            'data' => $data,
            'count' => count($data),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
        
    default:
        echo json_encode(['error' => 'Acción no válida']);
}
?>