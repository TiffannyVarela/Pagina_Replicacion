<?php
/*
VER REGISTROS CON ERROR EN BITACORA
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

$sql = "SELECT 
            id_registro as ID,
            tabla_afectada as TABLA,
            tipo_operacion as TIPO,
            estado_replicacion as ESTADO,
            mensaje_error as ERROR,
            SUBSTR(datos_json, 1, 500) as JSON_PREVIEW
        FROM BITACORA 
        WHERE UPPER(estado_replicacion) = 'ERROR'
        AND ROWNUM <= 20
        ORDER BY fecha_hora DESC";

$result = queryOracle($sql);

$data = [];

if (!isset($result['error']) && !empty($result)) {
    foreach ($result as $row) {
        $json_raw = isset($row['JSON_PREVIEW']) ? leerCLOB($row['JSON_PREVIEW']) : '{}';
        $data[] = [
            'ID' => $row['ID'] ?? 'N/A',
            'TABLA' => $row['TABLA'] ?? 'N/A',
            'TIPO' => $row['TIPO'] ?? 'N/A',
            'ESTADO' => $row['ESTADO'] ?? 'N/A',
            'ERROR' => $row['ERROR'] ?? 'N/A',
            'JSON_PREVIEW' => substr($json_raw, 0, 200)
        ];
    }
}

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>