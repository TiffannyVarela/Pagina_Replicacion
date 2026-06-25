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

$response = [
    //Estado de conexion MySQL (boolean)
    'mysql_online' => isMySQLConnected(),
    //Estado de conexion Oracle (boolean)
    'oracle_online' => isOracleConnected(),
    //Numero de registros PENDIENTES por tabla
    'pendientes' => 0,
    //Numero de registros REPLICADOS por tabla
    'replicados' => 0,
    //Numero de registros con ERRORES por tabla
    'errores' => 0,
    //Numero de registros con CONFLICTO por tabla
    'conflictos' => 0,
    //Cantidad total de tablas en Oracle
    'oracle_tables' => 0,
    //Cantidad total de registros en Oracle
    'oracle_rows' => 0,
    'timestamp' => date('Y-m-d H:i:s')
];

// DATOS DE MYSQL
if (isMySQLConnected()) {
    //Verificar la conexion a MySQl
    $conn_mysql = getMySQLConnection();
    //Verificar la conexion a MySQL. Agrupa los registros de la bitácora por estado de replicación
    $sql = "SELECT 
                SUM(CASE WHEN estado_replicacion = 'PENDIENTE' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado_replicacion = 'REPLICADO' THEN 1 ELSE 0 END) as replicados,
                SUM(CASE WHEN estado_replicacion = 'ERROR' THEN 1 ELSE 0 END) as errores,
                SUM(CASE WHEN estado_replicacion = 'CONFLICTO' THEN 1 ELSE 0 END) as conflictos
            FROM bitacora_replicacion";
    // Ejecutar la consulta y procesar resultados
    $result = $conn_mysql->query($sql);
    if ($result) {
        // Extraer los datos de la fila de resultados
        $data = $result->fetch_assoc();
        $response['pendientes'] = (int)($data['pendientes'] ?? 0);
        $response['replicados'] = (int)($data['replicados'] ?? 0);
        $response['errores'] = (int)($data['errores'] ?? 0);
        $response['conflictos'] = (int)($data['conflictos'] ?? 0);
    }
}

// DATOS DE ORACLE
//Se verifica la conexión a Oracle y se obtienen métricas de las tablas disponibles en la base de datos
if (isOracleConnected()) {
    //Obtiene todas las tablas disponibles en Oracle y cuenta cuántas existen
    $tables = getOracleTables();
    $response['oracle_tables'] = count($tables);
    //Obtiene el conteo de registros por tabla en Oracle y calcula el total sumando todos los conteos
    $stats = getOracleTableStats();
    $response['oracle_rows'] = array_sum($stats);
}

//Devuelve la respuesta completa en formato JSON con todas las estadistcas recolectadas
echo json_encode($response);
?>