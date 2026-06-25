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

/*
MAPEO DE TABLAS: MySQL ↔ Oracle
Array que define la equivalencia de los nombre de tablas de MySQL y Oracle

'nombre de la tabla en MySQL' => 'nombre de la tabla en Oracle'
*/
$tableMapping = [
    'tbl_clientes_logisticos' => 'CLIENTE_NAVIERA',
    'centros_logisticos' => 'TERMINAL_PORTUARIA',
    'unidades_transporte' => 'BUQUE_OPERACION',
    'contenedores' => 'CONTENEDOR_NAVIERO',
    'stock_carga' => 'INVENTARIO_CARGA',
    'ordenes_envio' => 'EMBARQUE_MARITIMO',
    'tbl_facturas_logisticas' => 'FACTURACION_EMBARQUE',
    'servicios_logisticos' => 'SERVICIO_PORTUARIO',
    'factura_servicios' => 'DETALLE_FACTURA_SERVICIO',
    'movimientos_carga' => 'TRANSFERENCIA_CARGA'
];

//Estructura de la respuesta JSON

$response = [
    //Estado de conexion MySQL (boolean)
    'mysql_online' => isMySQLConnected(),
    //Estado de vonecion Oracle (boolean)
    'oracle_online' => isOracleConnected(),
    //Arreglo con resultados de comparacion por tabla
    'comparison' => [],
    //Suma total de registros MySQL
    'total_mysql_records' => 0,
    //Suma total de registros Oracle
    'total_oracle_records' => 0,
    //Fechay hora de ejecucion
    'timestamp' => date('Y-m-d H:i:s'),
    //Array de mensajes de error
    'errors' => []
];

// Si ambos están online, hacer la comparación
if ($response['mysql_online'] && $response['oracle_online']) {
    //Obtener conexion a MySQL
    $conn_mysql = getMySQLConnection();

    //Iteracion sobre cada par de tabla para comparar sus registros
    foreach ($tableMapping as $mysqlTable => $oracleTable) {
        $mysqlCount = 0;
        $oracleCount = 0;
        $mysqlError = null;
        $oracleError = null;
        
        // Contar registros en MySQL
        try {
            $result = $conn_mysql->query("SELECT COUNT(*) as total FROM $mysqlTable");
            if ($result) {
                $row = $result->fetch_assoc();
                $mysqlCount = (int)($row['total'] ?? 0);
            } else {
                //Si la consulta falla, guarda el error
                $mysqlError = $conn_mysql->error;
            }
        } catch (Exception $e) {
            //Capturar excepciones
            $mysqlError = $e->getMessage();
            $mysqlCount = -1;
        }
        
        // Contar registros en Oracle
        try {
            $oracleCount = countOracleTable($oracleTable);
            if ($oracleCount === -1) {
                //Si retornaa -1 hubo error en la consulta 
                $oracleError = "Error contando tabla Oracle";
            }
        } catch (Exception $e) {
            //Capturar excepciones
            $oracleError = $e->getMessage();
            $oracleCount = -1;
        }
        
        //Verifica si ambas tablas tienen mismo numero de registros y si los conteos son validos
        $match = ($mysqlCount === $oracleCount && $mysqlCount >= 0);
        
        //Almacenar todos los datos de comparacion en el array de respuesta
        $response['comparison'][] = [
            //Nombre tabla MySQL
            'mysql_table' => $mysqlTable,
            //Nombre tabla Oracle
            'oracle_table' => $oracleTable,
            //Conteo MySQL
            'mysql_count' => $mysqlCount >= 0 ? $mysqlCount : 'N/A',
            //Conteo Oracle
            'oracle_count' => $oracleCount >= 0 ? $oracleCount : 'N/A',
            //Comprobar si coinciden los conteos
            'match' => $match,
            //Diferencia entre conteos
            'difference' => ($mysqlCount >= 0 && $oracleCount >= 0) ? $oracleCount - $mysqlCount : 'N/A',
            //Error de Mysql (en caso de existir)
            'mysql_error' => $mysqlError,
            //Error de Oracle (en caso de existir)
            'oracle_error' => $oracleError
        ];
        
        // SSumar los valores validos (mayores o iguales a 0)
        if ($mysqlCount >= 0) {
            $response['total_mysql_records'] += $mysqlCount;
        }
        if ($oracleCount >= 0) {
            $response['total_oracle_records'] += $oracleCount;
        }
    }
} else {
    /*
    MANEJO DE ERRORES DE CONEXION
    Si alguna base no esta disponible se registra el error correspondiente
    */
    if (!$response['mysql_online']) {
        $response['errors'][] = 'MySQL no está disponible';
    }
    if (!$response['oracle_online']) {
        $response['errors'][] = 'Oracle no está disponible';
    }
}

//Devuelve la respuesta completa en formato JSON con todos los datos de la comparacion
echo json_encode($response);
?>