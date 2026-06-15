<?php
// CONEXIÓN MYSQL
$host = 'mysql-11811146-unitec-11811146.d.aivencloud.com';
$port = '20628';
$database = 'naviera_replica';
$username = 'replicador';
$password = 'replicacion-2026';

$conn_mysql = null;
$mysql_error = null;

try {
    $conn_mysql = @new mysqli($host, $username, $password, $database, $port);
    
    if ($conn_mysql->connect_error) {
        $mysql_error = "Error conexión MySQL: " . $conn_mysql->connect_error;
        error_log($mysql_error);
        $conn_mysql = null;
    } else {
        $conn_mysql->set_charset("utf8mb4");
    }
} catch (Exception $e) {
    $mysql_error = "Excepción MySQL: " . $e->getMessage();
    error_log($mysql_error);
    $conn_mysql = null;
}

// FUNCIONES PARA VERIFICAR ESTADO

function isMySQLConnected() {
    global $conn_mysql;
    return ($conn_mysql !== null && @$conn_mysql->ping());
}

function isOracleConnected() {
    /*Esta función solo intenta conectar cuando se llama
    No guarda conexión persistente para evitar errores*/
    $oracle_host = 'globalshippingdb.ct2q4262uyl7.us-east-2.rds.amazonaws.com';
    $oracle_port = '1521';
    $oracle_service = 'DATABASE';
    $oracle_username = 'admin';
    $oracle_password = 'Holamundo_504';
    
    if (!extension_loaded('oci8')) {
        return false;
    }
    
    try {
        $oracle_tns = "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=$oracle_host)(PORT=$oracle_port))(CONNECT_DATA=(SERVICE_NAME=$oracle_service)))";
        $conn = @oci_connect($oracle_username, $oracle_password, $oracle_tns, 'AL32UTF8');
        if ($conn) {
            oci_close($conn);
            return true;
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

function getMySQLConnection() {
    global $conn_mysql, $mysql_error;
    if (!$conn_mysql) {
        error_log("MySQL no disponible: " . ($mysql_error ?? 'Desconocido'));
    }
    return $conn_mysql;
}

// Variable global para compatibilidad
$conn = $conn_mysql;
?>