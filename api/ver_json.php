<?php
/*
VER CONTENIDO DEL JSON EN BITACORA ORACLE
*/
require_once '../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isOracleConnected()) {
    echo json_encode(['error' => 'Oracle no disponible']);
    exit;
}

// Función para leer CLOB
function leerCLOB($clob) {
    if ($clob === null) return '{}';
    if (is_string($clob)) return $clob;
    if (is_object($clob) && method_exists($clob, 'load')) {
        try { return $clob->load() ?: '{}'; } catch (Exception $e) { return '{}'; }
    }
    return '{}';
}

// Obtener los primeros 10 registros con sus JSON
$sql = "SELECT 
            id_registro as ID,
            tabla_afectada as TABLA,
            tipo_operacion as TIPO,
            estado_replicacion as ESTADO,
            origen_evento as ORIGEN,
            datos_json as DATOS_JSON
        FROM BITACORA 
        WHERE ROWNUM <= 15
        ORDER BY fecha_hora DESC";

$result = queryOracle($sql);

$data = [];

if (!isset($result['error']) && !empty($result)) {
    foreach ($result as $row) {
        // --- CORRECCIÓN: Leer el CLOB correctamente ---
        $json_raw = isset($row['DATOS_JSON']) ? leerCLOB($row['DATOS_JSON']) : '{}';
        $json_decoded = json_decode($json_raw, true);
        
        // Si no se pudo decodificar, intentar limpiar
        if ($json_decoded === null) {
            $json_clean = str_replace(["\r", "\n", "\t"], ' ', $json_raw);
            $json_decoded = json_decode($json_clean, true);
        }
        
        $data[] = [
            'ID' => $row['ID'] ?? 'N/A',
            'TABLA' => $row['TABLA'] ?? 'N/A',
            'TIPO' => $row['TIPO'] ?? 'N/A',
            'ESTADO' => $row['ESTADO'] ?? 'N/A',
            'ORIGEN' => $row['ORIGEN'] ?? 'N/A',
            'JSON_RAW' => substr($json_raw, 0, 300) . (strlen($json_raw) > 300 ? '...' : ''),
            'JSON_KEYS' => ($json_decoded && is_array($json_decoded)) ? array_keys($json_decoded) : ['ERROR: No se pudo decodificar'],
            'JSON_SAMPLE' => $json_decoded
        ];
    }
}

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>