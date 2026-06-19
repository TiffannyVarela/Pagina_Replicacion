<?php

require_once '../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// VERIFICAR CONEXIÓN MYSQL
$mysql_online = false;
$mysql_error_msg = null;

try {
    global $conn_mysql;
    
    if ($conn_mysql !== null && @$conn_mysql->ping()) {
        $mysql_online = true;
    } else {
        $mysql_error_msg = 'MySQL no responde al ping';
    }
} catch (Exception $e) {
    $mysql_error_msg = $e->getMessage();
}

// VERIFICAR CONEXIÓN ORACLE
$oracle_online = false;
$oracle_error_msg = null;
$oracle_detalle = null;

if (!extension_loaded('oci8')) {
    $oracle_error_msg = 'OCI8 extensión no instalada';
    $oracle_detalle = 'Oracle Instant Client no está configurado';
} else {
    try {
        $tns = getOracleTNS();
        $conn = @oci_connect(ORACLE_USERNAME, ORACLE_PASSWORD, $tns, ORACLE_CHARSET);
        
        if ($conn) {
            $oracle_online = true;
            $oracle_detalle = 'Conexión exitosa a Oracle';
            oci_close($conn);
        } else {
            $e = oci_error();
            $oracle_error_msg = 'Error de conexión Oracle';
            $oracle_detalle = $e['message'] ?? 'Error desconocido';
        }
    } catch (Exception $e) {
        $oracle_error_msg = 'Excepción al conectar Oracle';
        $oracle_detalle = $e->getMessage();
    }
}

// OBTENER ÚLTIMA EJECUCIÓN
$ultima_ejecucion = null;
if ($mysql_online && isset($conn_mysql) && $conn_mysql !== null) {
    try {
        $sql = "SELECT ultima_ejecucion FROM control_replicacion 
                WHERE sistema_origen = 'MYSQL' AND sistema_destino = 'ORACLE' 
                ORDER BY id DESC LIMIT 1";
        $result = $conn_mysql->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            $ultima_ejecucion = $row['ultima_ejecucion'];
        }
    } catch (Exception $e) {
        // No crítico
    }
}

// ENVIAR RESPUESTA
$response = [
    'mysql' => $mysql_online,
    'oracle' => $oracle_online,
    'mysql_error' => $mysql_error_msg,
    'oracle_error' => $oracle_error_msg,
    'oracle_detalle' => $oracle_detalle,
    'ultima_ejecucion' => $ultima_ejecucion,
    'timestamp' => date('Y-m-d H:i:s'),
    '_debug' => [
        'oracle_host' => ORACLE_HOST,
        'oracle_port' => ORACLE_PORT,
        'oracle_service' => ORACLE_SERVICE,
        'oci8_loaded' => extension_loaded('oci8')
    ]
];

echo json_encode($response);
?>