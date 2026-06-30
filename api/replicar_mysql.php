<?php
set_time_limit(300);

//CONFIGURACION INICIAL
require_once '../config/db.php';

//HEADERS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

//VALIDACION: Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Metodo no permitido. Use POST']);
    exit;
}

//VALIDACION: Conexiones
if (!isMySQLConnected()) {
    echo json_encode(['error' => 'MySQL no disponible']);
    exit;
}

if (!isOracleConnected()) {
    echo json_encode(['error' => 'Oracle no disponible']);
    exit;
}

//OBTENER CONEXIONES
$conn_mysql = getMySQLConnection();
$conn_oracle = getOracleConnection();

//ORDEN DE PROCESAMIENTO (PADRES → HIJOS)
$ordenTablas = [
    'CLIENTE_NAVIERA' => 'tbl_clientes_logisticos',
    'TERMINAL_PORTUARIA' => 'centros_logisticos',
    'BUQUE_OPERACION' => 'unidades_transporte',
    'CONTENEDOR_NAVIERO' => 'contenedores',
    'SERVICIO_PORTUARIO' => 'servicios_logisticos',
    'INVENTARIO_CARGA' => 'stock_carga',
    'EMBARQUE_MARITIMO' => 'ordenes_envio',
    'FACTURACION_EMBARQUE' => 'tbl_facturas_logisticas',
    'DETALLE_FACTURA_SERVICIO' => 'factura_servicios',
    'TRANSFERENCIA_CARGA' => 'movimientos_carga'
];

//MAPEO DE CAMPOS: Oracle (minúsculas) → MySQL
$campoMapping = [
    'CLIENTE_NAVIERA' => [
        'cliente_id' => ['id_cliente'],
        'nombre_contacto' => ['nombre_cliente'],
        'telefono_contacto' => ['telefono'],
        'email_cliente' => ['correo'],
        'pais' => ['pais_origen'],
        'fecha_alta' => ['fecha_registro']
    ],
    'TERMINAL_PORTUARIA' => [
        'terminal_id' => ['id_terminal'],
        'nombre_centro' => ['nombre_terminal'],
        'municipio' => ['ciudad'],
        'pais_operacion' => ['pais'],
        'categoria' => ['tipo_terminal'],
        'capacidad_maxima' => ['capacidad_contenedores']
    ],
    'BUQUE_OPERACION' => [
        'transporte_id' => ['id_buque'],
        'nombre_unidad' => ['nombre_buque'],
        'capacidad_carga' => ['capacidad_toneladas'],
        'pais_bandera' => ['bandera'],
        'habilitado' => ['activo']
    ],
    'CONTENEDOR_NAVIERO' => [
        'contenedor_id' => ['id_contenedor'],
        'codigo' => ['codigo_contenedor'],
        'categoria' => ['tipo_contenedor'],
        'capacidad_kg' => ['capacidad_kg'],
        'requiere_refrigeracion' => ['refrigerado'],
        'estado' => ['estado_operacion']
    ],
    'INVENTARIO_CARGA' => [
        'inventario_id' => ['id_inventario'],
        'contenedor_id' => ['id_contenedor'],
        'centro_id' => ['id_terminal'],
        'peso_actual' => ['peso_disponible'],
        'fecha_modificacion' => ['fecha_actualizacion']
    ],
    'EMBARQUE_MARITIMO' => [
        'embarque_id' => ['id_embarque'],
        'cliente_id' => ['id_cliente'],
        'transporte_id' => ['id_buque'],
        'contenedor_id' => ['id_contenedor'],
        'fecha_envio' => ['fecha_salida'],
        'estatus' => ['estado_embarque'],
        'nivel_prioridad' => ['prioridad']
    ],
    'FACTURACION_EMBARQUE' => [
        'factura_id' => ['id_factura'],
        'embarque_id' => ['id_embarque'],
        'fecha' => ['fecha_emision'],
        'monto_total' => ['total_facturado'],
        'comentarios' => ['observaciones']
    ],
    'SERVICIO_PORTUARIO' => [
        'servicio_id' => ['id_servicio'],
        'descripcion_servicio' => ['descripcion_servicio'],
        'costo_base' => ['costo_base'],
        'requiere_autorizacion' => ['requiere_autorizacion'],
        'categoria_servicio' => ['tipo_servicio']
    ],
    'DETALLE_FACTURA_SERVICIO' => [
        'factura_id' => ['id_factura'],
        'servicio_id' => ['id_servicio'],
        'cantidad' => ['cantidad'],
        'subtotal' => ['subtotal'],
        'incluye_impuesto' => ['impuesto_aplicado']
    ],
    'TRANSFERENCIA_CARGA' => [
        'movimiento_id' => ['id_transferencia'],
        'contenedor_id' => ['id_contenedor'],
        'centro_origen' => ['terminal_origen'],
        'centro_destino' => ['terminal_destino'],
        'peso_movido' => ['peso_transferido'],
        'fecha_movimiento' => ['fecha_transferencia']
    ]
];


//FUNCIONES DE MAPEO


function mapearCategoriaTerminal($categoria) {
    $categoria = strtoupper(trim($categoria));
    $map = [
        'CONTENEDORES' => 'Puerto',
        'MIXTO' => 'Puerto',
        'GRANEL' => 'Puerto',
        'CARGA' => 'Puerto',
        'PASAJEROS' => 'Puerto',
        'BODEGA' => 'Bodega',
        'ADUANA' => 'Aduana'
    ];
    return $map[$categoria] ?? 'Bodega';
}

function mapearCategoriaServicio($categoria) {
    $categoria = strtoupper(trim($categoria));
    $map = [
        'CARGA' => 'Carga',
        'DESCARGA' => 'Descarga',
        'INSPECCION' => 'Inspección',
        'ADUANA' => 'Aduana',
        'INSPECCIÓN' => 'Inspección'
    ];
    return $map[$categoria] ?? 'Carga';
}

function mapearEstadoEmbarque($estado) {
    $estado = strtoupper(trim($estado));
    $map = [
        'PENDIENTE' => 'Pendiente',
        'EN_TRANSITO' => 'En tránsito',
        'ENTREGADO' => 'Entregado',
        'CANCELADO' => 'Cancelado'
    ];
    return $map[$estado] ?? 'Pendiente';
}

