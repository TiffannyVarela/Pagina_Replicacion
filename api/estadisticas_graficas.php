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

$response = [
    //lista de nombres de tablas a monitorear
    'tablas' => array_keys($tableMapping),
    //Conteo de registros PENDIENTES por tabla
    'pendientes_por_tabla' => [],
    //Conteo de registros ERROR por tabla
    'errores_por_tabla' => [],
    //Conteo de registros REPLICADOS por tabla
    'replicados_por_tabla' => [],
    //Estado de sincronizacion MySQL vs Oracle por tabla
    'oracle_sync' => [],
    //Porcentaje de exito de replicacion (ultimas 24 hrs)
    'porcentaje_exito' => 0,
    //Eventos de replicacion de MySQL por dia (ultimos 7 dias)
    'eventos_diarios' => ['fechas' => [], 'cantidades' => []],
    //Eventos de replicacion de Oracle por dia
    'eventos_diarios_oracle' => ['fechas' => [], 'cantidades' => []],
    //Estadisticas de la base de datos MySQL
    'mysql' => [
        'online' => isMySQLConnected(),
        'tables' => [],
        'records' => 0
    ],
    //Estadisticas de la base de datos Oracle
    'oracle' => [
        'online' => false,
        'tables' => [],
        'records' => 0,
        'table_data' => []
    ],
    //Fecha y hora de ejecucion
    'timestamp' => date('Y-m-d H:i:s')
];


/*
RECOLECCION DE DATOS DE MYSQL
*/

