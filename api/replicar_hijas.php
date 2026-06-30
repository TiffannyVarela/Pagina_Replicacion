<?php
/*
REPLICAR TABLAS HIJAS (FORZADO) - VERSIÓN CORREGIDA
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

// --- CORRECCIÓN: Mapear estado de embarque ---
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

// ============================================================
// 2. TABLAS HIJAS A REPLICAR
// ============================================================
$tablasHijas = [
    'EMBARQUE_MARITIMO' => 'ordenes_envio',
    'FACTURACION_EMBARQUE' => 'tbl_facturas_logisticas',
    'DETALLE_FACTURA_SERVICIO' => 'factura_servicios',
    'TRANSFERENCIA_CARGA' => 'movimientos_carga'
];

// Limpiar tablas hijas
echo "Limpiando tablas hijas en MySQL (DELETE)...\n";
foreach ($tablasHijas as $mysqlTable) {
    $conn_mysql->query("DELETE FROM $mysqlTable");
    echo "   ✅ $mysqlTable limpiada\n";
}

$resultados = [];

foreach ($tablasHijas as $oracleTable => $mysqlTable) {
    echo "\n📋 Procesando: $oracleTable → $mysqlTable\n";
    
    // Obtener TODOS los registros de la tabla (PENDIENTES + REPLICADOS)
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
        
        if ($oracleTable == 'EMBARQUE_MARITIMO') {
            $mysql_data['embarque_id'] = $datos['id_embarque'] ?? 0;
            $mysql_data['cliente_id'] = $datos['id_cliente'] ?? 0;
            $mysql_data['transporte_id'] = $datos['id_buque'] ?? 0;
            $mysql_data['contenedor_id'] = $datos['id_contenedor'] ?? 0;
            $mysql_data['fecha_envio'] = formatearFechaMySQL($datos['fecha_salida'] ?? date('Y-m-d H:i:s'));
            
            // --- CORRECCIÓN: Mapear estado de embarque ---
            $estado_oracle = $datos['estado_embarque'] ?? 'PENDIENTE';
            $mysql_data['estatus'] = mapearEstadoEmbarque($estado_oracle);
            
            $mysql_data['nivel_prioridad'] = (int)($datos['prioridad'] ?? 3);
        }
        elseif ($oracleTable == 'FACTURACION_EMBARQUE') {
            $mysql_data['factura_id'] = $datos['id_factura'] ?? 0;
            $mysql_data['embarque_id'] = $datos['id_embarque'] ?? 0;
            $mysql_data['fecha'] = formatearFechaMySQL($datos['fecha_emision'] ?? date('Y-m-d H:i:s'));
            $mysql_data['monto_total'] = (float)($datos['total_facturado'] ?? 0);
            $mysql_data['comentarios'] = $datos['observaciones'] ?? 'Factura generada desde Oracle';
        }
        elseif ($oracleTable == 'DETALLE_FACTURA_SERVICIO') {
            $mysql_data['factura_id'] = $datos['id_factura'] ?? 0;
            $mysql_data['servicio_id'] = $datos['id_servicio'] ?? 0;
            $mysql_data['cantidad'] = (int)($datos['cantidad'] ?? 1);
            $mysql_data['subtotal'] = (float)($datos['subtotal'] ?? 0);
            $mysql_data['incluye_impuesto'] = ($datos['impuesto_aplicado'] == 'S' || $datos['impuesto_aplicado'] == 1) ? 1 : 0;
        }
        elseif ($oracleTable == 'TRANSFERENCIA_CARGA') {
            $mysql_data['movimiento_id'] = $datos['id_transferencia'] ?? 0;
            $mysql_data['contenedor_id'] = $datos['id_contenedor'] ?? 0;
            $mysql_data['centro_origen'] = $datos['terminal_origen'] ?? 0;
            $mysql_data['centro_destino'] = $datos['terminal_destino'] ?? 0;
            $mysql_data['peso_movido'] = (float)($datos['peso_transferido'] ?? 0);
            $mysql_data['fecha_movimiento'] = formatearFechaMySQL($datos['fecha_transferencia'] ?? date('Y-m-d H:i:s'));
        }
        
        // Insertar en MySQL con ON DUPLICATE KEY UPDATE
        $conn_mysql->query("SET FOREIGN_KEY_CHECKS = 0");
        
        $fields = array_keys($mysql_data);
        $placeholders = array_fill(0, count($fields), '?');
        $updates = [];
        foreach ($fields as $field) {
            if ($field != 'embarque_id' && $field != 'factura_id' && 
                $field != 'movimiento_id') {
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