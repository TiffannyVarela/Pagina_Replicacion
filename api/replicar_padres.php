<?php
/*
REPLICAR TABLAS PADRE (FORZADO) - VERSIÓN CORREGIDA
*/

set_time_limit(300);
require_once '../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isMySQLConnected() || !isOracleConnected()) {
    echo json_encode(['error' => 'Conexiones no disponibles']);
    exit;
}

$conn_mysql = getMySQLConnection();
$conn_oracle = getOracleConnection();

// ============================================================
// 1. FUNCIONES AUXILIARES
// ============================================================

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
    $jsonStr = preg_replace('/,\s+/', ',', $jsonStr);
    $jsonStr = preg_replace('/:\s+/', ':', $jsonStr);
    return trim($jsonStr);
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
    $ts = strtotime($fecha);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
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

// --- CORRECCIÓN: Mapear categoría de terminal ---
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

// --- CORRECCIÓN: Mapear categoría de servicio ---
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

// ============================================================
// 2. TABLAS PADRE A REPLICAR
// ============================================================
$tablasPadre = [
    'CLIENTE_NAVIERA' => 'tbl_clientes_logisticos',
    'TERMINAL_PORTUARIA' => 'centros_logisticos',
    'BUQUE_OPERACION' => 'unidades_transporte',
    'CONTENEDOR_NAVIERO' => 'contenedores',
    'SERVICIO_PORTUARIO' => 'servicios_logisticos'
];

// Limpiar tablas
echo "Limpiando tablas padre en MySQL (DELETE)...\n";
foreach ($tablasPadre as $mysqlTable) {
    $conn_mysql->query("DELETE FROM $mysqlTable");
    echo "   ✅ $mysqlTable limpiada\n";
}

$resultados = [];

