<?php
/*
VER REGISTRO ESPECÍFICO DE BITACORA
*/
require_once '../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isOracleConnected()) {
    echo json_encode(['error' => 'Oracle no disponible']);
    exit;
}

$id = isset($_GET['id']) ? trim($_GET['id']) : '1435';

$sql = "SELECT 
            id_registro as ID,
            tabla_afectada as TABLA,
            tipo_operacion as TIPO,
            estado_replicacion as ESTADO,
            origen_evento as ORIGEN,
            SUBSTR(datos_json, 1, 1000) as DATOS_JSON,
            fecha_hora as FECHA,
            version_registro as VERSION,
            mensaje_error as ERROR
        FROM BITACORA 
        WHERE id_registro = '$id'";

$result = queryOracle($sql);

if (!isset($result['error']) && !empty($result)) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['error' => $result['error'] ?? 'No encontrado', 'id' => $id]);
}
?>