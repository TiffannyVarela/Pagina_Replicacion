<?php
/*
CONFIGURACION INICIAL
Incluir el archivo de configuracion de la base de datos
*/
require_once '../config/db.php';

//Devuelve datos en formato JSON
header('Content-Type: application/json');
//Permite peticiones desde cualquie origen (CORS)
header('Access-Control-Allow-Origin: *');
//Permite metodos GET y SET
header('Access-Control-Allow-Methods: GET, OPTIONS');
//Permite las cabeceras Content-Type
header('Access-Control-Allow-Headers: Content-Type');

// VERIFICAR CONEXIÓN MYSQL
//Estado de la conexion
$mysql_online = false;
//Mensaje de error de MySQL
$mysql_error_msg = null;

try {
    //Acceder a la conexion global de MySQL
    global $conn_mysql;
    
    /*
    VERIFICAR CONEXION MYSQL MEDIANTE ping()
    ping() comprueba si la conexion sigue activa
    Operador @ suprime warnings en caso de conexion nula
    Se verifica que la conexion no sea null
    */
    if ($conn_mysql !== null && @$conn_mysql->ping()) {
        $mysql_online = true;
    } else {
        $mysql_error_msg = 'MySQL no responde al ping';
    }
} catch (Exception $e) {
    //Capturar exceociones durante la verificacion
    $mysql_error_msg = $e->getMessage();
}

// VERIFICAR CONEXIÓN ORACLE
//Estado de la conexion
$oracle_online = false;
//Mensaje de error de MySQL
$oracle_error_msg = null;
//Adicional del error o estado
$oracle_detalle = null;

//Verificar si OCI8 existe y esta en uso (OCI8 es la extension necesaria para conectar PHP con Oracle)
if (!extension_loaded('oci8')) {
    $oracle_error_msg = 'OCI8 extensión no instalada';
    $oracle_detalle = 'Oracle Instant Client no está configurado';
} else {
    try {
        //Intentar conexion a Oracle 
        //Obtener la cadena de conexion TNS (Transparent Network Substrate) para Oracle 
        $tns = getOracleTNS();
        //Intentar establecer conexion
        $conn = @oci_connect(ORACLE_USERNAME, ORACLE_PASSWORD, $tns, ORACLE_CHARSET);
        
        //Si la conexion es exitosa
        if ($conn) {
            $oracle_online = true;
            $oracle_detalle = 'Conexión exitosa a Oracle';
            //Cerrar conexion de prueba
            oci_close($conn);
        } else {
            //Si la conexio fallo
            //Obtiene el ultimo error de Oracle
            $e = oci_error();
            //Se extra el mensaje de error para diagnostico
            $oracle_error_msg = 'Error de conexión Oracle';
            $oracle_detalle = $e['message'] ?? 'Error desconocido';
        }
    } catch (Exception $e) {
        //Capturar excepciones en el proceso de conexion
        $oracle_error_msg = 'Excepción al conectar Oracle';
        $oracle_detalle = $e->getMessage();
    }
}

/*
OBTENER ULTIMA EJECUCION DE REPLICACION
Consulta la tabla de control de replicacion para obtener la fecha y hora de la ultima ejecucion exitosa
*/
//Inicializar variable
$ultima_ejecucion = null;
if ($mysql_online && isset($conn_mysql) && $conn_mysql !== null) {
    try {
        //Consulta para obtener la ultima ejecucion
        $sql = "SELECT ultima_ejecucion FROM control_replicacion 
                WHERE sistema_origen = 'MYSQL' AND sistema_destino = 'ORACLE' 
                ORDER BY id DESC LIMIT 1";
        //Ejecutar consulta
        $result = $conn_mysql->query($sql);
        //Si hay resultado, extraer el valor
        if ($result && $row = $result->fetch_assoc()) {
            $ultima_ejecucion = $row['ultima_ejecucion'];
        }
    } catch (Exception $e) {
        // Si ocurre un error simplemente se ignora y $ultima_ejecucion se deja como null
    }
}

//Estructurar respuesta JSON con la informacion recolectada
//Estructura del JSON
$response = [
    //Estado de conexion MySQL (boolean)
    'mysql' => $mysql_online,
    //Estado de conexion Oracle (boolean)
    'oracle' => $oracle_online,
    //Mensaje de error de MySQL (string|null)
    'mysql_error' => $mysql_error_msg,
    //Mensaje de error de Oracle (string|null)
    'oracle_error' => $oracle_error_msg,
    //Detalle adicional de Oracle (string|null)
    'oracle_detalle' => $oracle_detalle,
    //Fecha de última replicación (string|null)
    'ultima_ejecucion' => $ultima_ejecucion,
    //Fecha/hora actual de la verificación
    'timestamp' => date('Y-m-d H:i:s'),
    //Información de depuración (host, puerto, servicio)
    '_debug' => [
        'oracle_host' => ORACLE_HOST,
        'oracle_port' => ORACLE_PORT,
        'oracle_service' => ORACLE_SERVICE,
        'oci8_loaded' => extension_loaded('oci8')
    ]
];

//Devuelve la respuesta completa en formato JSON con todas las estadistcas recolectadas
echo json_encode($response);
?>