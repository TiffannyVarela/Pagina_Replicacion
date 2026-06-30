<?php
/*
VER DATOS EN TABLAS ORACLE
*/
require_once '../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isOracleConnected()) {
    echo json_encode(['error' => 'Oracle no disponible']);
    exit;
}

$resultado = [];

// Tablas a verificar
$tablas = [
    'CLIENTE_NAVIERA' => 'Clientes',
    'TERMINAL_PORTUARIA' => 'Terminales',
    'BUQUE_OPERACION' => 'Buques',
    'CONTENEDOR_NAVIERO' => 'Contenedores',
    'SERVICIO_PORTUARIO' => 'Servicios',
    'INVENTARIO_CARGA' => 'Inventario',
    'EMBARQUE_MARITIMO' => 'Embarques',
    'FACTURACION_EMBARQUE' => 'Facturas',
    'DETALLE_FACTURA_SERVICIO' => 'Detalle Factura',
    'TRANSFERENCIA_CARGA' => 'Transferencias'
];

foreach ($tablas as $tabla => $nombre) {
    $count = countOracleTable($tabla);
    $resultado[$tabla] = [
        'nombre' => $nombre,
        'total' => $count
    ];
}

// Ver registros PENDIENTES en BITACORA
$sql = "SELECT 
            tabla_afectada as TABLA,
            COUNT(*) as TOTAL
        FROM BITACORA 
        WHERE UPPER(estado_replicacion) = 'PENDIENTE'
        GROUP BY tabla_afectada
        ORDER BY tabla_afectada";

$result = queryOracle($sql);

if (!isset($result['error']) && !empty($result)) {
    $resultado['pendientes_por_tabla'] = $result;
}

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>