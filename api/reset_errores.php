<?php
/*
REINICIAR REGISTROS CON ERROR A PENDIENTE
*/
require_once '../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isOracleConnected()) {
    echo json_encode(['error' => 'Oracle no disponible']);
    exit;
}

// Verificar BITACORA
$checkBitacora = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'BITACORA'");

if (isset($checkBitacora['error']) || empty($checkBitacora)) {
    echo json_encode(['error' => 'BITACORA no existe en Oracle']);
    exit;
}

// Contar errores antes
$sqlCount = "SELECT COUNT(*) as total FROM BITACORA WHERE UPPER(estado_replicacion) = 'ERROR'";
$resultCount = queryOracle($sqlCount);
$totalErrores = 0;
if (!isset($resultCount['error']) && !empty($resultCount)) {
    $totalErrores = (int)($resultCount[0]['TOTAL'] ?? 0);
}

$sql = "UPDATE BITACORA 
        SET estado_replicacion = 'PENDIENTE',
            mensaje_error = NULL,
            intentos_replicacion = 0
        WHERE UPPER(estado_replicacion) = 'ERROR'";

$conn = getOracleConnection(true);
$stmt = oci_parse($conn, $sql);
$result = oci_execute($stmt);
oci_free_statement($stmt);

if ($result) {
    echo json_encode([
        'success' => true,
        'message' => "Se reiniciaron $totalErrores registros de ERROR a PENDIENTE",
        'total_reiniciados' => $totalErrores,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} else {
    $e = oci_error($stmt);
    echo json_encode([
        'success' => false,
        'error' => $e['message'] ?? 'Error desconocido'
    ]);
}

oci_close($conn);
?>