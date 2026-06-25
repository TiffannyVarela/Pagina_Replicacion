<?php
/*
CONFIGURACION INICIAL
Incluir el archivo de configuracion de la base de datos
*/
require_once 'config/db.php';

//Verificar conexion para mostrar estado inicial
//Estado de conexion a MySQL
$mysql_online = isMySQLConnected();
//Estado de conexion a Oracle
$oracle_online = isOracleConnected();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Replicación Naviera</title>
    <!--Librerias externas necesarias-->
    <!--Graficas interactivas-->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!--Estilos Bootstrap-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!--Estilos locales-->
    <link href="css/styles.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid mt-4">
        <!--Encabezado-->
        <h1 class="mb-2">
            📊 Sistema de Replicación Bidireccional
        </h1>
        <h6 class="mb-4 text-muted">MySQL ↔ Oracle | GlobalShipping Corp.</h6>
        
        <!--Indicadores numericos-->
        <div class="row mb-4" id="indicadores">
            <!--Tarjeta: Pendientes-->
            <div class="col-md-3 col-sm-6">
                <div class="card text-center card-stats <?php echo !$mysql_online ? 'offline-card' : ''; ?>">
                    <div class="card-body">
                        <h5 class="card-title">⏳ Pendientes</h5>
                        <h2 class="text-warning" id="pendientes"><?php echo !$mysql_online ? '?' : '-'; ?></h2>
                    </div>
                </div>
            </div>
            <!--Tarjeta: Replicados-->
            <div class="col-md-3 col-sm-6">
                <div class="card text-center card-stats <?php echo !$mysql_online ? 'offline-card' : ''; ?>">
                    <div class="card-body">
                        <h5 class="card-title">✅ Replicados</h5>
                        <h2 class="text-success" id="replicados"><?php echo !$mysql_online ? '?' : '-'; ?></h2>
                    </div>
                </div>
            </div>
            <!--Tarjeta: Errores-->
            <div class="col-md-3 col-sm-6">
                <div class="card text-center card-stats <?php echo !$mysql_online ? 'offline-card' : ''; ?>">
                    <div class="card-body">
                        <h5 class="card-title">❌ Errores</h5>
                        <h2 class="text-danger" id="errores"><?php echo !$mysql_online ? '?' : '-'; ?></h2>
                    </div>
                </div>
            </div>
            <!--Tarjeta: Conflictos-->
            <div class="col-md-3 col-sm-6">
                <div class="card text-center card-stats <?php echo !$mysql_online ? 'offline-card' : ''; ?>">
                    <div class="card-body">
                        <h5 class="card-title">⚔️ Conflictos</h5>
                        <h2 class="text-info" id="conflictos"><?php echo !$mysql_online ? '?' : '-'; ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <!--Graficas de estado y porcentaje. Visualizacion de datos: Estado MySQL, Oracle y porcentaje de exito en 24h -->
        <div class="row mb-4">
            <!--Grafica: Estado Replicacion MySQL-->
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header card-header-custom">
                        <h5>📈 Estado de Replicación MySQL</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!$mysql_online): ?>
                            <!--Mensaje cuando MySQL esta offline-->
                            <div class="data-unavailable">
                                <p>📡 MySQL offline</p>
                            </div>
                        <?php else: ?>
                            <!--Canvas para la grafica de MySQL-->
                            <canvas id="chartEstadoMySQL" height="200"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--Grafica: Estado Replicacion Oracle-->
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header card-header-custom">
                        <h5>📈 Estado de Replicación Oracle</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!$oracle_online): ?>
                            <!--Mensaje cuando Oracle esta offline-->
                            <div class="data-unavailable">
                                <p>📡 Oracle offline</p>
                            </div>
                        <?php else: ?>
                            <!--Canvas para la grafica de Oracle-->
                            <canvas id="chartEstadoOracle" height="200"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--Grafica: Porcentaje de Exito-->
            <div class="col-md-2">
                <div class="card">
                    <div class="card-header card-header-custom">
                        <h5>🥧 Éxito (24h)</h5>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center" style="min-height: 250px;">
                        <?php if (!$mysql_online): ?>
                            <!--Mensaje cuando no hay datos-->
                            <div class="data-unavailable p-2">
                                <p>📡 Sin datos</p>
                            </div>
                        <?php else: ?>
                            <!--Canvas para la grafica-->
                            <canvas id="chartPorcentajeExito" height="200" width="200"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!--Eventos por Dia (MySQL y Oracle) y Estado de Conexion -->
        <div class="row mb-4">
            <!--Grafica: Eventos por Dia - MySQL-->
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header card-header-custom">
                        <h5>📊 Eventos por Día - MySQL</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!$mysql_online): ?>
                            <div class="data-unavailable">
                                <p>📡 MySQL offline</p>
                            </div>
                        <?php else: ?>
                            <canvas id="chartEventosMySQL" height="200"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--Grafica: Eventos por Dia - Oracle-->
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header card-header-custom">
                        <h5>📊 Eventos por Día - Oracle</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!$oracle_online): ?>
                            <div class="data-unavailable">
                                <p>📡 Oracle offline</p>
                            </div>
                        <?php else: ?>
                            <canvas id="chartEventosOracle" height="200"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--Tarjeta: Estado de Conexion-->
            <div class="col-md-2">
                <div class="card card-estado">
                    <div class="card-header card-header-custom">
                        <h5>🔌 Conexión</h5>
                    </div>
                    <div class="card-body">
                        <div id="estadoConexion">
                            <?php
                            /*Preparar variables de estado para MySQL y Oracle. Estas se mostraran en la tarjeta de conexion*/
                            $mysql_status_text = $mysql_online ? '🟢 Online' : '🔴 Offline';
                            $mysql_status_class = $mysql_online ? 'status-online' : 'status-offline';
                            $mysql_tooltip = $mysql_online ? 'Conexión exitosa a Aiven MySQL' : 'Conexión fallida';
                            
                            $oracle_status_text = $oracle_online ? '🟢 Online' : '🔴 Offline';
                            $oracle_status_class = $oracle_online ? 'status-online' : 'status-offline';
                            $oracle_tooltip = $oracle_online ? 'Conexión exitosa a AWS Oracle' : 'Conexión fallida';
                            ?>
                            <!--Estado de MySQL-->
                            <div class="mb-2">
                                <strong>MySQL</strong><br>
                                <span id="estado_mysql" class="<?php echo $mysql_status_class; ?>" data-tooltip="<?php echo htmlspecialchars($mysql_tooltip); ?>">
                                    <?php echo $mysql_status_text; ?>
                                </span>
                                <br><small id="mysql_detalle" class="text-muted">Aiven Cloud</small>
                            </div>
                            <!--Estado de Oracle-->
                            <div class="mb-2">
                                <strong>Oracle</strong><br>
                                <span id="estado_oracle" class="<?php echo $oracle_status_class; ?>" data-tooltip="<?php echo htmlspecialchars($oracle_tooltip); ?>">
                                    <?php echo $oracle_status_text; ?>
                                </span>
                                <br><small id="oracle_detalle" class="text-muted">AWS RDS</small>
                            </div>
                            <hr class="my-2">
                            <!--Ultima sincronizacion-->
                            <div id="ultimaSincronizacion">
                                <small>🔄 <span id="ultima_ejecucion"><?php echo !$mysql_online ? 'Esperando...' : 'Cargando...'; ?></span></small>
                            </div>
                            <!--Total de registros-->
                            <div id="registrosTotales">
                                <small>📊 <span id="total_registros">Cargando...</span></small>
                            </div>
                            <!--Alertas de estado offline-->
                            <?php if (!$mysql_online): ?>
                                <div class="alert alert-warning mt-2 mb-0 p-1 small">
                                    ⚠️ MySQL offline
                                </div>
                            <?php endif; ?>
                            <?php if (!$oracle_online): ?>
                                <div class="alert alert-warning mt-2 mb-0 p-1 small">
                                    ⚠️ Oracle offline
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!--Comparacion de Datos: MySQL vs Oracle-->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header card-header-custom">
                        <h5>🔄 Comparación de Datos: MySQL vs Oracle</h5>
                        <small class="text-white-50">Verifica la sincronización entre bases de datos</small>
                    </div>
                    <div class="card-body">
                        <!--Resumen de comparacion-->
                        <div id="comparacionResumen" class="mb-2"></div>
                        <!--Tabla de comparacion detallada-->
                        <div class="table-responsive">
                            <table class="table table-sm table-hover" id="tablaComparacion">
                                <thead>
                                    <tr>
                                        <th>Tabla MySQL</th>
                                        <th>Tabla Oracle</th>
                                        <th class="text-center">Registros MySQL</th>
                                        <th class="text-center">Registros Oracle</th>
                                        <th class="text-center">Diferencia</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="cuerpoComparacion">
                                    <tr><td colspan="6" class="text-center">Cargando...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!--Eventos y Errores. Ultimos 10 eventos procesados con su estado-->
        <div class="card mb-4">
            <div class="card-header card-header-custom">
                <h5>📋 Últimos 10 Eventos Procesados</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <?php if (!$mysql_online): ?>
                        <div class="data-unavailable">
                            <p>📡 Datos no disponibles - MySQL offline</p>
                        </div>
                    <?php else: ?>
                        <table class="table table-sm table-events">
                            <thead>
                                <!--Tabla de eventos recientes-->
                                <tr><th>ID</th><th>Tabla</th><th>Operación</th><th>Fecha/Hora</th><th>Estado</th></tr>
                            </thead>
                            <tbody id="tablaEventos">
                                <tr><td colspan="5" class="text-center">Cargando...</td></tr>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!--Registros con Error. Muestra los errores de replicacion-->
        <div class="card mb-4">
            <div class="card-header card-header-custom">
                <h5>⚠️ Registros con Error</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <?php if (!$mysql_online): ?>
                        <div class="data-unavailable">
                            <p>📡 Datos no disponibles - MySQL offline</p>
                        </div>
                    <?php else: ?>
                        <table class="table table-sm table-danger">
                            <thead>
                                <tr><th>ID</th><th>Tabla</th><th>Intento</th><th>Mensaje Error</th><th>Fecha</th></tr>
                            </thead>
                            <tbody id="tablaErrores">
                                <tr><td colspan="5" class="text-center">Cargando...</td></tr>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!--Logs del Sistema. Registros de eventos y errores del sistema  -->
        <div class="card mb-4">
            <div class="card-header card-header-custom">
                <h5>📝 Logs del Sistema</h5>
                <small class="text-white-50">Últimos errores y eventos del sistema</small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <?php if (!$mysql_online): ?>
                        <div class="data-unavailable">
                            <p>📡 Datos no disponibles - MySQL offline</p>
                        </div>
                    <?php else: ?>
                        <table class="table table-sm table-striped" id="tablaLogs">
                            <thead>
                                <tr>
                                    <th>Fecha/Hora</th>
                                    <th>Nivel</th>
                                    <th>Mensaje</th>
                                    <th>Contexto</th>
                                </tr>
                            </thead>
                            <tbody id="cuerpoLogs">
                                <tr><td colspan="4" class="text-center">Cargando logs...</td></tr>
                            </tbody>
                        </table>
                        <div class="text-end mt-2">
                            <button class="btn btn-sm btn-secondary" onclick="cargarLogs()">🔄 Refrescar Logs</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!--Filtros de Busqueda. Permite filtrar eventos por tabla y estado-->
        <?php if ($mysql_online): ?>
        <div class="card mb-4">
            <div class="card-header card-header-custom">
                <h5>🔍 Filtros de Búsqueda</h5>
            </div>
            <div class="card-body">
                <!--Filtro por tabla-->
                <div class="row">
                    <div class="col-md-4">
                        <label>Filtrar por tabla:</label>
                        <select id="filtroTabla" class="form-select">
                            <option value="">Todas</option>
                            <option value="tbl_clientes_logisticos">Clientes</option>
                            <option value="centros_logisticos">Centros</option>
                            <option value="contenedores">Contenedores</option>
                            <option value="ordenes_envio">Embarques</option>
                            <option value="stock_carga">Inventario</option>
                            <option value="tbl_facturas_logisticas">Facturas</option>
                            <option value="servicios_logisticos">Servicios</option>
                            <option value="factura_servicios">Detalle Factura</option>
                            <option value="movimientos_carga">Transferencias</option>
                            <option value="unidades_transporte">Unidades Transporte</option>
                        </select>
                    </div>
                    <!--Filtro por estado-->
                    <div class="col-md-4">
                        <label>Filtrar por estado:</label>
                        <select id="filtroEstado" class="form-select">
                            <option value="">Todos</option>
                            <option value="PENDIENTE">Pendientes</option>
                            <option value="REPLICADO">Replicados</option>
                            <option value="ERROR">Errores</option>
                            <option value="CONFLICTO">Conflictos</option>
                        </select>
                    </div>
                    <!--Boton aplicar filtros-->
                    <div class="col-md-4">
                        <label>&nbsp;</label>
                        <button id="btnAplicarFiltros" class="btn btn-primary w-100">Aplicar Filtros</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <!--Boton flotante de recargar-->
    <button class="btn btn-success refresh-btn" onclick="cargarDatos()">
        🔄 Refrescar
    </button>
    <!--Librerias externas-->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!--Parte visual de las graficas-->
    <script src="js/chart-config.js"></script>
    
    <?php if ($mysql_online): ?>
    <script>
        /*Variables globales para graficas. Almacenan las instancias de Chart.js para su actualizacion*/
        let chartEstadoMySQL, chartEstadoOracle, chartPorcentajeExito, chartEventosMySQL, chartEventosOracle;
        
        /*
        INICIALIZACION DEL DASHBOARD
        Carga datos al inicio y configura actualizacion automatica
         */
        $(document).ready(function() {
            //Carga inicial de datos
            cargarDatos();
            //Actualizacion cada 30 segundos
            setInterval(cargarDatos, 30000);
        });
        /*
        Orquesta la carga de todos los datos del dashboard
        Ejecuta todas las funciones de carga en paralelo
        */
        function cargarDatos() {
            //Mostrar spinners de carga en indicadores
            $('#pendientes').html('<span class="spinner-border spinner-border-sm" role="status"></span>');
            $('#replicados').html('<span class="spinner-border spinner-border-sm" role="status"></span>');
            $('#errores').html('<span class="spinner-border spinner-border-sm" role="status"></span>');
            $('#conflictos').html('<span class="spinner-border spinner-border-sm" role="status"></span>');
            //Cargar todos los componentes
            cargarIndicadores();
            cargarEstadisticasGraficas();
            cargarEventosRecientes();
            cargarErrores();
            cargarEstadoConexion();
            cargarComparacion();
            cargarLogs();
        }
        /*
        Obtiene y actualiza los KPIs principales del dashboard
        Endpoint: api/indicadores.php
        */
        function cargarIndicadores() {
            $.getJSON('api/indicadores.php')
                .done(function(data) {
                    //Actualizar valores de indicadores
                    $('#pendientes').text(data.pendientes || 0);
                    $('#replicados').text(data.replicados || 0);
                    $('#errores').text(data.errores || 0);
                    $('#conflictos').text(data.conflictos || 0);
                    //Calcular y mostrar total de registros procesados
                    let total = (data.replicados || 0) + (data.pendientes || 0) + (data.errores || 0);
                    $('#total_registros').text(total + ' registros procesados');
                })
                .fail(function() {
                    //Si falla, mostrar signos de interrogacion
                    $('#pendientes').text('?');
                    $('#replicados').text('?');
                    $('#errores').text('?');
                    $('#conflictos').text('?');
                });
        }
        /*
        Obtiene datos para todas las graficas y las renderiza
        Endpoint: api/estadisticas_graficas.php
        
        Estrategia de carga:
        1. Intenta cargar datos reales
        2. Si hay datos, los usa
        3. Si no hay datos, muestra graficas vacias
        4. Si falla la conexion, muestra graficas de error
        */
        function cargarEstadisticasGraficas() {
            console.log('🚀 cargarEstadisticasGraficas() ejecutándose');
            
            // PRIMERO: Intentar con datos REALES
            $.getJSON('api/estadisticas_graficas.php')
                .done(function(data) {
                    console.log('✅ Datos reales recibidos:', data);
                    
                    // Verificar si los datos reales tienen informacion
                    const hasReplicados = data.replicados_por_tabla && data.replicados_por_tabla.some(v => v > 0);
                    const hasPendientes = data.pendientes_por_tabla && data.pendientes_por_tabla.some(v => v > 0);
                    const hasErrores = data.errores_por_tabla && data.errores_por_tabla.some(v => v > 0);
                    const hasOracleData = data.oracle && data.oracle.online && data.oracle.table_data && 
                                          Object.values(data.oracle.table_data).some(v => v > 0);
                    const hasEventosMySQL = data.eventos_diarios && data.eventos_diarios.cantidades && 
                                            data.eventos_diarios.cantidades.some(v => v > 0);
                    const hasEventosOracle = data.eventos_diarios_oracle && data.eventos_diarios_oracle.cantidades && 
                                              data.eventos_diarios_oracle.cantidades.some(v => v > 0);
                    
                    if (hasReplicados || hasPendientes || hasErrores || hasOracleData || hasEventosMySQL || hasEventosOracle) {
                        console.log('✅ Usando datos REALES');
                        crearGraficas(data);
                    } else {
                        console.warn('⚠️ No hay datos reales, mostrando gráficas vacías');
                        crearGraficasVaciasSinDatos();
                    }
                })
                .fail(function(jqXHR, textStatus, errorThrown) {
                    console.error('❌ Error cargando datos reales:', textStatus, errorThrown);
                    console.warn('⚠️ Error de conexión, mostrando gráficas vacías');
                    crearGraficasVaciasSinDatos();
                });
        }
        //Crea y renderiza todas las graficas con los datos proporcionados
        function crearGraficas(data) {
            console.log('🔄 Creando gráficas con datos:', data);
            
            // Asegurar que las funciones de chart-config.js esten disponibles
            let labels = data.tablas || [];
            if (typeof getFriendlyTableNames === 'function') {
                labels = getFriendlyTableNames(data.tablas);
            }
            
            //Estado de Replicacion MySQL
            const ctxMySQL = document.getElementById('chartEstadoMySQL');
            if (ctxMySQL) {
                //Limpiar grafica anterior
                if (chartEstadoMySQL) chartEstadoMySQL.destroy();
                
                let datasetsMySQL = [];
                
                // Verificar si hay datos reales
                const hasReplicados = data.replicados_por_tabla && data.replicados_por_tabla.some(v => v > 0);
                const hasPendientes = data.pendientes_por_tabla && data.pendientes_por_tabla.some(v => v > 0);
                const hasErrores = data.errores_por_tabla && data.errores_por_tabla.some(v => v > 0);
                
                if (hasReplicados || hasPendientes || hasErrores) {
                    //Datasets con datos reales
                    datasetsMySQL = [
                        {
                            label: CHART_COLORS.replicados.label,
                            data: data.replicados_por_tabla || [],
                            backgroundColor: CHART_COLORS.replicados.color
                        },
                        {
                            label: CHART_COLORS.pendientes.label,
                            data: data.pendientes_por_tabla || [],
                            backgroundColor: CHART_COLORS.pendientes.color
                        },
                        {
                            label: CHART_COLORS.errores.label,
                            data: data.errores_por_tabla || [],
                            backgroundColor: CHART_COLORS.errores.color
                        }
                    ];
                } else {
                    //Dataset vacio si no hay datos
                    datasetsMySQL = [{
                        label: '📊 Sin datos de replicación',
                        data: new Array(labels.length).fill(0),
                        backgroundColor: '#6c757d'
                    }];
                }
                //Crear grafica de barras para MySQL
                chartEstadoMySQL = new Chart(ctxMySQL, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: datasetsMySQL
                    },
                    options: BAR_CHART_OPTIONS
                });
                console.log('✅ Gráfica MySQL creada');
            } else {
                console.error('❌ No se encontró chartEstadoMySQL');
            }
            
            //Estado de Replicacion Oracle
            const ctxOracle = document.getElementById('chartEstadoOracle');
            if (ctxOracle) {
                if (chartEstadoOracle) chartEstadoOracle.destroy();
                
                let datasetsOracle = [];
                let oracleLabels = labels;
                //Procesar datos de Oracle
                if (data.oracle && data.oracle.online && data.oracle.table_data) {
                    let oracleData = [];
                    let oracleLabelNames = [];
                    
                    //Obtener datos en el orden de las tablas
                    if (data.tablas) {
                        data.tablas.forEach(function(mysqlTable) {
                            let found = false;
                            for (const [key, value] of Object.entries(data.oracle.table_data)) {
                                if (key === mysqlTable || key === mysqlTable.toUpperCase()) {
                                    oracleData.push(value);
                                    oracleLabelNames.push(getFriendlyTableName(key));
                                    found = true;
                                    break;
                                }
                            }
                            if (!found) {
                                oracleData.push(0);
                                oracleLabelNames.push(getFriendlyTableName(mysqlTable) + ' (Oracle)');
                            }
                        });
                    } else {
                        // Si no hay tablas definidas, usar las de Oracle
                        for (const [key, value] of Object.entries(data.oracle.table_data)) {
                            oracleData.push(value);
                            oracleLabelNames.push(getFriendlyTableName(key));
                        }
                    }
                    
                    const hasOracleData = oracleData.some(v => v > 0);
                    if (hasOracleData) {
                        datasetsOracle = [{
                            label: CHART_COLORS.oracle_registros.label,
                            data: oracleData,
                            backgroundColor: CHART_COLORS.oracle_registros.color
                        }];
                    } else {
                        datasetsOracle = [{
                            label: '📊 Sin datos en Oracle',
                            data: new Array(oracleData.length).fill(0),
                            backgroundColor: '#6c757d'
                        }];
                    }
                    //Crear grafica de barras para Oracle
                    chartEstadoOracle = new Chart(ctxOracle, {
                        type: 'bar',
                        data: {
                            labels: oracleLabelNames,
                            datasets: datasetsOracle
                        },
                        options: BAR_CHART_OPTIONS
                    });
                } else {
                    //Oracle offline
                    chartEstadoOracle = new Chart(ctxOracle, {
                        type: 'bar',
                        data: {
                            labels: ['Oracle ' + (data.oracle?.online ? 'Online' : 'Offline')],
                            datasets: [{
                                label: data.oracle?.online ? '📊 Sin datos' : '📡 Sin conexión',
                                data: [0],
                                backgroundColor: data.oracle?.online ? '#6c757d' : '#dc3545'
                            }]
                        },
                        options: BAR_CHART_OPTIONS
                    });
                }
                console.log('✅ Gráfica Oracle creada');
            } else {
                console.error('❌ No se encontró chartEstadoOracle');
            }
            
            //Porcentaje de Exito
            const ctxPorcentaje = document.getElementById('chartPorcentajeExito');
            if (ctxPorcentaje) {
                if (chartPorcentajeExito) chartPorcentajeExito.destroy();
                
                const exito = data.porcentaje_exito || 0;
                //Crear grafica de dona
                chartPorcentajeExito = new Chart(ctxPorcentaje, {
                    type: 'doughnut',
                    data: {
                        labels: [CHART_COLORS.exito.label, CHART_COLORS.fallo.label],
                        datasets: [{
                            data: [exito, 100 - exito],
                            backgroundColor: [CHART_COLORS.exito.color, CHART_COLORS.fallo.color],
                            borderWidth: 2
                        }]
                    },
                    options: DOUGHNUT_CHART_OPTIONS
                });
                console.log('✅ Gráfica Porcentaje creada');
            } else {
                console.error('❌ No se encontró chartPorcentajeExito');
            }
            
            //Eventos por Dia - MySQL
            const ctxEventosMySQL = document.getElementById('chartEventosMySQL');
            if (ctxEventosMySQL) {
                if (chartEventosMySQL) chartEventosMySQL.destroy();
                
                let eventosFechas = data.eventos_diarios?.fechas || [];
                let eventosCantidades = data.eventos_diarios?.cantidades || [];
                
                // Si no hay fechas, usar los ultimos 7 dias
                if (eventosFechas.length === 0) {
                    const today = new Date();
                    for (let i = 6; i >= 0; i--) {
                        const d = new Date(today);
                        d.setDate(d.getDate() - i);
                        eventosFechas.push(d.toISOString().split('T')[0]);
                        eventosCantidades.push(0);
                    }
                }
                //Crear grafica de linea para eventos MySQL
                chartEventosMySQL = new Chart(ctxEventosMySQL, {
                    type: 'line',
                    data: {
                        labels: eventosFechas,
                        datasets: [{
                            label: CHART_COLORS.mysql.label,
                            data: eventosCantidades,
                            borderColor: CHART_COLORS.mysql.primary,
                            backgroundColor: CHART_COLORS.mysql.background,
                            fill: true,
                            tension: 0.3
                        }]
                    },
                    options: LINE_CHART_OPTIONS
                });
                console.log('✅ Gráfica Eventos MySQL creada');
            } else {
                console.error('❌ No se encontró chartEventosMySQL');
            }
            
            //Eventos por Dia - Oracle
            const ctxEventosOracle = document.getElementById('chartEventosOracle');
            if (ctxEventosOracle) {
                if (chartEventosOracle) chartEventosOracle.destroy();
                
                let oracleFechas = data.eventos_diarios_oracle?.fechas || [];
                let oracleCantidades = data.eventos_diarios_oracle?.cantidades || [];
                
                // Si no hay fechas de Oracle, usar los ultimos 7 dias con ceros
                if (oracleFechas.length === 0) {
                    const today = new Date();
                    for (let i = 6; i >= 0; i--) {
                        const d = new Date(today);
                        d.setDate(d.getDate() - i);
                        oracleFechas.push(d.toISOString().split('T')[0]);
                        oracleCantidades.push(0);
                    }
                }
                
                const hasOracleEvents = oracleCantidades.some(v => v > 0);
                //Crear grafica de linea para eventos Oracle
                chartEventosOracle = new Chart(ctxEventosOracle, {
                    type: 'line',
                    data: {
                        labels: oracleFechas,
                        datasets: [{
                            label: hasOracleEvents ? CHART_COLORS.oracle.label : '📡 Sin eventos Oracle',
                            data: oracleCantidades,
                            borderColor: hasOracleEvents ? CHART_COLORS.oracle.primary : '#6c757d',
                            backgroundColor: hasOracleEvents ? CHART_COLORS.oracle.background : 'rgba(108,117,125,0.1)',
                            fill: true,
                            tension: 0.3,
                            borderDash: hasOracleEvents ? [5, 5] : []
                        }]
                    },
                    options: LINE_CHART_OPTIONS
                });
                console.log('✅ Gráfica Eventos Oracle creada');
            } else {
                console.error('❌ No se encontró chartEventosOracle');
            }
        }

        //Crea graficas con datos vacios cuando no hay informacion disponible
        function crearGraficasVaciasSinDatos() {
            console.warn('📊 Mostrando gráficas vacías (sin datos)');
            
            //Estado de Replicacion MySQL (Vacia)
            const ctxMySQL = document.getElementById('chartEstadoMySQL');
            if (ctxMySQL) {
                if (chartEstadoMySQL) chartEstadoMySQL.destroy();
                //Obtener nombres de tablas
                let labels = [];
                if (typeof getMySQLTables === 'function') {
                    labels = getFriendlyTableNames(getMySQLTables());
                } else {
                    labels = ['Clientes', 'Centros', 'Unidades', 'Contenedores', 'Inventario', 'Embarques', 'Facturas', 'Servicios', 'Detalle Factura', 'Transferencias'];
                }
                
                chartEstadoMySQL = new Chart(ctxMySQL, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '📊 Sin datos de replicación',
                            data: new Array(labels.length).fill(0),
                            backgroundColor: '#6c757d',
                            borderColor: '#6c757d',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        ...BAR_CHART_OPTIONS,
                        plugins: {
                            ...BAR_CHART_OPTIONS.plugins,
                            legend: {
                                position: 'top',
                            }
                        }
                    }
                });
                console.log('✅ Gráfica MySQL vacía creada');
            }
            
            //Estado de Replicacion Oracle (Vacia)
            const ctxOracle = document.getElementById('chartEstadoOracle');
            if (ctxOracle) {
                if (chartEstadoOracle) chartEstadoOracle.destroy();
                
                let oracleLabels = [];
                if (typeof getOracleTables === 'function') {
                    oracleLabels = getFriendlyTableNames(getOracleTables());
                } else {
                    oracleLabels = ['Clientes (Oracle)', 'Centros (Oracle)', 'Unidades (Oracle)', 'Contenedores (Oracle)', 'Inventario (Oracle)', 'Embarques (Oracle)', 'Facturas (Oracle)', 'Servicios (Oracle)', 'Detalle (Oracle)', 'Transferencias (Oracle)'];
                }
                
                chartEstadoOracle = new Chart(ctxOracle, {
                    type: 'bar',
                    data: {
                        labels: oracleLabels,
                        datasets: [{
                            label: '📊 Sin datos en Oracle',
                            data: new Array(oracleLabels.length).fill(0),
                            backgroundColor: '#6c757d',
                            borderColor: '#6c757d',
                            borderWidth: 1
                        }]
                    },
                    options: BAR_CHART_OPTIONS
                });
                console.log('✅ Gráfica Oracle vacía creada');
            }
            
            //Porcentaje de Exito (Vacia)
            const ctxPorcentaje = document.getElementById('chartPorcentajeExito');
            if (ctxPorcentaje) {
                if (chartPorcentajeExito) chartPorcentajeExito.destroy();
                
                chartPorcentajeExito = new Chart(ctxPorcentaje, {
                    type: 'doughnut',
                    data: {
                        labels: ['Sin datos', 'Sin datos'],
                        datasets: [{
                            data: [50, 50],
                            backgroundColor: ['#6c757d', '#dee2e6'],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        ...DOUGHNUT_CHART_OPTIONS,
                        plugins: {
                            ...DOUGHNUT_CHART_OPTIONS.plugins,
                            legend: {
                                position: 'bottom',
                            }
                        }
                    }
                });
                console.log('✅ Gráfica Porcentaje vacía creada');
            }
            
            //Eventos por Dia - MySQL (Vacia)
            const ctxEventosMySQL = document.getElementById('chartEventosMySQL');
            if (ctxEventosMySQL) {
                if (chartEventosMySQL) chartEventosMySQL.destroy();
                
                const fechas = [];
                const today = new Date();
                for (let i = 6; i >= 0; i--) {
                    const d = new Date(today);
                    d.setDate(d.getDate() - i);
                    fechas.push(d.toISOString().split('T')[0]);
                }
                
                chartEventosMySQL = new Chart(ctxEventosMySQL, {
                    type: 'line',
                    data: {
                        labels: fechas,
                        datasets: [{
                            label: '📊 Sin eventos MySQL',
                            data: new Array(7).fill(0),
                            borderColor: '#6c757d',
                            backgroundColor: 'rgba(108,117,125,0.1)',
                            fill: true,
                            tension: 0.3
                        }]
                    },
                    options: LINE_CHART_OPTIONS
                });
                console.log('✅ Gráfica Eventos MySQL vacía creada');
            }
            
            //Eventos por Dia - Oracle (Vacia)
            const ctxEventosOracle = document.getElementById('chartEventosOracle');
            if (ctxEventosOracle) {
                if (chartEventosOracle) chartEventosOracle.destroy();
                
                const fechas = [];
                const today = new Date();
                for (let i = 6; i >= 0; i--) {
                    const d = new Date(today);
                    d.setDate(d.getDate() - i);
                    fechas.push(d.toISOString().split('T')[0]);
                }
                
                chartEventosOracle = new Chart(ctxEventosOracle, {
                    type: 'line',
                    data: {
                        labels: fechas,
                        datasets: [{
                            label: '📊 Sin eventos Oracle',
                            data: new Array(7).fill(0),
                            borderColor: '#6c757d',
                            backgroundColor: 'rgba(108,117,125,0.1)',
                            fill: true,
                            tension: 0.3,
                            borderDash: [5, 5]
                        }]
                    },
                    options: LINE_CHART_OPTIONS
                });
                console.log('✅ Gráfica Eventos Oracle vacía creada');
            }
        }

        function crearGraficasVacia() {
            console.warn('🔄 Creando gráficas vacías por error');
            
            const ctxMySQL = document.getElementById('chartEstadoMySQL');
            if (ctxMySQL) {
                if (chartEstadoMySQL) chartEstadoMySQL.destroy();
                chartEstadoMySQL = new Chart(ctxMySQL, {
                    type: 'bar',
                    data: {
                        labels: ['Sin datos'],
                        datasets: [{
                            label: '📊 Error al cargar datos',
                            data: [0],
                            backgroundColor: '#dc3545'
                        }]
                    },
                    options: BAR_CHART_OPTIONS
                });
            }
            
            const ctxOracle = document.getElementById('chartEstadoOracle');
            if (ctxOracle) {
                if (chartEstadoOracle) chartEstadoOracle.destroy();
                chartEstadoOracle = new Chart(ctxOracle, {
                    type: 'bar',
                    data: {
                        labels: ['Sin datos'],
                        datasets: [{
                            label: '📊 Error al cargar datos',
                            data: [0],
                            backgroundColor: '#dc3545'
                        }]
                    },
                    options: BAR_CHART_OPTIONS
                });
            }
            
            const ctxPorcentaje = document.getElementById('chartPorcentajeExito');
            if (ctxPorcentaje) {
                if (chartPorcentajeExito) chartPorcentajeExito.destroy();
                chartPorcentajeExito = new Chart(ctxPorcentaje, {
                    type: 'doughnut',
                    data: {
                        labels: ['✅ Error', '❌ Error'],
                        datasets: [{
                            data: [50, 50],
                            backgroundColor: ['#dc3545', '#6c757d'],
                            borderWidth: 2
                        }]
                    },
                    options: DOUGHNUT_CHART_OPTIONS
                });
            }
            
            const ctxEventosMySQL = document.getElementById('chartEventosMySQL');
            if (ctxEventosMySQL) {
                if (chartEventosMySQL) chartEventosMySQL.destroy();
                chartEventosMySQL = new Chart(ctxEventosMySQL, {
                    type: 'line',
                    data: {
                        labels: ['Sin datos'],
                        datasets: [{
                            label: '📊 Error al cargar',
                            data: [0],
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220,53,69,0.1)',
                            fill: true
                        }]
                    },
                    options: LINE_CHART_OPTIONS
                });
            }
            
            const ctxEventosOracle = document.getElementById('chartEventosOracle');
            if (ctxEventosOracle) {
                if (chartEventosOracle) chartEventosOracle.destroy();
                chartEventosOracle = new Chart(ctxEventosOracle, {
                    type: 'line',
                    data: {
                        labels: ['Sin datos'],
                        datasets: [{
                            label: '📊 Error al cargar',
                            data: [0],
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220,53,69,0.1)',
                            fill: true
                        }]
                    },
                    options: LINE_CHART_OPTIONS
                });
            }
        }
        
        /* 
        Carga los ultimos eventos procesados aplicando filtros
        Endpoint: api/eventos_recientes.php
        Filtros: tabla y estado
        */
        function cargarEventosRecientes() {
            const tabla = $('#filtroTabla').val();
            const estado = $('#filtroEstado').val();
            //Construir URL con parametros de filtro
            let url = 'api/eventos_recientes.php?';
            if (tabla) url += 'tabla=' + encodeURIComponent(tabla) + '&';
            if (estado) url += 'estado=' + encodeURIComponent(estado);
            
            $.getJSON(url)
                .done(function(data) {
                    let html = '';
                    if (data.length === 0) {
                        html = '<tr><td colspan="5" class="text-center">Sin eventos registrados</td></tr>';
                    } else {
                        data.forEach(evento => {
                            //Determinar clase de badge segun estado
                            let badgeClass = 'secondary';
                            if (evento.estado_replicacion === 'REPLICADO') badgeClass = 'success';
                            else if (evento.estado_replicacion === 'PENDIENTE') badgeClass = 'warning';
                            else if (evento.estado_replicacion === 'ERROR') badgeClass = 'danger';
                            else if (evento.estado_replicacion === 'CONFLICTO') badgeClass = 'info';
                            
                            html += `<tr>
                                <td>${evento.id}</td>
                                <td><code>${evento.tabla_afectada}</code></td>
                                <td>${evento.tipo_operacion}</td>
                                <td>${evento.fecha_hora}</td>
                                <td><span class="badge bg-${badgeClass}">${evento.estado_replicacion}</span></td>
                            </tr>`;
                        });
                    }
                    $('#tablaEventos').html(html);
                })
                .fail(function() {
                    $('#tablaEventos').html('<tr><td colspan="5" class="text-center text-danger">Error cargando eventos</td></tr>');
                });
        }
        /*
        Carga la lista de registros con error en replicacion
        Endpoint: api/errores.php
        */
        function cargarErrores() {
            $.getJSON('api/errores.php')
                .done(function(data) {
                    let html = '';
                    if (data.length === 0) {
                        html = '<tr><td colspan="5" class="text-center text-success">✅ Sin errores registrados</td></tr>';
                    } else {
                        data.forEach(error => {
                            html += `<tr>
                                <td>${error.id}</td>
                                <td><code>${error.tabla_afectada}</code></td>
                                <td>${error.intentos_replicacion}/3</td>
                                <td><small class="text-danger">${error.mensaje_error || 'N/A'}</small></td>
                                <td>${error.created_at}</td>
                            </tr>`;
                        });
                    }
                    $('#tablaErrores').html(html);
                })
                .fail(function() {
                    $('#tablaErrores').html('<tr><td colspan="5" class="text-center text-danger">Error cargando errores</td></tr>');
                });
        }
        /*
        Carga los logs del sistema (ultimos 20 registros)
        Endpoint: api/logs.php?action=recent&lines=20
        */
        function cargarLogs() {
            $.getJSON('api/logs.php?action=recent&lines=20')
                .done(function(data) {
                    if (data.success) {
                        let html = '';
                        if (data.logs.length === 0) {
                            html = '<tr><td colspan="4" class="text-center text-success">✅ No hay logs recientes</td></tr>';
                        } else {
                            data.logs.forEach(function(log) {
                                //Determinar clase e icono segun nivel de log

                                let levelClass = '';
                                let levelIcon = '';
                                if (log.level === 'ERROR' || log.level === 'CRITICAL') {
                                    levelClass = 'text-danger';
                                    levelIcon = '❌';
                                } else if (log.level === 'WARNING') {
                                    levelClass = 'text-warning';
                                    levelIcon = '⚠️';
                                } else if (log.level === 'INFO') {
                                    levelClass = 'text-info';
                                    levelIcon = 'ℹ️';
                                } else {
                                    levelClass = 'text-secondary';
                                    levelIcon = '📝';
                                }
                                //Formatear contexto si existe
                                let contextStr = '';
                                if (log.context) {
                                    contextStr = '<pre class="mb-0" style="font-size:10px; max-width:200px; white-space:pre-wrap; word-break:break-all;">' + JSON.stringify(log.context, null, 2) + '</pre>';
                                }
                                
                                html += `<tr>
                                    <td><small>${log.timestamp}</small></td>
                                    <td><span class="${levelClass}">${levelIcon} ${log.level}</span></td>
                                    <td><small>${log.message}</small></td>
                                    <td>${contextStr || '-'}</td>
                                </tr>`;
                            });
                        }
                        $('#cuerpoLogs').html(html);
                    }
                })
                .fail(function() {
                    $('#cuerpoLogs').html('<tr><td colspan="4" class="text-center text-danger">Error cargando logs</td></tr>');
                });
        }
        /*
        Actualiza el estado de conexion de ambas bases de datos
        Endpoint: api/estado_conexion.php
        */
        function cargarEstadoConexion() {
            $.getJSON('api/estado_conexion.php')
                .done(function(data) {
                    //Actualizar estado de MySQL
                    let mysqlSpan = $('#estado_mysql');
                    if (data.mysql) {
                        mysqlSpan.html('🟢 Online');
                        mysqlSpan.attr('class', 'status-online');
                        mysqlSpan.attr('data-tooltip', 'Conexión exitosa a Aiven MySQL');
                    } else {
                        mysqlSpan.html('🔴 Offline');
                        mysqlSpan.attr('class', 'status-offline');
                        mysqlSpan.attr('data-tooltip', data.mysql_error || 'MySQL no disponible');
                    }
                    //Actualizar estado de Oracle
                    let oracleSpan = $('#estado_oracle');
                    if (data.oracle) {
                        oracleSpan.html('🟢 Online');
                        oracleSpan.attr('class', 'status-online');
                        oracleSpan.attr('data-tooltip', data.oracle_detalle || 'Conexión exitosa a AWS Oracle');
                    } else {
                        oracleSpan.html('🔴 Offline');
                        oracleSpan.attr('class', 'status-offline');
                        oracleSpan.attr('data-tooltip', data.oracle_error || data.oracle_detalle || 'Oracle no disponible');
                    }
                    //Actualizar ultima ejecucion
                    $('#ultima_ejecucion').text(data.ultima_ejecucion || 'No registrada');
                })
                .fail(function() {
                    console.error('Error al obtener estado de conexión');
                });
        }
        /*
        Carga y muestra la comparacion de datos entre MySQL y Oracle
        Endpoint: api/comparacion_replicacion.php
        
        Muestra:
         - Resumen con totales y estado de sincronizacion
         - Tabla detallada por cada tabla
         */
        function cargarComparacion() {
            $.getJSON('api/comparacion_replicacion.php')
                .done(function(data) {
                    //Generar resumen de comparacion
                    let resumenHtml = '';
                    if (data.mysql_online && data.oracle_online) {
                        const totalDiff = Math.abs((data.total_mysql_records || 0) - (data.total_oracle_records || 0));
                        const isSynchronized = data.total_mysql_records === data.total_oracle_records;
                        
                        resumenHtml = `
                            <div class="row">
                                <div class="col-md-3 col-sm-6">
                                    <div class="alert alert-info text-center p-2 mb-1">
                                        <strong>📊 MySQL</strong>
                                        <br><span class="h5">${data.total_mysql_records || 0}</span> registros
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="alert alert-info text-center p-2 mb-1">
                                        <strong>📊 Oracle</strong>
                                        <br><span class="h5">${data.total_oracle_records || 0}</span> registros
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="alert ${isSynchronized ? 'alert-success' : 'alert-warning'} text-center p-2 mb-1">
                                        <strong>📈 Diferencia</strong>
                                        <br><span class="h5">${totalDiff}</span>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="alert ${isSynchronized ? 'alert-success' : 'alert-danger'} text-center p-2 mb-1">
                                        <strong>${isSynchronized ? '✅ Sincronizado' : '❌ Desincronizado'}</strong>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        resumenHtml = `
                            <div class="alert alert-warning">
                                ⚠️ Uno o ambos motores no están disponibles
                            </div>
                        `;
                    }
                    $('#comparacionResumen').html(resumenHtml);
                    //Generar tabla de comparacion detallada
                    let html = '';
                    //Verificar disponibilidad de bases de datos
                    if (!data.mysql_online || !data.oracle_online) {
                        html = `<tr><td colspan="6" class="text-center text-warning">
                            ⚠️ ${!data.mysql_online ? 'MySQL' : ''} 
                            ${!data.mysql_online && !data.oracle_online ? 'y' : ''} 
                            ${!data.oracle_online ? 'Oracle' : ''} no está disponible
                        </td></tr>`;
                        $('#cuerpoComparacion').html(html);
                        return;
                    }
                    //Generar filas para cada tabla comparada
                    data.comparison.forEach(function(item) {
                        let statusBadge = '';
                        let statusText = '';
                        let rowClass = '';
                        let diffText = '';
                        //Determinar estado segun comparacion
                        if (item.mysql_count === 'N/A' || item.oracle_count === 'N/A') {
                            statusBadge = 'badge bg-warning';
                            statusText = '⚠️ Error';
                            rowClass = 'table-warning';
                            diffText = 'N/A';
                        } else if (item.match) {
                            statusBadge = 'badge bg-success';
                            statusText = '✅ Sincronizado';
                            rowClass = 'table-success';
                            diffText = '0';
                        } else {
                            statusBadge = 'badge bg-danger';
                            statusText = '❌ Desincronizado';
                            rowClass = 'table-danger';
                            diffText = item.difference;
                        }
                        
                        html += `<tr class="${rowClass}">
                            <td><code>${item.mysql_table}</code></td>
                            <td><code>${item.oracle_table}</code></td>
                            <td class="text-center"><strong>${item.mysql_count}</strong></td>
                            <td class="text-center"><strong>${item.oracle_count}</strong></td>
                            <td class="text-center"><strong>${diffText}</strong></td>
                            <td><span class="${statusBadge}">${statusText}</span></td>
                        </tr>`;
                    });
                    
                    $('#cuerpoComparacion').html(html);
                })
                .fail(function() {
                    $('#cuerpoComparacion').html(`
                        <tr><td colspan="6" class="text-center text-danger">Error al cargar comparación</td></tr>
                    `);
                });
        }
        //Recarga la tabla de eventos al hacer clic en el boton
        $('#btnAplicarFiltros').click(function() {
            cargarEventosRecientes();
        });
        //Recarga datos cada 30 segundos solo si la pestaña esta visible
        setInterval(function() {
            if (document.hidden) return;// No recargar si la pestaña no esta visible
            cargarDatos();
        }, 30000);
    </script>
    <?php else: ?>
    <!--SCRIPT PARA CUANDO MYSQL ESTA OFFLINE-->
    <script>
        function checkReconnection() {
            $.getJSON('api/estado_conexion.php')
                .done(function(data) {
                    //Si MySQL se reconecta, recargar pagina
                    if (data.mysql) {
                        location.reload();
                    }
                    //Actualizar estado de Oracle si cambia
                    if (data.oracle) {
                        $('#estado_oracle').html('🟢 Online');
                        $('#estado_oracle').attr('class', 'status-online');
                    } else {
                        $('#estado_oracle').html('🔴 Offline');
                        $('#estado_oracle').attr('class', 'status-offline');
                    }
                });
        }
        //Configura verificacion periodica cada 10 segundos
        $(document).ready(function() {
            setInterval(checkReconnection, 10000);
        });
    </script>
    <?php endif; ?>
</body>
</html>