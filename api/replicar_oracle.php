    <?php
    /*
    Flujo del proceso:
     1. Verifica conexiones a MySQL y Oracle
     2. Obtiene registros pendientes de la bitacora
     3. Transforma datos segun diccionario de homologacion
     4. Ejecuta INSERT/UPDATE/DELETE en Oracle
     5. Actualiza estado de la bitacora
     6. Registra logs del proceso
     */
    /*
    CONFIGURACION INICIAL
    Incluir el archivo de configuracion de la base de datos
    */
    require_once '../config/db.php';

    //Devuelve datos en formato JSON
    header('Content-Type: application/json');
    //Permite peticiones desde cualquie origen (CORS)
    header('Access-Control-Allow-Origin: *');

    //Validacion del metodo HTTP. Solo se aceptan peticiones POST para evitar ejecucion accidental desde navegador (GET) o desde otros origenes no autorizados
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

    //DICCIONARIO DE HOMOLOGACIoN
    $tableMapping = [
        'tbl_clientes_logisticos' => 'CLIENTE_NAVIERA',
        'centros_logisticos' => 'TERMINAL_PORTUARIA',
        'unidades_transporte' => 'BUQUE_OPERACION',
        'contenedores' => 'CONTENEDOR_NAVIERO',
        'stock_carga' => 'INVENTARIO_CARGA',
        'ordenes_envio' => 'EMBARQUE_MARITIMO',
        'tbl_facturas_logisticas' => 'FACTURACION_EMBARQUE',
        'servicios_logisticos' => 'SERVICIO_PORTUARIO',
        'factura_servicios' => 'DETALLE_FACTURA_SERVICIO',
        'movimientos_carga' => 'TRANSFERENCIA_CARGA'
    ];

    /*OBTENER REGISTROS PENDIENTES
    Condiciones:
     1. estado_replicacion = 'PENDIENTE' → No se ha replicado
     2. origen_evento = 'MYSQL' → Provienen de MySQL
     3. intentos_replicacion < 3 → Maximo 3 intentos (evita bucles infinitos)
     4. ORDER BY id ASC → Mas antiguos primero (FIFO)
     5. LIMIT 50 → Procesa en lotes de 50 (control de recursos)
     */
    $sql = "SELECT id, tabla_afectada, tipo_operacion, id_registro, datos_json 
            FROM bitacora_replicacion 
            WHERE estado_replicacion = 'PENDIENTE' 
              AND origen_evento = 'MYSQL'
              AND intentos_replicacion < 3
            ORDER BY id ASC
            LIMIT 10";

    $result = $conn_mysql->query($sql);
    //Manejo de errores en la consulta. Si falla la consulta, devolver error y terminar
    if (!$result) {
        echo json_encode(['error' => 'Error consultando bitacora: ' . $conn_mysql->error]);
        exit;
    }

    //PROCESAR CADA REGISTRO
    $procesados = 0;
    $errores = [];
    $detalles = [];
    /*
    Bucle principal: Procesa cada registro pendiente
    Para cada registro:
     1. Obtiene los datos de la bitacora
     2. Transforma los datos segun el diccionario de homologacion
     3. Ejecuta la operacion en Oracle (INSERT/UPDATE/DELETE)
     4. Actualiza el estado en la bitacora
     5. Registra en logs de sistema
    */
    while ($row = $result->fetch_assoc()) {
        //Extraer datos del registro
        //ID de la bitacora
        $id = $row['id'];
        //Tabla en MySQL
        $tabla_mysql = $row['tabla_afectada'];
        //INSERT, UPDATE, DELETE
        $tipo = $row['tipo_operacion'];
        //ID del registro afectado
        $id_registro = $row['id_registro'];
        //Datos del registro (JSON)
        $datos = json_decode($row['datos_json'], true);
        
        //Obtener nombre de tabla en Oracle
        $tabla_oracle = $tableMapping[$tabla_mysql] ?? strtoupper($tabla_mysql);
        
        try {
            /*TRANSFORMAR DATOS SEGuN DICCIONARIO

             Aplica las conversiones necesarias entre MySQL y Oracle
             - DATE ↔ DATETIME
             - BOOLEAN ↔ CHAR(1)
             - ENUM ↔ VARCHAR2
             - Nombres de paises a codigos
             - Estados operativos
             - Formatos numericos
            */
            $datos_transformados = transformarDatos($tabla_mysql, $datos, $tipo);
            
            /*EJECUTAR OPERACIoN EN ORACLE
             Segun el tipo de operacion:
             - INSERT/UPDATE: Se usa MERGE para insertar o actualizar
             - DELETE: Se elimina el registro por su ID
            */
            if ($tipo == 'INSERT' || $tipo == 'UPDATE') {
                //Log de depuracion (para seguimiento en el servidor). Se registra la tabla y los datos que se van a replicar
                error_log("=== REPLICANDO A ORACLE ===");
                error_log("Tabla: $tabla_oracle");
                error_log("Datos: " . json_encode($datos_transformados));
                //Ejecutar replicacion en Oracle. Devuelve true si fue exitoso, false si fallo
                $oracle_ok = replicarRegistroOracle($conn_oracle, $tabla_oracle, $datos_transformados, $tabla_mysql);
                error_log("Resultado: " . ($oracle_ok ? "OK" : "FALLO"));
                
                if (!$oracle_ok) {
                    $error_msg = "Error al replicar a Oracle (INSERT/UPDATE)";
                }
                //Para DELETE: Obtener el ID del registro a eliminar el ID se extrae de los datos transformados
            } elseif ($tipo == 'DELETE') {
                $id_del_registro = obtenerIdRegistro($tabla_mysql, $datos_transformados);
                
                if ($id_del_registro) {
                    $oracle_ok = eliminarRegistroOraclePorID($conn_oracle, $tabla_oracle, $id_del_registro);
                    if (!$oracle_ok) {
                        $error_msg = "Error al eliminar en Oracle (ID: $id_del_registro)";
                    }
                } else {
                    $oracle_ok = false;
                    $error_msg = "No se pudo obtener el ID para DELETE en $tabla_oracle";
                }
            } else {
                $oracle_ok = false;
                $error_msg = "Tipo de operacion no soportada: $tipo";
            }
            
            /*ACTUALIZAR BITaCORA
             - Si fue exitoso: Marcar como 'REPLICADO'
             - Si fallo: Marcar como 'ERROR' con el mensaje de error
            */
            if ($oracle_ok) {
                //EXITO: Marcar como replicado
                $update = $conn_mysql->prepare(
                    "UPDATE bitacora_replicacion 
                     SET estado_replicacion = 'REPLICADO', 
                         intentos_replicacion = intentos_replicacion + 1,
                         mensaje_error = NULL
                     WHERE id = ?"
                );
                $update->bind_param("i", $id);
                $update->execute();
                $update->close();

                $oracle_ok = insertarEnBitacoraOracle($conn_oracle, $tabla_oracle, $tipo, $id_registro, $datos_transformados);
                
                if ($oracle_ok) {
                    $procesados++;
                    $detalles[] = "ID $id: $tabla_mysql → $tabla_oracle ($tipo)";
                    registrarLog($conn_mysql, 'REPLICADO', "ID $id: $tabla_mysql → Oracle ($tipo)");
                } else {
                    $errores[] = "ID $id: Error al insertar en BITACORA Oracle";
                }
                    
            } else {
                //ERROR: Marcar como error
                $error_msg = $error_msg ?? "Error al replicar a Oracle";
                $update = $conn_mysql->prepare(
                    "UPDATE bitacora_replicacion 
                     SET estado_replicacion = 'ERROR', 
                         intentos_replicacion = intentos_replicacion + 1,
                         mensaje_error = ?
                     WHERE id = ?"
                );
                $update->bind_param("si", $error_msg, $id);
                $update->execute();
                $update->close();
                //Acumular errores para la respuesta
                $errores[] = "ID $id: $error_msg";
                registrarLog($conn_mysql, 'ERROR_REPLICACION', "ID $id: $error_msg");
            }
            
        } catch (Exception $e) {
            //Excepcion: Marcar como error
            $error_msg = $e->getMessage();
            $update = $conn_mysql->prepare(
                "UPDATE bitacora_replicacion 
                 SET estado_replicacion = 'ERROR', 
                     intentos_replicacion = intentos_replicacion + 1,
                     mensaje_error = ?
                 WHERE id = ?"
            );
            $update->bind_param("si", $error_msg, $id);
            $update->execute();
            $update->close();
            
            $errores[] = "ID $id: " . $error_msg;
            registrarLog($conn_mysql, 'ERROR_REPLICACION', "ID $id: " . $error_msg);
        }
    }

    //RESPUESTA

    echo json_encode([
        //Indica que el proceso se ejecuto (true)
        'success' => true,
        //Numero de registros replicados exitosamente
        'procesados' => $procesados,
        //Lista de errores ocurridos
        'errores' => $errores,
        //Lista de operaciones realizadas
        'detalles' => $detalles,
        //Registros que aun quedan por replicar
        'total_pendientes' => contarPendientes($conn_mysql),
        //Fecha y hora del proceso
        'timestamp' => date('Y-m-d H:i:s')
    ]);



    //FUNCIONES AUXILIARES

    //Obtener ID del registro segun la tabla
    function obtenerIdRegistro($tabla_mysql, $datos_transformados) {
        $map = [
            'tbl_clientes_logisticos' => 'id_cliente',
            'centros_logisticos' => 'id_terminal',
            'unidades_transporte' => 'id_buque',
            'contenedores' => 'id_contenedor',
            'stock_carga' => 'id_inventario',
            'ordenes_envio' => 'id_embarque',
            'tbl_facturas_logisticas' => 'id_factura',
            'servicios_logisticos' => 'id_servicio',
            'factura_servicios' => 'id_factura', //Clave compuesta
            'movimientos_carga' => 'id_transferencia'
        ];
        
        //Caso especial: factura_servicios (clave compuesta)
        if ($tabla_mysql == 'factura_servicios') {
            if (isset($datos_transformados['id_factura']) && isset($datos_transformados['id_servicio'])) {
                return $datos_transformados['id_factura'] . '|' . $datos_transformados['id_servicio'];
            }
            return null;
        }
        
        $campo_id = $map[$tabla_mysql] ?? null;
        if ($campo_id && isset($datos_transformados[$campo_id])) {
            return $datos_transformados[$campo_id];
        }
        
        return null;
    }

    //Obtener campo ID en Oracle segun la tabla
    function getOracleIdField($oracleTable) {
        $map = [
            'CLIENTE_NAVIERA' => 'id_cliente',
            'TERMINAL_PORTUARIA' => 'id_terminal',
            'BUQUE_OPERACION' => 'id_buque',
            'CONTENEDOR_NAVIERO' => 'id_contenedor',
            'INVENTARIO_CARGA' => 'id_inventario',
            'EMBARQUE_MARITIMO' => 'id_embarque',
            'FACTURACION_EMBARQUE' => 'id_factura',
            'SERVICIO_PORTUARIO' => 'id_servicio',
            'DETALLE_FACTURA_SERVICIO' => null, //Clave compuesta
            'TRANSFERENCIA_CARGA' => 'id_transferencia'
        ];
        return $map[$oracleTable] ?? null;
    }

    //Eliminar registro en Oracle por ID
    function eliminarRegistroOraclePorID($conn, $oracleTable, $id) {
        //Caso especial: DETALLE_FACTURA_SERVICIO (clave compuesta)
        if ($oracleTable == 'DETALLE_FACTURA_SERVICIO') {
            $parts = explode('-', $id);
            if (count($parts) == 2) {
                $id_factura = $parts[0];
                $id_servicio = $parts[1];
                $sql = "DELETE FROM DETALLE_FACTURA_SERVICIO 
                        WHERE id_factura = :id_factura AND id_servicio = :id_servicio";
                $stmt = oci_parse($conn, $sql);
                oci_bind_by_name($stmt, ':id_factura', $id_factura);
                oci_bind_by_name($stmt, ':id_servicio', $id_servicio);
                return @oci_execute($stmt);
            }
            return false;
        }
        
        //Obtener el nombre de la columna ID para la tabla Oracle
        $idField = getOracleIdField($oracleTable);
        if (!$idField) {
            return false;
        }
        
        $sql = "DELETE FROM $oracleTable WHERE $idField = :id";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':id', $id);
        
        return @oci_execute($stmt);
    }

    //Contar registros pendientes
    function contarPendientes($conn) {
        $result = $conn->query(
            "SELECT COUNT(*) as total FROM bitacora_replicacion 
             WHERE estado_replicacion = 'PENDIENTE' AND origen_evento = 'MYSQL'"
        );
        if ($result) {
            $row = $result->fetch_assoc();
            return (int)($row['total'] ?? 0);
        }
        return 0;
    }

    //Registrar en logs
    function registrarLog($conn, $evento, $descripcion) {
        $stmt = $conn->prepare("INSERT INTO logs_replicacion (evento, descripcion, fecha) VALUES (?, ?, NOW())");
        $stmt->bind_param("ss", $evento, $descripcion);
        @$stmt->execute();
        $stmt->close();
    }


    //FUNCIONES DE TRANSFORMACIoN DE DATOS


    //TRANSFORMAR DATOS SEGuN DICCIONARIO DE HOMOLOGACIoN
    function transformarDatos($tabla_mysql, $datos, $tipo) {
        $resultado = [];
        
        switch ($tabla_mysql) {
            //CLIENTES
            case 'tbl_clientes_logisticos':
                $resultado['id_cliente'] = $datos['cliente_id'] ?? null;
                $resultado['nombre_cliente'] = $datos['nombre_contacto'] ?? null;
                $resultado['telefono'] = $datos['telefono_contacto'] ?? null;
                $resultado['correo'] = $datos['email_cliente'] ?? null;
                
                //nombre → codigo de 2 letras
                if (isset($datos['pais'])) {
                    $resultado['pais_origen'] = convertirNombrePaisACodigo($datos['pais']);
                }
                //DATETIME → DATE
                if (isset($datos['fecha_alta'])) {
            $resultado['fecha_registro'] = formatearFechaOracle($datos['fecha_alta']);
        }
        break;
                
            //CENTROS LOGiSTICOS
            case 'centros_logisticos':
                $resultado['id_terminal'] = $datos['terminal_id'] ?? null;
                $resultado['nombre_terminal'] = $datos['nombre_centro'] ?? null;
                $resultado['ciudad'] = $datos['municipio'] ?? null;
                
                //nombre → codigo de 2 letras
                if (isset($datos['pais_operacion'])) {
                    $resultado['pais'] = convertirNombrePaisACodigo($datos['pais_operacion']);
                }
                //ENUM → VARCHAR2
                if (isset($datos['categoria'])) {
                    $resultado['tipo_terminal'] = convertirCategoriaCentro($datos['categoria']);
                }
                
                $resultado['capacidad_contenedores'] = $datos['capacidad_maxima'] ?? 0;
                break;
                
            //UNIDADES DE TRANSPORTE
            case 'unidades_transporte':
                $resultado['id_buque'] = $datos['transporte_id'] ?? null;
                $resultado['nombre_buque'] = $datos['nombre_unidad'] ?? null;
                $resultado['capacidad_toneladas'] = $datos['capacidad_carga'] ?? 0;
                
                //nombre → codigo de 2 letras
                if (isset($datos['pais_bandera'])) {
                    $resultado['bandera'] = convertirNombrePaisACodigo($datos['pais_bandera']);
                }
                //BOOLEAN → CHAR(1)
                if (isset($datos['habilitado'])) {
                    $resultado['activo'] = $datos['habilitado'] ? 'S' : 'N';
                }
                break;
                
            //CONTENEDORES
            case 'contenedores':
                $resultado['id_contenedor'] = $datos['contenedor_id'] ?? null;
                $resultado['codigo_contenedor'] = $datos['codigo'] ?? null;
                //ENUM → VARCHAR2
                if (isset($datos['categoria'])) {
                    $resultado['tipo_contenedor'] = convertirCategoriaContenedor($datos['categoria']);
                }
                //BOOLEAN → CHAR(1)
                if (isset($datos['requiere_refrigeracion'])) {
                    $resultado['refrigerado'] = $datos['requiere_refrigeracion'] ? 'S' : 'N';
                }
                //ENUM → VARCHAR2
                if (isset($datos['estado'])) {
                    $resultado['estado_operacion'] = convertirEstadoContenedor($datos['estado']);
                }
                //DECIMAL → NUMBER
                if (isset($datos['capacidad_kg'])) {
                    $resultado['capacidad_kg'] = (float) $datos['capacidad_kg'];
                }
                break;
                
            //INVENTARIO
            case 'stock_carga':
                $resultado['id_inventario'] = $datos['inventario_id'] ?? null;
                $resultado['id_contenedor'] = $datos['contenedor_id'] ?? null;
                $resultado['id_terminal'] = $datos['centro_id'] ?? null;
                $resultado['peso_disponible'] = $datos['peso_actual'] ?? 0;
                //DATETIME → DATE
                if (isset($datos['fecha_modificacion'])) {
            $resultado['fecha_actualizacion'] = formatearFechaOracle($datos['fecha_modificacion']);
        }
                break;
                
            //EMBARQUES
            case 'ordenes_envio':
                $resultado['id_embarque'] = $datos['embarque_id'] ?? null;
                $resultado['id_cliente'] = $datos['cliente_id'] ?? null;
                $resultado['id_buque'] = $datos['transporte_id'] ?? null;
                $resultado['id_contenedor'] = $datos['contenedor_id'] ?? null;
                //DATETIME → DATE
                if (isset($datos['fecha_envio'])) {
            $resultado['fecha_salida'] = formatearFechaOracle($datos['fecha_envio']);
        }
                
                if (isset($datos['estatus'])) {
                    $resultado['estado_embarque'] = convertirEstadoEmbarque($datos['estatus']);
                }
                
                $resultado['prioridad'] = $datos['nivel_prioridad'] ?? 3;
                break;
                
            //FACTURAS
            case 'tbl_facturas_logisticas':
                $resultado['id_factura'] = $datos['factura_id'] ?? null;
                $resultado['id_embarque'] = $datos['embarque_id'] ?? null;
                
                if (isset($datos['fecha'])) {
            $resultado['fecha_emision'] = formatearFechaOracle($datos['fecha']);
        }
                
                $resultado['total_facturado'] = $datos['monto_total'] ?? 0;
                $resultado['observaciones'] = $datos['comentarios'] ?? null;
                break;
                
            //SERVICIOS LOGISTICOS
            case 'servicios_logisticos':
                $resultado['id_servicio'] = $datos['servicio_id'] ?? null;
                $resultado['descripcion_servicio'] = $datos['descripcion_servicio'] ?? null;
                $resultado['costo_base'] = $datos['costo_base'] ?? 0;
                //BOOLEAN → CHAR(1)
                if (isset($datos['requiere_autorizacion'])) {
                    $resultado['requiere_autorizacion'] = $datos['requiere_autorizacion'] ? 'S' : 'N';
                }
                
                if (isset($datos['categoria_servicio'])) {
                    $resultado['tipo_servicio'] = convertirCategoriaServicio($datos['categoria_servicio']);
                }
                break;
                
            //DETALLE FACTURA
            case 'factura_servicios':
                $resultado['id_factura'] = $datos['factura_id'] ?? null;
                $resultado['id_servicio'] = $datos['servicio_id'] ?? null;
                $resultado['cantidad'] = $datos['cantidad'] ?? 1;
                $resultado['subtotal'] = $datos['subtotal'] ?? 0;
                //BOOLEAN → CHAR(1)
                if (isset($datos['incluye_impuesto'])) {
                    $resultado['impuesto_aplicado'] = $datos['incluye_impuesto'] ? 'S' : 'N';
                }
                break;
                
            //MOVIMIENTOS DE CARGA
            case 'movimientos_carga':
                $resultado['id_transferencia'] = $datos['movimiento_id'] ?? null;
                $resultado['id_contenedor'] = $datos['contenedor_id'] ?? null;
                $resultado['terminal_origen'] = $datos['centro_origen'] ?? null;
                $resultado['terminal_destino'] = $datos['centro_destino'] ?? null;
                $resultado['peso_transferido'] = $datos['peso_movido'] ?? 0;
                
                if (isset($datos['fecha_movimiento'])) {
            $resultado['fecha_transferencia'] = formatearFechaOracle($datos['fecha_movimiento']);
        }
                break;
                
            default:
                //Si no hay mapeo, pasar datos tal cual
                $resultado = $datos;
        }
        
        return $resultado;
    }


    //FUNCIONES DE CONVERSIoN


    //Convertir nombre de pais a codigo de 2 letras
    function convertirNombrePaisACodigo($nombrePais) {
        //Mapeo completo de nombres de paises → codigos de 2 letras
        static $paises = [
            //America Latina
            'Honduras' => 'HN',
            'Guatemala' => 'GT',
            'El Salvador' => 'SV',
            'Nicaragua' => 'NI',
            'Costa Rica' => 'CR',
            'Panama' => 'PA',
            'Mexico' => 'MX',
            'Mexico' => 'MX',
            'Belice' => 'BZ',
            'Argentina' => 'AR',
            'Bolivia' => 'BO',
            'Brasil' => 'BR',
            'Brazil' => 'BR',
            'Chile' => 'CL',
            'Colombia' => 'CO',
            'Ecuador' => 'EC',
            'Paraguay' => 'PY',
            'Peru' => 'PE',
            'Peru' => 'PE',
            'Uruguay' => 'UY',
            'Venezuela' => 'VE',
            
            //Norteamerica
            'Estados Unidos' => 'US',
            'United States' => 'US',
            'USA' => 'US',
            'Canada' => 'CA',
            'Canada' => 'CA',
            
            //Europa
            'España' => 'ES',
            'Spain' => 'ES',
            'Reino Unido' => 'UK',
            'United Kingdom' => 'UK',
            'Alemania' => 'DE',
            'Germany' => 'DE',
            'Francia' => 'FR',
            'France' => 'FR',
            'Italia' => 'IT',
            'Italy' => 'IT',
            'Portugal' => 'PT',
            'Holanda' => 'NL',
            'Netherlands' => 'NL',
            'Belgica' => 'BE',
            'Belgium' => 'BE',
            'Suiza' => 'CH',
            'Switzerland' => 'CH',
            
            //Asia
            'Japon' => 'JP',
            'Japan' => 'JP',
            'China' => 'CN',
            'India' => 'IN',
            'Corea del Sur' => 'KR',
            'South Korea' => 'KR',
            
            //Oceania
            'Australia' => 'AU',
            'Nueva Zelanda' => 'NZ',
            'New Zealand' => 'NZ',
            
            //africa
            'Sudafrica' => 'ZA',
            'South Africa' => 'ZA',
            'Egipto' => 'EG',
            'Egypt' => 'EG',
        ];
        
        //Limpiar el valor
        $nombreLimpio = trim($nombrePais);
        
        //Si ya es un codigo de 2 letras, devolverlo en mayusculas
        if (strlen($nombreLimpio) == 2) {
            return strtoupper($nombreLimpio);
        }
        
        //Si es un codigo de 3 letras, truncar a 2
        if (strlen($nombreLimpio) == 3) {
            return strtoupper(substr($nombreLimpio, 0, 2));
        }
        
        //Buscar en el mapa de nombres completos
        if (isset($paises[$nombreLimpio])) {
            return $paises[$nombreLimpio];
        }
        
        //Buscar en el mapa con busqueda sin case sensitive
        $nombreLower = strtolower($nombreLimpio);
        foreach ($paises as $nombre => $codigo) {
            if (strtolower($nombre) === $nombreLower) {
                return $codigo;
            }
        }
        
        //Si no se encuentra, truncar a 2 letras (ultimo recurso)
        $resultado = strtoupper(substr($nombreLimpio, 0, 2));
        
        //Registrar en log que no se encontro el pais
        error_log("Pais no encontrado en el mapa: '$nombrePais' → se trunco a '$resultado'");
        
        return $resultado;
    }

    function convertirPais($valor) {
        return convertirNombrePaisACodigo($valor);
    }

    //Conversion de categoria de centro (ENUM → VARCHAR2)
    function convertirCategoriaCentro($categoria) {
        $map = [
            'Puerto' => 'Puerto',
            'Bodega' => 'Bodega',
            'Aduana' => 'Aduana'
        ];
        return $map[$categoria] ?? $categoria;
    }

    //Conversion de categoria de contenedor (ENUM → VARCHAR2)
    function convertirCategoriaContenedor($categoria) {
        $map = [
            'Seco' => 'Seco',
            'Refrigerado' => 'Refrigerado',
            'Tanque' => 'Tanque'
        ];
        return $map[$categoria] ?? $categoria;
    }

    //Conversion de estado de contenedor (ENUM → VARCHAR2)
    function convertirEstadoContenedor($estado) {
        $map = [
            'Disponible' => 'Disponible',
            'Retenido' => 'Retenido',
            'Mantenimiento' => 'Mantenimiento',
            'Inactivo' => 'Inactivo'
        ];
        return $map[$estado] ?? $estado;
    }

    //Conversion de estado de embarque (ENUM → VARCHAR2)
    function convertirEstadoEmbarque($estado) {
        $map = [
            'Pendiente' => 'Pendiente',
            'En transito' => 'EN_TRANSITO',
            'Entregado' => 'Entregado',
            'Cancelado' => 'Cancelado'
        ];
        return $map[$estado] ?? $estado;
    }

    //Conversion de categoria de servicio (ENUM → VARCHAR2)
    function convertirCategoriaServicio($categoria) {
        $map = [
            'Carga' => 'Carga',
            'Descarga' => 'Descarga',
            'Inspeccion' => 'Inspeccion',
            'Aduana' => 'Aduana'
        ];
        return $map[$categoria] ?? $categoria;
    }


    //FUNCIONES DE REPLICACIoN A ORACLE (INSERT/UPDATE)


    //Replicar registro (INSERT/UPDATE) en Oracle
    function replicarRegistroOracle($conn, $oracleTable, $datos, $mysqlTable) {
        //Eliminar campos null para evitar errores
        $datos = array_filter($datos, function($v) {
            return $v !== null;
        });
        
        if (empty($datos)) {
            error_log("⚠️ No hay datos para replicar en $oracleTable");
            return false;
        }
        
        //Validar campos requeridos segun tabla
        $camposRequeridos = [
            'CLIENTE_NAVIERA' => ['id_cliente', 'nombre_cliente'],
            'TERMINAL_PORTUARIA' => ['id_terminal', 'nombre_terminal'],
            'BUQUE_OPERACION' => ['id_buque', 'nombre_buque'],
            'CONTENEDOR_NAVIERO' => ['id_contenedor', 'codigo_contenedor'],
            'INVENTARIO_CARGA' => ['id_inventario', 'id_contenedor', 'id_terminal'],
            'EMBARQUE_MARITIMO' => ['id_embarque', 'id_cliente'],
            'FACTURACION_EMBARQUE' => ['id_factura', 'id_embarque'],
            'SERVICIO_PORTUARIO' => ['id_servicio', 'descripcion_servicio'],
            'DETALLE_FACTURA_SERVICIO' => ['id_factura', 'id_servicio'],
            'TRANSFERENCIA_CARGA' => ['id_transferencia', 'id_contenedor'],
        ];
        
        if (isset($camposRequeridos[$oracleTable])) {
            foreach ($camposRequeridos[$oracleTable] as $campo) {
                if (!isset($datos[$campo]) || empty($datos[$campo])) {
                    error_log("Campo requerido faltante: $campo en $oracleTable");
                    error_log("   Datos: " . json_encode($datos));
                    return false;
                }
            }
        }
        
        //Continuar con la replicacion segun la tabla
        switch ($oracleTable) {
            case 'CLIENTE_NAVIERA':
                return replicarClienteOracle($conn, $datos);
            case 'TERMINAL_PORTUARIA':
                return replicarTerminalOracle($conn, $datos);
            case 'BUQUE_OPERACION':
                return replicarBuqueOracle($conn, $datos);
            case 'CONTENEDOR_NAVIERO':
                return replicarContenedorOracle($conn, $datos);
            case 'INVENTARIO_CARGA':
                return replicarInventarioOracle($conn, $datos);
            case 'EMBARQUE_MARITIMO':
                return replicarEmbarqueOracle($conn, $datos);
            case 'FACTURACION_EMBARQUE':
                return replicarFacturaOracle($conn, $datos);
            case 'SERVICIO_PORTUARIO':
                return replicarServicioOracle($conn, $datos);
            case 'DETALLE_FACTURA_SERVICIO':
                return replicarDetalleFacturaOracle($conn, $datos);
            case 'TRANSFERENCIA_CARGA':
                return replicarTransferenciaOracle($conn, $datos);
            default:
                error_log("Tabla Oracle no soportada: $oracleTable");
                error_log("   Datos: " . json_encode($datos));
                return false;
        }
    }



    //FUNCIONES DE REPLICACIoN POR TABLA (INSERT/UPDATE)
    function replicarClienteOracle($conn, $datos) {
        $sql = "MERGE INTO CLIENTE_NAVIERA t
                USING (SELECT :id AS id FROM DUAL) s
                ON (t.id_cliente = s.id)
                WHEN MATCHED THEN
                    UPDATE SET 
                        nombre_cliente = :nombre,
                        telefono = :telefono,
                        correo = :correo,
                        pais_origen = :pais,
                        fecha_registro = TO_DATE(:fecha, 'YYYY-MM-DD HH24:MI:SS')
                WHEN NOT MATCHED THEN
                    INSERT (id_cliente, nombre_cliente, telefono, correo, pais_origen, fecha_registro)
                    VALUES (:id, :nombre, :telefono, :correo, :pais, TO_DATE(:fecha, 'YYYY-MM-DD HH24:MI:SS'))";
        
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':id', $datos['id_cliente']);
        oci_bind_by_name($stmt, ':nombre', $datos['nombre_cliente']);
        oci_bind_by_name($stmt, ':telefono', $datos['telefono']);
        oci_bind_by_name($stmt, ':correo', $datos['correo']);
        oci_bind_by_name($stmt, ':pais', $datos['pais_origen']);
        oci_bind_by_name($stmt, ':fecha', $datos['fecha_registro']);
        
        $result = oci_execute($stmt);
        
        if (!$result) {
            $e = oci_error($stmt);
            error_log("Error Oracle en CLIENTE_NAVIERA: " . ($e['message'] ?? 'Desconocido'));
            error_log("   Codigo: " . ($e['code'] ?? 'N/A'));
            error_log("   Datos: " . json_encode($datos));
            oci_free_statement($stmt);
            return false;
        }
        
        oci_free_statement($stmt);
        return true;
    }

    function replicarTerminalOracle($conn, $datos) {
        $sql = "MERGE INTO TERMINAL_PORTUARIA t
                USING (SELECT :id AS id FROM DUAL) s
                ON (t.id_terminal = s.id)
                WHEN MATCHED THEN
                    UPDATE SET 
                        nombre_terminal = :nombre,
                        ciudad = :ciudad,
                        pais = :pais,
                        tipo_terminal = :tipo,
                        capacidad_contenedores = :capacidad
                WHEN NOT MATCHED THEN
                    INSERT (id_terminal, nombre_terminal, ciudad, pais, tipo_terminal, capacidad_contenedores)
                    VALUES (:id, :nombre, :ciudad, :pais, :tipo, :capacidad)";
        
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':id', $datos['id_terminal']);
        oci_bind_by_name($stmt, ':nombre', $datos['nombre_terminal']);
        oci_bind_by_name($stmt, ':ciudad', $datos['ciudad']);
        oci_bind_by_name($stmt, ':pais', $datos['pais']);
        oci_bind_by_name($stmt, ':tipo', $datos['tipo_terminal']);
        oci_bind_by_name($stmt, ':capacidad', $datos['capacidad_contenedores']);
        
        $result = oci_execute($stmt);
        if (!$result) {
            $e = oci_error($stmt);
            error_log("Error Oracle en TERMINAL_PORTUARIA: " . ($e['message'] ?? 'Desconocido'));
            oci_free_statement($stmt);
            return false;
        }
        oci_free_statement($stmt);
        return true;
    }

    function replicarBuqueOracle($conn, $datos) {
        $sql = "MERGE INTO BUQUE_OPERACION t
                USING (SELECT :id AS id FROM DUAL) s
                ON (t.id_buque = s.id)
                WHEN MATCHED THEN
                    UPDATE SET 
                        nombre_buque = :nombre,
                        capacidad_toneladas = :capacidad,
                        bandera = :bandera,
                        activo = :activo
                WHEN NOT MATCHED THEN
                    INSERT (id_buque, nombre_buque, capacidad_toneladas, bandera, activo)
                    VALUES (:id, :nombre, :capacidad, :bandera, :activo)";
        
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':id', $datos['id_buque']);
        oci_bind_by_name($stmt, ':nombre', $datos['nombre_buque']);
        oci_bind_by_name($stmt, ':capacidad', $datos['capacidad_toneladas']);
        oci_bind_by_name($stmt, ':bandera', $datos['bandera']);
        oci_bind_by_name($stmt, ':activo', $datos['activo']);
        
        $result = oci_execute($stmt);
        if (!$result) {
            $e = oci_error($stmt);
            error_log("Error Oracle en BUQUE_OPERACION: " . ($e['message'] ?? 'Desconocido'));
            oci_free_statement($stmt);
            return false;
        }
        oci_free_statement($stmt);
        return true;
    }

    function replicarContenedorOracle($conn, $datos) {
        $sql = "MERGE INTO CONTENEDOR_NAVIERO t
                USING (SELECT :id AS id FROM DUAL) s
                ON (t.id_contenedor = s.id)
                WHEN MATCHED THEN
                    UPDATE SET 
                        codigo_contenedor = :codigo,
                        tipo_contenedor = :tipo,
                        refrigerado = :refrigerado,
                        estado_operacion = :estado,
                        capacidad_kg = :capacidad
                WHEN NOT MATCHED THEN
                    INSERT (id_contenedor, codigo_contenedor, tipo_contenedor, refrigerado, estado_operacion, capacidad_kg)
                    VALUES (:id, :codigo, :tipo, :refrigerado, :estado, :capacidad)";
        
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':id', $datos['id_contenedor']);
        oci_bind_by_name($stmt, ':codigo', $datos['codigo_contenedor']);
        oci_bind_by_name($stmt, ':tipo', $datos['tipo_contenedor']);
        oci_bind_by_name($stmt, ':refrigerado', $datos['refrigerado']);
        oci_bind_by_name($stmt, ':estado', $datos['estado_operacion']);
        oci_bind_by_name($stmt, ':capacidad', $datos['capacidad_kg']);
        
        $result = oci_execute($stmt);
        if (!$result) {
            $e = oci_error($stmt);
            error_log("Error Oracle en CONTENEDOR_NAVIERO: " . ($e['message'] ?? 'Desconocido'));
            oci_free_statement($stmt);
            return false;
        }
        oci_free_statement($stmt);
        return true;
    }

    function replicarInventarioOracle($conn, $datos) {
        $sql = "MERGE INTO INVENTARIO_CARGA t
                USING (SELECT :id AS id FROM DUAL) s
                ON (t.id_inventario = s.id)
                WHEN MATCHED THEN
                    UPDATE SET 
                        id_contenedor = :contenedor,
                        id_terminal = :terminal,
                        peso_disponible = :peso,
                        fecha_actualizacion = TO_DATE(:fecha, 'YYYY-MM-DD HH24:MI:SS')
                WHEN NOT MATCHED THEN
                    INSERT (id_inventario, id_contenedor, id_terminal, peso_disponible, fecha_actualizacion)
                    VALUES (:id, :contenedor, :terminal, :peso, TO_DATE(:fecha, 'YYYY-MM-DD HH24:MI:SS'))";
        
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':id', $datos['id_inventario']);
        oci_bind_by_name($stmt, ':contenedor', $datos['id_contenedor']);
        oci_bind_by_name($stmt, ':terminal', $datos['id_terminal']);
        oci_bind_by_name($stmt, ':peso', $datos['peso_disponible']);
        oci_bind_by_name($stmt, ':fecha', $datos['fecha_actualizacion']);
        
        $result = oci_execute($stmt);
        if (!$result) {
            $e = oci_error($stmt);
            error_log("Error Oracle en INVENTARIO_CARGA: " . ($e['message'] ?? 'Desconocido'));
            oci_free_statement($stmt);
            return false;
        }
        oci_free_statement($stmt);
        return true;
    }

    function replicarEmbarqueOracle($conn, $datos) {
        $sql = "MERGE INTO EMBARQUE_MARITIMO t
                USING (SELECT :id AS id FROM DUAL) s
                ON (t.id_embarque = s.id)
                WHEN MATCHED THEN
                    UPDATE SET 
                        id_cliente = :cliente,
                        id_buque = :buque,
                        id_contenedor = :contenedor,
                        fecha_salida = TO_DATE(:fecha, 'YYYY-MM-DD HH24:MI:SS'),
                        estado_embarque = :estado,
                        prioridad = :prioridad
                WHEN NOT MATCHED THEN
                    INSERT (id_embarque, id_cliente, id_buque, id_contenedor, fecha_salida, estado_embarque, prioridad)
                    VALUES (:id, :cliente, :buque, :contenedor, TO_DATE(:fecha, 'YYYY-MM-DD HH24:MI:SS'), :estado, :prioridad)";
        
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':id', $datos['id_embarque']);
        oci_bind_by_name($stmt, ':cliente', $datos['id_cliente']);
        oci_bind_by_name($stmt, ':buque', $datos['id_buque']);
        oci_bind_by_name($stmt, ':contenedor', $datos['id_contenedor']);
        oci_bind_by_name($stmt, ':fecha', $datos['fecha_salida']);
        oci_bind_by_name($stmt, ':estado', $datos['estado_embarque']);
        oci_bind_by_name($stmt, ':prioridad', $datos['prioridad']);
        
        $result = oci_execute($stmt);
        if (!$result) {
            $e = oci_error($stmt);
            error_log("Error Oracle en EMBARQUE_MARITIMO: " . ($e['message'] ?? 'Desconocido'));
            oci_free_statement($stmt);
            return false;
        }
        oci_free_statement($stmt);
        return true;
    }

    function replicarFacturaOracle($conn, $datos) {
        $sql = "MERGE INTO FACTURACION_EMBARQUE t
                USING (SELECT :id AS id FROM DUAL) s
                ON (t.id_factura = s.id)
                WHEN MATCHED THEN
                    UPDATE SET 
                        id_embarque = :embarque,
                        fecha_emision = TO_DATE(:fecha, 'YYYY-MM-DD HH24:MI:SS'),
                        total_facturado = :total,
                        observaciones = :obs
                WHEN NOT MATCHED THEN
                    INSERT (id_factura, id_embarque, fecha_emision, total_facturado, observaciones)
                    VALUES (:id, :embarque, TO_DATE(:fecha, 'YYYY-MM-DD HH24:MI:SS'), :total, :obs)";
        
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':id', $datos['id_factura']);
        oci_bind_by_name($stmt, ':embarque', $datos['id_embarque']);
        oci_bind_by_name($stmt, ':fecha', $datos['fecha_emision']);
        oci_bind_by_name($stmt, ':total', $datos['total_facturado']);
        oci_bind_by_name($stmt, ':obs', $datos['observaciones']);
        
        $result = oci_execute($stmt);
        if (!$result) {
            $e = oci_error($stmt);
            error_log("Error Oracle en FACTURACION_EMBARQUE: " . ($e['message'] ?? 'Desconocido'));
            oci_free_statement($stmt);
            return false;
        }
        oci_free_statement($stmt);
        return true;
    }

    function replicarServicioOracle($conn, $datos) {
        $sql = "MERGE INTO SERVICIO_PORTUARIO t
                USING (SELECT :id AS id FROM DUAL) s
                ON (t.id_servicio = s.id)
                WHEN MATCHED THEN
                    UPDATE SET 
                        descripcion_servicio = :descripcion,
                        costo_base = :costo,
                        requiere_autorizacion = :autorizacion,
                        tipo_servicio = :tipo
                WHEN NOT MATCHED THEN
                    INSERT (id_servicio, descripcion_servicio, costo_base, requiere_autorizacion, tipo_servicio)
                    VALUES (:id, :descripcion, :costo, :autorizacion, :tipo)";
        
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':id', $datos['id_servicio']);
        oci_bind_by_name($stmt, ':descripcion', $datos['descripcion_servicio']);
        oci_bind_by_name($stmt, ':costo', $datos['costo_base']);
        oci_bind_by_name($stmt, ':autorizacion', $datos['requiere_autorizacion']);
        oci_bind_by_name($stmt, ':tipo', $datos['tipo_servicio']);
        
        $result = oci_execute($stmt);
        if (!$result) {
            $e = oci_error($stmt);
            error_log("Error Oracle en SERVICIO_PORTUARIO: " . ($e['message'] ?? 'Desconocido'));
            oci_free_statement($stmt);
            return false;
        }
        oci_free_statement($stmt);
        return true;
    }

    function replicarDetalleFacturaOracle($conn, $datos) {
        $sql = "MERGE INTO DETALLE_FACTURA_SERVICIO t
                USING (SELECT :factura AS factura, :servicio AS servicio FROM DUAL) s
                ON (t.id_factura = s.factura AND t.id_servicio = s.servicio)
                WHEN MATCHED THEN
                    UPDATE SET 
                        cantidad = :cantidad,
                        subtotal = :subtotal,
                        impuesto_aplicado = :impuesto
                WHEN NOT MATCHED THEN
                    INSERT (id_factura, id_servicio, cantidad, subtotal, impuesto_aplicado)
                    VALUES (:factura, :servicio, :cantidad, :subtotal, :impuesto)";
        
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':factura', $datos['id_factura']);
        oci_bind_by_name($stmt, ':servicio', $datos['id_servicio']);
        oci_bind_by_name($stmt, ':cantidad', $datos['cantidad']);
        oci_bind_by_name($stmt, ':subtotal', $datos['subtotal']);
        oci_bind_by_name($stmt, ':impuesto', $datos['impuesto_aplicado']);
        
        $result = oci_execute($stmt);
        if (!$result) {
            $e = oci_error($stmt);
            error_log("Error Oracle en DETALLE_FACTURA_SERVICIO: " . ($e['message'] ?? 'Desconocido'));
            oci_free_statement($stmt);
            return false;
        }
        oci_free_statement($stmt);
        return true;
    }

    function replicarTransferenciaOracle($conn, $datos) {
        $sql = "MERGE INTO TRANSFERENCIA_CARGA t
                USING (SELECT :id AS id FROM DUAL) s
                ON (t.id_transferencia = s.id)
                WHEN MATCHED THEN
                    UPDATE SET 
                        id_contenedor = :contenedor,
                        terminal_origen = :origen,
                        terminal_destino = :destino,
                        peso_transferido = :peso,
                        fecha_transferencia = TO_DATE(:fecha, 'YYYY-MM-DD HH24:MI:SS')
                WHEN NOT MATCHED THEN
                    INSERT (id_transferencia, id_contenedor, terminal_origen, terminal_destino, peso_transferido, fecha_transferencia)
                    VALUES (:id, :contenedor, :origen, :destino, :peso, TO_DATE(:fecha, 'YYYY-MM-DD HH24:MI:SS'))";
        
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':id', $datos['id_transferencia']);
        oci_bind_by_name($stmt, ':contenedor', $datos['id_contenedor']);
        oci_bind_by_name($stmt, ':origen', $datos['terminal_origen']);
        oci_bind_by_name($stmt, ':destino', $datos['terminal_destino']);
        oci_bind_by_name($stmt, ':peso', $datos['peso_transferido']);
        oci_bind_by_name($stmt, ':fecha', $datos['fecha_transferencia']);
        
        $result = oci_execute($stmt);
        if (!$result) {
            $e = oci_error($stmt);
            error_log("Error Oracle en TRANSFERENCIA_CARGA: " . ($e['message'] ?? 'Desconocido'));
            oci_free_statement($stmt);
            return false;
        }
        oci_free_statement($stmt);
        return true;
    }

    //Formatear fecha para Oracle
    function formatearFechaOracle($fecha) {
        if (empty($fecha)) {
            return null;
        }
        
        $timestamp = null;
        
        //Formato con microsegundos
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d+$/', $fecha)) {
            $fecha = preg_replace('/\.\d+$/', '', $fecha);
            $timestamp = strtotime($fecha);
        }
        
        //Formato estandar
        if (!$timestamp) {
            $timestamp = strtotime($fecha);
        }
        
        if (!$timestamp) {
            return $fecha;
        }
        
        return date('Y-m-d H:i:s', $timestamp);
    }

    function insertarEnBitacoraOracle($conn, $tabla_oracle, $tipo, $id_registro, $datos) {
            $checkBitacora = queryOracle("SELECT table_name FROM user_tables WHERE table_name = 'BITACORA'");
            
            if (isset($checkBitacora['error']) || empty($checkBitacora)) {
                //BITACORA no existe, intentar con LOGS_REPLICACION_ORACLE
                $descripcion = "Replicado: $tabla_oracle - ID: $id_registro - Tipo: $tipo";
                $sql = "INSERT INTO LOGS_REPLICACION_ORACLE (evento, descripcion, fecha) 
                        VALUES ('REPLICADO', :descripcion, SYSTIMESTAMP)";
                
                $stmt = oci_parse($conn, $sql);
                oci_bind_by_name($stmt, ':descripcion', $descripcion);
                $result = oci_execute($stmt);
                oci_free_statement($stmt);
                
                return $result;
            }
            
            // Convertir tipo_operacion a CHAR(1)
            $tipo_char = '';
            if ($tipo == 'INSERT') $tipo_char = 'I';
            elseif ($tipo == 'UPDATE') $tipo_char = 'U';
            elseif ($tipo == 'DELETE') $tipo_char = 'D';
            else $tipo_char = 'I';
            
            // Convertir datos a JSON
            $datos_json = json_encode($datos);
            
            // Generar hash
            $hash = hash('sha256', $tabla_oracle . $id_registro . json_encode($datos));
            
            $sql = "INSERT INTO BITACORA (
                        id_registro,
                        tabla_afectada,
                        tipo_operacion,
                        fecha_hora,
                        datos_json,
                        usuario_bd,
                        ip_origen,
                        origen_evento,
                        hash_registro,
                        version_registro,
                        estado_replicacion,
                        intentos_replicacion
                    ) VALUES (
                        :id_registro,
                        :tabla_afectada,
                        :tipo_operacion,
                        SYSTIMESTAMP,
                        :datos_json,
                        USER,
                        SYS_CONTEXT('USERENV', 'IP_ADDRESS'),
                        'MYSQL',
                        :hash_registro,
                        1,
                        'REPLICADO',
                        0
                    )";
            
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':id_registro', $id_registro);
            oci_bind_by_name($stmt, ':tabla_afectada', $tabla_oracle);
            oci_bind_by_name($stmt, ':tipo_operacion', $tipo_char);
            oci_bind_by_name($stmt, ':datos_json', $datos_json);
            oci_bind_by_name($stmt, ':hash_registro', $hash);
            
            $result = oci_execute($stmt);
            oci_free_statement($stmt);
            
            return $result;
        }


    //Actualizar checkpoint de replicacion
    function actualizarCheckpoint($conn_mysql, $ultimo_id) {
        $sql = "UPDATE control_replicacion 
                SET ultimo_id_procesado = ?, ultima_ejecucion = NOW()
                WHERE sistema_origen = 'MYSQL' AND sistema_destino = 'ORACLE'";
        $stmt = $conn_mysql->prepare($sql);
        $stmt->bind_param("i", $ultimo_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    //CERRAR CONEXIONES
    if (isset($conn_mysql)) $conn_mysql->close();
    if (isset($conn_oracle)) oci_close($conn_oracle);
    ?>