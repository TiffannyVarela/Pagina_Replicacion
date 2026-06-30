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

    //Tablas de Oracle que se excluyen
    $excludeTables = [
        'BITACORA',
        'LAMBDA_EXECUTION_LOGS',
        'LOGS_REPLICACION_ORACLE'
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
        //Eventos por hora - MySQL
        'eventos_hora_mysql' => ['horas' => [], 'cantidades' => []],
        //Eventos por hora - Oracle
        'eventos_hora_oracle' => ['horas' => [], 'cantidades' => []],
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
        //Estadisticas de Oracle por estado (REPLICADOS, PENDIENTES, ERRORES)
        'oracle_estados' => [
            'replicados_por_tabla' => [],
            'pendientes_por_tabla' => [],
            'errores_por_tabla' => [],
            'conflictos_por_tabla' => [],
            'tablas' => []
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
                //Contar registros pendientes
                $sqlPend = "SELECT COUNT(*) as total 
                            FROM bitacora_replicacion 
                            WHERE tabla_afectada = '$tabla' AND estado_replicacion = 'PENDIENTE'";
                $resultPend = $conn_mysql->query($sqlPend);
                $pendientesFinal[] = $resultPend ? (int)$resultPend->fetch_assoc()['total'] : 0;
                
                //Contar registros con errores
                $sqlErr = "SELECT COUNT(*) as total 
                           FROM bitacora_replicacion 
                           WHERE tabla_afectada = '$tabla' AND estado_replicacion = 'ERROR'";
                $resultErr = $conn_mysql->query($sqlErr);
                $erroresFinal[] = $resultErr ? (int)$resultErr->fetch_assoc()['total'] : 0;
                
                //Contar registros replicados
                $sqlRep = "SELECT COUNT(*) as total 
                           FROM bitacora_replicacion 
                           WHERE tabla_afectada = '$tabla' AND estado_replicacion = 'REPLICADO'";
                $resultRep = $conn_mysql->query($sqlRep);
                $replicadosFinal[] = $resultRep ? (int)$resultRep->fetch_assoc()['total'] : 0;
                
                //Verificar sincronizacion con Oracle (solo si Oracle esta online) compara el conteo total de registros entre MySQL y Oracle para cada tabla
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

            $response['pendientes_por_tabla'] = $pendientesFinal;
            $response['errores_por_tabla'] = $erroresFinal;
            $response['replicados_por_tabla'] = $replicadosFinal;
            $response['oracle_sync'] = $oracleSync;
            
            //Calculo de porcentaje de éxito
            //Porcentaje desde MySQL
            $porcentaje_mysql = 0;
            $sql3 = "SELECT 
                        ROUND(SUM(CASE WHEN estado_replicacion = 'REPLICADO' THEN 1 ELSE 0 END) * 100.0 /COUNT(*), 2) as porcentaje
                     FROM bitacora_replicacion 
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            $result3 = $conn_mysql->query($sql3);
            if ($result3 && $result3->num_rows > 0) {
                $row = $result3->fetch_assoc();
                $porcentaje_mysql = (float)($row['porcentaje'] ?? 0);
            }

            //Porcentaje desde Oracle (LOGS_REPLICACION_ORACLE)
            $porcentaje_oracle = 0;
            if (isOracleConnected()) {
                //Verificar si existe LOGS_REPLICACION_ORACLE
                $checkLogs = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'LOGS_REPLICACION_ORACLE'");
                if (!isset($checkLogs['error']) && !empty($checkLogs)) {
                    //Contar eventos de éxito vs total en Oracle
                    $sqlOracle = "SELECT 
                                    ROUND(SUM(CASE WHEN evento LIKE '%REPLICADO%' OR evento LIKE '%EXITO%' THEN 1 ELSE 0 END) * 100.0 /COUNT(*), 2) as porcentaje
                                  FROM LOGS_REPLICACION_ORACLE 
                                  WHERE fecha >= SYSDATE - 1";
                    $resultOracle = queryOracle($sqlOracle);
                    if (!isset($resultOracle['error']) && !empty($resultOracle)) {
                        $porcentaje_oracle = (float)($resultOracle[0]['PORCENTAJE'] ?? 0);
                    }
                } else {
                    //Fallback a BITACORA
                    $checkBitacora = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'BITACORA'");
                    if (!isset($checkBitacora['error']) && !empty($checkBitacora)) {
                        $sqlOracle = "SELECT 
                                        ROUND(SUM(CASE WHEN estado_replicacion = 'REPLICADO' THEN 1 ELSE 0 END) * 100.0 /COUNT(*), 2) as porcentaje
                                      FROM BITACORA 
                                      WHERE fecha_hora >= SYSDATE - 1";
                        $resultOracle = queryOracle($sqlOracle);
                        if (!isset($resultOracle['error']) && !empty($resultOracle)) {
                            $porcentaje_oracle = (float)($resultOracle[0]['PORCENTAJE'] ?? 0);
                        }
                    }
                }
            }

            //Promedio de ambos porcentajes (o usar el que tenga datos)
            if ($porcentaje_mysql > 0 && $porcentaje_oracle > 0) {
                $porcentaje = round(($porcentaje_mysql + $porcentaje_oracle) /2, 2);
            } elseif ($porcentaje_mysql > 0) {
                $porcentaje = $porcentaje_mysql;
            } elseif ($porcentaje_oracle > 0) {
                $porcentaje = $porcentaje_oracle;
            } else {
                $porcentaje = 0;
            }

            $response['porcentaje_exito'] = (float)$porcentaje;
            
            //EVENTOS POR HORA - MySQL
            $horas = [];
            $cantidades = [];

            //Inicializar todas las horas con 0
            for ($i = 0; $i < 24; $i++) {
                $horas[] = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
                $cantidades[] = 0;
            }

            $sql4 = "SELECT HOUR(fecha) as hora, COUNT(*) as total_eventos 
                     FROM logs_replicacion 
                     WHERE fecha >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                     GROUP BY HOUR(fecha) 
                     ORDER BY hora ASC";
            $result4 = $conn_mysql->query($sql4);

            if ($result4) {
                while($row = $result4->fetch_assoc()) {
                    $hora = (int)$row['hora'];
                    if ($hora >= 0 && $hora < 24) {
                        $cantidades[$hora] = (int)$row['total_eventos'];
                    }
                }
            }
            
            //Asigna todos los datos recolectados al array de respuesta principal
            $response['pendientes_por_tabla'] = $pendientesFinal;
            $response['errores_por_tabla'] = $erroresFinal;
            $response['replicados_por_tabla'] = $replicadosFinal;
            $response['oracle_sync'] = $oracleSync;
            $response['porcentaje_exito'] = (float)$porcentaje;
            $response['eventos_hora_mysql'] = [
                'horas' => $horas,
                'cantidades' => $cantidades
            ];
            
            //Estadisticas generales de MySQL. Obtiene el listado completo de tablas y su cantidad de registros
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
            //Manejo de errores
            logError("Error en estadisticas MySQL: " . $e->getMessage(), 'ERROR');
        }
    }


    /*
    RECOLECCION DE DATOS DE ORACLE
    */
    //Verificar conexion a Oracle activa
    if (isOracleConnected()) {
        try {
            //Marca Oracle como Online y obtiene las estadisticas generales de todas la tablas
            $response['oracle']['online'] = true;
            $oracleStats = getOracleTableStats();
            $response['oracle']['tables'] = $oracleStats;
            $response['oracle']['records'] = array_sum($oracleStats);
            
            //Obtener datos de todas las tablas mapeadas
            foreach ($tableMapping as $mysqlTable => $oracleTable) {
                //Verificar si la tabla Oracle esta en la lista de exclusion
                if (!in_array($oracleTable, $excludeTables)) {
                    $count = countOracleTable($oracleTable);
                    $response['oracle']['table_data'][$oracleTable] = $count;
                }
            }
            
            $oracleEstados = obtenerEstadosDesdeBitacoraOracle();
            if (!empty($oracleEstados['tablas'])) {
                $response['oracle_estados'] = $oracleEstados;
            }
            
            $oracleHoras = [];
            $oracleCantidades = [];
            for ($i = 0; $i < 24; $i++) {
                $oracleHoras[] = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
                $oracleCantidades[] = 0;
            }
            
            $checkBitacora = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'BITACORA'");
            if (!isset($checkBitacora['error']) && !empty($checkBitacora)) {
                // Obtener eventos por hora desde BITACORA
                $sqlEventosHora = "SELECT 
                                    TO_CHAR(fecha_hora, 'HH24') as HORA, 
                                    COUNT(*) as TOTAL 
                                  FROM BITACORA 
                                  WHERE fecha_hora >= SYSDATE - 1 
                                  GROUP BY TO_CHAR(fecha_hora, 'HH24') 
                                  ORDER BY HORA ASC";
                
                $resultEventos = queryOracle($sqlEventosHora);
                
                if (!isset($resultEventos['error']) && !empty($resultEventos)) {
                    foreach ($resultEventos as $row) {
                        $hora = (int)($row['HORA'] ?? 0);
                        if ($hora >= 0 && $hora < 24) {
                            $oracleCantidades[$hora] = (int)($row['TOTAL'] ?? 0);
                        }
                    }
                }
            }
            
            $response['eventos_hora_oracle'] = [
                'horas' => $oracleHoras,
                'cantidades' => $oracleCantidades
            ];
            
        } catch (Exception $e) {
            logError("Error en estadisticas Oracle: " . $e->getMessage(), 'ERROR');
            $response['oracle']['online'] = false;
        }
    }

    //Devuelve la respuesta completa en formato JSON con todas las estadistcas recolectadas
    echo json_encode($response);
    
    function obtenerPorcentajeExitoOracle() {
        $checkBitacora = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'BITACORA'");
        if (isset($checkBitacora['error']) || empty($checkBitacora)) {
            return 0;
        }
        
        $sql = "SELECT 
                    ROUND(SUM(CASE WHEN UPPER(estado_replicacion) = 'REPLICADO' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as PORCENTAJE
                FROM BITACORA 
                WHERE fecha_hora >= SYSDATE - 1";
        
        $result = queryOracle($sql);
        
        if (!isset($result['error']) && !empty($result) && isset($result[0])) {
            return (float)($result[0]['PORCENTAJE'] ?? 0);
        }
        
        return 0;
    }

    /*
    OBTENER ESTADOS DE REPLICACIÓN DESDE BITACORA ORACLE
    */
    function obtenerEstadosDesdeBitacoraOracle() {
        $resultado = [
            'replicados_por_tabla' => [],
            'pendientes_por_tabla' => [],
            'errores_por_tabla' => [],
            'conflictos_por_tabla' => [],
            'tablas' => []
        ];
        
        $checkBitacora = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'BITACORA'");
        if (isset($checkBitacora['error']) || empty($checkBitacora)) {
            return $resultado;
        }
        
        $sql = "SELECT 
                tabla_afectada as TABLA_AFECTADA,
                SUM(CASE WHEN UPPER(estado_replicacion) = 'REPLICADO' THEN 1 ELSE 0 END) as REPLICADOS,
                SUM(CASE WHEN UPPER(estado_replicacion) = 'PENDIENTE' THEN 1 ELSE 0 END) as PENDIENTES,
                SUM(CASE WHEN UPPER(estado_replicacion) = 'ERROR' THEN 1 ELSE 0 END) as ERRORES,
                SUM(CASE WHEN UPPER(estado_replicacion) = 'CONFLICTO' THEN 1 ELSE 0 END) as CONFLICTOS
            FROM BITACORA
            GROUP BY tabla_afectada
            ORDER BY tabla_afectada";
        
        $result = queryOracle($sql);
        
        if (!isset($result['error']) && !empty($result)) {
            foreach ($result as $row) {
                $resultado['tablas'][] = $row['TABLA_AFECTADA'];
                $resultado['replicados_por_tabla'][] = (int)($row['REPLICADOS'] ?? 0);
                $resultado['pendientes_por_tabla'][] = (int)($row['PENDIENTES'] ?? 0);
                $resultado['errores_por_tabla'][] = (int)($row['ERRORES'] ?? 0);
                $resultado['conflictos_por_tabla'][] = (int)($row['CONFLICTOS'] ?? 0);
            }
        }
        
        return $resultado;
    }
    ?>