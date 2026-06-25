<?php

/*
CONFIGURACION INICIAL
Incluir el archivo de configuracion de la base de datos el cual contiene la variable $conn que maneja la conexion
*/
require_once '../config/db.php';

/*
CONSULTA SQL
Consulta para obtener los ultimos 20 registros con estado ERROR ordenados por fecha de creacion descendiente
*/
$sql = "SELECT id, tabla_afectada, intentos_replicacion, mensaje_error, created_at 
        FROM bitacora_replicacion 
        WHERE estado_replicacion = 'ERROR' 
        ORDER BY created_at DESC LIMIT 20";

/*
EJECUCION DE LA CONSULTA
*/
$result = $conn->query($sql);

/*
PROCESAMIENTO DE DATOS
Inicializa un arreglo vacio para almacenar los datos
*/
$data = [];
/*
Recorrer las filas del resultado de la consulta 
fetch_assoc() convierte la fila en un arreglo asociativo
*/
while($row = $result->fetch_assoc()) {
    //Agrega cada registro al arreglo de datos
    $data[] = $row;
}

/*
RESPUESTA EN FORMATO JSON
*/
header('Content-Type: application/json');
//Convierte el arreglo a formato JSON y lo envia como respuesta
//json_encode() convierte el arreglo PHP a una cadena JSON
echo json_encode($data);
?>