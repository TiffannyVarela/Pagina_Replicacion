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

    //Verificar la conexion a Oracle, si no esta disponible se devuelve un array vacio
    if (!isOracleConnected()) {
        echo json_encode([]);
        exit;
    }

    //Consulta Bitacora
    $checkBitacora = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'BITACORA'");

    if (isset($checkBitacora['error']) || empty($checkBitacora)) {
        // Si no existe BITACORA, intentar con LOGS_REPLICACION_ORACLE
        $checkLogs = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'LOGS_REPLICACION_ORACLE'");
        
        if (!isset($checkLogs['error']) && !empty($checkLogs)) {
            //Usar LOGS_REPLICACION_ORACLE
            $sql = "SELECT 
                        id, 
                        evento as tabla_afectada, 
                        descripcion as tipo_operacion, 
                        fecha as fecha_hora, 
                        evento as estado_replicacion
                    FROM LOGS_REPLICACION_ORACLE 
                    ORDER BY fecha DESC 
                    FETCH FIRST 10 ROWS ONLY";
            
            $result = queryOracle($sql);
            
            if (!isset($result['error']) && !empty($result)) {
                $data = [];
                foreach ($result as $row) {
                    $data[] = [
                        'id' => $row['ID'] ?? null,
                        'tabla_afectada' => $row['TABLA_AFECTADA'] ?? null,
                        'tipo_operacion' => $row['TIPO_OPERACION'] ?? null,
                        'fecha_hora' => $row['FECHA_HORA'] ?? null,
                        'estado_replicacion' => $row['ESTADO_REPLICACION'] ?? null
                    ];
                }
                echo json_encode($data);
                exit;
            }
        }
        
        echo json_encode([]);
        exit;
    }

    $sql = "SELECT 
            id_registro as ID,
            tabla_afectada as TABLA_AFECTADA,
            CASE tipo_operacion 
                WHEN 'I' THEN 'INSERT'
                WHEN 'U' THEN 'UPDATE'
                WHEN 'D' THEN 'DELETE'
                ELSE tipo_operacion
            END as TIPO_OPERACION,
            TO_CHAR(fecha_hora, 'YYYY-MM-DD HH24:MI:SS') as FECHA_HORA,
            estado_replicacion as ESTADO_REPLICACION
        FROM BITACORA 
        ORDER BY fecha_hora DESC 
        FETCH FIRST 10 ROWS ONLY";

    $result = queryOracle($sql);

    if (isset($result['error']) || empty($result)) {
        echo json_encode([]);
        exit;
    }

    // Procesar los resultados
    $data = [];
    foreach ($result as $row) {
        $data[] = [
            'id' => $row['ID'] ?? null,
            'tabla_afectada' => $row['TABLA_AFECTADA'] ?? null,
            'tipo_operacion' => $row['TIPO_OPERACION'] ?? null,
            'fecha_hora' => $row['FECHA_HORA'] ?? null,
            'estado_replicacion' => $row['ESTADO_REPLICACION'] ?? null
        ];
    }

    echo json_encode($data);
    ?>