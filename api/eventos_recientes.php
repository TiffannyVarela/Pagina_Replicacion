<?php
require_once '../config/db.php';

$tabla = $_GET['tabla'] ?? '';
$estado = $_GET['estado'] ?? '';

$sql = "SELECT id, tabla_afectada, tipo_operacion, fecha_hora, estado_replicacion 
        FROM bitacora_replicacion 
        WHERE 1=1";

if($tabla) $sql .= " AND tabla_afectada = '$tabla'";
if($estado) $sql .= " AND estado_replicacion = '$estado'";

$sql .= " ORDER BY fecha_hora DESC LIMIT 10";

$result = $conn->query($sql);
$data = [];
while($row = $result->fetch_assoc()) {
    $data[] = $row;
}

header('Content-Type: application/json');
echo json_encode($data);
?>