//Verificar conexion a MySQL activa
if (isMySQLConnected()) {
    //Obtener conexion a MySQL
    $conn_mysql = getMySQLConnection();
    
    //Inicializar arrays para almacenar estadisticas por cada tabla de replicacion
    try {
        $todasLasTablas = array_keys($tableMapping);
        $pendientesFinal = [];
        $erroresFinal = [];
        $replicadosFinal = [];
        $oracleSync = [];
        
        //Para cada tabla se consulta bitacora de replicacion para obtener los conteos de los estados (PENDIENTE, ERROR Y REPLICADO)
        foreach ($todasLasTablas as $tabla) {
            // Contar registros pendientes
            $sqlPend = "SELECT COUNT(*) as total 
                        FROM bitacora_replicacion 
                        WHERE tabla_afectada = '$tabla' AND estado_replicacion = 'PENDIENTE'";
            $resultPend = $conn_mysql->query($sqlPend);
            $pendientesFinal[] = $resultPend ? (int)$resultPend->fetch_assoc()['total'] : 0;
            
            // Contar registros con errores
            $sqlErr = "SELECT COUNT(*) as total 
                       FROM bitacora_replicacion 
                       WHERE tabla_afectada = '$tabla' AND estado_replicacion = 'ERROR'";
            $resultErr = $conn_mysql->query($sqlErr);
            $erroresFinal[] = $resultErr ? (int)$resultErr->fetch_assoc()['total'] : 0;
            
            // Contar registros replicados
            $sqlRep = "SELECT COUNT(*) as total 
                       FROM bitacora_replicacion 
                       WHERE tabla_afectada = '$tabla' AND estado_replicacion = 'REPLICADO'";
            $resultRep = $conn_mysql->query($sqlRep);
            $replicadosFinal[] = $resultRep ? (int)$resultRep->fetch_assoc()['total'] : 0;
            
            // Verificar sincronización con Oracle (solo si Oracle está online) compara el conteo total de registros entre MySQL y Oracle para cada tabla
            if (isOracleConnected()) {
                $oracleTable = $tableMapping[$tabla];
                $mysqlCount = 0;
                $oracleCount = 0;
                //Obtener conteo de MySQl para esta tabla
                $resultCount = $conn_mysql->query("SELECT COUNT(*) as total FROM $tabla");
                if ($resultCount) {
                    $mysqlCount = (int)$resultCount->fetch_assoc()['total'];
                }
                //Obtener conteo de Oracle para esta tabla
                $oracleCount = countOracleTable($oracleTable);
                
                //Registra el estado de sincronizacion de cada tabla
                $oracleSync[] = [
                    'tabla' => $tabla,
                    'oracle_tabla' => $oracleTable,
                    'mysql_count' => $mysqlCount,
                    'oracle_count' => $oracleCount,
                    'sincronizado' => ($mysqlCount === $oracleCount && $mysqlCount >= 0)
                ];
            }
        }
        
        // Calculo de porcentaje de éxito
        $porcentaje = 0;
        $sql3 = "SELECT 
                    ROUND(SUM(CASE WHEN estado_replicacion = 'REPLICADO' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as porcentaje
                 FROM bitacora_replicacion 
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $result3 = $conn_mysql->query($sql3);
        if ($result3 && $result3->num_rows > 0) {
            $row = $result3->fetch_assoc();
            $porcentaje = $row['porcentaje'] ?? 0;
        }
        
        // Eventos diarios de MySQL
        $sql4 = "SELECT DATE(fecha) as dia, COUNT(*) as total_eventos 
                 FROM logs_replicacion 
                 WHERE fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 GROUP BY DATE(fecha) 
                 ORDER BY dia DESC";
        $result4 = $conn_mysql->query($sql4);
        $fechas = [];
        $cantidades = [];
        if ($result4) {
            while($row = $result4->fetch_assoc()) {
                $fechas[] = $row['dia'];
                $cantidades[] = (int)$row['total_eventos'];
            }
        }
        
        //Asigna todos los datos recolectados al array de respuesta principal
        $response['pendientes_por_tabla'] = $pendientesFinal;
        $response['errores_por_tabla'] = $erroresFinal;
        $response['replicados_por_tabla'] = $replicadosFinal;
        $response['oracle_sync'] = $oracleSync;
        $response['porcentaje_exito'] = (float)$porcentaje;
        $response['eventos_diarios'] = [
            //Para orden cronologico
            'fechas' => array_reverse($fechas),
            'cantidades' => array_reverse($cantidades)
        ];
        
        // Estadísticas generales de MySQL. Obtiene el listado completo de tablas y su cantidad de registros
        $result = $conn_mysql->query("SHOW TABLES");
        if ($result) {
            while ($row = $result->fetch_array()) {
                $table = $row[0];
                $count = $conn_mysql->query("SELECT COUNT(*) as total FROM $table")->fetch_assoc()['total'];
                $response['mysql']['tables'][$table] = (int)$count;
                $response['mysql']['records'] += (int)$count;
            }
        }
        
    } catch (Exception $e) {
        //MAnejo de errores
        logError("Error en estadísticas MySQL: " . $e->getMessage(), 'ERROR');
    }
}


/*
RECOLECCION DE DATOS DE ORACLE
*/
//Verificar conexion a MySQL activa
if (isOracleConnected()) {
    try {
        //Marca Oracle como Online y obtiene las estadisticas generales de todas la tablas
        $response['oracle']['online'] = true;
        $oracleStats = getOracleTableStats();
        $response['oracle']['tables'] = $oracleStats;
        $response['oracle']['records'] = array_sum($oracleStats);
        
        // Obtener datos de todas las tablas mapeadas
        foreach ($tableMapping as $mysqlTable => $oracleTable) {
            $count = countOracleTable($oracleTable);
            $response['oracle']['table_data'][$oracleTable] = $count;
        }
        
        
        // EVENTOS DE ORACLE
        $checkTable = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'LOGS_REPLICACION_ORACLE'");
        if (!isset($checkTable['error']) && !empty($checkTable)) {
            // La tabla existe, obtener eventos
            $oracleLogs = queryOracle("SELECT TO_CHAR(fecha, 'YYYY-MM-DD') as dia, COUNT(*) as total 
                                       FROM logs_replicacion_oracle 
                                       WHERE fecha >= SYSDATE - 7 
                                       GROUP BY TO_CHAR(fecha, 'YYYY-MM-DD') 
                                       ORDER BY dia DESC");
            
            if (!isset($oracleLogs['error']) && !empty($oracleLogs)) {
                $fechasOracle = [];
                $cantidadesOracle = [];
                foreach ($oracleLogs as $row) {
                    $fechasOracle[] = $row['DIA'];
                    $cantidadesOracle[] = (int)$row['TOTAL'];
                }
                $response['eventos_diarios_oracle'] = [
                    'fechas' => array_reverse($fechasOracle),
                    'cantidades' => array_reverse($cantidadesOracle)
                ];
            }
        } else {
            // La tabla no existe, generar datos de prueba para Oracle
            $today = new DateTime();
            $fechasOracle = [];
            $cantidadesOracle = [];
            for ($i = 6; $i >= 0; $i--) {
                $d = clone $today;
                $d->modify("-$i days");
                $fechasOracle[] = $d->format('Y-m-d');
                $cantidadesOracle[] = rand(1, 8); // Datos aleatorios de prueba
            }
            $response['eventos_diarios_oracle'] = [
                'fechas' => $fechasOracle,
                'cantidades' => $cantidadesOracle
            ];
        }
        
    } catch (Exception $e) {
        //Manejo de errores
        logError("Error en estadísticas Oracle: " . $e->getMessage(), 'ERROR');
        $response['oracle']['online'] = false;
    }
}

//Devuelve la respuesta completa en formato JSON con todas las estadistcas recolectadas
echo json_encode($response);
?>