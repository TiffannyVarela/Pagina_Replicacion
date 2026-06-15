<?php
// Endpoint específico para verificar estado de Oracle

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Configuración de Oracle
$oracle_host = 'globalshippingdb.ct2q4262uyl7.us-east-2.rds.amazonaws.com';
$oracle_port = '1521';
$oracle_service = 'DATABASE';
$oracle_username = 'admin';
$oracle_password = 'Holamundo_504';

$resultado = [
    'online' => false,
    'error' => null,
    'detalle' => null,
    'timestamp' => date('Y-m-d H:i:s')
];

// Verificar si la extensión está instalada
if (!extension_loaded('oci8')) {
    $resultado['error'] = 'OCI8 extension no instalada';
    $resultado['detalle'] = 'Oracle Instant Client no está configurado en este servidor';
    echo json_encode($resultado);
    exit;
}

// Intentar conexión
try {
    $oracle_tns = "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=$oracle_host)(PORT=$oracle_port))(CONNECT_DATA=(SERVICE_NAME=$oracle_service)))";
    $conn = @oci_connect($oracle_username, $oracle_password, $oracle_tns, 'AL32UTF8');
    
    if ($conn) {
        $resultado['online'] = true;
        // Probar una consulta simple
        $stmt = oci_parse($conn, "SELECT 'OK' as status FROM DUAL");
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        $resultado['detalle'] = 'Conexión exitosa - ' . ($row['STATUS'] ?? 'OK');
        oci_free_statement($stmt);
        oci_close($conn);
    } else {
        $e = oci_error();
        $resultado['error'] = 'Error de autenticación o conexión';
        $resultado['detalle'] = $e['message'] ?? 'Credenciales inválidas o servidor no disponible';
    }
} catch (Exception $e) {
    $resultado['error'] = 'Excepción al conectar';
    $resultado['detalle'] = $e->getMessage();
}

echo json_encode($resultado);
?>