function mapearEstadoContenedor($estado) {
    $estado = strtoupper(trim($estado));
    $map = [
        'DISPONIBLE' => 'Disponible',
        'EN_TRANSITO' => 'Disponible',
        'RETENIDO' => 'Retenido',
        'MANTENIMIENTO' => 'Mantenimiento',
        'INACTIVO' => 'Inactivo'
    ];
    return $map[$estado] ?? 'Disponible';
}


//FUNCIONES AUXILIARES


function leerCLOB($clob) {
    if ($clob === null) return '{}';
    if (is_string($clob)) return $clob;
    if (is_object($clob) && method_exists($clob, 'load')) {
        try { return $clob->load() ?: '{}'; } catch (Exception $e) { return '{}'; }
    }
    return '{}';
}

function limpiarJSON($jsonStr) {
    $jsonStr = str_replace(["\r", "\n", "\t"], ' ', $jsonStr);
    $jsonStr = preg_replace('/\s+/', ' ', $jsonStr);
    $jsonStr = preg_replace('/\{\s+/', '{', $jsonStr);
    $jsonStr = preg_replace('/\s+\}/', '}', $jsonStr);
    $jsonStr = preg_replace('/\[\s+/', '[', $jsonStr);
    $jsonStr = preg_replace('/\s+\]/', ']', $jsonStr);
    $jsonStr = preg_replace('/,\s+/', ',', $jsonStr);
    $jsonStr = preg_replace('/:\s+/', ':', $jsonStr);
    return trim($jsonStr);
}

function obtenerPendientesPorTabla($conn_oracle, $tabla_oracle) {
    $resultado = [];
    
    $sql = "SELECT 
                id_registro as ID,
                tabla_afectada as TABLA_AFECTADA,
                tipo_operacion as TIPO_OPERACION,
                datos_json as DATOS_JSON,
                version_registro as VERSION_REGISTRO
            FROM BITACORA 
            WHERE UPPER(tabla_afectada) = '" . strtoupper($tabla_oracle) . "'
              AND UPPER(estado_replicacion) = 'PENDIENTE'
            ORDER BY id_registro ASC
            FETCH FIRST 30 ROWS ONLY";
    
    $result = queryOracle($sql);
    
    if (!isset($result['error']) && !empty($result)) {
        foreach ($result as $row) {
            $datos_json_raw = isset($row['DATOS_JSON']) ? leerCLOB($row['DATOS_JSON']) : '{}';
            $resultado[] = [
                'ID' => $row['ID'] ?? null,
                'TABLA_AFECTADA' => $row['TABLA_AFECTADA'] ?? null,
                'TIPO_OPERACION' => $row['TIPO_OPERACION'] ?? 'I',
                'DATOS_JSON' => $datos_json_raw,
                'VERSION_REGISTRO' => $row['VERSION_REGISTRO'] ?? 1
            ];
        }
    }
    return $resultado;
}

function existeRegistroMySQL($conn, $tabla, $id_campo, $id_valor) {
    if (empty($id_campo) || empty($id_valor)) {
        return false;
    }
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM $tabla WHERE $id_campo = ?");
    $stmt->bind_param("i", $id_valor);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total = (int)($row['total'] ?? 0);
    $stmt->close();
    
    return $total > 0;
}

function obtenerIdFieldMySQL($tabla) {
    $idFields = [
        'tbl_clientes_logisticos' => 'cliente_id',
        'centros_logisticos' => 'terminal_id',
        'unidades_transporte' => 'transporte_id',
        'contenedores' => 'contenedor_id',
        'stock_carga' => 'inventario_id',
        'ordenes_envio' => 'embarque_id',
        'tbl_facturas_logisticas' => 'factura_id',
        'servicios_logisticos' => 'servicio_id',
        'factura_servicios' => 'factura_id',
        'movimientos_carga' => 'movimiento_id'
    ];
    return $idFields[$tabla] ?? null;
}


//FUNCION PARA REPLICAR REGISTROS FALTANTES DIRECTAMENTE


function replicarFaltantesDirecto($conn_mysql, $conn_oracle, $oracleTable, $mysqlTable, $idField, $mysqlIdField) {
    //Obtener IDs de Oracle
    $sql = "SELECT $idField FROM $oracleTable";
    $result = queryOracle($sql);
    
    if (isset($result['error']) || empty($result)) {
        return ['replicados' => 0, 'errores' => []];
    }
    
    $oracleIds = [];
    foreach ($result as $row) {
        $oracleIds[] = $row[$idField];
    }
    
    if (empty($oracleIds)) {
        return ['replicados' => 0, 'errores' => []];
    }
    
    //Obtener IDs de MySQL
    $mysqlIds = [];
    $sql = "SELECT $mysqlIdField FROM $mysqlTable";
    $result2 = $conn_mysql->query($sql);
    if ($result2) {
        while ($row = $result2->fetch_assoc()) {
            $mysqlIds[] = $row[$mysqlIdField];
        }
    }
    
    //IDs que faltan en MySQL
    $faltantes = array_diff($oracleIds, $mysqlIds);
    
    if (empty($faltantes)) {
        return ['replicados' => 0, 'errores' => []];
    }
    
    error_log("   ℹ️ Faltan " . count($faltantes) . " registros en $mysqlTable, replicando desde Oracle...");
    
    //Obtener datos completos de Oracle para los IDs faltantes
    $idsStr = implode(',', $faltantes);
    $sql = "SELECT * FROM $oracleTable WHERE $idField IN ($idsStr)";
    $result = queryOracle($sql);
    
    if (isset($result['error']) || empty($result)) {
        return ['replicados' => 0, 'errores' => ['Error al obtener datos de Oracle']];
    }
    
    $replicados = 0;
    $errores = [];
    
    if ($mysqlTable == 'movimientos_carga') {
        error_log("   ⚠️ Deshabilitando triggers para movimientos_carga...");
        $conn_mysql->query("SET @DISABLE_TRIGGERS = 1");
    }
    
    $conn_mysql->query("SET FOREIGN_KEY_CHECKS = 0");
    $conn_mysql->query("SET UNIQUE_CHECKS = 0");
    
    foreach ($result as $row) {
        $datos_transformados = transformarDatos($oracleTable, $row, 'INSERT');
        $mysql_ok = replicarRegistroMySQL($conn_mysql, $mysqlTable, $datos_transformados);
        
        if ($mysql_ok) {
            $replicados++;
        } else {
            $errores[] = "Error al insertar ID: " . ($row[$idField] ?? 'unknown');
        }
    }
    
    $conn_mysql->query("SET FOREIGN_KEY_CHECKS = 1");
    $conn_mysql->query("SET UNIQUE_CHECKS = 1");
    
    if ($mysqlTable == 'movimientos_carga') {
        error_log("   ✅ Reactivando triggers para movimientos_carga...");
        $conn_mysql->query("SET @DISABLE_TRIGGERS = 0");
    }
    
    return ['replicados' => $replicados, 'errores' => $errores];
}


