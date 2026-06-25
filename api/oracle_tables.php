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

// Verificar conexión a Oracle. Comprueba si la base de datos Oracle esta disponible antes de procesar cualquier solicitud. Si no esta disponible, devuelve un error inmediato y termina la ejecucion.
if (!isOracleConnected()) {
    // Si Oracle no está disponible, devolver respuesta de error
    echo json_encode([
        // Mensaje de error
        'error' => 'Oracle no disponible',
        // Estado de conexion
        'oracle_online' => false,
        // Lista vacia de tablas
        'tables' => [],
        // Estadisticas vacias
        'table_stats' => [],
        //Fecha y hora de al consulta
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

//Lee el parametro 'action' de la URL (GET) para determinar que operacion debe realizar la API. Si no se especifica, por defecto usa 'list'.
$action = $_GET['action'] ?? 'list';

switch ($action) {
    //Lista todas las tablas disponibles en la base de datos Oracle junto con el numero de registros que contiene cada una.
    case 'list':
        //Obtener lista de nombres de tablas de Oracle
        $tables = getOracleTables();
        //Obtener estadisticas de las tablas (numero de filas)
        $stats = getOracleTableStats();
        //Crear un array con el nombre y numero de filas para cada tabla existente
        $result = [];
        foreach ($tables as $table) {
            $result[] = [
                'name' => $table,
                'rows' => $stats[$table] ?? 0
            ];
        }
        //Devuelve informacion de todas las tablas con sus conteos
        echo json_encode([
            //Indicador de exito
            'success' => true,
            //Array con informacion de tablas
            'tables' => $result,
            //Numero total de tablas
            'total' => count($result),
            //Fecha y hora de al consulta
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
    //Obtiene los datos de una tabla especifica con limite de registros    
    case 'data':
        // Obtener parametros de la URL
        //Nombre de la tabla a consultar
        $table = $_GET['table'] ?? '';
        //Limite de registros
        $limit = (int)($_GET['limit'] ?? 10);
        //Verificar que se haya especificado un nombre de tabla
        if (empty($table)) {
            //Si no se proporciona tabla, devolver error
            echo json_encode(['error' => 'Nombre de tabla requerido']);
            exit;
        }
        
        // Validar que la tabla existe
        $tables = getOracleTables();
        if (!in_array(strtoupper($table), array_map('strtoupper', $tables))) {
            //Si la tabla no existe, devolver error
            echo json_encode(['error' => 'Tabla no encontrada: ' . $table]);
            exit;
        }
        //Consulta SELECT con limitacion de filas para obtener solo los primeros N registros
        $sql = "SELECT * FROM $table WHERE ROWNUM <= $limit";
        $data = queryOracle($sql);
        //Si devuelve un array con 'error', la consulta fallo
        if (isset($data['error'])) {
            echo json_encode(['error' => $data['error']]);
            exit;
        }
        
        // Obtener nombres de columnas
        $columns = [];
        if (!empty($data)) {
            // array_keys() obtiene las claves (nombres de columnas) del primer registro
            $columns = array_keys($data[0]);
        }
        
        echo json_encode([
            //Indicador de exito
            'success' => true,
            //Nombre de la tabla consultada
            'table' => $table,
            //Nombres de las columnas
            'columns' => $columns,
            //Datos obtenidos
            'data' => $data,
            //Numero de registros devueltos
            'count' => count($data),
            //Fecha y hora de al consulta
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
    //Si la acción solicitada no coincide con ningún caso, se devuelve un mensaje de error     
    default:
        echo json_encode(['error' => 'Acción no válida']);
}
?>