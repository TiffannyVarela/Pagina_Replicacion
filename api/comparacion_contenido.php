    <?php
    /*
    CONFIGURACION INICIAL
    Incluir el archivo de configuracion de la base de datos
    */
    require_once '../config/db.php';

    /*
    FUNCION DE DEPURACION PARA COMPARAR CAMPOS
    */
    function debugComparison($mysqlData, $oracleData, $tableName) {
        $debug = [];
        
        //Tomar solo los primeros 5 registros para depuración
        $mysqlSample = array_slice($mysqlData, 0, 5, true);
        $oracleSample = array_slice($oracleData, 0, 5, true);
        
        $debug['table'] = $tableName;
        $debug['mysql_count'] = count($mysqlData);
        $debug['oracle_count'] = count($oracleData);
        $debug['mysql_sample_keys'] = array_keys($mysqlSample);
        $debug['oracle_sample_keys'] = array_keys($oracleSample);
        $debug['mysql_sample_data'] = $mysqlSample;
        $debug['oracle_sample_data'] = $oracleSample;
        
        //Verificar si hay claves comunes
        $commonKeys = array_intersect(array_keys($mysqlData), array_keys($oracleData));
        $debug['common_keys_count'] = count($commonKeys);
        $debug['common_keys_sample'] = array_slice($commonKeys, 0, 5);
        
        return $debug;
    }

    /*
    FUNCION DE NORMALIZACION DE VALORES
    Convierte valores de diferentes formatos a un formato estandar para comparacion
    */
    function normalizeValue($value, $field) {
        //Si es null, devolver 'NULL'
        if ($value === null || $value === 'NULL') {
            return 'NULL';
        }
        
        $str = trim(strval($value));
        
        //Si esta vacio, devolver 'NULL'
        if ($str === '') {
            return 'NULL';
        }
        
        
        //NORMALIZAR BOOLEANOS
        
        if (in_array(strtoupper($str), ['S', 'Y', 'TRUE', '1', 'T', 'SI'])) {
            return 'TRUE';
        }
        if (in_array(strtoupper($str), ['N', 'F', 'FALSE', '0', 'NO'])) {
            return 'FALSE';
        }
        
        
        //NORMALIZAR PAISES (nombre completo → codigo)
        
        $paises = [
            //America Latina
            'Honduras' => 'HN',
            'Guatemala' => 'GT',
            'El Salvador' => 'SV',
            'Nicaragua' => 'NI',
            'Costa Rica' => 'CR',
            'Panama' => 'PA',
            'Mexico' => 'MX',
            'México' => 'MX',
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
            'Perú' => 'PE',
            'Uruguay' => 'UY',
            'Venezuela' => 'VE',
            
            //Norteamerica
            'Estados Unidos' => 'US',
            'United States' => 'US',
            'USA' => 'US',
            'Canada' => 'CA',
            'Canadá' => 'CA',
            
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
            'Bélgica' => 'BE',
            'Belgium' => 'BE',
            'Suiza' => 'CH',
            'Switzerland' => 'CH',
            
            //Asia
            'Japon' => 'JP',
            'Japón' => 'JP',
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
            'Sudáfrica' => 'ZA',
            'South Africa' => 'ZA',
            'Egipto' => 'EG',
            'Egypt' => 'EG',
        ];
        
        //Si el campo es de pais, normalizar a codigo de 2 letras
        if (strpos(strtolower($field), 'pais') !== false || 
            strpos(strtolower($field), 'pais_') !== false ||
            strpos(strtolower($field), 'bandera') !== false ||
            strpos(strtolower($field), 'country') !== false) {
            //Si ya es un codigo de 2 letras, devolverlo en mayusculas
            if (strlen($str) == 2 && ctype_alpha($str)) {
                return strtoupper($str);
            }
            //Buscar en el mapa de paises
            if (isset($paises[$str])) {
                return $paises[$str];
            }
            //Buscar insensible a mayusculas
            foreach ($paises as $nombre => $codigo) {
                if (strtolower($nombre) === strtolower($str)) {
                    return $codigo;
                }
            }
            //Si no se encuentra, devolver el original
            return $str;
        }
        
        
        //NORMALIZAR NUMEROS (eliminar .00)
        
        if (is_numeric($str)) {
            //Si es un numero con decimales .00, convertirlo a entero
            if (preg_match('/^\d+\.00$/', $str)) {
                return strval(intval($str));
            }
            //Si es un numero, mantenerlo como esta
            return $str;
        }
        
        
        //NORMALIZAR FECHAS
        
        if (strpos(strtolower($field), 'fecha') !== false || 
            strpos(strtolower($field), 'date') !== false ||
            strpos(strtolower($field), 'time') !== false) {
            $timestamp = strtotime($str);
            if ($timestamp !== false) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        }
        
        
        //NORMALIZAR ESTADOS DE EMBARQUE
        
        $estados = [
            'En transito' => 'EN_TRANSITO',
            'En tránsito' => 'EN_TRANSITO',
            'Entregado' => 'ENTREGADO',
            'Pendiente' => 'PENDIENTE',
            'Cancelado' => 'CANCELADO',
            'EN_TRANSITO' => 'EN_TRANSITO',
            'ENTREGADO' => 'ENTREGADO',
            'PENDIENTE' => 'PENDIENTE',
            'CANCELADO' => 'CANCELADO',
        ];
        if (isset($estados[$str])) {
            return $estados[$str];
        }
        
        
        //NORMALIZAR CATEGORIAS DE CONTENEDOR
        
        $categorias = [
            'Seco' => 'SECO',
            'Refrigerado' => 'REFRIGERADO',
            'Tanque' => 'TANQUE',
            'SECO' => 'SECO',
            'REFRIGERADO' => 'REFRIGERADO',
            'TANQUE' => 'TANQUE',
        ];
        if (isset($categorias[$str])) {
            return $categorias[$str];
        }
        
        
        //NORMALIZAR ESTADOS DE CONTENEDOR
        
        $estadosContenedor = [
            'Disponible' => 'DISPONIBLE',
            'Retenido' => 'RETENIDO',
            'Mantenimiento' => 'MANTENIMIENTO',
            'Inactivo' => 'INACTIVO',
            'DISPONIBLE' => 'DISPONIBLE',
            'RETENIDO' => 'RETENIDO',
            'MANTENIMIENTO' => 'MANTENIMIENTO',
            'INACTIVO' => 'INACTIVO',
        ];
        if (isset($estadosContenedor[$str])) {
            return $estadosContenedor[$str];
        }
        
        
        //NORMALIZAR SI/NO
        
        if (in_array(strtoupper($str), ['S', 'SI', 'Y', 'YES', '1', 'TRUE', 'T'])) {
            return 'S';
        }
        if (in_array(strtoupper($str), ['N', 'NO', 'FALSE', '0', 'F'])) {
            return 'N';
        }
        
        return $str;
    }

    //Devuelve datos en formato JSON
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');

    /*
    MAPEO DE TABLAS: MySQL ↔ Oracle
    */
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

    /*
    MAPEO DE CAMPOS PARA COMPARACION DE CONTENIDO
    */
    $fieldMapping = [
        'tbl_clientes_logisticos' => [
            'mysql_fields' => ['cliente_id', 'nombre_contacto', 'telefono_contacto', 'email_cliente', 'pais', 'fecha_alta'],
            'oracle_fields' => ['ID_CLIENTE', 'NOMBRE_CLIENTE', 'TELEFONO', 'CORREO', 'PAIS_ORIGEN', 'FECHA_REGISTRO'],
            'id_field' => 'cliente_id',
            'oracle_id_field' => 'ID_CLIENTE'
        ],
        'centros_logisticos' => [
            'mysql_fields' => ['terminal_id', 'nombre_centro', 'municipio', 'pais_operacion', 'categoria', 'capacidad_maxima'],
            'oracle_fields' => ['ID_TERMINAL', 'NOMBRE_TERMINAL', 'CIUDAD', 'PAIS', 'TIPO_TERMINAL', 'CAPACIDAD_CONTENEDORES'],
            'id_field' => 'terminal_id',
            'oracle_id_field' => 'ID_TERMINAL'
        ],
        'unidades_transporte' => [
            'mysql_fields' => ['transporte_id', 'nombre_unidad', 'capacidad_carga', 'pais_bandera', 'habilitado'],
            'oracle_fields' => ['ID_BUQUE', 'NOMBRE_BUQUE', 'CAPACIDAD_TONELADAS', 'BANDERA', 'ACTIVO'],
            'id_field' => 'transporte_id',
            'oracle_id_field' => 'ID_BUQUE'
        ],
        'contenedores' => [
            'mysql_fields' => ['contenedor_id', 'codigo', 'categoria', 'capacidad_kg', 'requiere_refrigeracion', 'estado'],
            'oracle_fields' => ['ID_CONTENEDOR', 'CODIGO_CONTENEDOR', 'TIPO_CONTENEDOR', 'CAPACIDAD_KG', 'REFRIGERADO', 'ESTADO_OPERACION'],
            'id_field' => 'contenedor_id',
            'oracle_id_field' => 'ID_CONTENEDOR'
        ],
        'stock_carga' => [
            'mysql_fields' => ['inventario_id', 'contenedor_id', 'centro_id', 'peso_actual', 'fecha_modificacion'],
            'oracle_fields' => ['ID_INVENTARIO', 'ID_CONTENEDOR', 'ID_TERMINAL', 'PESO_DISPONIBLE', 'FECHA_ACTUALIZACION'],
            'id_field' => 'inventario_id',
            'oracle_id_field' => 'ID_INVENTARIO'
        ],
        'ordenes_envio' => [
            'mysql_fields' => ['embarque_id', 'cliente_id', 'transporte_id', 'contenedor_id', 'fecha_envio', 'estatus', 'nivel_prioridad'],
            'oracle_fields' => ['ID_EMBARQUE', 'ID_CLIENTE', 'ID_BUQUE', 'ID_CONTENEDOR', 'FECHA_SALIDA', 'ESTADO_EMBARQUE', 'PRIORIDAD'],
            'id_field' => 'embarque_id',
            'oracle_id_field' => 'ID_EMBARQUE'
        ],
        'tbl_facturas_logisticas' => [
            'mysql_fields' => ['factura_id', 'embarque_id', 'fecha', 'monto_total', 'comentarios'],
            'oracle_fields' => ['ID_FACTURA', 'ID_EMBARQUE', 'FECHA_EMISION', 'TOTAL_FACTURADO', 'OBSERVACIONES'],
            'id_field' => 'factura_id',
            'oracle_id_field' => 'ID_FACTURA'
        ],
        'servicios_logisticos' => [
            'mysql_fields' => ['servicio_id', 'descripcion_servicio', 'costo_base', 'requiere_autorizacion', 'categoria_servicio'],
            'oracle_fields' => ['ID_SERVICIO', 'DESCRIPCION_SERVICIO', 'COSTO_BASE', 'REQUIERE_AUTORIZACION', 'TIPO_SERVICIO'],
            'id_field' => 'servicio_id',
            'oracle_id_field' => 'ID_SERVICIO'
        ],
        'factura_servicios' => [
            'mysql_fields' => ['factura_id', 'servicio_id', 'cantidad', 'subtotal', 'incluye_impuesto'],
            'oracle_fields' => ['ID_FACTURA', 'ID_SERVICIO', 'CANTIDAD', 'SUBTOTAL', 'IMPUESTO_APLICADO'],
            'id_field' => 'factura_id',
            'oracle_id_field' => 'ID_FACTURA',
            'composite_key' => true,
            'composite_fields' => ['factura_id', 'servicio_id'],
            'oracle_composite_fields' => ['ID_FACTURA', 'ID_SERVICIO']
        ],
        'movimientos_carga' => [
            'mysql_fields' => ['movimiento_id', 'contenedor_id', 'centro_origen', 'centro_destino', 'peso_movido', 'fecha_movimiento'],
            'oracle_fields' => ['ID_TRANSFERENCIA', 'ID_CONTENEDOR', 'TERMINAL_ORIGEN', 'TERMINAL_DESTINO', 'PESO_TRANSFERIDO', 'FECHA_TRANSFERENCIA'],
            'id_field' => 'movimiento_id',
            'oracle_id_field' => 'ID_TRANSFERENCIA'
        ]
    ];

    $response = [
        'mysql_online' => isMySQLConnected(),
        'oracle_online' => isOracleConnected(),
        'comparison' => [],
        'summary' => [
            'total_tables' => 0,
            'synced_tables' => 0,
            'differences_found' => 0,
            'total_records_compared' => 0,
            'only_in_mysql_total' => 0,
            'only_in_oracle_total' => 0
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];

    if (!$response['mysql_online'] || !$response['oracle_online']) {
        echo json_encode($response);
        exit;
    }

    $conn_mysql = getMySQLConnection();

    foreach ($tableMapping as $mysqlTable => $oracleTable) {
        //Verificar si hay mapeo de campos para esta tabla
        if (!isset($fieldMapping[$mysqlTable])) {
            continue;
        }
        
        $mapping = $fieldMapping[$mysqlTable];
        $mysqlFields = $mapping['mysql_fields'];
        $oracleFields = $mapping['oracle_fields'];
        $idField = $mapping['id_field'];
        $oracleIdField = $mapping['oracle_id_field'];
        $isComposite = isset($mapping['composite_key']) && $mapping['composite_key'] === true;
        $compositeFields = $mapping['composite_fields'] ?? [];
        $oracleCompositeFields = $mapping['oracle_composite_fields'] ?? [];
        
        
        //OBTENER DATOS DE MYSQL
        
        $mysqlData = [];
        $sql = "SELECT " . implode(', ', $mysqlFields) . " FROM $mysqlTable";
        $result = $conn_mysql->query($sql);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $hashString = '';
                foreach ($mysqlFields as $field) {
                    //Aplicar normalizacion al valor
                    $value = normalizeValue($row[$field] ?? 'NULL', $field);
                    $hashString .= $value . '|';
                }
                
                //Construir clave única (simple o compuesta)
                if ($isComposite) {
                    $key = '';
                    foreach ($compositeFields as $field) {
                        $key .= ($row[$field] ?? 'NULL') . '|';
                    }
                    $key = rtrim($key, '|');
                } else {
                    $key = $row[$idField] ?? null;
                }
                
                if ($key !== null) {
                    $mysqlData[$key] = [
                        'data' => $row,
                        'hash' => md5($hashString)
                    ];
                }
            }
        }
        
        
        //OBTENER DATOS DE ORACLE
        
        $oracleData = [];
        $sql = "SELECT " . implode(', ', $oracleFields) . " FROM $oracleTable";
        $result = queryOracle($sql);
        
        if (!isset($result['error']) && !empty($result)) {
            foreach ($result as $row) {
                $hashString = '';
                foreach ($oracleFields as $field) {
                    //Aplicar normalizacion al valor
                    $value = normalizeValue($row[$field] ?? 'NULL', $field);
                    $hashString .= $value . '|';
                }
                
                //Construir clave única (simple o compuesta)
                if ($isComposite) {
                    $key = '';
                    foreach ($oracleCompositeFields as $field) {
                        $key .= ($row[$field] ?? 'NULL') . '|';
                    }
                    $key = rtrim($key, '|');
                } else {
                    $key = $row[$oracleIdField] ?? null;
                }
                
                if ($key !== null) {
                    $oracleData[$key] = [
                        'data' => $row,
                        'hash' => md5($hashString)
                    ];
                }
            }
        }
        
        
        //COMPARAR DATOS
        
        $mysqlIds = array_keys($mysqlData);
        $oracleIds = array_keys($oracleData);
        $commonIds = array_intersect($mysqlIds, $oracleIds);

        //Mostrar información de la comparación
        $debugInfo = debugComparison($mysqlData, $oracleData, $mysqlTable);

        //Comparar contenido de registros comunes
        $differences = [];
        $diffCount = 0;
        $matchedCount = 0;

        foreach ($commonIds as $id) {
            if ($mysqlData[$id]['hash'] !== $oracleData[$id]['hash']) {
                $diffCount++;
                
                //Mostrar diferencias específicas
                $mysqlRow = $mysqlData[$id]['data'];
                $oracleRow = $oracleData[$id]['data'];
                
                //Encontrar qué campos son diferentes
                $fieldDiffs = [];
                foreach ($mysqlRow as $field => $value) {
                    $mysqlVal = $value ?? 'NULL';
                    //Buscar el campo correspondiente en Oracle
                    $oracleField = null;
                    foreach ($oracleRow as $ofield => $ovalue) {
                        if (strtoupper($field) == strtoupper($ofield) || 
                            strpos(strtoupper($ofield), strtoupper($field)) !== false) {
                            $oracleField = $ofield;
                            break;
                        }
                    }
                    if ($oracleField) {
                        $oracleVal = $oracleRow[$oracleField] ?? 'NULL';
                        //Comparar valores normalizados
                        $mysqlNormalized = normalizeValue($mysqlVal, $field);
                        $oracleNormalized = normalizeValue($oracleVal, $oracleField);
                        if ($mysqlNormalized !== $oracleNormalized) {
                            $fieldDiffs[$field] = [
                                'mysql' => $mysqlVal,
                                'oracle' => $oracleVal,
                                'mysql_normalized' => $mysqlNormalized,
                                'oracle_normalized' => $oracleNormalized,
                                'oracle_field' => $oracleField
                            ];
                        }
                    }
                }
                
                //Solo agregar si hay diferencias reales
                if (!empty($fieldDiffs)) {
                    $differences[] = [
                        'id' => $id,
                        'mysql_data' => $mysqlRow,
                        'oracle_data' => $oracleRow,
                        'field_differences' => $fieldDiffs
                    ];
                } else {
                    //Si no hay diferencias de campo, pero el hash es diferente, es un falso positivo
                    //Lo marcamos como match
                    $matchedCount++;
                }
            } else {
                $matchedCount++;
            }
        }

        //Re-calcular diffCount basado en diferencias reales
        $diffCount = count($differences);

        $onlyInMysql = count(array_diff($mysqlIds, $oracleIds));
        $onlyInOracle = count(array_diff($oracleIds, $mysqlIds));
        $synced = ($onlyInMysql == 0 && $onlyInOracle == 0 && $diffCount == 0);

        //Agregar informacion de depuracion a la respuesta
        $comparison = [
            'mysql_table' => $mysqlTable,
            'oracle_table' => $oracleTable,
            'mysql_count' => count($mysqlData),
            'oracle_count' => count($oracleData),
            'records_in_both' => count($commonIds),
            'only_in_mysql' => $onlyInMysql,
            'only_in_oracle' => $onlyInOracle,
            'different_content' => $diffCount,
            'matched_content' => $matchedCount,
            'synced' => $synced,
            'sync_percentage' => count($commonIds) > 0 ? round((count($commonIds) - $diffCount) /count($commonIds) * 100, 2) : 0,
            'differences' => $differences,
            //INFORMACION DE DEPURACION
            'debug' => $debugInfo,
            'sample_differences' => array_slice($differences, 0, 3)
        ];

        $response['comparison'][] = $comparison;
        
        //Actualizar resumen
        $response['summary']['total_tables']++;
        if ($synced) {
            $response['summary']['synced_tables']++;
        }
        $response['summary']['differences_found'] += $diffCount;
        $response['summary']['total_records_compared'] += count($commonIds);
        $response['summary']['only_in_mysql_total'] += $onlyInMysql;
        $response['summary']['only_in_oracle_total'] += $onlyInOracle;
    }

    echo json_encode($response);
    ?>