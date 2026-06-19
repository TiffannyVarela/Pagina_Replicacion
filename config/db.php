<?php


// CONFIGURACIÓN MYSQL (Aiven)
define('MYSQL_HOST', 'mysql-11811146-unitec-11811146.d.aivencloud.com');
define('MYSQL_PORT', 20628);
define('MYSQL_DATABASE', 'naviera_replica');
define('MYSQL_USERNAME', 'replicador');
define('MYSQL_PASSWORD', 'replicacion-2026');


// CONFIGURACIÓN ORACLE (AWS RDS)
define('ORACLE_HOST', '3.18.30.214');
define('ORACLE_PORT', 1521);
define('ORACLE_SERVICE', 'DATABASE');
define('ORACLE_USERNAME', 'admin');
define('ORACLE_PASSWORD', 'Holamundo_504');
define('ORACLE_CHARSET', 'AL32UTF8');

// CONEXIÓN MYSQL
$conn_mysql = null;
$mysql_error = null;

try {
    $conn_mysql = @new mysqli(
        MYSQL_HOST, 
        MYSQL_USERNAME, 
        MYSQL_PASSWORD, 
        MYSQL_DATABASE, 
        MYSQL_PORT
    );
    
    if ($conn_mysql->connect_error) {
        $mysql_error = "Error conexión MySQL: " . $conn_mysql->connect_error;
        error_log($mysql_error);
        $conn_mysql = null;
    } else {
        $conn_mysql->set_charset("utf8mb4");
        $conn_mysql->query("SET SESSION wait_timeout = 28800");
        $conn_mysql->query("SET SESSION interactive_timeout = 28800");
    }
} catch (Exception $e) {
    $mysql_error = "Excepción MySQL: " . $e->getMessage();
    error_log($mysql_error);
    $conn_mysql = null;
}

// FUNCIONES DE VERIFICACIÓN

function isMySQLConnected() {
    global $conn_mysql;
    if ($conn_mysql === null) return false;
    try {
        return @$conn_mysql->ping();
    } catch (Exception $e) {
        return false;
    }
}

//Obtiene conexión Oracle usando Easy Connect
function getOracleConnection() {
    if (!extension_loaded('oci8')) {
        return null;
    }
    
    try {
        $easy_connect = ORACLE_HOST . ':' . ORACLE_PORT . '/' . ORACLE_SERVICE;
        $conn = @oci_connect(ORACLE_USERNAME, ORACLE_PASSWORD, $easy_connect, ORACLE_CHARSET);
        return $conn;
    } catch (Exception $e) {
        return null;
    }
}

function isOracleConnected() {
    $conn = getOracleConnection();
    if ($conn) {
        oci_close($conn);
        return true;
    }
    return false;
}

function getMySQLConnection() {
    global $conn_mysql;
    return $conn_mysql;
}

//  Ejecuta una consulta en Oracle y devuelve los resultados como array
function queryOracle($sql, $params = []) {
    $conn = getOracleConnection();
    if (!$conn) {
        return ['error' => 'No se pudo conectar a Oracle'];
    }
    
    try {
        $stmt = oci_parse($conn, $sql);
        
        foreach ($params as $key => $value) {
            oci_bind_by_name($stmt, $key, $value);
        }
        
        oci_execute($stmt);
        
        $results = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $results[] = $row;
        }
        
        oci_free_statement($stmt);
        oci_close($conn);
        
        return $results;
    } catch (Exception $e) {
        oci_close($conn);
        return ['error' => $e->getMessage()];
    }
}

// Obtiene conteo de registros de una tabla en Oracle
function countOracleTable($tableName) {
    $result = queryOracle("SELECT COUNT(*) as total FROM $tableName");
    if (isset($result['error'])) {
        return 0;
    }
    return (int)($result[0]['TOTAL'] ?? 0);
}

// Obtiene las tablas de Oracle (para mostrar en el dashboard)
function getOracleTables() {
    $sql = "SELECT table_name FROM user_tables ORDER BY table_name";
    $result = queryOracle($sql);
    if (isset($result['error'])) {
        return [];
    }
    $tables = [];
    foreach ($result as $row) {
        $tables[] = $row['TABLE_NAME'];
    }
    return $tables;
}

// Obtiene estadísticas de las tablas de Oracle
function getOracleTableStats() {
    $sql = "SELECT table_name, num_rows FROM user_tables ORDER BY table_name";
    $result = queryOracle($sql);
    if (isset($result['error'])) {
        return [];
    }
    $stats = [];
    foreach ($result as $row) {
        $stats[$row['TABLE_NAME']] = (int)($row['NUM_ROWS'] ?? 0);
    }
    return $stats;
}

// Variable global para compatibilidad
$conn = $conn_mysql;

// REGISTRAR ESTADO INICIAL
if (isMySQLConnected() && $conn_mysql !== null) {
    try {
        $check = $conn_mysql->query("SHOW TABLES LIKE 'logs_replicacion'");
        if ($check && $check->num_rows > 0) {
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
            }
        }
    } catch (Exception $e) {}
}
?>