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

    //Consulta para obtener los ultimos 10 eventos de la tabla BITACORA de Oracle
    $sql = "SELECT id, tabla_afectada, tipo_operacion, fecha_hora, estado_replicacion 
            FROM BITACORA 
            ORDER BY fecha_hora DESC 
            FETCH FIRST 10 ROWS ONLY";

    $result = queryOracle($sql);

    if (isset($result['error'])) {
        echo json_encode([]);
        exit;
    }

    //Procesar los resultados
    $data = [];
    foreach ($result as $row) {
        //Mapear los nombres de columnas de Oracle a nombres consistentes
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