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
        //ESTADO DE CONEXION
        'mysql_online' => isMySQLConnected(),
        'oracle_online' => isOracleConnected(),
        
        //ESTADiSTICAS DE REPLICACION (MySQL)
        'pendientes' => 0,
        'replicados' => 0,
        'errores' => 0,
        'conflictos' => 0,
        
        //ESTADiSTICAS DE REPLICACION (Oracle)
        'oracle_pendientes' => 0,
        'oracle_replicados' => 0,
        'oracle_errores' => 0,
        'oracle_conflictos' => 0,
        
        //ESTADiSTICAS DE TABLAS (MySQL)
        'mysql_tables' => 0,
        'mysql_rows' => 0,
        'mysql_table_list' => [],
        
        //ESTADiSTICAS DE TABLAS (Oracle)
        'oracle_tables' => 0,
        'oracle_rows' => 0,
        'oracle_table_list' => [],
        
        'timestamp' => date('Y-m-d H:i:s')
    ];

    //DATOS DE MYSQL
    if (isMySQLConnected()) {
        //Verificar la conexion a MySQl
        $conn_mysql = getMySQLConnection();
        //Verificar la conexion a MySQL. Agrupa los registros de la bitacora por estado de replicacion
        $sql = "SELECT 
                    SUM(CASE WHEN estado_replicacion = 'PENDIENTE' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN estado_replicacion = 'REPLICADO' THEN 1 ELSE 0 END) as replicados,
                    SUM(CASE WHEN estado_replicacion = 'ERROR' THEN 1 ELSE 0 END) as errores,
                    SUM(CASE WHEN estado_replicacion = 'CONFLICTO' THEN 1 ELSE 0 END) as conflictos
                FROM bitacora_replicacion";
        //Ejecutar la consulta y procesar resultados
        $result = $conn_mysql->query($sql);
        if ($result) {
            //Extraer los datos de la fila de resultados
            $data = $result->fetch_assoc();
            $response['pendientes'] = (int)($data['pendientes'] ?? 0);
            $response['replicados'] = (int)($data['replicados'] ?? 0);
            $response['errores'] = (int)($data['errores'] ?? 0);
            $response['conflictos'] = (int)($data['conflictos'] ?? 0);
        }
        //Estadisticas de tablas MySQL
        $result = $conn_mysql->query("SHOW TABLES");
        $tableNames = [];
        $totalRows = 0;
        
        if ($result) {
            while ($row = $result->fetch_array()) {
                $table = $row[0];
                $tableNames[] = $table;
                
                //Contar registros de cada tabla
                $countResult = $conn_mysql->query("SELECT COUNT(*) as total FROM $table");
                if ($countResult) {
                    $countRow = $countResult->fetch_assoc();
                    $totalRows += (int)$countRow['total'];
                }
            }
    }
            $response['mysql_tables'] = count($tableNames);
        $response['mysql_rows'] = $totalRows;
        $response['mysql_table_list'] = $tableNames;
    }


    //DATOS DE ORACLE
    //Se verifica la conexion a Oracle y se obtienen métricas de las tablas disponibles en la base de datos
    if (isOracleConnected()) {
        $checkBitacora = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'BITACORA'");
        
        if (!isset($checkBitacora['error']) && !empty($checkBitacora)) {
            $sql = "SELECT 
                        SUM(CASE WHEN UPPER(estado_replicacion) = 'PENDIENTE' THEN 1 ELSE 0 END) as PENDIENTES,
                        SUM(CASE WHEN UPPER(estado_replicacion) = 'REPLICADO' THEN 1 ELSE 0 END) as REPLICADOS,
                        SUM(CASE WHEN UPPER(estado_replicacion) = 'ERROR' THEN 1 ELSE 0 END) as ERRORES,
                        SUM(CASE WHEN UPPER(estado_replicacion) = 'CONFLICTO' THEN 1 ELSE 0 END) as CONFLICTOS
                    FROM BITACORA";
            
            $result = queryOracle($sql);
            
            if (!isset($result['error']) && !empty($result) && isset($result[0])) {
                $response['oracle_pendientes'] = (int)($result[0]['PENDIENTES'] ?? 0);
                $response['oracle_replicados'] = (int)($result[0]['REPLICADOS'] ?? 0);
                $response['oracle_errores'] = (int)($result[0]['ERRORES'] ?? 0);
                $response['oracle_conflictos'] = (int)($result[0]['CONFLICTOS'] ?? 0);
            } else {
                //Si no hay datos, intentar con LOGS_REPLICACION_ORACLE
                $checkLogs = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'LOGS_REPLICACION_ORACLE'");
                
                if (!isset($checkLogs['error']) && !empty($checkLogs)) {
                    $sqlLogs = "SELECT 
                                    SUM(CASE WHEN UPPER(evento) LIKE '%REPLIC%' OR UPPER(evento) LIKE '%EXITO%' THEN 1 ELSE 0 END) as REPLICADOS,
                                    SUM(CASE WHEN UPPER(evento) LIKE '%PENDIENT%' OR UPPER(evento) LIKE '%ESPERA%' THEN 1 ELSE 0 END) as PENDIENTES,
                                    SUM(CASE WHEN UPPER(evento) LIKE '%ERROR%' OR UPPER(evento) LIKE '%FALLO%' THEN 1 ELSE 0 END) as ERRORES,
                                    SUM(CASE WHEN UPPER(evento) LIKE '%CONFLICT%' THEN 1 ELSE 0 END) as CONFLICTOS
                                FROM LOGS_REPLICACION_ORACLE";
                    
                    $resultLogs = queryOracle($sqlLogs);
                    
                    if (!isset($resultLogs['error']) && !empty($resultLogs) && isset($resultLogs[0])) {
                        $response['oracle_replicados'] = (int)($resultLogs[0]['REPLICADOS'] ?? 0);
                        $response['oracle_pendientes'] = (int)($resultLogs[0]['PENDIENTES'] ?? 0);
                        $response['oracle_errores'] = (int)($resultLogs[0]['ERRORES'] ?? 0);
                        $response['oracle_conflictos'] = (int)($resultLogs[0]['CONFLICTOS'] ?? 0);
                    }
                }
            }
        } else {
            //BITACORA no existe, intentar con LOGS_REPLICACION_ORACLE
            $checkLogs = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'LOGS_REPLICACION_ORACLE'");
            
            if (!isset($checkLogs['error']) && !empty($checkLogs)) {
                $sqlLogs = "SELECT 
                                SUM(CASE WHEN UPPER(evento) LIKE '%REPLIC%' OR UPPER(evento) LIKE '%EXITO%' THEN 1 ELSE 0 END) as REPLICADOS,
                                SUM(CASE WHEN UPPER(evento) LIKE '%PENDIENT%' OR UPPER(evento) LIKE '%ESPERA%' THEN 1 ELSE 0 END) as PENDIENTES,
                                SUM(CASE WHEN UPPER(evento) LIKE '%ERROR%' OR UPPER(evento) LIKE '%FALLO%' THEN 1 ELSE 0 END) as ERRORES,
                                SUM(CASE WHEN UPPER(evento) LIKE '%CONFLICT%' THEN 1 ELSE 0 END) as CONFLICTOS
                            FROM LOGS_REPLICACION_ORACLE";
                
                $resultLogs = queryOracle($sqlLogs);
                
                if (!isset($resultLogs['error']) && !empty($resultLogs) && isset($resultLogs[0])) {
                    $response['oracle_replicados'] = (int)($resultLogs[0]['REPLICADOS'] ?? 0);
                    $response['oracle_pendientes'] = (int)($resultLogs[0]['PENDIENTES'] ?? 0);
                    $response['oracle_errores'] = (int)($resultLogs[0]['ERRORES'] ?? 0);
                    $response['oracle_conflictos'] = (int)($resultLogs[0]['CONFLICTOS'] ?? 0);
                }
            }
        }

        // Obtener tablas de Oracle
        $tables = getOracleTables();
        $response['oracle_tables'] = count($tables);
        $stats = getOracleTableStats();
        $response['oracle_rows'] = array_sum($stats);
    }
    //Totales combinados
    $response['total_pendientes'] = $response['pendientes'] + $response['oracle_pendientes'];
    $response['total_replicados'] = $response['replicados'] + $response['oracle_replicados'];
    $response['total_errores'] = $response['errores'] + $response['oracle_errores'];
    $response['total_conflictos'] = $response['conflictos'] + $response['oracle_conflictos'];

    //Devuelve la respuesta completa en formato JSON con todas las estadistcas recolectadas
    echo json_encode($response);
    ?>