//TRANSFORMAR DATOS


function transformarDatos($tabla_oracle, $datos, $tipo) {
    global $campoMapping;
    $resultado = [];
    $hoy = date('Y-m-d H:i:s');
    $mapaCampos = $campoMapping[$tabla_oracle] ?? [];
    
    foreach ($mapaCampos as $mysqlField => $oracleFields) {
        $value = null;
        foreach ((array)$oracleFields as $oracleField) {
            if (isset($datos[$oracleField])) {
                $value = $datos[$oracleField];
                break;
            }
            $upperField = strtoupper($oracleField);
            if (isset($datos[$upperField])) {
                $value = $datos[$upperField];
                break;
            }
        }
        if ($value !== null) {
            if (in_array($mysqlField, ['requiere_refrigeracion', 'habilitado', 'requiere_autorizacion', 'incluye_impuesto'])) {
                $value = ($value == 'S' || $value == '1' || $value === true || $value === 'TRUE' || strtoupper($value) == 'S') ? 1 : 0;
            }
            if (in_array($mysqlField, ['pais', 'pais_operacion', 'pais_bandera'])) {
                $value = convertirCodigoPaisANombre($value);
            }
            if (in_array($mysqlField, ['fecha_alta', 'fecha_modificacion', 'fecha_movimiento', 'fecha_envio', 'fecha'])) {
                $value = formatearFechaMySQL($value);
            }
            $resultado[$mysqlField] = $value;
        }
    }
    
    
    //VALORES POR DEFECTO POR TABLA (SOLO LAS PRINCIPALES)
    
    
    if ($tabla_oracle == 'TERMINAL_PORTUARIA') {
        if (isset($datos['id_terminal']) && $datos['id_terminal'] > 0) {
            $resultado['terminal_id'] = (int)$datos['id_terminal'];
        }
        if (empty($resultado['nombre_centro']) && isset($datos['nombre_terminal'])) {
            $resultado['nombre_centro'] = $datos['nombre_terminal'];
        }
        if (empty($resultado['nombre_centro'])) {
            $resultado['nombre_centro'] = 'Terminal ' . ($datos['id_terminal'] ?? 0);
        }
        if (empty($resultado['municipio']) && isset($datos['ciudad'])) {
            $resultado['municipio'] = $datos['ciudad'];
        }
        if (empty($resultado['pais_operacion']) && isset($datos['pais'])) {
            $resultado['pais_operacion'] = convertirCodigoPaisANombre($datos['pais']);
        }
        if (!isset($resultado['capacidad_maxima']) && isset($datos['capacidad_contenedores'])) {
            $resultado['capacidad_maxima'] = (int)$datos['capacidad_contenedores'];
        }
        if (!isset($resultado['capacidad_maxima']) || $resultado['capacidad_maxima'] <= 0) {
            $resultado['capacidad_maxima'] = 100;
        }
        if (empty($resultado['categoria']) && isset($datos['tipo_terminal'])) {
            $resultado['categoria'] = mapearCategoriaTerminal($datos['tipo_terminal']);
        }
        if (empty($resultado['categoria'])) {
            $resultado['categoria'] = 'Bodega';
        }
        if (empty($resultado['pais_operacion'])) $resultado['pais_operacion'] = 'N/A';
        if (empty($resultado['municipio'])) $resultado['municipio'] = 'Ciudad no especificada';
    }
    
    if ($tabla_oracle == 'CLIENTE_NAVIERA') {
        if (isset($datos['id_cliente']) && $datos['id_cliente'] > 0) {
            $resultado['cliente_id'] = (int)$datos['id_cliente'];
        }
        if (empty($resultado['nombre_contacto']) && isset($datos['nombre_cliente'])) {
            $resultado['nombre_contacto'] = $datos['nombre_cliente'];
        }
        if (empty($resultado['telefono_contacto']) && isset($datos['telefono'])) {
            $resultado['telefono_contacto'] = $datos['telefono'];
        }
        if (empty($resultado['email_cliente']) && isset($datos['correo'])) {
            $resultado['email_cliente'] = $datos['correo'];
        }
        if (empty($resultado['pais']) && isset($datos['pais_origen'])) {
            $resultado['pais'] = convertirCodigoPaisANombre($datos['pais_origen']);
        }
        if (empty($resultado['fecha_alta']) && isset($datos['fecha_registro'])) {
            $resultado['fecha_alta'] = formatearFechaMySQL($datos['fecha_registro']);
        }
        if (empty($resultado['nombre_contacto'])) {
            $resultado['nombre_contacto'] = 'Cliente ' . ($datos['id_cliente'] ?? 0);
        }
        if (!isset($resultado['fecha_alta']) || empty($resultado['fecha_alta'])) {
            $resultado['fecha_alta'] = $hoy;
        }
        if (empty($resultado['pais'])) $resultado['pais'] = 'N/A';
        if (empty($resultado['telefono_contacto'])) $resultado['telefono_contacto'] = 'N/A';
        if (empty($resultado['email_cliente'])) $resultado['email_cliente'] = 'cliente' . ($datos['id_cliente'] ?? 0) . '@example.com';
    }
    
    if ($tabla_oracle == 'BUQUE_OPERACION') {
        if (isset($datos['id_buque']) && $datos['id_buque'] > 0) {
            $resultado['transporte_id'] = (int)$datos['id_buque'];
        }
        if (empty($resultado['nombre_unidad']) && isset($datos['nombre_buque'])) {
            $resultado['nombre_unidad'] = $datos['nombre_buque'];
        }
        if (empty($resultado['nombre_unidad'])) {
            $resultado['nombre_unidad'] = 'Buque ' . ($datos['id_buque'] ?? 0);
        }
        if (!isset($resultado['capacidad_carga']) && isset($datos['capacidad_toneladas'])) {
            $resultado['capacidad_carga'] = (float)$datos['capacidad_toneladas'];
        }
        if (!isset($resultado['capacidad_carga']) || $resultado['capacidad_carga'] <= 0) {
            $resultado['capacidad_carga'] = 1000;
        }
        if (empty($resultado['pais_bandera']) && isset($datos['bandera'])) {
            $resultado['pais_bandera'] = convertirCodigoPaisANombre($datos['bandera']);
        }
        if (empty($resultado['pais_bandera'])) $resultado['pais_bandera'] = 'N/A';
        if (!isset($resultado['habilitado'])) $resultado['habilitado'] = 1;
    }
    
    if ($tabla_oracle == 'CONTENEDOR_NAVIERO') {
        if (isset($datos['id_contenedor']) && $datos['id_contenedor'] > 0) {
            $resultado['contenedor_id'] = (int)$datos['id_contenedor'];
        }
        if (empty($resultado['codigo']) && isset($datos['codigo_contenedor'])) {
            $resultado['codigo'] = $datos['codigo_contenedor'];
        }
        if (empty($resultado['codigo'])) {
            $resultado['codigo'] = 'CONT-' . str_pad($datos['id_contenedor'] ?? 0, 3, '0', STR_PAD_LEFT);
        }
        
        $refrigerado = $datos['refrigerado'] ?? $datos['REFRIGERADO'] ?? 'N';
        $tipo = $datos['tipo_contenedor'] ?? $datos['TIPO_CONTENEDOR'] ?? '';
        
        if (strtoupper($refrigerado) == 'S' || $refrigerado == '1' || $refrigerado === true) {
            $resultado['categoria'] = 'Refrigerado';
        } elseif (strpos(strtoupper($tipo), 'TANQUE') !== false || strpos(strtoupper($tipo), 'TANK') !== false) {
            $resultado['categoria'] = 'Tanque';
        } else {
            $resultado['categoria'] = 'Seco';
        }
        
        $resultado['requiere_refrigeracion'] = (strtoupper($refrigerado) == 'S' || $refrigerado == '1' || $refrigerado === true) ? 1 : 0;
        
        if ($resultado['requiere_refrigeracion'] == 1) {
            $resultado['categoria'] = 'Refrigerado';
        }
        
        if (!isset($resultado['capacidad_kg']) && isset($datos['capacidad_kg'])) {
            $resultado['capacidad_kg'] = (float)$datos['capacidad_kg'];
        }
        if (!isset($resultado['capacidad_kg']) || $resultado['capacidad_kg'] <= 0) {
            if ($resultado['categoria'] == 'Refrigerado') {
                $resultado['capacidad_kg'] = 28000;
            } elseif ($resultado['categoria'] == 'Tanque') {
                $resultado['capacidad_kg'] = 25000;
            } else {
                $resultado['capacidad_kg'] = 30000;
            }
        }
        
        if (empty($resultado['estado']) && isset($datos['estado_operacion'])) {
            $resultado['estado'] = mapearEstadoContenedor($datos['estado_operacion']);
        }
        if (empty($resultado['estado'])) {
            $resultado['estado'] = 'Disponible';
        }
    }
    
    if ($tabla_oracle == 'SERVICIO_PORTUARIO') {
        if (isset($datos['id_servicio']) && $datos['id_servicio'] > 0) {
            $resultado['servicio_id'] = (int)$datos['id_servicio'];
        }
        if (empty($resultado['descripcion_servicio']) && isset($datos['descripcion_servicio'])) {
            $resultado['descripcion_servicio'] = $datos['descripcion_servicio'];
        }
        if (empty($resultado['descripcion_servicio'])) {
            $resultado['descripcion_servicio'] = 'Servicio ' . ($datos['id_servicio'] ?? 0);
        }
        if (!isset($resultado['costo_base']) && isset($datos['costo_base'])) {
            $resultado['costo_base'] = (float)$datos['costo_base'];
        }
        if (!isset($resultado['costo_base']) || $resultado['costo_base'] < 0) {
            $resultado['costo_base'] = 0;
        }
        if (!isset($resultado['requiere_autorizacion']) && isset($datos['requiere_autorizacion'])) {
            $val = $datos['requiere_autorizacion'];
            $resultado['requiere_autorizacion'] = ($val == 'S' || $val == '1') ? 1 : 0;
        }
        if (empty($resultado['categoria_servicio']) && isset($datos['tipo_servicio'])) {
            $resultado['categoria_servicio'] = mapearCategoriaServicio($datos['tipo_servicio']);
        }
        if (empty($resultado['categoria_servicio'])) {
            $resultado['categoria_servicio'] = 'Carga';
        }
        if (!isset($resultado['requiere_autorizacion'])) $resultado['requiere_autorizacion'] = 0;
    }
    
    if ($tabla_oracle == 'INVENTARIO_CARGA') {
        if (isset($datos['id_inventario']) && $datos['id_inventario'] > 0) {
            $resultado['inventario_id'] = (int)$datos['id_inventario'];
        }
        if (isset($datos['id_contenedor']) && $datos['id_contenedor'] > 0) {
            $resultado['contenedor_id'] = (int)$datos['id_contenedor'];
        }
        if (isset($datos['id_terminal']) && $datos['id_terminal'] > 0) {
            $resultado['centro_id'] = (int)$datos['id_terminal'];
        }
        if (!isset($resultado['peso_actual']) && isset($datos['peso_disponible'])) {
            $resultado['peso_actual'] = (float)$datos['peso_disponible'];
        }
        if (!isset($resultado['peso_actual']) || $resultado['peso_actual'] <= 0) {
            $resultado['peso_actual'] = 1;
        }
        if (empty($resultado['fecha_modificacion']) && isset($datos['fecha_actualizacion'])) {
            $resultado['fecha_modificacion'] = formatearFechaMySQL($datos['fecha_actualizacion']);
        }
        if (!isset($resultado['fecha_modificacion']) || empty($resultado['fecha_modificacion'])) {
            $resultado['fecha_modificacion'] = $hoy;
        }
        if (!isset($resultado['contenedor_id']) || $resultado['contenedor_id'] <= 0) {
            $resultado['contenedor_id'] = $datos['id_contenedor'] ?? 1;
        }
        if (!isset($resultado['centro_id']) || $resultado['centro_id'] <= 0) {
            $resultado['centro_id'] = $datos['id_terminal'] ?? 1;
        }
    }
    
    if ($tabla_oracle == 'EMBARQUE_MARITIMO') {
        if (isset($datos['id_embarque']) && $datos['id_embarque'] > 0) {
            $resultado['embarque_id'] = (int)$datos['id_embarque'];
        }
        if (isset($datos['id_cliente']) && $datos['id_cliente'] > 0) {
            $resultado['cliente_id'] = (int)$datos['id_cliente'];
        }
        if (isset($datos['id_buque']) && $datos['id_buque'] > 0) {
            $resultado['transporte_id'] = (int)$datos['id_buque'];
        }
        if (isset($datos['id_contenedor']) && $datos['id_contenedor'] > 0) {
            $resultado['contenedor_id'] = (int)$datos['id_contenedor'];
        }
        if (!isset($resultado['fecha_envio']) && isset($datos['fecha_salida'])) {
            $resultado['fecha_envio'] = formatearFechaMySQL($datos['fecha_salida']);
        }
        if (empty($resultado['estatus']) && isset($datos['estado_embarque'])) {
            $resultado['estatus'] = mapearEstadoEmbarque($datos['estado_embarque']);
        }
        if (empty($resultado['estatus'])) {
            $resultado['estatus'] = 'Pendiente';
        }
        if (!isset($resultado['nivel_prioridad']) && isset($datos['prioridad'])) {
            $resultado['nivel_prioridad'] = (int)$datos['prioridad'];
        }
        if (!isset($resultado['embarque_id']) || $resultado['embarque_id'] <= 0) {
            $resultado['embarque_id'] = $datos['id_embarque'] ?? 0;
        }
        if (!isset($resultado['cliente_id']) || $resultado['cliente_id'] <= 0) {
            $resultado['cliente_id'] = $datos['id_cliente'] ?? 1;
        }
        if (!isset($resultado['transporte_id']) || $resultado['transporte_id'] <= 0) {
            $resultado['transporte_id'] = $datos['id_buque'] ?? 1;
        }
        if (!isset($resultado['contenedor_id']) || $resultado['contenedor_id'] <= 0) {
            $resultado['contenedor_id'] = $datos['id_contenedor'] ?? 1;
        }
        if (!isset($resultado['fecha_envio']) || empty($resultado['fecha_envio'])) {
            $resultado['fecha_envio'] = $hoy;
        }
        if (empty($resultado['estatus'])) $resultado['estatus'] = 'Pendiente';
        if (!isset($resultado['nivel_prioridad']) || $resultado['nivel_prioridad'] <= 0) {
            $resultado['nivel_prioridad'] = 3;
        }
    }
    
    if ($tabla_oracle == 'FACTURACION_EMBARQUE') {
        if (isset($datos['id_factura']) && $datos['id_factura'] > 0) {
            $resultado['factura_id'] = (int)$datos['id_factura'];
        }
        if (isset($datos['id_embarque']) && $datos['id_embarque'] > 0) {
            $resultado['embarque_id'] = (int)$datos['id_embarque'];
        }
        if (!isset($resultado['fecha']) && isset($datos['fecha_emision'])) {
            $resultado['fecha'] = formatearFechaMySQL($datos['fecha_emision']);
        }
        if (!isset($resultado['monto_total']) && isset($datos['total_facturado'])) {
            $resultado['monto_total'] = (float)$datos['total_facturado'];
        }
        if (empty($resultado['comentarios']) && isset($datos['observaciones'])) {
            $resultado['comentarios'] = $datos['observaciones'];
        }
        if (!isset($resultado['factura_id']) || $resultado['factura_id'] <= 0) {
            $resultado['factura_id'] = $datos['id_factura'] ?? 0;
        }
        if (!isset($resultado['embarque_id']) || $resultado['embarque_id'] <= 0) {
            $resultado['embarque_id'] = $datos['id_embarque'] ?? 1;
        }
        if (!isset($resultado['fecha']) || empty($resultado['fecha'])) $resultado['fecha'] = $hoy;
        if (!isset($resultado['monto_total']) || $resultado['monto_total'] < 0) $resultado['monto_total'] = 0;
        if (empty($resultado['comentarios'])) $resultado['comentarios'] = 'Factura generada desde Oracle';
    }
    
    if ($tabla_oracle == 'DETALLE_FACTURA_SERVICIO') {
        if (isset($datos['id_factura']) && $datos['id_factura'] > 0) {
            $resultado['factura_id'] = (int)$datos['id_factura'];
        }
        if (isset($datos['id_servicio']) && $datos['id_servicio'] > 0) {
            $resultado['servicio_id'] = (int)$datos['id_servicio'];
        }
        if (!isset($resultado['cantidad']) && isset($datos['cantidad'])) {
            $resultado['cantidad'] = (int)$datos['cantidad'];
        }
        if (!isset($resultado['subtotal']) && isset($datos['subtotal'])) {
            $resultado['subtotal'] = (float)$datos['subtotal'];
        }
        if (!isset($resultado['incluye_impuesto']) && isset($datos['impuesto_aplicado'])) {
            $val = $datos['impuesto_aplicado'];
            $resultado['incluye_impuesto'] = ($val == 'S' || $val == '1') ? 1 : 0;
        }
        if (!isset($resultado['factura_id']) || $resultado['factura_id'] <= 0) {
            $resultado['factura_id'] = $datos['id_factura'] ?? 1;
        }
        if (!isset($resultado['servicio_id']) || $resultado['servicio_id'] <= 0) {
            $resultado['servicio_id'] = $datos['id_servicio'] ?? 1;
        }
        if (!isset($resultado['cantidad']) || $resultado['cantidad'] <= 0) $resultado['cantidad'] = 1;
        if (!isset($resultado['subtotal']) || $resultado['subtotal'] < 0) $resultado['subtotal'] = 0;
        if (!isset($resultado['incluye_impuesto'])) $resultado['incluye_impuesto'] = 0;
    }
    
    if ($tabla_oracle == 'TRANSFERENCIA_CARGA') {
        if (isset($datos['id_transferencia']) && $datos['id_transferencia'] > 0) {
            $resultado['movimiento_id'] = (int)$datos['id_transferencia'];
        }
        if (isset($datos['id_contenedor']) && $datos['id_contenedor'] > 0) {
            $resultado['contenedor_id'] = (int)$datos['id_contenedor'];
        }
        if (isset($datos['terminal_origen']) && $datos['terminal_origen'] > 0) {
            $resultado['centro_origen'] = (int)$datos['terminal_origen'];
        }
        if (isset($datos['terminal_destino']) && $datos['terminal_destino'] > 0) {
            $resultado['centro_destino'] = (int)$datos['terminal_destino'];
        }
        if (!isset($resultado['peso_movido']) && isset($datos['peso_transferido'])) {
            $resultado['peso_movido'] = (float)$datos['peso_transferido'];
        }
        if (!isset($resultado['fecha_movimiento']) && isset($datos['fecha_transferencia'])) {
            $resultado['fecha_movimiento'] = formatearFechaMySQL($datos['fecha_transferencia']);
        }
        if (!isset($resultado['movimiento_id']) || $resultado['movimiento_id'] <= 0) {
            $resultado['movimiento_id'] = $datos['id_transferencia'] ?? 0;
        }
        if (!isset($resultado['contenedor_id']) || $resultado['contenedor_id'] <= 0) {
            $resultado['contenedor_id'] = $datos['id_contenedor'] ?? 1;
        }
        if (!isset($resultado['centro_origen']) || $resultado['centro_origen'] <= 0) {
            $resultado['centro_origen'] = $datos['terminal_origen'] ?? 1;
        }
        if (!isset($resultado['centro_destino']) || $resultado['centro_destino'] <= 0) {
            $resultado['centro_destino'] = $datos['terminal_destino'] ?? 1;
        }
        if (!isset($resultado['peso_movido']) || $resultado['peso_movido'] <= 0) $resultado['peso_movido'] = 1;
        if (!isset($resultado['fecha_movimiento']) || empty($resultado['fecha_movimiento'])) {
            $resultado['fecha_movimiento'] = $hoy;
        }
    }
    
    return $resultado;
}


