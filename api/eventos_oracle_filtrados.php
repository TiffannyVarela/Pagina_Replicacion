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

    //Verificar conexion a Oracle
    if (!isOracleConnected()) {
        echo json_encode([]);
        exit;
    }

    //Parametros de filtrado
    $tabla = isset($_GET['tabla']) ? trim($_GET['tabla']) : '';
    $estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';

    //INTENTAR CON LOGS_REPLICACION_ORACLE
    $checkLogs = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'LOGS_REPLICACION_ORACLE'");

    if (!isset($checkLogs['error']) && !empty($checkLogs)) {
        //Usar LOGS_REPLICACION_ORACLE
        $sql = "SELECT id, evento as tabla_afectada, descripcion as tipo_operacion, fecha as fecha_hora, evento as estado_replicacion 
                FROM LOGS_REPLICACION_ORACLE 
                WHERE 1=1";
        
        if (!empty($tabla)) {
            $sql .= " AND evento LIKE '%" . strtoupper($tabla) . "%'";
        }
        
        if (!empty($estado)) {
            $sql .= " AND UPPER(evento) LIKE '%" . strtoupper($estado) . "%'";
        }
        
        $sql .= " ORDER BY fecha DESC FETCH FIRST 10 ROWS ONLY";
        
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

    //FALLBACK: INTENTAR CON BITACORA
    $checkBitacora = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'BITACORA'");

    if (!isset($checkBitacora['error']) && !empty($checkBitacora)) {
        $sql = "SELECT id, tabla_afectada, tipo_operacion, fecha_hora, estado_replicacion 
                FROM BITACORA 
                WHERE 1=1";
        
        if (!empty($tabla)) {
            $sql .= " AND UPPER(tabla_afectada) LIKE '%" . strtoupper($tabla) . "%'";
        }
        
        if (!empty($estado)) {
            $sql .= " AND UPPER(estado_replicacion) = '" . strtoupper($estado) . "'";
        }
        
        $sql .= " ORDER BY fecha_hora DESC FETCH FIRST 10 ROWS ONLY";
        
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

    //Si no hay datos, devolver array vacio
    echo json_encode([]);
    ?>