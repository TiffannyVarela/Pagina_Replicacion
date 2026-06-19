<?php

require_once '../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isMySQLConnected()) {
    echo json_encode([]);
    exit;
}

$tabla = $_GET['tabla'] ?? '';
$estado = $_GET['estado'] ?? '';

$sql = "SELECT id, tabla_afectada, tipo_operacion, fecha_hora, estado_replicacion 
        FROM bitacora_replicacion 
        WHERE 1=1";

if($tabla) $sql .= " AND tabla_afectada = '" . $conn_mysql->real_escape_string($tabla) . "'";
if($estado) $sql .= " AND estado_replicacion = '" . $conn_mysql->real_escape_string($estado) . "'";

$sql .= " ORDER BY fecha_hora DESC LIMIT 10";

$result = $conn_mysql->query($sql);
$data = [];
while($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
?>