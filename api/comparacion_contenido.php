<?php
/*
CONFIGURACION INICIAL
Incluir el archivo de configuracion de la base de datos
*/
require_once '../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

/*
FUNCION PARA LEER CLOB DE ORACLE
*/
function leerCLOB($clob) {
    if ($clob === null) return 'NULL';
    if (is_string($clob)) return $clob;
    if (is_object($clob) && method_exists($clob, 'load')) {
        try { 
            $content = $clob->load();
            return $content !== false ? $content : 'NULL';
        } catch (Exception $e) { 
            return 'NULL'; 
        }
    }
    return 'NULL';
}

/*
FUNCION DE NORMALIZACION DE VALORES
*/
function normalizeValue($value, $field) {
    
    if ($value === null || $value === 'NULL' || $value === '') {
        return 'NULL';
    }
    
    //Si es un objeto OCILob, leerlo
    if (is_object($value) && method_exists($value, 'load')) {
        try {
            $value = $value->load();
        } catch (Exception $e) {
            return 'NULL';
        }
    }
    
    $str = trim(strval($value));
    if ($str === '') {
        return 'NULL';
    }
    
    //Normalizar fechas Oracle (DD-MON-YYYY)
    if (preg_match('/^(\d{1,2})-([A-Z]{3})-(\d{2,4})$/', $str, $matches)) {
        $meses = ['JAN'=>'01','FEB'=>'02','MAR'=>'03','APR'=>'04','MAY'=>'05','JUN'=>'06','JUL'=>'07','AUG'=>'08','SEP'=>'09','OCT'=>'10','NOV'=>'11','DEC'=>'12'];
        $mes = $meses[strtoupper($matches[2])] ?? '01';
        $year = $matches[3];
        if (strlen($year) == 2) {
            $year = ($year >= 70) ? '19' . $year : '20' . $year;
        }
        return $year . '-' . $mes . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
    }
    
    //Normalizar fechas/hora Oracle (DD-MON-YYYY HH24:MI:SS)
    if (preg_match('/^(\d{1,2})-([A-Z]{3})-(\d{2,4}) (\d{2}):(\d{2}):(\d{2})$/', $str, $matches)) {
        $meses = ['JAN'=>'01','FEB'=>'02','MAR'=>'03','APR'=>'04','MAY'=>'05','JUN'=>'06','JUL'=>'07','AUG'=>'08','SEP'=>'09','OCT'=>'10','NOV'=>'11','DEC'=>'12'];
        $mes = $meses[strtoupper($matches[2])] ?? '01';
        $year = $matches[3];
        if (strlen($year) == 2) {
            $year = ($year >= 70) ? '19' . $year : '20' . $year;
        }
        return $year . '-' . $mes . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT) . 
               ' ' . $matches[4] . ':' . $matches[5] . ':' . $matches[6];
    }
    
    //Booleanos
    $strUpper = strtoupper($str);
    if (in_array($strUpper, ['S', 'Y', 'TRUE', '1', 'T', 'SI', 'YES'])) {
        return 'TRUE';
    }
    if (in_array($strUpper, ['N', 'F', 'FALSE', '0', 'NO'])) {
        return 'FALSE';
    }
    
    //Países
    $paises = [
        'Honduras' => 'HN', 'Guatemala' => 'GT', 'El Salvador' => 'SV',
        'Nicaragua' => 'NI', 'Costa Rica' => 'CR', 'Panama' => 'PA',
        'Mexico' => 'MX', 'Mexico' => 'MX', 'Belice' => 'BZ',
        'Argentina' => 'AR', 'Bolivia' => 'BO', 'Brasil' => 'BR',
        'Brazil' => 'BR', 'Chile' => 'CL', 'Colombia' => 'CO',
        'Ecuador' => 'EC', 'Paraguay' => 'PY', 'Peru' => 'PE',
        'Peru' => 'PE', 'Uruguay' => 'UY', 'Venezuela' => 'VE',
        'Estados Unidos' => 'US', 'United States' => 'US', 'USA' => 'US',
        'Canada' => 'CA', 'Canada' => 'CA', 'España' => 'ES',
        'Spain' => 'ES', 'Reino Unido' => 'UK', 'United Kingdom' => 'UK',
        'Alemania' => 'DE', 'Germany' => 'DE', 'Francia' => 'FR',
        'France' => 'FR', 'Italia' => 'IT', 'Italy' => 'IT',
        'Portugal' => 'PT', 'Holanda' => 'NL', 'Netherlands' => 'NL',
        'Belgica' => 'BE', 'Belgica' => 'BE', 'Belgium' => 'BE',
        'Suiza' => 'CH', 'Switzerland' => 'CH', 'Japon' => 'JP',
        'Japon' => 'JP', 'Japan' => 'JP', 'China' => 'CN',
        'India' => 'IN', 'Corea del Sur' => 'KR', 'South Korea' => 'KR',
        'Australia' => 'AU', 'Nueva Zelanda' => 'NZ', 'New Zealand' => 'NZ',
        'Sudafrica' => 'ZA', 'Sudafrica' => 'ZA', 'South Africa' => 'ZA',
        'Egipto' => 'EG', 'Egypt' => 'EG', 'Israel' => 'IL', 
        'Taiwan' => 'TW', 'Singapur' => 'SG', 'Dinamarca' => 'DK', 
        'Suiza' => 'CH', 'LR' => 'LR', 'MH' => 'MH'
    ];
    
    $paisFields = ['pais', 'pais_', 'bandera', 'country', 'origen', 'operacion'];
    foreach ($paisFields as $pf) {
        if (strpos(strtolower($field), $pf) !== false) {
            if (strlen($str) == 2 && ctype_alpha($str)) {
                return strtoupper($str);
            }
            if (isset($paises[$str])) {
                return $paises[$str];
            }
            foreach ($paises as $nombre => $codigo) {
                if (strtolower($nombre) === strtolower($str)) {
                    return $codigo;
                }
            }
            return $str;
        }
    }
    
    //Números
    if (is_numeric($str)) {
        if (preg_match('/^\d+\.00$/', $str)) {
            return strval(intval($str));
        }
        if (preg_match('/^\d+$/', $str)) {
            return $str;
        }
        return $str;
    }
    
    //Estados de embarque
    $estados = [
        'En transito' => 'EN_TRANSITO', 'En tránsito' => 'EN_TRANSITO',
        'Entregado' => 'ENTREGADO', 'Pendiente' => 'PENDIENTE',
        'Cancelado' => 'CANCELADO', 'EN_TRANSITO' => 'EN_TRANSITO',
        'ENTREGADO' => 'ENTREGADO', 'PENDIENTE' => 'PENDIENTE',
        'CANCELADO' => 'CANCELADO'
    ];
    if (isset($estados[$str])) {
        return $estados[$str];
    }
    
    //Categorías de contenedor
    $categorias = [
        'Seco' => 'SECO', 'Refrigerado' => 'REFRIGERADO',
        'Tanque' => 'TANQUE', 'SECO' => 'SECO',
        'REFRIGERADO' => 'REFRIGERADO', 'TANQUE' => 'TANQUE'
    ];
    if (isset($categorias[$str])) {
        return $categorias[$str];
    }
    
    //Estados de contenedor
    $estadosContenedor = [
        'Disponible' => 'DISPONIBLE', 'Retenido' => 'RETENIDO',
        'Mantenimiento' => 'MANTENIMIENTO', 'Inactivo' => 'INACTIVO',
        'DISPONIBLE' => 'DISPONIBLE', 'RETENIDO' => 'RETENIDO',
        'MANTENIMIENTO' => 'MANTENIMIENTO', 'INACTIVO' => 'INACTIVO'
    ];
    if (isset($estadosContenedor[$str])) {
        return $estadosContenedor[$str];
    }
    
    //SI/NO
    if (in_array(strtoupper($str), ['S', 'SI', 'Y', 'YES', '1', 'TRUE', 'T'])) {
        return 'S';
    }
    if (in_array(strtoupper($str), ['N', 'NO', 'FALSE', '0', 'F'])) {
        return 'N';
    }
    
    if (strlen($str) == 2 && ctype_alpha($str)) {
        return strtoupper($str);
    }
    
    return $str;
}

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
                $value = normalizeValue($row[$field] ?? 'NULL', $field);
                $hashString .= $value . '|';
            }
            
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
                $value = isset($row[$field]) ? normalizeValue($row[$field], $field) : 'NULL';
                $hashString .= $value . '|';
            }
            
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
    
    $differences = [];
    $diffCount = 0;
    $matchedCount = 0;
    
    foreach ($commonIds as $id) {
        if ($mysqlData[$id]['hash'] !== $oracleData[$id]['hash']) {
            $diffCount++;
            
            $mysqlRow = $mysqlData[$id]['data'];
            $oracleRow = $oracleData[$id]['data'];
            
            $fieldDiffs = [];
            foreach ($mysqlRow as $field => $value) {
                $mysqlVal = $value ?? 'NULL';
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
            
            if (!empty($fieldDiffs)) {
                $differences[] = [
                    'id' => $id,
                    'mysql_data' => $mysqlRow,
                    'oracle_data' => $oracleRow,
                    'field_differences' => $fieldDiffs
                ];
            } else {
                $matchedCount++;
            }
        } else {
            $matchedCount++;
        }
    }
    
    $diffCount = count($differences);
    $onlyInMysql = count(array_diff($mysqlIds, $oracleIds));
    $onlyInOracle = count(array_diff($oracleIds, $mysqlIds));
    $synced = ($onlyInMysql == 0 && $onlyInOracle == 0 && $diffCount == 0);
    
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
        'sample_differences' => array_slice($differences, 0, 3)
    ];
    
    $response['comparison'][] = $comparison;
    
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