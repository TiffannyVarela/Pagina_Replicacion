<?php
/*
PROPoSITO:
    Ejecuta la replicacion desde Oracle hacia MySQL.
    Espera a que termine antes de la siguiente iteracion.
*/

set_time_limit(0);

// Configuración para Windows
if (PHP_OS === 'WINNT') {
    if (!defined('STDIN')) {
        define('STDIN', fopen('php://stdin', 'r'));
    }
}

// Directorio de logs
$logFile = __DIR__ . '/logs/cron_loop.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

echo " CRON DE REPLICACION - Oracle → MySQL\n";
echo " Logs: $logFile\n";
echo " Ejecucion continua - espera a que termine cada ciclo\n";
echo " Presiona Ctrl+C para detener.\n\n";

$iteracion = 0;

while (true) {
    $iteracion++;
    $timestamp = date('Y-m-d H:i:s');
    
    echo "[$timestamp] Iteración #$iteracion\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    // --- CORRECCIÓN: Medir tiempo de ejecución ---
    $inicio = microtime(true);
    
    echo "   Oracle → MySQL\n";
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/Pagina/api/replicar_mysql.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutos de timeout
        curl_setopt($ch, CURLOPT_HEADER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $fin = microtime(true);
        $duracion = round($fin - $inicio, 2);
        
        $log = "[$timestamp] Oracle→MySQL | HTTP $httpCode | Duración: {$duracion}s - ";
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['procesados'])) {
                $log .= "✅ Procesados: {$data['procesados']}, Pendientes: {$data['total_pendientes']}";
                if (!empty($data['errores'])) {
                    $log .= " | ❌ Errores: " . count($data['errores']);
                }
            } else {
                $log .= "⚠️ Respuesta: " . substr($response, 0, 100);
            }
        } else {
            $log .= "❌ ERROR: $error";
        }
        
        echo "     $log\n";
        file_put_contents($logFile, $log . "\n", FILE_APPEND);
        
    } catch (Exception $e) {
        $errorLog = "[$timestamp] Oracle→MySQL | ❌ EXCEPCIÓN: " . $e->getMessage() . "\n";
        echo "     $errorLog";
        file_put_contents($logFile, $errorLog, FILE_APPEND);
    }
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    echo "  Esperando 30 segundos antes de la siguiente iteración...\n\n";
    sleep(30);
}
?>