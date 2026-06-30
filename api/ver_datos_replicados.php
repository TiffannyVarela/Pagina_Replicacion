<?php
/*
VER DATOS REPLICADOS EN MYSQL
*/
require_once '../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isMySQLConnected()) {
    echo json_encode(['error' => 'MySQL no disponible']);
    exit;
}

$conn_mysql = getMySQLConnection();

$resultado = [
    'timestamp' => date('Y-m-d H:i:s'),
    'tablas' => []
];

// Ver todas las tablas principales
$tablas = [
    'tbl_clientes_logisticos',
    'centros_logisticos',
    'unidades_transporte',
    'contenedores',
    'servicios_logisticos',
    'stock_carga',
    'ordenes_envio',
    'tbl_facturas_logisticas',
    'factura_servicios',
    'movimientos_carga'
];

foreach ($tablas as $tabla) {
    $count = 0;
    $sample = [];
    
    $result = $conn_mysql->query("SELECT COUNT(*) as total FROM $tabla");
    if ($result) {
        $row = $result->fetch_assoc();
        $count = (int)$row['total'];
    }
    
    if ($count > 0) {
        $result2 = $conn_mysql->query("SELECT * FROM $tabla LIMIT 3");
        if ($result2) {
            while ($row = $result2->fetch_assoc()) {
                $sample[] = $row;
            }
        }
    }
    
    $resultado['tablas'][$tabla] = [
        'total' => $count,
        'sample' => $sample
    ];
}

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$conn_mysql->close();
?>