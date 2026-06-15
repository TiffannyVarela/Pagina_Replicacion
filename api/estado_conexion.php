<?php
// Estado de ambas conexiones: MySQL y Oracle

require_once '../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$response = [
    'mysql' => false,
    'oracle' => false,
    'mysql_error' => null,
    'oracle_error' => null,
    'oracle_detalle' => null,
    'ultima_ejecucion' => null,
    'timestamp' => date('Y-m-d H:i:s')
];

// VERIFICAR MYSQL
$response['mysql'] = isMySQLConnected();
if (!$response['mysql']) {
    global $mysql_error;
    $response['mysql_error'] = $mysql_error ?? 'Conexión fallida';
}

// Obtener última ejecución (si MySQL está disponible)
if ($response['mysql'] && $conn_mysql) {
    $sql = "SELECT ultima_ejecucion FROM control_replicacion 
            WHERE sistema_origen = 'MYSQL' AND sistema_destino = 'ORACLE' 
            ORDER BY id DESC LIMIT 1";
    $result = $conn_mysql->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        $response['ultima_ejecucion'] = $row['ultima_ejecucion'];
    }
}

// VERIFICAR ORACLE
$oracle_host = 'globalshippingdb.ct2q4262uyl7.us-east-2.rds.amazonaws.com';
$oracle_port = '1521';
$oracle_service = 'DATABASE';
$oracle_username = 'admin';
$oracle_password = 'Holamundo_504';

if (!extension_loaded('oci8')) {
    $response['oracle_error'] = 'OCI8 extensión no instalada';
    $response['oracle_detalle'] = 'Oracle Instant Client no está configurado';
} else {
    try {
        $oracle_tns = "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=$oracle_host)(PORT=$oracle_port))(CONNECT_DATA=(SERVICE_NAME=$oracle_service)))";
        $conn = @oci_connect($oracle_username, $oracle_password, $oracle_tns, 'AL32UTF8');
        
        if ($conn) {
            $response['oracle'] = true;
            $response['oracle_detalle'] = 'Conexión exitosa';
            oci_close($conn);
        } else {
            $e = oci_error();
            $response['oracle_error'] = 'Error de conexión';
            $response['oracle_detalle'] = $e['message'] ?? 'Credenciales inválidas o servidor no disponible';
        }
    } catch (Exception $e) {
        $response['oracle_error'] = 'Excepción';
        $response['oracle_detalle'] = $e->getMessage();
    }
}

echo json_encode($response);
?>