//FUNCIONES DE CONVERSIÓN


function convertirCodigoPaisANombre($codigo) {
    if (empty($codigo)) return 'N/A';
    $codigo = strtoupper(trim($codigo));
    if (strlen($codigo) > 2 || !ctype_alpha($codigo)) return $codigo;
    
    $paises = [
        'HN' => 'Honduras', 'GT' => 'Guatemala', 'SV' => 'El Salvador',
        'NI' => 'Nicaragua', 'CR' => 'Costa Rica', 'PA' => 'Panama',
        'MX' => 'Mexico', 'BZ' => 'Belice', 'AR' => 'Argentina',
        'BO' => 'Bolivia', 'BR' => 'Brasil', 'CL' => 'Chile',
        'CO' => 'Colombia', 'EC' => 'Ecuador', 'PY' => 'Paraguay',
        'PE' => 'Peru', 'UY' => 'Uruguay', 'VE' => 'Venezuela',
        'US' => 'Estados Unidos', 'CA' => 'Canada',
        'ES' => 'España', 'UK' => 'Reino Unido', 'DE' => 'Alemania',
        'FR' => 'Francia', 'IT' => 'Italia', 'PT' => 'Portugal',
        'NL' => 'Holanda', 'BE' => 'Belgica', 'CH' => 'Suiza',
        'JP' => 'Japon', 'CN' => 'China', 'IN' => 'India',
        'KR' => 'Corea del Sur', 'AU' => 'Australia', 'NZ' => 'Nueva Zelanda',
        'ZA' => 'Sudafrica', 'EG' => 'Egipto', 'IL' => 'Israel',
        'TW' => 'Taiwan', 'SG' => 'Singapur', 'DK' => 'Dinamarca'
    ];
    return $paises[$codigo] ?? $codigo;
}

