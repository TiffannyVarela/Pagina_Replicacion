<?php
/*
VER JSON DE CONTENEDORES EN BITACORA
*/
require_once '../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isOracleConnected()) {
    echo json_encode(['error' => 'Oracle no disponible']);
    exit;
}

function leerCLOB($clob) {
    if ($clob === null) return '{}';
    if (is_string($clob)) return $clob;
    if (is_object($clob) && method_exists($clob, 'load')) {
        try { return $clob->load() ?: '{}'; } catch (Exception $e) { return '{}'; }
    }
    return '{}';
}

// Obtener un contenedor específico para ver su JSON
$sql = "SELECT 
            id_registro as ID,
            tabla_afectada as TABLA,
            datos_json as DATOS_JSON
        FROM BITACORA 
        WHERE UPPER(tabla_afectada) = 'CONTENEDOR_NAVIERO'
        AND ROWNUM <= 3
        ORDER BY id_registro ASC";

$result = queryOracle($sql);

$data = [];

if (!isset($result['error']) && !empty($result)) {
    foreach ($result as $row) {
        $json_raw = isset($row['DATOS_JSON']) ? leerCLOB($row['DATOS_JSON']) : '{}';
        $data[] = [
            'ID' => $row['ID'] ?? 'N/A',
            'TABLA' => $row['TABLA'] ?? 'N/A',
            'JSON_RAW' => $json_raw,
            'JSON_LENGTH' => strlen($json_raw)
        ];
    }
}

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>