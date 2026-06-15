<?php
require_once '../config/db.php';

$sql = "SELECT id, tabla_afectada, intentos_replicacion, mensaje_error, created_at 
        FROM bitacora_replicacion 
        WHERE estado_replicacion = 'ERROR' 
        ORDER BY created_at DESC LIMIT 20";

$result = $conn->query($sql);
$data = [];
while($row = $result->fetch_assoc()) {
    $data[] = $row;
}

header('Content-Type: application/json');
echo json_encode($data);
?>