function formatearFechaMySQL($fecha) {
    if (empty($fecha)) return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $fecha)) return $fecha;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) return $fecha . ' 00:00:00';
    
    if (preg_match('/^(\d{1,2})-([A-Z]{3})-(\d{2,4})$/', $fecha, $m)) {
        $meses = ['JAN'=>'01','FEB'=>'02','MAR'=>'03','APR'=>'04','MAY'=>'05','JUN'=>'06','JUL'=>'07','AUG'=>'08','SEP'=>'09','OCT'=>'10','NOV'=>'11','DEC'=>'12'];
        $year = (strlen($m[3]) == 2) ? (($m[3] >= 70) ? '19' . $m[3] : '20' . $m[3]) : $m[3];
        return $year . '-' . ($meses[strtoupper($m[2])] ?? '01') . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT) . ' 00:00:00';
    }
    
    if (preg_match('/^(\d{1,2})-([A-Z]{3})-(\d{2,4}) (\d{2}):(\d{2}):(\d{2})$/', $fecha, $m)) {
        $meses = ['JAN'=>'01','FEB'=>'02','MAR'=>'03','APR'=>'04','MAY'=>'05','JUN'=>'06','JUL'=>'07','AUG'=>'08','SEP'=>'09','OCT'=>'10','NOV'=>'11','DEC'=>'12'];
        $year = (strlen($m[3]) == 2) ? (($m[3] >= 70) ? '19' . $m[3] : '20' . $m[3]) : $m[3];
        return $year . '-' . ($meses[strtoupper($m[2])] ?? '01') . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT) . 
               ' ' . $m[4] . ':' . $m[5] . ':' . $m[6];
    }
    
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $fecha)) {
        return str_replace('T', ' ', substr($fecha, 0, 19));
    }
    
    $ts = strtotime($fecha);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

