    <?php
    /*
    CONFIGURACION INICIAL
    Incluir el archivo de configuracion de la base de datos
    */
    require_once '../config/db.php';

    //Devuelve datos en formato JSON
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');

    //Validacion del metodo HTTP. Solo se aceptan peticiones POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Metodo no permitido. Use POST']);
        exit;
    }

    //VERIFICAR CONEXIONES
    if (!isMySQLConnected()) {
        echo json_encode(['error' => 'MySQL no disponible']);
        exit;
    }

    if (!isOracleConnected()) {
        echo json_encode(['error' => 'Oracle no disponible']);
        exit;
    }

    //Obtener conexiones
    $conn_mysql = getMySQLConnection();
    $conn_oracle = getOracleConnection();

    /*
    FUNCION PARA NORMALIZAR NOMBRE DE TABLA ORACLE
    Convierte nombres en minusculas a mayusculas (formato Oracle estandar)
    */
    function normalizarNombreTablaOracle($nombre) {
        if (empty($nombre)) return null;
        
        //Si ya esta en mayusculas, devolver tal cual
        if (strtoupper($nombre) === $nombre) {
            return $nombre;
        }
        
        //Mapeo de nombres comunes (minusculas → mayusculas)
        $map = [
            'cliente_naviera' => 'CLIENTE_NAVIERA',
            'terminal_portuaria' => 'TERMINAL_PORTUARIA',
            'buque_operacion' => 'BUQUE_OPERACION',
            'contenedor_naviero' => 'CONTENEDOR_NAVIERO',
            'inventario_carga' => 'INVENTARIO_CARGA',
            'embarque_maritimo' => 'EMBARQUE_MARITIMO',
            'facturacion_embarque' => 'FACTURACION_EMBARQUE',
            'servicio_portuario' => 'SERVICIO_PORTUARIO',
            'detalle_factura_servicio' => 'DETALLE_FACTURA_SERVICIO',
            'transferencia_carga' => 'TRANSFERENCIA_CARGA'
        ];
        
        $nombreLower = strtolower($nombre);
        if (isset($map[$nombreLower])) {
            return $map[$nombreLower];
        }
        
        //Si no se encuentra, devolver en mayusculas (ultimo recurso)
        return strtoupper($nombre);
    }

    /*
    MAPEO DE TABLAS: Oracle → MySQL
    (Inverso al de replicar_oracle.php)
    */
    $tableMapping = [
        'CLIENTE_NAVIERA' => 'tbl_clientes_logisticos',
        'TERMINAL_PORTUARIA' => 'centros_logisticos',
        'BUQUE_OPERACION' => 'unidades_transporte',
        'CONTENEDOR_NAVIERO' => 'contenedores',
        'INVENTARIO_CARGA' => 'stock_carga',
        'EMBARQUE_MARITIMO' => 'ordenes_envio',
        'FACTURACION_EMBARQUE' => 'tbl_facturas_logisticas',
        'SERVICIO_PORTUARIO' => 'servicios_logisticos',
        'DETALLE_FACTURA_SERVICIO' => 'factura_servicios',
        'TRANSFERENCIA_CARGA' => 'movimientos_carga'
    ];

    /*
    MAPEO DE CAMPOS ORACLE → MYSQL
    */
    $campoMapping = [
        'CLIENTE_NAVIERA' => [
            'cliente_id' => 'ID_CLIENTE',
            'nombre_contacto' => 'NOMBRE_CLIENTE',
            'telefono_contacto' => 'TELEFONO',
            'email_cliente' => 'CORREO',
            'pais' => 'PAIS_ORIGEN',
            'fecha_alta' => 'FECHA_REGISTRO'
        ],
        'TERMINAL_PORTUARIA' => [
            'terminal_id' => 'ID_TERMINAL',
            'nombre_centro' => 'NOMBRE_TERMINAL',
            'municipio' => 'CIUDAD',
            'pais_operacion' => 'PAIS',
            'categoria' => 'TIPO_TERMINAL',
            'capacidad_maxima' => 'CAPACIDAD_CONTENEDORES'
        ],
        'BUQUE_OPERACION' => [
            'transporte_id' => 'ID_BUQUE',
            'nombre_unidad' => 'NOMBRE_BUQUE',
            'capacidad_carga' => 'CAPACIDAD_TONELADAS',
            'pais_bandera' => 'BANDERA',
            'habilitado' => 'ACTIVO'
        ],
        'CONTENEDOR_NAVIERO' => [
            'contenedor_id' => 'ID_CONTENEDOR',
            'codigo' => 'CODIGO_CONTENEDOR',
            'categoria' => 'TIPO_CONTENEDOR',
            'capacidad_kg' => 'CAPACIDAD_KG',
            'requiere_refrigeracion' => 'REFRIGERADO',
            'estado' => 'ESTADO_OPERACION'
        ],
        'INVENTARIO_CARGA' => [
            'inventario_id' => 'ID_INVENTARIO',
            'contenedor_id' => 'ID_CONTENEDOR',
            'centro_id' => 'ID_TERMINAL',
            'peso_actual' => 'PESO_DISPONIBLE',
            'fecha_modificacion' => 'FECHA_ACTUALIZACION'
        ],
        'EMBARQUE_MARITIMO' => [
            'embarque_id' => 'ID_EMBARQUE',
            'cliente_id' => 'ID_CLIENTE',
            'transporte_id' => 'ID_BUQUE',
            'contenedor_id' => 'ID_CONTENEDOR',
            'fecha_envio' => 'FECHA_SALIDA',
            'estatus' => 'ESTADO_EMBARQUE',
            'nivel_prioridad' => 'PRIORIDAD'
        ],
        'FACTURACION_EMBARQUE' => [
            'factura_id' => 'ID_FACTURA',
            'embarque_id' => 'ID_EMBARQUE',
            'fecha' => 'FECHA_EMISION',
            'monto_total' => 'TOTAL_FACTURADO',
            'comentarios' => 'OBSERVACIONES'
        ],
        'SERVICIO_PORTUARIO' => [
            'servicio_id' => 'ID_SERVICIO',
            'descripcion_servicio' => 'DESCRIPCION_SERVICIO',
            'costo_base' => 'COSTO_BASE',
            'requiere_autorizacion' => 'REQUIERE_AUTORIZACION',
            'categoria_servicio' => 'TIPO_SERVICIO'
        ],
        'DETALLE_FACTURA_SERVICIO' => [
            'factura_id' => 'ID_FACTURA',
            'servicio_id' => 'ID_SERVICIO',
            'cantidad' => 'CANTIDAD',
            'subtotal' => 'SUBTOTAL',
            'incluye_impuesto' => 'IMPUESTO_APLICADO'
        ],
        'TRANSFERENCIA_CARGA' => [
            'movimiento_id' => 'ID_TRANSFERENCIA',
            'contenedor_id' => 'ID_CONTENEDOR',
            'centro_origen' => 'TERMINAL_ORIGEN',
            'centro_destino' => 'TERMINAL_DESTINO',
            'peso_movido' => 'PESO_TRANSFERIDO',
            'fecha_movimiento' => 'FECHA_TRANSFERENCIA'
        ]
    ];

    /*
    OBTENER REGISTROS PENDIENTES DESDE ORACLE
    Primero intentar con BITACORA, luego con LOGS_REPLICACION_ORACLE
    */
    $oracleData = [];

    //INTENTAR CON BITACORA PRIMERO
    $checkBitacora = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'BITACORA'");
    if (!isset($checkBitacora['error']) && !empty($checkBitacora)) {
        $sql = "SELECT id_registro, tabla_afectada, tipo_operacion, datos_json
                FROM (
                    SELECT id_registro, tabla_afectada, tipo_operacion, datos_json
                    FROM BITACORA 
                    WHERE UPPER(estado_replicacion) = 'PENDIENTE'
                    ORDER BY fecha_hora ASC
                )
                WHERE ROWNUM <= 10";
        $result = queryOracle($sql);
        if (!isset($result['error']) && !empty($result)) {
            foreach ($result as $row) {
                $oracleData[] = [
                    'ID' => $row['ID_REGISTRO'] ?? null,
                    'TABLA_AFECTADA' => $row['TABLA_AFECTADA'] ?? null,
                    'TIPO_OPERACION' => $row['TIPO_OPERACION'] ?? null,
                    'DATOS_JSON' => $row['DATOS_JSON'] ?? '{}'
                ];
            }
        }
    }

    //Si no hay datos, intentar con LOGS_REPLICACION_ORACLE
    if (empty($oracleData)) {
        $checkLogs = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'LOGS_REPLICACION_ORACLE'");
        if (!isset($checkLogs['error']) && !empty($checkLogs)) {
            $sql = "SELECT id, evento, descripcion, fecha
                    FROM LOGS_REPLICACION_ORACLE 
                    WHERE UPPER(evento) LIKE '%PENDIENTE%' 
                       OR UPPER(evento) LIKE '%ESPERA%'
                    ORDER BY fecha ASC
                    FETCH FIRST 50 ROWS ONLY";
            $result = queryOracle($sql);
            if (!isset($result['error']) && !empty($result)) {
                foreach ($result as $row) {
                    $oracleData[] = [
                        'ID' => $row['ID'] ?? null,
                        'TABLA_AFECTADA' => $row['EVENTO'] ?? null,
                        'TIPO_OPERACION' => $row['DESCRIPCION'] ?? null,
                        'DATOS_JSON' => $row['DESCRIPCION'] ?? '{}'
                    ];
                }
            }
        }
    }

    //Si no hay datos pendientes, responder con exito pero sin procesar
    if (empty($oracleData)) {
        echo json_encode([
            'success' => true,
            'procesados' => 0,
            'errores' => [],
            'detalles' => ['No hay registros pendientes en Oracle'],
            'total_pendientes' => 0,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    foreach ($oracleData as $row) {
        error_log("   Tabla: " . ($row['TABLA_AFECTADA'] ?? 'N/A') . " | ID: " . ($row['ID'] ?? 'N/A'));
    }

    //PROCESAR CADA REGISTRO
    $procesados = 0;
    $errores = [];
    $detalles = [];

    foreach ($oracleData as $row) {
        //Extraer datos del registro
        $id = $row['ID'] ?? null;
        $tabla_oracle_raw = $row['TABLA_AFECTADA'] ?? null;
        $tipo = $row['TIPO_OPERACION'] ?? 'INSERT';
        $datos_json_raw = $row['DATOS_JSON'] ?? '{}';
        
        //VERIFICAR SI EL REGISTRO YA FUE REPLICADO
        //Verificar en BITACORA si ya fue replicado
        $checkSql = "SELECT estado_replicacion FROM BITACORA WHERE id_registro = :id";
        $checkStmt = oci_parse($conn_oracle, $checkSql);
        oci_bind_by_name($checkStmt, ':id', $id);
        oci_execute($checkStmt);
        $checkRow = oci_fetch_assoc($checkStmt);
        oci_free_statement($checkStmt);
        
        if ($checkRow && ($checkRow['ESTADO_REPLICACION'] == 'REPLICADO')) {
            error_log("Registro ID $id ya esta REPLICADO en BITACORA, saltando...");
            continue; //Saltar este registro
        }
        
        //NORMALIZAR NOMBRE DE TABLA
        $tabla_oracle = normalizarNombreTablaOracle($tabla_oracle_raw);
        
        //Si no hay tabla o ID, saltar
        if (!$tabla_oracle || !$id) {
            $errores[] = "Registro invalido: tabla=$tabla_oracle_raw, id=$id";
            continue;
        }
        
        //Obtener nombre de tabla en MySQL
        $tabla_mysql = $tableMapping[$tabla_oracle] ?? null;
        
        if (!$tabla_mysql) {
            $errores[] = "Tabla no mapeada: $tabla_oracle";
            error_log("Tabla no mapeada: $tabla_oracle");
            continue;
        }
        
        error_log("Tabla MySQL: $tabla_mysql");
        
        try {
            //OBTENER DATOS DEL REGISTRO DESDE EL JSON DE LA BITACORA
            $datos_json = '';
            if (is_object($datos_json_raw) && method_exists($datos_json_raw, 'load')) {
                $datos_json = $datos_json_raw->load();
            } elseif (is_string($datos_json_raw)) {
                $datos_json = $datos_json_raw;
            } else {
                $datos_json = json_encode($datos_json_raw);
            }
            
            //Decodificar JSON
            $datos_oracle = json_decode($datos_json, true);
            if (!$datos_oracle) {
                //Si falla, intentar decodificar manualmente
                $datos_oracle = decodificarJSONManual($datos_json);
            }
            
            if (!$datos_oracle || empty($datos_oracle)) {
                $errores[] = "No se pudieron obtener datos de Oracle para ID $id en $tabla_oracle";
                error_log("No se obtuvieron datos para ID $id");
                continue;
            }
            
            error_log("Datos obtenidos de Oracle: " . json_encode(array_keys($datos_oracle)));
            
            //TRANSFORMAR DATOS DE ORACLE → MYSQL
            $datos_transformados = transformarDatosOracleToMySQL($tabla_oracle, $datos_oracle, $tipo);
            
            error_log("Datos transformados: " . json_encode(array_keys($datos_transformados)));
            error_log("Datos transformados para $tabla_oracle → $tabla_mysql:");
            error_log(json_encode($datos_transformados));
            
            //EJECUTAR OPERACIoN EN MYSQL
            $mysql_ok = replicarRegistroMySQL($conn_mysql, $tabla_mysql, $datos_transformados, $tabla_oracle);
            
            if ($mysql_ok) {
                //Marcar como replicado en Oracle
                error_log("Registro replicado correctamente: ID $id, Tabla: $tabla_mysql");
                $oracle_ok = marcarReplicadoOracle($conn_oracle, 'BITACORA', $id);
                if ($oracle_ok) {
                    $procesados++;
                    $detalles[] = "ID $id: $tabla_oracle → $tabla_mysql";
                    registrarLogMySQL($conn_mysql, 'REPLICADO_ORACLE', "ID $id: $tabla_oracle → MySQL");
                    error_log("ID $id replicado exitosamente");
                } else {
                    $errores[] = "ID $id: Error al marcar como replicado en Oracle";
                    error_log("Error al marcar como replicado en Oracle");
                }
            } else {
                error_log("Error al replicar en MySQL. Tabla: $tabla_mysql, Datos: " . json_encode($datos_transformados));
                $error_msg = "Error al replicar a MySQL - tabla: $tabla_mysql";
                $errores[] = "ID $id: $error_msg";
                marcarErrorOracle($conn_oracle, $tabla_oracle, $id, $error_msg);
                registrarLogMySQL($conn_mysql, 'ERROR_REPLICACION', "ID $id: $error_msg");
                error_log("Error al replicar a MySQL: $error_msg");
            }
            
        } catch (Exception $e) {
            $error_msg = $e->getMessage();
            $errores[] = "ID $id: " . $error_msg;
            marcarErrorOracle($conn_oracle, $tabla_oracle, $id, $error_msg);
            registrarLogMySQL($conn_mysql, 'ERROR_REPLICACION', "ID $id: " . $error_msg);
            error_log("Excepcion: " . $error_msg);
        }
    }

    //RESPUESTA
    echo json_encode([
        'success' => true,
        'procesados' => $procesados,
        'errores' => $errores,
        'detalles' => $detalles,
        'total_pendientes' => contarPendientesOracle($conn_oracle),
        'timestamp' => date('Y-m-d H:i:s')
    ]);

    //CERRAR CONEXIONES
    if (isset($conn_mysql)) $conn_mysql->close();
    if (isset($conn_oracle)) oci_close($conn_oracle);




    //FUNCIONES AUXILIARES


    /*
    OBTENER DATOS DE UN REGISTRO EN ORACLE
    */
    function obtenerDatosRegistroOracle($conn, $tabla, $id) {
        //Mapeo de campos ID por tabla
        $idFields = [
            'CLIENTE_NAVIERA' => 'ID_CLIENTE',
            'TERMINAL_PORTUARIA' => 'ID_TERMINAL',
            'BUQUE_OPERACION' => 'ID_BUQUE',
            'CONTENEDOR_NAVIERO' => 'ID_CONTENEDOR',
            'INVENTARIO_CARGA' => 'ID_INVENTARIO',
            'EMBARQUE_MARITIMO' => 'ID_EMBARQUE',
            'FACTURACION_EMBARQUE' => 'ID_FACTURA',
            'SERVICIO_PORTUARIO' => 'ID_SERVICIO',
            'DETALLE_FACTURA_SERVICIO' => null, //Clave compuesta
            'TRANSFERENCIA_CARGA' => 'ID_TRANSFERENCIA'
        ];
        
        $idField = $idFields[$tabla] ?? null;
        
        if (!$idField) {
            //Caso especial: DETALLE_FACTURA_SERVICIO
            if ($tabla == 'DETALLE_FACTURA_SERVICIO') {
                $parts = explode('|', $id);
                if (count($parts) == 2) {
                    $sql = "SELECT * FROM DETALLE_FACTURA_SERVICIO 
                            WHERE ID_FACTURA = :id1 AND ID_SERVICIO = :id2";
                    $stmt = oci_parse($conn, $sql);
                    oci_bind_by_name($stmt, ':id1', $parts[0]);
                    oci_bind_by_name($stmt, ':id2', $parts[1]);
                    oci_execute($stmt);
                    $row = oci_fetch_assoc($stmt);
                    oci_free_statement($stmt);
                    return $row;
                }
                return null;
            }
            return null;
        }
        
        $sql = "SELECT * FROM $tabla WHERE $idField = :id";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':id', $id);
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);
        
        return $row;
    }

    /*
    TRANSFORMAR DATOS DE ORACLE → MYSQL
    */
    function transformarDatosOracleToMySQL($tabla_oracle, $datos, $tipo) {
        $resultado = [];
        
        //Para DELETE, solo devolver el ID
        if ($tipo == 'DELETE') {
            $idField = [
                'CLIENTE_NAVIERA' => 'ID_CLIENTE',
                'TERMINAL_PORTUARIA' => 'ID_TERMINAL',
                'BUQUE_OPERACION' => 'ID_BUQUE',
                'CONTENEDOR_NAVIERO' => 'ID_CONTENEDOR',
                'INVENTARIO_CARGA' => 'ID_INVENTARIO',
                'EMBARQUE_MARITIMO' => 'ID_EMBARQUE',
                'FACTURACION_EMBARQUE' => 'ID_FACTURA',
                'SERVICIO_PORTUARIO' => 'ID_SERVICIO',
                'DETALLE_FACTURA_SERVICIO' => null,
                'TRANSFERENCIA_CARGA' => 'ID_TRANSFERENCIA'
            ];
            $idFieldName = $idField[$tabla_oracle] ?? null;
            if ($idFieldName && isset($datos[$idFieldName])) {
                return ['id' => $datos[$idFieldName]];
            }
            return ['id' => null];
        }
        
        //Mapeo de campos (con soporte para mayusculas y minusculas)
        $map = [
            'CLIENTE_NAVIERA' => [
                'cliente_id' => ['ID_CLIENTE', 'id_cliente'],
                'nombre_contacto' => ['NOMBRE_CLIENTE', 'nombre_cliente'],
                'telefono_contacto' => ['TELEFONO', 'telefono'],
                'email_cliente' => ['CORREO', 'correo'],
                'pais' => ['PAIS_ORIGEN', 'pais_origen'],
                'fecha_alta' => ['FECHA_REGISTRO', 'fecha_registro']
            ],
            'TERMINAL_PORTUARIA' => [
                'terminal_id' => ['ID_TERMINAL', 'id_terminal'],
                'nombre_centro' => ['NOMBRE_TERMINAL', 'nombre_terminal'],
                'municipio' => ['CIUDAD', 'ciudad'],
                'pais_operacion' => ['PAIS', 'pais'],
                'categoria' => ['TIPO_TERMINAL', 'tipo_terminal'],
                'capacidad_maxima' => ['CAPACIDAD_CONTENEDORES', 'capacidad_contenedores']
            ],
            'BUQUE_OPERACION' => [
                'transporte_id' => ['ID_BUQUE', 'id_buque'],
                'nombre_unidad' => ['NOMBRE_BUQUE', 'nombre_buque'],
                'capacidad_carga' => ['CAPACIDAD_TONELADAS', 'capacidad_toneladas'],
                'pais_bandera' => ['BANDERA', 'bandera'],
                'habilitado' => ['ACTIVO', 'activo']
            ],
            'CONTENEDOR_NAVIERO' => [
                'contenedor_id' => ['ID_CONTENEDOR', 'id_contenedor'],
                'codigo' => ['CODIGO_CONTENEDOR', 'codigo_contenedor'],
                'categoria' => ['TIPO_CONTENEDOR', 'tipo_contenedor'],
                'capacidad_kg' => ['CAPACIDAD_KG', 'capacidad_kg'],
                'requiere_refrigeracion' => ['REFRIGERADO', 'refrigerado'],
                'estado' => ['ESTADO_OPERACION', 'estado_operacion']
            ],
            'INVENTARIO_CARGA' => [
                'inventario_id' => ['ID_INVENTARIO', 'id_inventario'],
                'contenedor_id' => ['ID_CONTENEDOR', 'id_contenedor'],
                'centro_id' => ['ID_TERMINAL', 'id_terminal'],
                'peso_actual' => ['PESO_DISPONIBLE', 'peso_disponible'],
                'fecha_modificacion' => ['FECHA_ACTUALIZACION', 'fecha_actualizacion']
            ],
            'EMBARQUE_MARITIMO' => [
                'embarque_id' => ['ID_EMBARQUE', 'id_embarque'],
                'cliente_id' => ['ID_CLIENTE', 'id_cliente'],
                'transporte_id' => ['ID_BUQUE', 'id_buque'],
                'contenedor_id' => ['ID_CONTENEDOR', 'id_contenedor'],
                'fecha_envio' => ['FECHA_SALIDA', 'fecha_salida'],
                'estatus' => ['ESTADO_EMBARQUE', 'estado_embarque'],
                'nivel_prioridad' => ['PRIORIDAD', 'prioridad']
            ],
            'FACTURACION_EMBARQUE' => [
                'factura_id' => ['ID_FACTURA', 'id_factura'],
                'embarque_id' => ['ID_EMBARQUE', 'id_embarque'],
                'fecha' => ['FECHA_EMISION', 'fecha_emision'],
                'monto_total' => ['TOTAL_FACTURADO', 'total_facturado'],
                'comentarios' => ['OBSERVACIONES', 'observaciones']
            ],
            'SERVICIO_PORTUARIO' => [
                'servicio_id' => ['ID_SERVICIO', 'id_servicio'],
                'descripcion_servicio' => ['DESCRIPCION_SERVICIO', 'descripcion_servicio'],
                'costo_base' => ['COSTO_BASE', 'costo_base'],
                'requiere_autorizacion' => ['REQUIERE_AUTORIZACION', 'requiere_autorizacion'],
                'categoria_servicio' => ['TIPO_SERVICIO', 'tipo_servicio']
            ],
            'DETALLE_FACTURA_SERVICIO' => [
                'factura_id' => ['ID_FACTURA', 'id_factura'],
                'servicio_id' => ['ID_SERVICIO', 'id_servicio'],
                'cantidad' => ['CANTIDAD', 'cantidad'],
                'subtotal' => ['SUBTOTAL', 'subtotal'],
                'incluye_impuesto' => ['IMPUESTO_APLICADO', 'impuesto_aplicado']
            ],
            'TRANSFERENCIA_CARGA' => [
                'movimiento_id' => ['ID_TRANSFERENCIA', 'id_transferencia'],
                'contenedor_id' => ['ID_CONTENEDOR', 'id_contenedor'],
                'centro_origen' => ['TERMINAL_ORIGEN', 'terminal_origen'],
                'centro_destino' => ['TERMINAL_DESTINO', 'terminal_destino'],
                'peso_movido' => ['PESO_TRANSFERIDO', 'peso_transferido'],
                'fecha_movimiento' => ['FECHA_TRANSFERENCIA', 'fecha_transferencia']
            ]
        ];
        
        $mapaCampos = $map[$tabla_oracle] ?? [];
        
        foreach ($mapaCampos as $mysqlField => $oracleFields) {
            $value = null;
            foreach ($oracleFields as $oracleField) {
                if (isset($datos[$oracleField])) {
                    $value = $datos[$oracleField];
                    break;
                }
            }
            
            if ($value !== null) {
                //Convertir S/N a 1/0 para booleanos
                if (in_array($mysqlField, ['requiere_refrigeracion', 'habilitado', 'requiere_autorizacion', 'incluye_impuesto'])) {
                    $value = ($value == 'S' || $value == '1') ? 1 : 0;
                }
                
                //Convertir codigo de país a nombre completo
                if (in_array($mysqlField, ['pais', 'pais_operacion', 'pais_bandera'])) {
                    $value = convertirCodigoPaisANombre($value);
                }
                
                //Formatear fechas
                if (in_array($mysqlField, ['fecha_alta', 'fecha_modificacion', 'fecha_movimiento', 'fecha_envio', 'fecha'])) {
                    $value = formatearFechaMySQL($value);
                }
                
                $resultado[$mysqlField] = $value;
            }
        }
        
        //Aplicar valores por defecto para campos obligatorios
        $hoy = date('Y-m-d H:i:s');
        
        if ($tabla_oracle == 'SERVICIO_PORTUARIO') {
            if (empty($resultado['descripcion_servicio'])) {
                $resultado['descripcion_servicio'] = 'Servicio ' . ($datos['id_servicio'] ?? $datos['ID_SERVICIO'] ?? 0);
            }
            if (!isset($resultado['categoria_servicio']) || empty($resultado['categoria_servicio'])) {
                $resultado['categoria_servicio'] = 'Carga';
            }
            if (!isset($resultado['costo_base'])) $resultado['costo_base'] = 0;
            if (!isset($resultado['requiere_autorizacion'])) $resultado['requiere_autorizacion'] = 0;
        }
        
        if ($tabla_oracle == 'TERMINAL_PORTUARIA') {
            if (empty($resultado['nombre_centro'])) {
                $resultado['nombre_centro'] = 'Terminal ' . ($datos['id_terminal'] ?? $datos['ID_TERMINAL'] ?? 0);
            }
            if (!isset($resultado['capacidad_maxima']) || $resultado['capacidad_maxima'] == 0) {
                $resultado['capacidad_maxima'] = 100;
            }
            if (empty($resultado['categoria'])) $resultado['categoria'] = 'Bodega';
            if (empty($resultado['pais_operacion'])) $resultado['pais_operacion'] = 'N/A';
        }
        
        if ($tabla_oracle == 'CONTENEDOR_NAVIERO') {
            if (empty($resultado['codigo'])) {
                $resultado['codigo'] = 'CONT-' . str_pad($resultado['contenedor_id'] ?? 0, 3, '0', STR_PAD_LEFT);
            }
            if (!isset($resultado['capacidad_kg']) || $resultado['capacidad_kg'] == 0) {
                $resultado['capacidad_kg'] = 30000;
            }
            if (empty($resultado['categoria'])) $resultado['categoria'] = 'Seco';
            if (empty($resultado['estado'])) $resultado['estado'] = 'Disponible';
            if (!isset($resultado['requiere_refrigeracion'])) $resultado['requiere_refrigeracion'] = 0;
        }
        
        if ($tabla_oracle == 'INVENTARIO_CARGA') {
            $peso = $datos['peso_disponible'] ?? $datos['PESO_DISPONIBLE'] ?? 0;
            if (empty($peso) || $peso == 0) $peso = 1;
            $resultado['peso_actual'] = $peso;
            if (!isset($resultado['fecha_modificacion']) || empty($resultado['fecha_modificacion'])) {
                $resultado['fecha_modificacion'] = $hoy;
            }
            if (!isset($resultado['inventario_id'])) {
                $resultado['inventario_id'] = $datos['id_inventario'] ?? $datos['ID_INVENTARIO'] ?? 0;
            }
            if (!isset($resultado['contenedor_id'])) {
                $resultado['contenedor_id'] = $datos['id_contenedor'] ?? $datos['ID_CONTENEDOR'] ?? 0;
            }
            if (!isset($resultado['centro_id'])) {
                $resultado['centro_id'] = $datos['id_terminal'] ?? $datos['ID_TERMINAL'] ?? 1;
            }
        }
        
        if ($tabla_oracle == 'TRANSFERENCIA_CARGA') {
            if (!isset($resultado['fecha_movimiento']) || empty($resultado['fecha_movimiento'])) {
                $resultado['fecha_movimiento'] = $hoy;
            }
            if (!isset($resultado['peso_movido']) || $resultado['peso_movido'] == 0) {
                $resultado['peso_movido'] = 1;
            }
        }
        
        if ($tabla_oracle == 'CLIENTE_NAVIERA') {
            if (empty($resultado['nombre_contacto'])) {
                $resultado['nombre_contacto'] = 'Cliente ' . ($datos['id_cliente'] ?? $datos['ID_CLIENTE'] ?? 0);
            }
            if (!isset($resultado['fecha_alta']) || empty($resultado['fecha_alta'])) {
                $resultado['fecha_alta'] = $hoy;
            }
            if (empty($resultado['pais'])) $resultado['pais'] = 'N/A';
        }
        
        if ($tabla_oracle == 'EMBARQUE_MARITIMO') {
            if (!isset($resultado['fecha_envio']) || empty($resultado['fecha_envio'])) $resultado['fecha_envio'] = $hoy;
            if (empty($resultado['estatus'])) $resultado['estatus'] = 'Pendiente';
            if (!isset($resultado['nivel_prioridad'])) $resultado['nivel_prioridad'] = 3;
        }
        
        if ($tabla_oracle == 'FACTURACION_EMBARQUE') {
            if (!isset($resultado['fecha']) || empty($resultado['fecha'])) $resultado['fecha'] = $hoy;
            if (!isset($resultado['monto_total'])) $resultado['monto_total'] = 0;
        }
        
        if ($tabla_oracle == 'DETALLE_FACTURA_SERVICIO') {
            if (!isset($resultado['cantidad'])) $resultado['cantidad'] = 1;
            if (!isset($resultado['subtotal'])) $resultado['subtotal'] = 0;
            if (!isset($resultado['incluye_impuesto'])) $resultado['incluye_impuesto'] = 0;
        }
        
        if ($tabla_oracle == 'BUQUE_OPERACION') {
            if (empty($resultado['nombre_unidad'])) {
                $resultado['nombre_unidad'] = 'Buque ' . ($datos['id_buque'] ?? $datos['ID_BUQUE'] ?? 0);
            }
            if (empty($resultado['pais_bandera'])) $resultado['pais_bandera'] = 'N/A';
            if (!isset($resultado['capacidad_carga'])) $resultado['capacidad_carga'] = 0;
            if (!isset($resultado['habilitado'])) $resultado['habilitado'] = 1;
        }
        
        return $resultado;
    }

    /*
    CONVERTIR CoDIGO DE PAÍS A NOMBRE COMPLETO
    */
    function convertirCodigoPaisANombre($codigo) {
        static $paises = [
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
            'KR' => 'Corea del Sur', 'AU' => 'Australia', 'NZ' => 'Nueva Zelanda'
        ];
        
        if (!$codigo) return null;
        $codigo = strtoupper(trim($codigo));
        if (strlen($codigo) > 2 || !ctype_alpha($codigo)) return $codigo;
        return $paises[$codigo] ?? $codigo;
    }

    /*
    REPLICAR REGISTRO EN MYSQL (INSERT/UPDATE)
    */
    function replicarRegistroMySQL($conn, $tabla, $datos, $tabla_oracle) {
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        $datos = array_filter($datos, function($v) { return $v !== null; });
        if (empty($datos)) { $conn->query("SET FOREIGN_KEY_CHECKS = 1"); return false; }
        
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
        if (!$idField || !isset($datos[$idField])) { $conn->query("SET FOREIGN_KEY_CHECKS = 1"); return false; }
        
        $fields = array_keys($datos);
        $placeholders = array_fill(0, count($fields), '?');
        $updates = [];
        foreach ($fields as $field) {
            if ($field != $idField) $updates[] = "$field = VALUES($field)";
        }
        
        $sql = "INSERT INTO $tabla (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")
                ON DUPLICATE KEY UPDATE " . implode(', ', $updates);
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) { $conn->query("SET FOREIGN_KEY_CHECKS = 1"); return false; }
        
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
        return $result;
    }

    /*
    ELIMINAR REGISTRO EN MYSQL
    */
    function eliminarRegistroMySQL($conn, $tabla, $id) {
        $idFields = [
            'tbl_clientes_logisticos' => 'cliente_id',
            'centros_logisticos' => 'terminal_id',
            'unidades_transporte' => 'transporte_id',
            'contenedores' => 'contenedor_id',
            'stock_carga' => 'inventario_id',
            'ordenes_envio' => 'embarque_id',
            'tbl_facturas_logisticas' => 'factura_id',
            'servicios_logisticos' => 'servicio_id',
            'movimientos_carga' => 'movimiento_id'
        ];
        $idField = $idFields[$tabla] ?? null;
        if (!$idField) return false;
        $stmt = $conn->prepare("DELETE FROM $tabla WHERE $idField = ?");
        $stmt->bind_param("i", $id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /*
    MARCAR REGISTRO COMO REPLICADO EN ORACLE
    */
    function marcarReplicadoOracle($conn, $tabla, $id) {
        error_log("🔍 Intentando marcar como REPLICADO: Tabla=$tabla, ID=$id");
        if (strtoupper($tabla) == 'BITACORA') {
            $sql = "UPDATE BITACORA SET estado_replicacion = 'REPLICADO' WHERE id_registro = :id";
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':id', $id);
            $result = oci_execute($stmt);
            oci_free_statement($stmt);
            if ($result) { error_log("✅ BITACORA: ID $id marcado como REPLICADO"); return true; }
            else { $e = oci_error($stmt); error_log("❌ Error: " . ($e['message'] ?? 'Desconocido')); return false; }
        }
        error_log("❌ No se pudo marcar ID $id en tabla $tabla");
        return false;
    }

    /*
    MARCAR REGISTRO CON ERROR EN ORACLE
    */
    function marcarErrorOracle($conn, $tabla, $id, $error) {
        $sql = "UPDATE LOGS_REPLICACION_ORACLE SET evento = 'ERROR', descripcion = :error WHERE id = :id";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':error', $error);
        oci_bind_by_name($stmt, ':id', $id);
        @oci_execute($stmt);
        oci_free_statement($stmt);
    }

    /*
    CONTAR REGISTROS PENDIENTES EN ORACLE
    */
    function contarPendientesOracle($conn) {
        $sql = "SELECT COUNT(*) as total FROM BITACORA WHERE UPPER(estado_replicacion) = 'PENDIENTE'";
        $stmt = oci_parse($conn, $sql);
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);
        if ($row && isset($row['TOTAL'])) return (int)$row['TOTAL'];
        $sql = "SELECT COUNT(*) as total FROM LOGS_REPLICACION_ORACLE WHERE UPPER(evento) LIKE '%PENDIENTE%' OR UPPER(evento) LIKE '%ESPERA%'";
        $stmt = oci_parse($conn, $sql);
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);
        return (int)($row['TOTAL'] ?? 0);
    }

    /*
    FORMATEAR FECHA ORACLE A MYSQL
    */
    function formatearFechaMySQL($fecha) {
        if (empty($fecha)) return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $fecha)) return $fecha;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) return $fecha . ' 00:00:00';
        if (preg_match('/^(\d{1,2})-([A-Z]{3})-(\d{2})$/', $fecha, $m)) {
            $meses = ['JAN'=>'01','FEB'=>'02','MAR'=>'03','APR'=>'04','MAY'=>'05','JUN'=>'06','JUL'=>'07','AUG'=>'08','SEP'=>'09','OCT'=>'10','NOV'=>'11','DEC'=>'12'];
            return (($m[3]>=70)?'19':'20').$m[3].'-'.$meses[strtoupper($m[2])].'-'.str_pad($m[1],2,'0',STR_PAD_LEFT).' 00:00:00';
        }
        if (preg_match('/^(\d{1,2})-([A-Z]{3})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/', $fecha, $m)) {
            $meses = ['JAN'=>'01','FEB'=>'02','MAR'=>'03','APR'=>'04','MAY'=>'05','JUN'=>'06','JUL'=>'07','AUG'=>'08','SEP'=>'09','OCT'=>'10','NOV'=>'11','DEC'=>'12'];
            return (($m[3]>=70)?'19':'20').$m[3].'-'.$meses[strtoupper($m[2])].'-'.str_pad($m[1],2,'0',STR_PAD_LEFT).' '.$m[4].':'.$m[5].':'.$m[6];
        }
        $ts = strtotime($fecha);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    /*
    DECODIFICAR JSON MANUALMENTE (para JSON mal formados)
    */
    function decodificarJSONManual($jsonStr) {
        $datos = [];
        $jsonStr = str_replace(["\r", "\n", "\t"], ' ', $jsonStr);
        preg_match_all('/"([^"]+)"\s*:\s*"?([^",}\s]+)"?/', $jsonStr, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $key = trim($match[1]);
            $value = trim($match[2]);
            if ($value === '' || strtolower($value) === 'null') $datos[$key] = null;
            elseif (is_numeric($value)) $datos[$key] = (float)$value;
            elseif (strtolower($value) === 'true') $datos[$key] = true;
            elseif (strtolower($value) === 'false') $datos[$key] = false;
            else $datos[$key] = $value;
        }
        return $datos;
    }

    /*
    REGISTRAR LOG EN MYSQL
    */
    function registrarLogMySQL($conn, $evento, $descripcion) {
        $stmt = $conn->prepare("INSERT INTO logs_replicacion (evento, descripcion, fecha) VALUES (?, ?, NOW())");
        $stmt->bind_param("ss", $evento, $descripcion);
        @$stmt->execute();
        $stmt->close();
    }
    ?>