foreach ($tablasPadre as $oracleTable => $mysqlTable) {
    echo "\n📋 Procesando: $oracleTable → $mysqlTable\n";
    
    $sql = "SELECT 
                id_registro as ID,
                datos_json as DATOS_JSON
            FROM BITACORA 
            WHERE UPPER(tabla_afectada) = '" . strtoupper($oracleTable) . "'
            ORDER BY id_registro ASC
            FETCH FIRST 50 ROWS ONLY";
    
    $result = queryOracle($sql);
    
    if (isset($result['error']) || empty($result)) {
        $resultados[$oracleTable] = ['error' => 'No se encontraron datos'];
        continue;
    }
    
    $replicados = 0;
    $errores = [];
    
    foreach ($result as $row) {
        $id = $row['ID'] ?? null;
        $json_raw = isset($row['DATOS_JSON']) ? leerCLOB($row['DATOS_JSON']) : '{}';
        $json_limpio = limpiarJSON($json_raw);
        $datos = json_decode($json_limpio, true);
        
        if (!$datos) {
            $datos = decodificarJSONManual($json_raw);
        }
        
        if (!$datos || empty($datos)) {
            $errores[] = "ID $id: No se pudo decodificar JSON";
            continue;
        }
        
        // Transformar datos según la tabla
        $mysql_data = [];
        
        if ($oracleTable == 'TERMINAL_PORTUARIA') {
            $mysql_data['terminal_id'] = $datos['id_terminal'] ?? 0;
            $mysql_data['nombre_centro'] = $datos['nombre_terminal'] ?? 'Terminal ' . ($datos['id_terminal'] ?? 0);
            $mysql_data['municipio'] = $datos['ciudad'] ?? 'Ciudad no especificada';
            $mysql_data['pais_operacion'] = convertirCodigoPaisANombre($datos['pais'] ?? 'N/A');
            
            // --- CORRECCIÓN: Mapear categoría de terminal ---
            $categoria_oracle = $datos['tipo_terminal'] ?? 'BODEGA';
            $mysql_data['categoria'] = mapearCategoriaTerminal($categoria_oracle);
            
            $mysql_data['capacidad_maxima'] = (int)($datos['capacidad_contenedores'] ?? 100);
        }
        elseif ($oracleTable == 'CLIENTE_NAVIERA') {
            $mysql_data['cliente_id'] = $datos['id_cliente'] ?? 0;
            $mysql_data['nombre_contacto'] = $datos['nombre_cliente'] ?? 'Cliente ' . ($datos['id_cliente'] ?? 0);
            $mysql_data['telefono_contacto'] = $datos['telefono'] ?? 'N/A';
            $mysql_data['email_cliente'] = $datos['correo'] ?? 'cliente' . ($datos['id_cliente'] ?? 0) . '@example.com';
            $mysql_data['pais'] = convertirCodigoPaisANombre($datos['pais_origen'] ?? 'N/A');
            $mysql_data['fecha_alta'] = formatearFechaMySQL($datos['fecha_registro'] ?? date('Y-m-d'));
        }
        elseif ($oracleTable == 'BUQUE_OPERACION') {
            $mysql_data['transporte_id'] = $datos['id_buque'] ?? 0;
            $mysql_data['nombre_unidad'] = $datos['nombre_buque'] ?? 'Buque ' . ($datos['id_buque'] ?? 0);
            $mysql_data['capacidad_carga'] = (float)($datos['capacidad_toneladas'] ?? 1000);
            $mysql_data['pais_bandera'] = convertirCodigoPaisANombre($datos['bandera'] ?? 'N/A');
            $mysql_data['habilitado'] = ($datos['activo'] == 'S' || $datos['activo'] == 1) ? 1 : 1;
        }
        elseif ($oracleTable == 'CONTENEDOR_NAVIERO') {
            $mysql_data['contenedor_id'] = $datos['id_contenedor'] ?? 0;
            $mysql_data['codigo'] = $datos['codigo_contenedor'] ?? 'CONT-' . str_pad($datos['id_contenedor'] ?? 0, 3, '0', STR_PAD_LEFT);
            
            $refrigerado = $datos['refrigerado'] ?? 'N';
            $tipo = $datos['tipo_contenedor'] ?? '';
            if (strtoupper($refrigerado) == 'S' || $refrigerado == '1') {
                $mysql_data['categoria'] = 'Refrigerado';
            } elseif (strpos(strtoupper($tipo), 'TANQUE') !== false || strpos(strtoupper($tipo), 'TANK') !== false) {
                $mysql_data['categoria'] = 'Tanque';
            } else {
                $mysql_data['categoria'] = 'Seco';
            }
            
            $mysql_data['requiere_refrigeracion'] = (strtoupper($refrigerado) == 'S' || $refrigerado == '1') ? 1 : 0;
            if ($mysql_data['requiere_refrigeracion'] == 1) {
                $mysql_data['categoria'] = 'Refrigerado';
            }
            
            $mysql_data['capacidad_kg'] = (float)($datos['capacidad_kg'] ?? 0);
            if ($mysql_data['capacidad_kg'] <= 0) {
                if ($mysql_data['categoria'] == 'Refrigerado') $mysql_data['capacidad_kg'] = 28000;
                elseif ($mysql_data['categoria'] == 'Tanque') $mysql_data['capacidad_kg'] = 25000;
                else $mysql_data['capacidad_kg'] = 30000;
            }
            
            $mysql_data['estado'] = mapearEstadoContenedor($datos['estado_operacion'] ?? 'DISPONIBLE');
        }
        elseif ($oracleTable == 'SERVICIO_PORTUARIO') {
            $mysql_data['servicio_id'] = $datos['id_servicio'] ?? 0;
            $mysql_data['descripcion_servicio'] = $datos['descripcion_servicio'] ?? 'Servicio ' . ($datos['id_servicio'] ?? 0);
            $mysql_data['costo_base'] = (float)($datos['costo_base'] ?? 0);
            $mysql_data['requiere_autorizacion'] = ($datos['requiere_autorizacion'] == 'S' || $datos['requiere_autorizacion'] == 1) ? 1 : 0;
            
            // --- CORRECCIÓN: Mapear categoría de servicio ---
            $categoria_servicio = $datos['tipo_servicio'] ?? 'CARGA';
            $mysql_data['categoria_servicio'] = mapearCategoriaServicio($categoria_servicio);
        }
        
        // Insertar en MySQL
        $conn_mysql->query("SET FOREIGN_KEY_CHECKS = 0");
        
        $fields = array_keys($mysql_data);
        $placeholders = array_fill(0, count($fields), '?');
        $updates = [];
        foreach ($fields as $field) {
            if ($field != 'contenedor_id' && $field != 'cliente_id' && $field != 'terminal_id' && 
                $field != 'transporte_id' && $field != 'servicio_id') {
                $updates[] = "$field = VALUES($field)";
            }
        }
        
        $sql_insert = "INSERT INTO $mysqlTable (" . implode(', ', $fields) . ") 
                        VALUES (" . implode(', ', $placeholders) . ")
                        ON DUPLICATE KEY UPDATE " . implode(', ', $updates);
        
        $stmt = $conn_mysql->prepare($sql_insert);
        if (!$stmt) {
            $errores[] = "ID $id: Error preparando SQL: " . $conn_mysql->error;
            continue;
        }
        
        $types = '';
        $values = [];
        foreach ($mysql_data as $value) {
            if (is_int($value)) $types .= 'i';
            elseif (is_float($value)) $types .= 'd';
            else $types .= 's';
            $values[] = $value;
        }
        
        $stmt->bind_param($types, ...$values);
        if ($stmt->execute()) {
            $replicados++;
            echo "   ✅ ID $id replicado\n";
        } else {
            $errores[] = "ID $id: " . $stmt->error;
            echo "   ❌ ID $id: " . $stmt->error . "\n";
        }
        $stmt->close();
    }
    
    $conn_mysql->query("SET FOREIGN_KEY_CHECKS = 1");
    
    $resultados[$oracleTable] = [
        'replicados' => $replicados,
        'errores' => $errores
    ];
}

// ============================================================
// 3. RESPUESTA
// ============================================================
echo "\n\n============================================================\n";
echo "RESUMEN FINAL:\n";
foreach ($resultados as $tabla => $data) {
    $replicados = $data['replicados'] ?? 0;
    $errores = count($data['errores'] ?? []);
    echo "   $tabla: ✅ $replicados replicados, ❌ $errores errores\n";
}

echo json_encode([
    'success' => true,
    'resultados' => $resultados,
    'timestamp' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT);

if (isset($conn_mysql)) $conn_mysql->close();
if (isset($conn_oracle)) oci_close($conn_oracle);
?>