function replicarRegistroMySQL($conn, $tabla, $datos) {
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    $conn->query("SET UNIQUE_CHECKS = 0");
    
    $datos = array_filter($datos, function($v) { return $v !== null; });
    if (empty($datos)) {
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $conn->query("SET UNIQUE_CHECKS = 1");
        return false;
    }
    
    $idFields = [
        'tbl_clientes_logisticos' => 'cliente_id',
        'centros_logisticos' => 'terminal_id',
        'unidades_transporte' => 'transporte_id',
        'contenedores' => 'contenedor_id',
        'stock_carga' => 'inventario_id',
        'ordenes_envio' => 'embarque_id',
        'tbl_facturas_logisticas' => 'factura_id',
        'servicios_logisticos' => 'servicio_id',
        'factura_servicios' => 'factura_id',
        'movimientos_carga' => 'movimiento_id'
    ];
    
    $idField = $idFields[$tabla] ?? null;
    
    if ($idField && isset($datos[$idField]) && ($datos[$idField] == 0 || $datos[$idField] === null)) {
        unset($datos[$idField]);
    }
    
    if (empty($datos)) {
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $conn->query("SET UNIQUE_CHECKS = 1");
        return false;
    }
    
    $fields = array_keys($datos);
    $placeholders = array_fill(0, count($fields), '?');
    $updates = [];
    foreach ($fields as $field) {
        if ($field != $idField) {
            $updates[] = "$field = VALUES($field)";
        }
    }
    
    $sql = "INSERT INTO $tabla (" . implode(', ', $fields) . ") 
            VALUES (" . implode(', ', $placeholders) . ")
            ON DUPLICATE KEY UPDATE " . implode(', ', $updates);
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $conn->query("SET UNIQUE_CHECKS = 1");
        return false;
    }
    
    $types = '';
    $values = [];
    foreach ($datos as $value) {
        if (is_int($value)) $types .= 'i';
        elseif (is_float($value)) $types .= 'd';
        else $types .= 's';
        $values[] = $value;
    }
    
    $stmt->bind_param($types, ...$values);
    $result = $stmt->execute();
    $stmt->close();
    
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    $conn->query("SET UNIQUE_CHECKS = 1");
    return $result;
}

