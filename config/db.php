    <?php
    //CONFIGURACION MYSQL (Aiven)
    //Host
    define('MYSQL_HOST', 'mysql-proyecto-unitec-11811146.j.aivencloud.com');
    //Puerto
    define('MYSQL_PORT', 20628);
    //Nombre de la base de datos
    define('MYSQL_DATABASE', 'naviera_replica');
    //Credenciales
    define('MYSQL_USERNAME', 'replicador');
    define('MYSQL_PASSWORD', 'replicacion-2026');


    //CONFIGURACION ORACLE (AWS RDS)
    //Host (en este caso se tuvo que usar la ip por problemas con el llamado al URL desde OCI8)
    define('ORACLE_HOST', '3.18.30.214');
    //Puerto
    define('ORACLE_PORT', 1521);
    //Servicio
    define('ORACLE_SERVICE', 'DATABASE');
    //Credenciales
    define('ORACLE_USERNAME', 'admin');
    define('ORACLE_PASSWORD', 'Holamundo_504');
    //Charset
    define('ORACLE_CHARSET', 'AL32UTF8');

    //CONFIGURACION DE LOGS
    //Ruta del archivo de logs
    define('LOG_FILE', __DIR__ . '/../logs/error.log');
    //Nivel de detalle de los logs (DEBUG, INFO, WARNING, ERROR, CRITICAL)
    define('LOG_LEVEL', 'DEBUG');

    /*FUNCION DE LOG
    string $message Mensaje a registrar
    string $level Nivel de severidad
    array $context Datos adicionales de contexto
    */
    function logError($message, $level = 'ERROR', $context = []) {
        //Crear el directorio de logs si no existe
        $logDir = dirname(LOG_FILE);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        //Formatear la entrada del log con timestamp y nivel
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $logEntry = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;
        //Escribir en el archivo de logs
        error_log($logEntry, 3, LOG_FILE);
        //Para errores criticos, tambien registrar en el log del sistema
        if ($level === 'ERROR' || $level === 'CRITICAL') {
            error_log($logEntry);
        }
    }

    /*
    FUNCION PARA OBTENER CONEXION MYSQL
    */
    function getMySQLConnection($retry = true) {
        global $mysql_error, $mysql_last_error_time, $conn_mysql;
        
        if (isset($conn_mysql) && $conn_mysql !== null) {
            try {
                if (@$conn_mysql->ping()) {
                    return $conn_mysql;
                }
            } catch (Exception $e) {
                //La conexion no es valida, continuar con una nueva
            }
        }
        
        $maxRetries = $retry ? 3 : 1;
        $attempt = 0;
        $lastError = null;
        
        while ($attempt < $maxRetries) {
            try {
                $conn = @new mysqli(
                    MYSQL_HOST, 
                    MYSQL_USERNAME, 
                    MYSQL_PASSWORD, 
                    MYSQL_DATABASE, 
                    MYSQL_PORT
                );
                
                if (!$conn->connect_error) {
                    $conn->set_charset("utf8mb4");
                    $conn->query("SET SESSION wait_timeout = 28800");
                    $conn->query("SET SESSION interactive_timeout = 28800");
                    
                    $conn_mysql = $conn;
                    
                    if ($attempt > 0) {
                        logError("Conexion MySQL exitosa después de $attempt reintentos", 'INFO');
                    }
                    return $conn;
                }
                
                $lastError = $conn->connect_error;
                logError("Intento $attempt: Error MySQL: $lastError", 'WARNING');
                
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                logError("Intento $attempt: Excepcion MySQL: $lastError", 'WARNING');
            }
            
            $attempt++;
            if ($attempt < $maxRetries) {
                sleep(1);
            }
        }
        
        $mysql_error = "No se pudo conectar a MySQL después de $maxRetries intentos: $lastError";
        $mysql_last_error_time = date('Y-m-d H:i:s');
        logError($mysql_error, 'ERROR');
        $conn_mysql = null;
        return null;
    }

    /*FUNCION PARA OBTENER CONEXION ORACLE
    Construye la cadena TNS para conexion Oracle
    string Cadena TNS en formato host:puerto/servicio
    */
    function getOracleTNS() {
        return ORACLE_HOST . ':' . ORACLE_PORT . '/' . ORACLE_SERVICE;
    }

    /*
    Obtiene una conexion a Oracle
     */
    function getOracleConnection($retry = true) {
        global $oracle_error, $oracle_last_error_time, $oracle_conn;
        
        //Si ya hay una conexion, verificarla
        if (isset($oracle_conn) && $oracle_conn !== null) {
            // Verificar si la conexion sigue activa
            try {
                $check = oci_parse($oracle_conn, "SELECT 1 FROM DUAL");
                if (@oci_execute($check)) {
                    oci_free_statement($check);
                    return $oracle_conn;
                }
                oci_free_statement($check);
            } catch (Exception $e) {
                //Conexion no valida, continuar con una nueva
            }
            oci_close($oracle_conn);
            $oracle_conn = null;
        }
        
        if (!extension_loaded('oci8')) {
            logError('OCI8 extension no instalada', 'CRITICAL');
            return null;
        }
        
        $maxRetries = $retry ? 3 : 1;
        $attempt = 0;
        $lastError = null;
        
        while ($attempt < $maxRetries) {
            try {
                $tns = getOracleTNS();
                $conn = @oci_connect(ORACLE_USERNAME, ORACLE_PASSWORD, $tns, ORACLE_CHARSET);
                
                if ($conn) {
                    $oracle_conn = $conn;
                    if ($attempt > 0) {
                        logError("Conexion Oracle exitosa después de $attempt reintentos", 'INFO');
                    }
                    return $conn;
                }
                
                $e = oci_error();
                $lastError = $e['message'] ?? 'Error desconocido';
                logError("Intento $attempt: Error Oracle: $lastError", 'WARNING');
                
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                logError("Intento $attempt: Excepcion Oracle: $lastError", 'WARNING');
            }
            
            $attempt++;
            if ($attempt < $maxRetries) {
                sleep(1);
            }
        }
        
        $oracle_error = "No se pudo conectar a Oracle después de $maxRetries intentos: $lastError";
        $oracle_last_error_time = date('Y-m-d H:i:s');
        logError($oracle_error, 'ERROR');
        $oracle_conn = null;
        return null;
    }

    //CONEXIoN INICIAL MYSQL
    $conn_mysql = getMySQLConnection(true);

    //CONEXIoN INICIAL ORACLE
    $oracle_conn = getOracleConnection(true);

    //Verifica si la conexion a MySQL esta activa
    function isMySQLConnected() {
        global $conn_mysql;
        
        if (isset($conn_mysql) && $conn_mysql !== null) {
            try {
                return @$conn_mysql->ping();
            } catch (Exception $e) {
                logError("Excepcion en isMySQLConnected: " . $e->getMessage(), 'ERROR');
                return false;
            }
        }
        
        //Intentar reconectar
        $conn = getMySQLConnection(false);
        if ($conn === null) return false;
        return true;
    }

    //Verifica si la conexion a Oracle esta activa
    function isOracleConnected() {
        global $oracle_conn;
        
        if (isset($oracle_conn) && $oracle_conn !== null) {
            try {
                $check = oci_parse($oracle_conn, "SELECT 1 FROM DUAL");
                $result = @oci_execute($check);
                oci_free_statement($check);
                return $result;
            } catch (Exception $e) {
                return false;
            }
        }
        
        // Intentar reconectar
        $conn = getOracleConnection(false);
        if ($conn) {
            return true;
        }
        return false;
    }

    /*
    Ejecuta una consulta SQL en MySQL con parametros opcionales
    string $sql Consulta SQL a ejecutar
    array $params Parametros para la consulta preparada (opcional)
    mysqli_result|array Resultado de la consulta o array con error
    */
    function executeMySQLQuery($sql, $params = []) {
        //Obtener conexion a MySQL
        $conn = getMySQLConnection(true);
        if (!$conn) {
            logError("No hay conexion MySQL para ejecutar query", 'ERROR', ['sql' => $sql]);
            return ['error' => 'MySQL no disponible'];
        }
        //Si hay parametros, usar consulta preparada
        try {
            if (!empty($params)) {
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Error preparando query: " . $conn->error);
                }
                //Construir tipos de parametros para bind
                $types = '';
                $bindParams = [];
                foreach ($params as $key => $value) {
                    if (is_int($value)) {
                        $types .= 'i';
                    } elseif (is_float($value)) {
                        $types .= 'd';
                    } elseif (is_string($value)) {
                        $types .= 's';
                    } else {
                        $types .= 'b';
                    }
                    $bindParams[] = &$params[$key];
                }
                //Vincular parametros y ejecutar
                array_unshift($bindParams, $types);
                call_user_func_array([$stmt, 'bind_param'], $bindParams);
                
                $stmt->execute();
                $result = $stmt->get_result();
                $stmt->close();
                $conn->close();
                
                return $result;
            } else {
                //Consulta simple sin parametros
                $result = $conn->query($sql);
                if (!$result) {
                    throw new Exception("Error en query: " . $conn->error);
                }
                $conn->close();
                return $result;
            }
        } catch (Exception $e) {
            logError("Error en executeMySQLQuery: " . $e->getMessage(), 'ERROR', ['sql' => $sql]);
            if (isset($conn)) $conn->close();
            return ['error' => $e->getMessage()];
        }
    }

    //Ejecuta una consulta SQL en Oracle con parametros opcionales
    function queryOracle($sql, $params = []) {
        //Obtener conexion a Oracle
        $conn = getOracleConnection(true);
        if (!$conn) {
            return ['error' => 'No se pudo conectar a Oracle'];
        }
        
        try {
            //Parsear la consulta SQL
            $stmt = oci_parse($conn, $sql);
            if (!$stmt) {
                $e = oci_error($conn);
                throw new Exception("Error parseando SQL: " . ($e['message'] ?? 'Error desconocido'));
            }
            //Vincular parametros si existen
            foreach ($params as $key => $value) {
                oci_bind_by_name($stmt, $key, $value);
            }
            //Ejecutar la consulta
            if (!oci_execute($stmt)) {
                $e = oci_error($stmt);
                throw new Exception("Error ejecutando SQL: " . ($e['message'] ?? 'Error desconocido'));
            }
            //Recuperar todos los resultados
            $results = [];
            while ($row = oci_fetch_assoc($stmt)) {
                //Procesar cada fila para manejar valores especiales
                foreach ($row as $key => $value) {
                    if ($value === null) {
                        $row[$key] = null;
                    } elseif (is_resource($value)) {
                        //Convertir recursos a string
                        $row[$key] = (string)$value;
                    }
                }
                $results[] = $row;
            }
            //Liberar recursos
            oci_free_statement($stmt);
            oci_close($conn);
            
            return $results;
        } catch (Exception $e) {
            logError("Error en queryOracle: " . $e->getMessage(), 'ERROR', ['sql' => $sql]);
            if (isset($stmt)) {
                oci_free_statement($stmt);
            }
            if (isset($conn)) {
                oci_close($conn);
            }
            return ['error' => $e->getMessage()];
        }
    }

    //Cuenta los registros de una tabla en Oracle
    function countOracleTable($tableName) {
        //Limpiarnombre de tabla para prevenir inyeccion SQL
        $tableName = strtoupper(preg_replace('/[^a-zA-Z0-9_]/', '', $tableName));
        //Ejecutar consulta de conteo
        $result = queryOracle("SELECT COUNT(*) as total FROM $tableName");
        if (isset($result['error'])) {
            logError("Error contando tabla Oracle: $tableName - " . $result['error'], 'ERROR');
            return 0;
        }
        return (int)($result[0]['TOTAL'] ?? 0);
    }

    //Obtiene la lista de tablas del usuario en Oracle
    function getOracleTables() {
        //Consulta para obtener tablas del usuario actual
        $sql = "SELECT table_name FROM user_tables ORDER BY table_name";
        $result = queryOracle($sql);
        if (isset($result['error'])) {
            logError("Error obteniendo tablas Oracle: " . $result['error'], 'ERROR');
            return [];
        }
        //Extraer nombres de tablas del resultado
        $tables = [];
        foreach ($result as $row) {
            $tables[] = $row['TABLE_NAME'];
        }
        return $tables;
    }

    //Obtiene estadisticas de todas las tablas Oracle
    function getOracleTableStats() {
        //Consulta para obtener estadisticas de tablas
        $sql = "SELECT table_name, num_rows FROM user_tables ORDER BY table_name";
        $result = queryOracle($sql);
        if (isset($result['error'])) {
            logError("Error obteniendo estadísticas Oracle: " . $result['error'], 'ERROR');
            return [];
        }
        //Construir array de estadisticas
        $stats = [];
        foreach ($result as $row) {
            $stats[$row['TABLE_NAME']] = (int)($row['NUM_ROWS'] ?? 0);
        }
        return $stats;
    }

    //Variable global para compatibilidad
    $conn = $conn_mysql;

    //REGISTRAR ESTADO INICIAL
    if ($conn_mysql !== null) {
        try {
            //Verificar si existe la tabla de logs
            $check = $conn_mysql->query("SHOW TABLES LIKE 'logs_replicacion'");
            if ($check && $check->num_rows > 0) {
                //Registrar estado inicial de Oracle
                $estado_oracle = isOracleConnected() ? 'ONLINE' : 'OFFLINE';
                $stmt = $conn_mysql->prepare("
                    INSERT INTO logs_replicacion (evento, descripcion, fecha) 
                    VALUES ('INICIO_SISTEMA', ?, NOW())
                ");
                if ($stmt) {
                    $desc = "Sistema iniciado - Oracle: $estado_oracle";
                    $stmt->bind_param("s", $desc);
                    $stmt->execute();
                    $stmt->close();
                    logError("Sistema iniciado - Oracle: $estado_oracle", 'INFO');
                }
            }
        } catch (Exception $e) {
            logError("Error registrando estado inicial: " . $e->getMessage(), 'ERROR');
        }
    }

    //Recopila informacion detallada de MySQL y Oracle incluyendo estado de conexion, lista de tablas y conteo de registros
    function getCompleteStats() {
        //Inicializar estructura de estadisticas
        $stats = [
            'mysql' => [
                'online' => isMySQLConnected(),
                'tables' => [],
                'records' => 0,
                'errors' => []
            ],
            'oracle' => [
                'online' => isOracleConnected(),
                'tables' => [],
                'records' => 0,
                'errors' => []
            ]
        ];
        //Obtiene todas las tablas y sus conteos de registros de MySQL
        if ($stats['mysql']['online']) {
            try {
                $conn = getMySQLConnection(false);
                if ($conn) {
                    //Obtener todas las tablas
                    $result = $conn->query("SHOW TABLES");
                    while ($row = $result->fetch_array()) {
                        $table = $row[0];
                        //Contar registros de cada tabla
                        $count = $conn->query("SELECT COUNT(*) as total FROM $table")->fetch_assoc()['total'];
                        $stats['mysql']['tables'][$table] = (int)$count;
                        $stats['mysql']['records'] += (int)$count;
                    }
                    $conn->close();
                }
            } catch (Exception $e) {
                $stats['mysql']['errors'][] = $e->getMessage();
                logError("Error obteniendo estadísticas MySQL: " . $e->getMessage(), 'ERROR');
            }
        }
        //Obtiene todas las estadisticas de Oracle 
        if ($stats['oracle']['online']) {
            try {
                $oracleStats = getOracleTableStats();
                $stats['oracle']['tables'] = $oracleStats;
                $stats['oracle']['records'] = array_sum($oracleStats);
            } catch (Exception $e) {
                $stats['oracle']['errors'][] = $e->getMessage();
                logError("Error obteniendo estadísticas Oracle: " . $e->getMessage(), 'ERROR');
            }
        }
        
        return $stats;
    }
    ?>