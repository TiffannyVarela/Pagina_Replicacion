<?php
// test_oracle.php - Probar conexión a Oracle AWS

echo "<h1>Prueba de Conexión a Oracle AWS</h1>";

$oracle_host = 'globalshippingdb.ct2q4262uyl7.us-east-2.rds.amazonaws.com';
$oracle_port = '1521';
$oracle_service = 'DATABASE';
$oracle_username = 'admin';
$oracle_password = 'Holamundo_504';

// Verificar extensión
if (!extension_loaded('oci8')) {
    echo "<p style='color:orange'>⚠️ OCI8 extensión NO instalada</p>";
    echo "<p>Para instalar:</p>";
    echo "<ol>";
    echo "<li>Descargar Oracle Instant Client de: https://www.oracle.com/database/technologies/instant-client/winx64-64-downloads.html</li>";
    echo "<li>Extraer en C:\instantclient</li>";
    echo "<li>Agregar C:\instantclient al PATH</li>";
    echo "<li>Descomentar extension=php_oci8_12c.dll en php.ini</li>";
    echo "<li>Reiniciar XAMPP</li>";
    echo "</ol>";
} else {
    echo "<p style='color:green'>✅ OCI8 extensión INSTALADA</p>";
    
    $oracle_tns = "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=$oracle_host)(PORT=$oracle_port))(CONNECT_DATA=(SERVICE_NAME=$oracle_service)))";
    echo "<p>TNS: " . htmlspecialchars($oracle_tns) . "</p>";
    
    $conn = @oci_connect($oracle_username, $oracle_password, $oracle_tns, 'AL32UTF8');
    
    if ($conn) {
        echo "<p style='color:green'>✅ Conexión exitosa a Oracle!</p>";
        
        // Probar consulta
        $stmt = oci_parse($conn, "SELECT 'Conectado' as mensaje, SYSDATE as fecha FROM DUAL");
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        echo "<p>Mensaje: " . $row['MENSAJE'] . "</p>";
        echo "<p>Fecha Oracle: " . $row['FECHA'] . "</p>";
        
        oci_free_statement($stmt);
        oci_close($conn);
    } else {
        $e = oci_error();
        echo "<p style='color:red'>❌ Error de conexión a Oracle</p>";
        echo "<p>Error: " . htmlspecialchars($e['message'] ?? 'Desconocido') . "</p>";
        echo "<p>Verifica:</p>";
        echo "<ul>";
        echo "<li>El servidor AWS esté encendido</li>";
        echo "<li>Las credenciales sean correctas</li>";
        echo "<li>Tu IP esté permitida en el Security Group de AWS</li>";
        echo "</ul>";
    }
}
?>