function marcarReplicadoOracleBitacora($conn, $id) {
    $sql = "UPDATE BITACORA SET estado_replicacion = 'REPLICADO' WHERE id_registro = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $id);
    $result = oci_execute($stmt);
    oci_free_statement($stmt);
    return $result;
}

function marcarErrorOracleBitacora($conn, $id, $error) {
    $error_truncado = substr($error, 0, 40);
    $sql = "UPDATE BITACORA SET estado_replicacion = 'ERROR', mensaje_error = :error WHERE id_registro = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':error', $error_truncado);
    oci_bind_by_name($stmt, ':id', $id);
    @oci_execute($stmt);
    oci_free_statement($stmt);
}

function contarPendientesOracle($conn) {
    $sql = "SELECT COUNT(*) as total FROM BITACORA WHERE UPPER(estado_replicacion) = 'PENDIENTE'";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    return $row ? (int)$row['TOTAL'] : 0;
}

function decodificarJSONManual($jsonStr) {
    $datos = [];
    $jsonStr = str_replace(["\r", "\n", "\t"], ' ', $jsonStr);
    $jsonStr = trim($jsonStr);
    $decoded = json_decode($jsonStr, true);
    if ($decoded !== null) return $decoded;
    preg_match_all('/"([^"]+)"\s*:\s*("(?:[^"\\\\]|\\\\.)*"|[^",}\s]+)/', $jsonStr, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $key = trim($match[1]);
        $value = trim($match[2]);
        if (strpos($value, '"') === 0) {
            $value = substr($value, 1, -1);
            $value = str_replace(['\\"', '\\n', '\\r', '\\t'], ['"', "\n", "\r", "\t"], $value);
        }
        if ($value === '' || strtolower($value) === 'null') {
            $datos[$key] = null;
        } elseif (is_numeric($value)) {
            $datos[$key] = (strpos($value, '.') !== false) ? (float)$value : (int)$value;
        } elseif (strtolower($value) === 'true') {
            $datos[$key] = true;
        } elseif (strtolower($value) === 'false') {
            $datos[$key] = false;
        } else {
            $datos[$key] = $value;
        }
    }
    return $datos;
}

function registrarLogMySQL($conn, $evento, $descripcion) {
    $stmt = $conn->prepare("INSERT INTO logs_replicacion (evento, descripcion, fecha) VALUES (?, ?, NOW())");
    $stmt->bind_param("ss", $evento, $descripcion);
    @$stmt->execute();
    $stmt->close();
}


//REINICIAR ERRORES A PENDIENTE

$sql = "UPDATE BITACORA 
        SET estado_replicacion = 'PENDIENTE',
            mensaje_error = NULL,
            intentos_replicacion = 0
        WHERE UPPER(estado_replicacion) = 'ERROR'";

$stmt = oci_parse($conn_oracle, $sql);
oci_execute($stmt);
oci_free_statement($stmt);


//PROCESAR REGISTROS PENDIENTES

$procesados = 0;
$errores = [];
$detalles = [];

