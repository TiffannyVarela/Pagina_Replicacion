    <?php
    /*
    CONFIGURACION INICIAL
    Incluir el archivo de configuracion de la base de datos
    */
    require_once '../config/db.php';

    //Devuelve datos en formato JSON
    header('Content-Type: application/json');
    //Permite peticiones desde cualquie origen (CORS)
    header('Access-Control-Allow-Origin: *');

    //Obtiene el parametro 'action' de la URL (GET) para determinar que tipo de consulta realizar. Por defecto, muestra los logs recientes.
    $action = $_GET['action'] ?? 'recent';

    switch ($action) {
        //Obtener logs recientes
        case 'recent':
            //Obtener numero de lineas a mostrar (50)
            $lines = (int)($_GET['lines'] ?? 50);
            //Obtener la ruta del archivo de log desde la constante definida en db.php
            $logFile = LOG_FILE;
            
            //Si el archivo no existe, devolver un error en formato JSON
            if (!file_exists($logFile)) {
                echo json_encode(['error' => 'Archivo de log no encontrado']);
                //Terminar la ejecucion del script
                exit;
            }
            //Leer todo el contenido del archivo
            $content = file_get_contents($logFile);
            //Dividir el contenido en lineas y eliminar lineas vacias
            $lines_array = array_filter(explode("\n", $content));
            //Obtener solo las ultimas N lineas
            $recent = array_slice($lines_array, -$lines);
            //Array para almacenar los logs procesados
            $logs = [];
            //Utiliza una expresion regular para extraer los componentes de cada linea
            foreach ($recent as $line) {
                if (preg_match('/\[(.*?)\] \[(.*?)\] (.*?)(?:\| Context: (.*))?$/', $line, $matches)) {
                    $logs[] = [
                        //Fecha y hora del evento
                        'timestamp' => $matches[1],
                        //Nivel de log (INFO, ERROR, CRITICAL)
                        'level' => $matches[2],
                        //Mensaje del log
                        'message' => $matches[3],
                        //Datos adicionales en formato JSON
                        'context' => isset($matches[4]) ? json_decode($matches[4], true) : null
                    ];
                }
            }
            
            //Devuelve un objeto JSON
            echo json_encode([
                //Indicador de exito
                'success' => true,
                //Array de logs procesados
                'logs' => $logs,
                //Total de lineas en el archivo
                'total_lines' => count($lines_array),
                //Fecha y hora de al consulta
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
        //Filtra y devuelve unicamente las lineas del log que contienen errores (ERROR) o errores criticos (CRITICAL)    
        case 'errors':
            //Obtener la ruta del archivo de log
            $logFile = LOG_FILE;
            //Validar la existencia del archivo de log
            if (!file_exists($logFile)) {
                echo json_encode(['error' => 'Archivo de log no encontrado']);
                exit;
            }
            //Leer todo el contenido del archivo
            $content = file_get_contents($logFile);
            //Dividir el contenido en lineas
            $lines = explode("\n", $content);
            //Array para almacenar los errores encontrados
            $errors = [];
            
            //Busca las lineas que contengan '[ERROR]' o '[CRITICAL]' que son los niveles mas graves del log
            foreach ($lines as $line) {
                //Verificar si la linea contiene algun nivel de error
                if (strpos($line, '[ERROR]') !== false || strpos($line, '[CRITICAL]') !== false) {
                    //Utiliza la misma expresion regular que en el caso 'recent' para extraer los componentes del log
                    if (preg_match('/\[(.*?)\] \[(.*?)\] (.*?)(?:\| Context: (.*))?$/', $line, $matches)) {
                        $errors[] = [
                            'timestamp' => $matches[1],
                            'level' => $matches[2],
                            'message' => $matches[3],
                            'context' => isset($matches[4]) ? json_decode($matches[4], true) : null
                        ];
                    }
                }
            }
            //Devuelve un objeto JSON
            echo json_encode([
                //Indicador de exito
                'success' => true,
                //Ultimos 20 errores encontrados
                'errors' => array_slice($errors, -20),
                //Total de errores en el archivo
                'total_errors' => count($errors),
                //Fecha y hora de al consulta
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
        //Si la accion solicitada no coincide con ningun caso, se devuelve un mensaje de error    
        default:
            echo json_encode(['error' => 'Accion no valida']);
    }
    ?>