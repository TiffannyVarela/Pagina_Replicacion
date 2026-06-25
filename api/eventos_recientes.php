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

//Verificar la conexion a MySQl, si no esta disponible se devuelve un array vacio
if (!isMySQLConnected()) {
    echo json_encode([]);
    exit;
}

//Parametros de filtrado (por nombre de tabla o por estado de replicacion)
$tabla = isset($_GET['tabla']) ? trim($_GET['tabla']) : '';
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';

//Se seleccionan los campos principales de la bitacora
$sql = "SELECT id, tabla_afectada, tipo_operacion, fecha_hora, estado_replicacion 
        FROM bitacora_replicacion 
        WHERE 1=1";

//params es un array con los valores a filtrar
$params = [];
//types es una cadena que indica los tipos de datos
$types = "";


//Filtro por tabla afectada. Si se proporciona el parametro tabla se incluye a la condicon de la consulta del SQL
if (!empty($tabla)) {
    $sql .= " AND tabla_afectada = ?";
    $params[] = $tabla;
    $types .= "s"; //'s' indica tipo string
}

//Filtro por estado de replicacion. Si se proporciona el parametro estado se incluye a la condicon de la consulta del SQL
if (!empty($estado)) {
    $sql .= " AND estado_replicacion = ?";
    $params[] = $estado;
    $types .= "s";//'s' indica tipo string
}


//Ordenar desde los registros mas recientes al inicio y solo devuelve los ultimos 10 eventos
$sql .= " ORDER BY fecha_hora DESC LIMIT 10";

//Verificar conexion MySQL. Si hay error, se devuelve el mensaje en formato JSON.
$conn = getMySQLConnection();
if (!$conn) {
    echo json_encode(['error' => 'MySQL no disponible']);
    exit;
}

try {
    //Realizar la consulta con parametros
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            //Recorrer todas las filas de resultados y luego almacenarlos en un array para luego convertirlos a JSON
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
            //Devolver datos en formato JSON
            echo json_encode($data);
        } else {
            //Si falla, devolver array vacio
            echo json_encode([]);
        }
    } else {
        //Realizar la consulta sin parametros
        $result = $conn->query($sql);
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
    }
} catch (Exception $e) {
    ///Si ocurre un erros se registra en el log del sistema y se devuelve un array vacio
    logError("Error en eventos_recientes: " . $e->getMessage(), 'ERROR');
    echo json_encode([]);
}

//Se cierra la conexion a MySQL
$conn->close();
?>