foreach ($ordenTablas as $tabla_oracle => $tabla_mysql) {
    $pendientes = obtenerPendientesPorTabla($conn_oracle, $tabla_oracle);
    
    if (empty($pendientes)) {
        continue;
    }
    
    foreach ($pendientes as $row) {
        $id = $row['ID'] ?? null;
        $tipo_oracle = $row['TIPO_OPERACION'] ?? 'I';
        $datos_json_raw = $row['DATOS_JSON'] ?? '{}';
        $version = $row['VERSION_REGISTRO'] ?? 1;
        
        if (!$id) {
            $errores[] = "Registro sin ID";
            continue;
        }
        
        $json_limpio = limpiarJSON($datos_json_raw);
        $datos_oracle = json_decode($json_limpio, true);
        
        if (!$datos_oracle) {
            $datos_oracle = decodificarJSONManual($datos_json_raw);
        }
        
        if (!$datos_oracle || empty($datos_oracle)) {
            $errores[] = "ID $id: No se pudieron obtener datos de Oracle";
            continue;
        }
        
        $tipo_mysql = '';
        $tipo_oracle_upper = strtoupper(trim($tipo_oracle));
        if ($tipo_oracle_upper == 'I') $tipo_mysql = 'INSERT';
        elseif ($tipo_oracle_upper == 'U') $tipo_mysql = 'UPDATE';
        elseif ($tipo_oracle_upper == 'D') $tipo_mysql = 'DELETE';
        else $tipo_mysql = 'INSERT';
        
        try {
            $datos_transformados = transformarDatos($tabla_oracle, $datos_oracle, $tipo_mysql);
            
            $idField = obtenerIdFieldMySQL($tabla_mysql);
            $idValor = $datos_transformados[$idField] ?? null;
            
            if ($idField && $idValor && existeRegistroMySQL($conn_mysql, $tabla_mysql, $idField, $idValor)) {
                //El registro ya existe, solo marcar como REPLICADO en Oracle
                $oracle_ok = marcarReplicadoOracleBitacora($conn_oracle, $id);
                if ($oracle_ok) {
                    $procesados++;
                    $detalles[] = "ID $id: $tabla_oracle → $tabla_mysql ($tipo_mysql, v$version) [YA EXISTÍA]";
                    registrarLogMySQL($conn_mysql, 'REPLICADO_ORACLE', "ID $id: $tabla_oracle → MySQL (ya existía)");
                } else {
                    $errores[] = "ID $id: Error al marcar como replicado en Oracle (ya existía)";
                }
                continue;
            }
            
            $mysql_ok = replicarRegistroMySQL($conn_mysql, $tabla_mysql, $datos_transformados);
            
            if ($mysql_ok) {
                $oracle_ok = marcarReplicadoOracleBitacora($conn_oracle, $id);
                if ($oracle_ok) {
                    $procesados++;
                    $detalles[] = "ID $id: $tabla_oracle → $tabla_mysql ($tipo_mysql, v$version)";
                    registrarLogMySQL($conn_mysql, 'REPLICADO_ORACLE', "ID $id: $tabla_oracle → MySQL");
                } else {
                    $errores[] = "ID $id: Error al marcar como replicado en Oracle";
                }
            } else {
                $error_msg = "Error en MySQL - tabla: $tabla_mysql";
                $errores[] = "ID $id: $error_msg";
                marcarErrorOracleBitacora($conn_oracle, $id, $error_msg);
                registrarLogMySQL($conn_mysql, 'ERROR_REPLICACION', "ID $id: $error_msg");
            }
            
        } catch (Exception $e) {
            $errores[] = "ID $id: " . $e->getMessage();
            marcarErrorOracleBitacora($conn_oracle, $id, $e->getMessage());
            registrarLogMySQL($conn_mysql, 'ERROR_REPLICACION', "ID $id: " . $e->getMessage());
        }
    }
}


//VERIFICAR Y REPLICAR REGISTROS FALTANTES DIRECTAMENTE

$total_pendientes = contarPendientesOracle($conn_oracle);

$idConfig = [
    'CLIENTE_NAVIERA' => ['oracle_id' => 'ID_CLIENTE', 'mysql_id' => 'cliente_id'],
    'TERMINAL_PORTUARIA' => ['oracle_id' => 'ID_TERMINAL', 'mysql_id' => 'terminal_id'],
    'BUQUE_OPERACION' => ['oracle_id' => 'ID_BUQUE', 'mysql_id' => 'transporte_id'],
    'CONTENEDOR_NAVIERO' => ['oracle_id' => 'ID_CONTENEDOR', 'mysql_id' => 'contenedor_id'],
    'SERVICIO_PORTUARIO' => ['oracle_id' => 'ID_SERVICIO', 'mysql_id' => 'servicio_id'],
    'INVENTARIO_CARGA' => ['oracle_id' => 'ID_INVENTARIO', 'mysql_id' => 'inventario_id'],
    'EMBARQUE_MARITIMO' => ['oracle_id' => 'ID_EMBARQUE', 'mysql_id' => 'embarque_id'],
    'FACTURACION_EMBARQUE' => ['oracle_id' => 'ID_FACTURA', 'mysql_id' => 'factura_id'],
    'DETALLE_FACTURA_SERVICIO' => ['oracle_id' => 'ID_FACTURA', 'mysql_id' => 'factura_id'],
    'TRANSFERENCIA_CARGA' => ['oracle_id' => 'ID_TRANSFERENCIA', 'mysql_id' => 'movimiento_id']
];

if ($total_pendientes == 0) {
    error_log("🔍 Verificando registros faltantes en MySQL...");
    
    foreach ($ordenTablas as $oracleTable => $mysqlTable) {
        if (!isset($idConfig[$oracleTable])) continue;
        
        $oracleId = $idConfig[$oracleTable]['oracle_id'];
        $mysqlId = $idConfig[$oracleTable]['mysql_id'];
        
        if ($oracleTable == 'DETALLE_FACTURA_SERVICIO') continue;
        
        $resultado = replicarFaltantesDirecto($conn_mysql, $conn_oracle, $oracleTable, $mysqlTable, $oracleId, $mysqlId);
        
        if ($resultado['replicados'] > 0) {
            $procesados += $resultado['replicados'];
            $detalles[] = "✅ Replicados {$resultado['replicados']} registros faltantes de $oracleTable → $mysqlTable";
            registrarLogMySQL($conn_mysql, 'REPLICADO_DIRECTO', "$oracleTable → $mysqlTable: {$resultado['replicados']} registros");
            error_log("   ✅ Replicados {$resultado['replicados']} registros faltantes de $oracleTable → $mysqlTable");
        }
        if (!empty($resultado['errores'])) {
            foreach ($resultado['errores'] as $err) {
                $errores[] = $err;
            }
        }
    }
    
    $total_pendientes = contarPendientesOracle($conn_oracle);
}


//RESPONDER

echo json_encode([
    'success' => true,
    'procesados' => $procesados,
    'errores' => $errores,
    'detalles' => $detalles,
    'total_pendientes' => $total_pendientes,
    'timestamp' => date('Y-m-d H:i:s')
]);

if (isset($conn_mysql)) $conn_mysql->close();
if (isset($conn_oracle)) oci_close($conn_oracle);
?>