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
            
            //Por si hay tablas en Oracle que no estan en el mapeo
            $allOracleTables = getOracleTables();
            foreach ($allOracleTables as $table) {
                if (!in_array($table, $excludeTables) && !isset($response['oracle']['table_data'][$table])) {
                    $count = countOracleTable($table);
                    $response['oracle']['table_data'][$table] = $count;
                }
            }
            
            //RECOLECCION DE ESTADOS DE ORACLE DESDE LOGS_REPLICACION_ORACLE
            $oracleEstados = [
                'replicados_por_tabla' => [],
                'pendientes_por_tabla' => [],
                'errores_por_tabla' => [],
                'conflictos_por_tabla' => [],
                'tablas' => []
            ];
            
            //Verificar si existe LOGS_REPLICACION_ORACLE
            $checkLogs = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'LOGS_REPLICACION_ORACLE'");
            
            if (!isset($checkLogs['error']) && !empty($checkLogs)) {
                //OBTENER EVENTOS POR TIPO DESDE LOGS_REPLICACION_ORACLE
                //La tabla LOGS_REPLICACION_ORACLE tiene: evento, descripcion, fecha
                //Vamos a agrupar por evento para mostrar en la grafica
                $sqlEstados = "SELECT 
                                evento as TABLA_AFECTADA,
                                COUNT(*) as TOTAL
                              FROM LOGS_REPLICACION_ORACLE
                              GROUP BY evento
                              ORDER BY evento";
                
                $resultEstados = queryOracle($sqlEstados);
                
                if (!isset($resultEstados['error']) && !empty($resultEstados)) {
                    //Inicializar contadores para los tipos de eventos comunes
                    $eventosMap = [
                        'REPLICADO' => 0,
                        'PENDIENTE' => 0,
                        'ERROR' => 0,
                        'CONFLICTO' => 0,
                        'ERROR_CONEXION' => 0,
                        'JOB_EJECUTADO' => 0,
                        'SINCRONIZACION' => 0,
                        'OTRO' => 0
                    ];
                    
                    foreach ($resultEstados as $row) {
                        $evento = strtoupper($row['TABLA_AFECTADA'] ?? 'OTRO');
                        
                        //Mapear eventos a categorias
                        if (strpos($evento, 'REPLIC') !== false || strpos($evento, 'EXITO') !== false) {
                            $eventosMap['REPLICADO'] += (int)$row['TOTAL'];
                        } elseif (strpos($evento, 'PENDIENT') !== false || strpos($evento, 'ESPERA') !== false) {
                            $eventosMap['PENDIENTE'] += (int)$row['TOTAL'];
                        } elseif (strpos($evento, 'ERROR') !== false || strpos($evento, 'FALLO') !== false) {
                            $eventosMap['ERROR'] += (int)$row['TOTAL'];
                        } elseif (strpos($evento, 'CONFLICT') !== false) {
                            $eventosMap['CONFLICTO'] += (int)$row['TOTAL'];
                        } elseif (strpos($evento, 'CONEXION') !== false) {
                            $eventosMap['ERROR_CONEXION'] += (int)$row['TOTAL'];
                        } elseif (strpos($evento, 'JOB') !== false || strpos($evento, 'EJECUT') !== false) {
                            $eventosMap['JOB_EJECUTADO'] += (int)$row['TOTAL'];
                        } else {
                            $eventosMap['OTRO'] += (int)$row['TOTAL'];
                        }
                    }
                    
                    //Construir el array de estados
                    $oracleEstados['tablas'] = ['REPLICADO', 'PENDIENTE', 'ERROR', 'CONFLICTO'];
                    $oracleEstados['replicados_por_tabla'] = [$eventosMap['REPLICADO'], 0, 0, 0];
                    $oracleEstados['pendientes_por_tabla'] = [0, $eventosMap['PENDIENTE'], 0, 0];
                    $oracleEstados['errores_por_tabla'] = [0, 0, $eventosMap['ERROR'], 0];
                    $oracleEstados['conflictos_por_tabla'] = [0, 0, 0, $eventosMap['CONFLICTO']];
                    
                    $response['oracle_estados'] = $oracleEstados;
                }
            } else {
                //FALLBACK: Usar BITACORA si existe
                $checkBitacora = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'BITACORA'");
                
                if (!isset($checkBitacora['error']) && !empty($checkBitacora)) {
                    //Detectar nombres de columnas en BITACORA
                    $columnCheck = queryOracle("SELECT * FROM BITACORA WHERE ROWNUM = 1");
                    
                    if (!isset($columnCheck['error']) && !empty($columnCheck)) {
                        $columnNames = array_keys($columnCheck[0]);
                        
                        $tablaCol = null;
                        $estadoCol = null;
                        
                        foreach ($columnNames as $col) {
                            $colUpper = strtoupper($col);
                            if (strpos($colUpper, 'TABLA') !== false || strpos($colUpper, 'TABLE') !== false) {
                                $tablaCol = $col;
                            }
                            if (strpos($colUpper, 'ESTADO') !== false || strpos($colUpper, 'STATUS') !== false) {
                                $estadoCol = $col;
                            }
                        }
                        
                        if (!$tablaCol) $tablaCol = 'TABLA_AFECTADA';
                        if (!$estadoCol) $estadoCol = 'ESTADO_REPLICACION';
                        
                        $sqlEstados = "SELECT 
                                        $tablaCol as TABLA_AFECTADA,
                                        SUM(CASE WHEN $estadoCol = 'REPLICADO' THEN 1 ELSE 0 END) as REPLICADOS,
                                        SUM(CASE WHEN $estadoCol = 'PENDIENTE' THEN 1 ELSE 0 END) as PENDIENTES,
                                        SUM(CASE WHEN $estadoCol = 'ERROR' THEN 1 ELSE 0 END) as ERRORES,
                                        SUM(CASE WHEN $estadoCol = 'CONFLICTO' THEN 1 ELSE 0 END) as CONFLICTOS
                                      FROM BITACORA
                                      GROUP BY $tablaCol
                                      ORDER BY $tablaCol";
                        
                        $resultEstados = queryOracle($sqlEstados);
                        
                        if (!isset($resultEstados['error']) && !empty($resultEstados)) {
                            foreach ($resultEstados as $row) {
                                $oracleEstados['tablas'][] = $row['TABLA_AFECTADA'];
                                $oracleEstados['replicados_por_tabla'][] = (int)$row['REPLICADOS'];
                                $oracleEstados['pendientes_por_tabla'][] = (int)$row['PENDIENTES'];
                                $oracleEstados['errores_por_tabla'][] = (int)$row['ERRORES'];
                                $oracleEstados['conflictos_por_tabla'][] = (int)$row['CONFLICTOS'];
                            }
                            
                            $response['oracle_estados'] = $oracleEstados;
                        }
                    }
                }
            }
            
            //Si no se encontraron datos en LOGS_REPLICACION_ORACLE ni en BITACORA, usar datos de las tablas mapeadas como fallback
            if (empty($response['oracle_estados']['tablas'])) {
                $tablasOracle = array_keys($response['oracle']['table_data']);
                $oracleEstados = [
                    'replicados_por_tabla' => [],
                    'pendientes_por_tabla' => [],
                    'errores_por_tabla' => [],
                    'conflictos_por_tabla' => [],
                    'tablas' => []
                ];
                
                foreach ($tablasOracle as $tabla) {
                    $oracleEstados['tablas'][] = $tabla;
                    $oracleEstados['replicados_por_tabla'][] = 0;
                    $oracleEstados['pendientes_por_tabla'][] = 0;
                    $oracleEstados['errores_por_tabla'][] = 0;
                    $oracleEstados['conflictos_por_tabla'][] = 0;
                }
                
                $response['oracle_estados'] = $oracleEstados;
            }
            
            //EVENTOS POR HORA - Oracle
            $oracleHoras = [];
            $oracleCantidades = [];
            
            //Inicializar todas las horas con 0
            for ($i = 0; $i < 24; $i++) {
                $oracleHoras[] = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
                $oracleCantidades[] = 0;
            }
            
            //Intentar desde LOGS_REPLICACION_ORACLE (usando columna fecha)
            $checkLogsHoras = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'LOGS_REPLICACION_ORACLE'");
            if (!isset($checkLogsHoras['error']) && !empty($checkLogsHoras)) {
                //Usar la columna fecha de LOGS_REPLICACION_ORACLE
                $oracleLogs = queryOracle("SELECT TO_CHAR(fecha, 'HH24') as hora, COUNT(*) as total 
                                           FROM LOGS_REPLICACION_ORACLE 
                                           WHERE fecha >= SYSDATE - 1 
                                           GROUP BY TO_CHAR(fecha, 'HH24') 
                                           ORDER BY hora ASC");
                
                if (!isset($oracleLogs['error']) && !empty($oracleLogs)) {
                    foreach ($oracleLogs as $row) {
                        $hora = (int)$row['HORA'];
                        if ($hora >= 0 && $hora < 24) {
                            $oracleCantidades[$hora] = (int)$row['TOTAL'];
                        }
                    }
                }
            } else {
                //Fallback a BITACORA
                $checkBitacoraHoras = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'BITACORA'");
                if (!isset($checkBitacoraHoras['error']) && !empty($checkBitacoraHoras)) {
                    //Detectar columna de fecha en BITACORA
                    $columnCheck = queryOracle("SELECT * FROM BITACORA WHERE ROWNUM = 1");
                    if (!isset($columnCheck['error']) && !empty($columnCheck)) {
                        $columnNames = array_keys($columnCheck[0]);
                        $fechaCol = null;
                        foreach ($columnNames as $col) {
                            $colUpper = strtoupper($col);
                            if (strpos($colUpper, 'FECHA') !== false || strpos($colUpper, 'DATE') !== false || strpos($colUpper, 'TIME') !== false) {
                                $fechaCol = $col;
                                break;
                            }
                        }
                        if (!$fechaCol) $fechaCol = 'FECHA_HORA';
                        
                        $oracleLogs = queryOracle("SELECT TO_CHAR($fechaCol, 'HH24') as hora, COUNT(*) as total 
                                                   FROM BITACORA 
                                                   WHERE $fechaCol >= SYSDATE - 1 
                                                   GROUP BY TO_CHAR($fechaCol, 'HH24') 
                                                   ORDER BY hora ASC");
                        
                        if (!isset($oracleLogs['error']) && !empty($oracleLogs)) {
                            foreach ($oracleLogs as $row) {
                                $hora = (int)$row['HORA'];
                                if ($hora >= 0 && $hora < 24) {
                                    $oracleCantidades[$hora] = (int)$row['TOTAL'];
                                }
                            }
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
    ?>