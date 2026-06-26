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

    //Verificar conexión a Oracle. Comprueba si la base de datos Oracle esta disponible antes de procesar cualquier solicitud. Si no esta disponible, devuelve un error inmediato y termina la ejecucion.
    if (!isOracleConnected()) {
        //Si Oracle no está disponible, devolver respuesta de error
        echo json_encode([
            //Mensaje de error
            'error' => 'Oracle no disponible',
            //Estado de conexion
            'oracle_online' => false,
            //Lista vacia de tablas
            'tables' => [],
            //Estadisticas vacias
            'table_stats' => [],
            //Fecha y hora de al consulta
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    //Obtener accion solicitada. Lee el parametro 'action' de la URL (GET) para determinar que operacion debe realizar la API. Si no se especifica, por defecto usa 'summary' (summary, tables, stats, table_data).
    $action = $_GET['action'] ?? 'summary';

    switch ($action) {
        //Lista todas las tablas disponibles en la base de datos Oracle
        case 'tables':
            //Obtener lista de tablas de Oracle
            $tables = getOracleTables();
            echo json_encode([
                //Indicador de exito
                'success' => true,
                //Array con nombres de tablas
                'tables' => $tables,
                //Número total de tablas
                'count' => count($tables),
                //Fecha y hora de al consulta
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'stats':
            //Obtiene estadisticas detalladas de todas las tablas
            $stats = getOracleTableStats();
            echo json_encode([
                //Indicador de exito
                'success' => true,
                //Array con estadisticas
                'stats' => $stats,
                //Fecha y hora de al consulta
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        //Obtiene los datos de una tabla especifica con limite de registros    
        case 'table_data':
            //Nombre de la tabla a consultar
            $table = $_GET['table'] ?? '';
            //Límite de registros
            $limit = (int)($_GET['limit'] ?? 10);
            //Verificar que se haya especificado un nombre de tabla
            if (empty($table)) {
                //Si no se proporciona tabla, devolver error
                echo json_encode(['error' => 'Nombre de tabla requerido']);
                exit;
            }
            
            //Validar que la tabla existe
            $tables = getOracleTables();
            if (!in_array(strtoupper($table), array_map('strtoupper', $tables))) {
                //Si la tabla no existe, devolver error
                echo json_encode(['error' => 'Tabla no encontrada: ' . $table]);
                exit;
            }
            //Consulta para obtener solo los primeros N registros
            $sql = "SELECT * FROM $table WHERE ROWNUM <= $limit";
            $data = queryOracle($sql);
            //Si devuelve un array con 'error', la consulta fallo
            if (isset($data['error'])) {
                echo json_encode(['error' => $data['error']]);
                exit;
            }
            //Respuesta exitosa con datos de la tabla
            echo json_encode([
                //Indicador de exito
                'success' => true,
                //Nombre de la tabla consultada
                'table' => $table,
                //Datos obtenidos
                'data' => $data,
                //Número de registros devueltos
                'count' => count($data),
                //Fecha y hora de al consulta
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'summary':
        default:
            //Resumen general de Oracle
            $tables = getOracleTables();
            $stats = getOracleTableStats();
            //Crear un array con el nombre y numero de filas para cada tabla existente
            $table_info = [];
            foreach ($tables as $table) {
                $table_info[] = [
                    //Nombre de la tabla
                    'name' => $table,
                    //Número de filas (0 si no tiene estadisticas)
                    'rows' => $stats[$table] ?? 0
                ];
            }
            //Devuelve informacion recopilada de toda la base de datos
            echo json_encode([
                //Indicador de exito
                'success' => true,
                //Confirmacion de conexion
                'oracle_online' => true,
                //Número total de tablas
                'total_tables' => count($tables),
                //Suma total de registros en todas las tablas
                'total_rows' => array_sum($stats),
                //Informacion detallada por tabla
                'tables' => $table_info,
                //Fecha y hora de al consulta
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
    }
    ?>