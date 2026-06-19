<?php


header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔌 Prueba de Conexión Oracle</h1>";

// Cargar configuración 
require_once 'config/db.php';

echo "<p><strong>Configuración cargada desde:</strong> " . __DIR__ . "/config/db.php</p>";

// VERIFICAR CONSTANTES
echo "<h3>1. Verificando constantes definidas:</h3>";
echo "<ul>";
echo "<li>ORACLE_HOST: " . ORACLE_HOST . "</li>";
echo "<li>ORACLE_PORT: " . ORACLE_PORT . "</li>";
echo "<li>ORACLE_SERVICE: " . ORACLE_SERVICE . "</li>";
echo "<li>ORACLE_USERNAME: " . ORACLE_USERNAME . "</li>";
echo "<li>ORACLE_CHARSET: " . ORACLE_CHARSET . "</li>";
echo "</ul>";

// VERIFICAR EXTENSIÓN OCI8
echo "<h3>2. Verificando extensión OCI8:</h3>";
if (extension_loaded('oci8')) {
    echo "✅ OCI8 está instalado<br>";
    echo "Versión: " . phpversion('oci8') . "<br>";
} else {
    echo "❌ OCI8 NO está instalado<br>";
    die();
}

// VERIFICAR DNS
echo "<h3>3. Verificando resolución DNS:</h3>";
$ip = gethostbyname(ORACLE_HOST);
if ($ip === ORACLE_HOST) {
    echo "⚠️ El hostname no se resolvió.<br>";
} else {
    echo "✅ Host resuelto a: $ip<br>";
}

// VERIFICAR PUERTO
echo "<h3>4. Verificando puerto " . ORACLE_PORT . ":</h3>";
$fp = @fsockopen(ORACLE_HOST, ORACLE_PORT, $errno, $errstr, 5);
if ($fp) {
    echo "✅ Puerto " . ORACLE_PORT . " está ABIERTO<br>";
    fclose($fp);
} else {
    echo "❌ Puerto " . ORACLE_PORT . " está CERRADO: $errstr<br>";
}

// PROBAR CONEXIÓN OCI8
echo "<h3>5. Probando conexión OCI8:</h3>";

$tns = getOracleTNS();
echo "TNS: " . htmlspecialchars($tns) . "<br>";

$conn = @oci_connect(ORACLE_USERNAME, ORACLE_PASSWORD, $tns, ORACLE_CHARSET);

if ($conn) {
    echo "✅ <strong style='color:green'>CONEXIÓN EXITOSA a Oracle!</strong><br>";
    
    // Probar consulta
    $stmt = oci_parse($conn, "SELECT 'Conexión exitosa' as mensaje, SYSDATE as fecha FROM DUAL");
    oci_execute($stmt);
    $row = oci_fetch_assoc($stmt);
    
    echo "<h4>Resultado:</h4>";
    echo "<ul>";
    echo "<li>Mensaje: " . ($row['MENSAJE'] ?? 'N/A') . "</li>";
    echo "<li>Fecha Oracle: " . ($row['FECHA'] ?? 'N/A') . "</li>";
    echo "</ul>";
    
    // Mostrar tablas
    echo "<h4>Tablas disponibles:</h4>";
    $stmt = oci_parse($conn, "SELECT table_name FROM user_tables WHERE ROWNUM <= 10");
    oci_execute($stmt);
    echo "<ul>";
    $count = 0;
    while ($row = oci_fetch_assoc($stmt)) {
        echo "<li>" . $row['TABLE_NAME'] . "</li>";
        $count++;
    }
    if ($count == 0) {
        echo "<li>No hay tablas en el esquema</li>";
    }
    echo "</ul>";
    
    oci_free_statement($stmt);
    oci_close($conn);
} else {
    $e = oci_error();
    echo "❌ <strong style='color:red'>Error de conexión</strong><br>";
    echo "<ul>";
    echo "<li>Código: " . ($e['code'] ?? 'N/A') . "</li>";
    echo "<li>Mensaje: " . ($e['message'] ?? 'Error desconocido') . "</li>";
    echo "</ul>";
}

// PROBAR FUNCIÓN isOracleConnected()
echo "<h3>6. Probando función isOracleConnected():</h3>";
$result = isOracleConnected();
echo $result ? "✅ Conexión exitosa desde la función" : "❌ Falló la conexión desde la función";
?>