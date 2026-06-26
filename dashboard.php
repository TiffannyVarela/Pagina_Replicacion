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
        <title>Dashboard Replicacion Naviera</title>
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
                📊 Sistema de Replicacion Bidireccional
            </h1>
            <h6 class="mb-4 text-muted">MySQL ↔ Oracle | GlobalShipping Corp.</h6>
            
            <!--Indicadores numericos-->
            <div class="row mb-4" id="indicadores">
            <!-- Pendientes Totales -->
            <div class="col-md-3 col-sm-6">
                <div class="card text-center card-stats <?php echo !$mysql_online ? 'offline-card' : ''; ?>">
                    <div class="card-body">
                        <h5 class="card-title">⏳ Pendientes</h5>
                        <h2 class="text-warning" id="pendientes"><?php echo !$mysql_online ? '?' : '-'; ?></h2>
                        <small class="text-muted" id="pendientes_detalle">MySQL: 0 | Oracle: 0</small>
                    </div>
                </div>
            </div>
            <!-- Replicados Totales -->
            <div class="col-md-3 col-sm-6">
                <div class="card text-center card-stats <?php echo !$mysql_online ? 'offline-card' : ''; ?>">
                    <div class="card-body">
                        <h5 class="card-title">✅ Replicados</h5>
                        <h2 class="text-success" id="replicados"><?php echo !$mysql_online ? '?' : '-'; ?></h2>
                        <small class="text-muted" id="replicados_detalle">MySQL: 0 | Oracle: 0</small>
                    </div>
                </div>
            </div>
            <!-- Errores Totales -->
            <div class="col-md-3 col-sm-6">
                <div class="card text-center card-stats <?php echo !$mysql_online ? 'offline-card' : ''; ?>">
                    <div class="card-body">
                        <h5 class="card-title">❌ Errores</h5>
                        <h2 class="text-danger" id="errores"><?php echo !$mysql_online ? '?' : '-'; ?></h2>
                        <small class="text-muted" id="errores_detalle">MySQL: 0 | Oracle: 0</small>
                    </div>
                </div>
            </div>
            <!-- Conflictos Totales -->
            <div class="col-md-3 col-sm-6">
                <div class="card text-center card-stats <?php echo !$mysql_online ? 'offline-card' : ''; ?>">
                    <div class="card-body">
                        <h5 class="card-title">⚔️ Conflictos</h5>
                        <h2 class="text-info" id="conflictos"><?php echo !$mysql_online ? '?' : '-'; ?></h2>
                        <small class="text-muted" id="conflictos_detalle">MySQL: 0 | Oracle: 0</small>
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
                            <h5>📈 Estado de Replicacion MySQL</h5>
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
                            <h5>📈 Estado de Replicacion Oracle</h5>
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
            
            <!--Eventos por Hora (MySQL y Oracle) y Estado de Conexion-->
            <div class="row mb-4">
                <!--Grafica: Eventos por Hora - MySQL-->
                <div class="col-md-5">
                    <div class="card">
                        <div class="card-header card-header-custom">
                            <h5>📊 Eventos por Hora - MySQL (últimas 24h)</h5>
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
                <!--Grafica: Eventos por Hora - Oracle-->
                <div class="col-md-5">
                    <div class="card">
                        <div class="card-header card-header-custom">
                            <h5>📊 Eventos por Hora - Oracle (últimas 24h)</h5>
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
                            <h5>🔌 Conexion</h5>
                        </div>
                        <div class="card-body">
                            <div id="estadoConexion">
                                <?php
                                /*Preparar variables de estado para MySQL y Oracle. Estas se mostraran en la tarjeta de conexion*/
                                $mysql_status_text = $mysql_online ? '🟢 Online' : '🔴 Offline';
                                $mysql_status_class = $mysql_online ? 'status-online' : 'status-offline';
                                $mysql_tooltip = $mysql_online ? 'Conexion exitosa a Aiven MySQL' : 'Conexion fallida';
                                
                                $oracle_status_text = $oracle_online ? '🟢 Online' : '🔴 Offline';
                                $oracle_status_class = $oracle_online ? 'status-online' : 'status-offline';
                                $oracle_tooltip = $oracle_online ? 'Conexion exitosa a AWS Oracle' : 'Conexion fallida';
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
            
            <!--Comparacion de Cantidad de Datos: MySQL vs Oracle-->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header card-header-custom">
                            <h5>🔄 Comparacion de Cantidad de Datos: MySQL vs Oracle</h5>
                            <small class="text-white-50">Verifica que el número de registros coincida</small>
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
            <!--Comparacion de Contenido: MySQL vs Oracle-->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header card-header-custom">
                            <h5>🔍 Comparacion de Contenido: MySQL vs Oracle</h5>
                            <small class="text-white-50">Compara el contenido real de los datos, no solo la cantidad</small>
                        </div>
                        <div class="card-body">
                            <!--Resumen de comparacion de contenido-->
                            <div id="comparacionContenidoResumen" class="mb-2"></div>
                            <!--Tabla de comparacion de contenido detallada-->
                            <div class="table-responsive">
                                <table class="table table-sm table-hover" id="tablaComparacionContenido">
                                    <thead>
                                        <tr>
                                            <th>Tabla MySQL</th>
                                            <th>Tabla Oracle</th>
                                            <th class="text-center">Registros MySQL</th>
                                            <th class="text-center">Registros Oracle</th>
                                            <th class="text-center">Solo MySQL</th>
                                            <th class="text-center">Solo Oracle</th>
                                            <th class="text-center">Con diferencias</th>
                                            <th class="text-center">% Sincronizacion</th>
                                            <th>Estado</th>
                                            <th>Accion</th>
                                        </tr>
                                    </thead>
                                    <tbody id="cuerpoComparacionContenido">
                                        <tr><td colspan="10" class="text-center">Cargando...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <!--Detalle de diferencias-->
                            <div id="detalleDiferencias" class="mt-3" style="display:none;">
                                <div class="card">
                                    <div class="card-header bg-warning">
                                        <h6 class="mb-0">📋 Detalle de diferencias encontradas</h6>
                                    </div>
                                    <div class="card-body" id="contenidoDiferencias"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!--Eventos y Errores. Ultimos 10 eventos procesados con su estado-->
            <div class="card mb-4">
                <div class="card-header card-header-custom">
                    <h5>📋 Últimos 10 Eventos MySQL</h5>
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
                                    <tr><th>ID</th><th>Tabla</th><th>Operacion</th><th>Fecha/Hora</th><th>Estado</th></tr>
                                </thead>
                                <tbody id="tablaEventos">
                                    <tr><td colspan="5" class="text-center">Cargando...</td></tr>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!--Eventos de Oracle-->
            <div class="card mb-4">
                <div class="card-header card-header-custom">
                    <h5>📋 Últimos 10 Eventos Oracle</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <?php if (!$oracle_online): ?>
                            <div class="data-unavailable">
                                <p>📡 Oracle offline</p>
                            </div>
                        <?php else: ?>
                            <table class="table table-sm table-events">
                                <thead>
                                    <tr><th>ID</th><th>Tabla</th><th>Operacion</th><th>Fecha/Hora</th><th>Estado</th></tr>
                                </thead>
                                <tbody id="tablaEventosOracle">
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
            <!--MySQL-->
            <?php if ($mysql_online): ?>
            <div class="card mb-4">
                <div class="card-header card-header-custom">
                    <h5>🔍 Filtros de Búsqueda - MySQL</h5>
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
            <!--Oracle-->
            <?php if ($oracle_online): ?>
            <div class="card mb-4">
                <div class="card-header card-header-custom">
                    <h5>🔍 Filtros de Búsqueda - Oracle</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <label>Filtrar por evento:</label>
                            <select id="filtroTablaOracle" class="form-select">
                                <option value="">Todos</option>
                                <option value="REPLICADO">Replicado</option>
                                <option value="ERROR">Error</option>
                                <option value="CONFLICTO">Conflicto</option>
                                <option value="PENDIENTE">Pendiente</option>
                                <option value="JOB">Job</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Filtrar por estado:</label>
                            <select id="filtroEstadoOracle" class="form-select">
                                <option value="">Todos</option>
                                <option value="REPLICADO">Replicados</option>
                                <option value="PENDIENTE">Pendientes</option>
                                <option value="ERROR">Errores</option>
                                <option value="CONFLICTO">Conflictos</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>&nbsp;</label>
                            <button id="btnAplicarFiltrosOracle" class="btn btn-primary w-100">Aplicar Filtros Oracle</button>
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
                cargarEventosOracle();
                cargarErrores();
                cargarEstadoConexion();
                cargarComparacion();
                cargarComparacionContenido();
                cargarLogs();
            }
            
            /*
            Obtiene y actualiza los KPIs principales del dashboard
            Endpoint: api/indicadores.php
            */
            function cargarIndicadores() {
                $.getJSON('api/indicadores.php')
                    .done(function(data) {
                        //TOTALES COMBINADOS
                        $('#pendientes').text(data.total_pendientes || 0);
                        $('#replicados').text(data.total_replicados || 0);
                        $('#errores').text(data.total_errores || 0);
                        $('#conflictos').text(data.total_conflictos || 0);
                        
                        //DETALLES POR BASE
                        $('#pendientes_detalle').text('MySQL: ' + (data.pendientes || 0) + ' | Oracle: ' + (data.oracle_pendientes || 0));
                        $('#replicados_detalle').text('MySQL: ' + (data.replicados || 0) + ' | Oracle: ' + (data.oracle_replicados || 0));
                        $('#errores_detalle').text('MySQL: ' + (data.errores || 0) + ' | Oracle: ' + (data.oracle_errores || 0));
                        $('#conflictos_detalle').text('MySQL: ' + (data.conflictos || 0) + ' | Oracle: ' + (data.oracle_conflictos || 0));
                        
                        //TOTAL DE REGISTROS
                        let totalGeneral = (data.total_pendientes || 0) + (data.total_replicados || 0) + (data.total_errores || 0) + (data.total_conflictos || 0);
                        $('#total_registros').text(totalGeneral + ' registros procesados en total');
                        
                        //ESTADÍSTICAS DE TABLAS
                        if (data.mysql_online) {
                            $('#mysql_detalle').text('Aiven Cloud - ' + (data.mysql_rows || 0) + ' registros en ' + (data.mysql_tables || 0) + ' tablas');
                        }
                        
                        if (data.oracle_online) {
                            $('#oracle_detalle').text('AWS RDS - ' + (data.oracle_rows || 0) + ' registros en ' + (data.oracle_tables || 0) + ' tablas');
                        }
                    })
                    .fail(function() {
                        //Totales
                        $('#pendientes').text('?');
                        $('#replicados').text('?');
                        $('#errores').text('?');
                        $('#conflictos').text('?');
                        
                        //Detalles
                        $('#pendientes_detalle').text('Error al cargar');
                        $('#replicados_detalle').text('Error al cargar');
                        $('#errores_detalle').text('Error al cargar');
                        $('#conflictos_detalle').text('Error al cargar');
                    });
            }
            
            /*
            Obtiene datos para todas las graficas y las renderiza
            Endpoint: api/estadisticas_graficas.php
            */
            function cargarEstadisticasGraficas() {
                $.getJSON('api/estadisticas_graficas.php')
                    .done(function(data) {
                        console.log('📊 Datos recibidos para graficas:', data);
                        
                        //Verificar si los datos reales tienen informacion
                        const hasReplicados = data.replicados_por_tabla && data.replicados_por_tabla.some(v => v > 0);
                        const hasPendientes = data.pendientes_por_tabla && data.pendientes_por_tabla.some(v => v > 0);
                        const hasErrores = data.errores_por_tabla && data.errores_por_tabla.some(v => v > 0);
                        const hasOracleData = data.oracle && data.oracle.online && data.oracle.table_data && 
                                              Object.values(data.oracle.table_data).some(v => v > 0);
                        const hasEventosMySQL = data.eventos_hora_mysql && data.eventos_hora_mysql.cantidades && 
                                                data.eventos_hora_mysql.cantidades.some(v => v > 0);
                        const hasEventosOracle = data.eventos_hora_oracle && data.eventos_hora_oracle.cantidades && 
                                                 data.eventos_hora_oracle.cantidades.some(v => v > 0);
                        const hasOracleEstados = data.oracle_estados && data.oracle_estados.tablas && 
                                                 data.oracle_estados.tablas.length > 0;
                        
                        //Si hay datos, crear graficas
                        if (hasReplicados || hasPendientes || hasErrores || hasOracleData || hasEventosMySQL || hasEventosOracle || hasOracleEstados) {
                            console.log('✅ Creando graficas con datos reales');
                            crearGraficas(data);
                        } else {
                            console.warn('⚠️ No hay datos reales, mostrando graficas vacías');
                            crearGraficasVacias();
                        }
                    })
                    .fail(function(jqXHR, textStatus, errorThrown) {
                        console.error('❌ Error cargando datos reales:', textStatus, errorThrown);
                        console.warn('⚠️ Error de conexion, mostrando graficas vacías');
                        crearGraficasVacias();
                    });
            }
            
            /*
            Crea y renderiza todas las graficas con los datos proporcionados
            */
            function crearGraficas(data) {
                //Obtener todos los canvas
                const canvasEstadoMySQL = document.getElementById('chartEstadoMySQL');
                const canvasEstadoOracle = document.getElementById('chartEstadoOracle');
                const canvasPorcentaje = document.getElementById('chartPorcentajeExito');
                const canvasEventosMySQL = document.getElementById('chartEventosMySQL');
                const canvasEventosOracle = document.getElementById('chartEventosOracle');
                
                //Estado de Replicacion MySQL
                if (canvasEstadoMySQL) {
                    if (chartEstadoMySQL) chartEstadoMySQL.destroy();
                    
                    let labels = data.tablas || [];
                    if (labels.length === 0) labels = ['Sin datos'];
                    
                    if (typeof getFriendlyTableNames === 'function') {
                        labels = getFriendlyTableNames(labels);
                    }
                    
                    const hasReplicados = data.replicados_por_tabla && data.replicados_por_tabla.some(v => v > 0);
                    const hasPendientes = data.pendientes_por_tabla && data.pendientes_por_tabla.some(v => v > 0);
                    const hasErrores = data.errores_por_tabla && data.errores_por_tabla.some(v => v > 0);
                    
                    let datasets = [];
                    if (hasReplicados || hasPendientes || hasErrores) {
                        datasets = [
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
                        datasets = [{
                            label: '📊 Sin datos de replicacion',
                            data: new Array(labels.length).fill(0),
                            backgroundColor: '#6c757d'
                        }];
                    }
                    
                    chartEstadoMySQL = new Chart(canvasEstadoMySQL, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: datasets
                        },
                        options: BAR_CHART_OPTIONS
                    });
                    console.log('✅ Grafica Estado MySQL creada');
                }
                
                //Estado de Replicacion Oracle
                if (canvasEstadoOracle) {
                    if (chartEstadoOracle) chartEstadoOracle.destroy();
                    
                    let oracleLabels = [];
                    let oracleReplicados = [];
                    let oraclePendientes = [];
                    let oracleErrores = [];
                    let tieneDatosEstados = false;
                    
                    //Verificar si tenemos datos de estados de Oracle
                    if (data.oracle_estados && data.oracle_estados.tablas && data.oracle_estados.tablas.length > 0) {
                        const estadosData = data.oracle_estados;
                        oracleLabels = estadosData.tablas.map(function(tabla) {
                            let friendly = tabla;
                            if (typeof getFriendlyTableName === 'function') {
                                friendly = getFriendlyTableName(tabla);
                            }
                            return friendly;
                        });
                        oracleReplicados = estadosData.replicados_por_tabla || [];
                        oraclePendientes = estadosData.pendientes_por_tabla || [];
                        oracleErrores = estadosData.errores_por_tabla || [];
                        
                        tieneDatosEstados = oracleReplicados.some(v => v > 0) || 
                                            oraclePendientes.some(v => v > 0) || 
                                            oracleErrores.some(v => v > 0);
                    }
                    
                    if (tieneDatosEstados) {
                        //Grafica con 3 colores para Oracle
                        chartEstadoOracle = new Chart(canvasEstadoOracle, {
                            type: 'bar',
                            data: {
                                labels: oracleLabels,
                                datasets: [
                                    {
                                        label: '✅ Replicados (Oracle)',
                                        data: oracleReplicados,
                                        backgroundColor: '#20c997'  //Verde menta
                                    },
                                    {
                                        label: '⏳ Pendientes (Oracle)',
                                        data: oraclePendientes,
                                        backgroundColor: '#fd7e14'  //Naranja
                                    },
                                    {
                                        label: '❌ Errores (Oracle)',
                                        data: oracleErrores,
                                        backgroundColor: '#dc3545'  //Rojo
                                    }
                                ]
                            },
                            options: BAR_CHART_OPTIONS
                        });
                    } else {
                        //Fallback: mostrar conteo total por tabla
                        let oracleLabelsFallback = [];
                        let oracleDataFallback = [];
                        
                        if (data.oracle && data.oracle.online && data.oracle.table_data) {
                            const oracleTableNames = Object.keys(data.oracle.table_data);
                            if (oracleTableNames.length > 0) {
                                oracleTableNames.forEach(function(tableName) {
                                    let friendlyName = tableName;
                                    try {
                                        if (typeof getFriendlyTableName === 'function') {
                                            friendlyName = getFriendlyTableName(tableName);
                                        }
                                    } catch(e) {
                                        console.warn('Error al obtener nombre amigable para:', tableName, e);
                                    }
                                    oracleLabelsFallback.push(friendlyName);
                                    oracleDataFallback.push(data.oracle.table_data[tableName] || 0);
                                });
                            }
                        }
                        
                        if (oracleLabelsFallback.length === 0) {
                            oracleLabelsFallback = ['Sin datos'];
                            oracleDataFallback = [0];
                        }
                        
                        const hasOracleData = oracleDataFallback.some(v => v > 0);
                        
                        chartEstadoOracle = new Chart(canvasEstadoOracle, {
                            type: 'bar',
                            data: {
                                labels: oracleLabelsFallback,
                                datasets: [{
                                    label: hasOracleData ? '📊 Registros Oracle' : '📊 Sin datos en Oracle',
                                    data: oracleDataFallback,
                                    backgroundColor: hasOracleData ? '#6c5ce7' : '#6c757d'
                                }]
                            },
                            options: BAR_CHART_OPTIONS
                        });
                    }
                }

                
                //Porcentaje de Éxito
                if (canvasPorcentaje) {
                    if (chartPorcentajeExito) chartPorcentajeExito.destroy();
                    
                    let exito = data.porcentaje_exito || 0;
                    if (isNaN(exito)) exito = 0;
                    
                    chartPorcentajeExito = new Chart(canvasPorcentaje, {
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
                }
                
                //Eventos por Hora - MySQL
                if (canvasEventosMySQL) {
                    if (chartEventosMySQL) chartEventosMySQL.destroy();
                    
                    let eventosHoras = [];
                    let eventosCantidades = [];
                    
                    if (data.eventos_hora_mysql && data.eventos_hora_mysql.horas && data.eventos_hora_mysql.horas.length > 0) {
                        eventosHoras = data.eventos_hora_mysql.horas;
                        eventosCantidades = data.eventos_hora_mysql.cantidades || [];
                    } else {
                        for (let i = 0; i < 24; i++) {
                            eventosHoras.push(String(i).padStart(2, '0') + ':00');
                            eventosCantidades.push(0);
                        }
                    }
                    
                    const hasEventos = eventosCantidades.some(v => v > 0);
                    
                    chartEventosMySQL = new Chart(canvasEventosMySQL, {
                        type: 'bar',
                        data: {
                            labels: eventosHoras,
                            datasets: [{
                                label: hasEventos ? CHART_COLORS.mysql.label : '📡 Sin eventos MySQL',
                                data: eventosCantidades,
                                backgroundColor: hasEventos ? CHART_COLORS.mysql.primary : '#6c757d',
                                borderColor: hasEventos ? CHART_COLORS.mysql.primary : '#6c757d',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                }
                            },
                            scales: {
                                x: {
                                    ticks: {
                                        maxRotation: 45,                                minRotation: 30,
                                        font: {
                                            size: 8
                                        }
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            }
                        }
                    });
                }
                
                //Eventos por Hora - Oracle
                if (canvasEventosOracle) {
                    if (chartEventosOracle) chartEventosOracle.destroy();
                    
                    let oracleHoras = [];
                    let oracleCantidades = [];
                    
                    if (data.eventos_hora_oracle && data.eventos_hora_oracle.horas && data.eventos_hora_oracle.horas.length > 0) {
                        oracleHoras = data.eventos_hora_oracle.horas;
                        oracleCantidades = data.eventos_hora_oracle.cantidades || [];
                    } else {
                        for (let i = 0; i < 24; i++) {
                            oracleHoras.push(String(i).padStart(2, '0') + ':00');
                            oracleCantidades.push(0);
                        }
                    }
                    
                    const hasOracleEvents = oracleCantidades.some(v => v > 0);
                    
                    chartEventosOracle = new Chart(canvasEventosOracle, {
                        type: 'bar',
                        data: {
                            labels: oracleHoras,
                            datasets: [{
                                label: hasOracleEvents ? CHART_COLORS.oracle.label : '📡 Sin eventos Oracle',
                                data: oracleCantidades,
                                backgroundColor: hasOracleEvents ? CHART_COLORS.oracle.primary : '#6c757d',
                                borderColor: hasOracleEvents ? CHART_COLORS.oracle.primary : '#6c757d',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                }
                            },
                            scales: {
                                x: {
                                    ticks: {
                                        maxRotation: 45,
                                        minRotation: 30,
                                        font: {
                                            size: 8
                                        }
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            }
                        }
                    });
                }
            }
            
            /*
            Crea graficas con datos vacios cuando no hay informacion disponible
            */
            function crearGraficasVacias() {
                console.warn('📊 Mostrando graficas vacías (sin datos)');
                
                const canvasEstadoMySQL = document.getElementById('chartEstadoMySQL');
                const canvasEstadoOracle = document.getElementById('chartEstadoOracle');
                const canvasPorcentaje = document.getElementById('chartPorcentajeExito');
                const canvasEventosMySQL = document.getElementById('chartEventosMySQL');
                const canvasEventosOracle = document.getElementById('chartEventosOracle');
                
                //Horas para eventos
                let horas = [];
                for (let i = 0; i < 24; i++) {
                    horas.push(String(i).padStart(2, '0') + ':00');
                }
                
                //Grafica Eventos MySQL vacía
                if (canvasEventosMySQL) {
                    if (chartEventosMySQL) chartEventosMySQL.destroy();
                    chartEventosMySQL = new Chart(canvasEventosMySQL, {
                        type: 'bar',
                        data: {
                            labels: horas,
                            datasets: [{
                                label: '📊 Sin eventos MySQL',
                                data: new Array(24).fill(0),
                                backgroundColor: '#6c757d',
                                borderColor: '#6c757d',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                }
                            },
                            scales: {
                                x: {
                                    ticks: {
                                        maxRotation: 45,
                                        minRotation: 30,
                                        font: {
                                            size: 8
                                        }
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            }
                        }
                    });
                }
                
                //Grafica Eventos Oracle vacía
                if (canvasEventosOracle) {
                    if (chartEventosOracle) chartEventosOracle.destroy();
                    chartEventosOracle = new Chart(canvasEventosOracle, {
                        type: 'bar',
                        data: {
                            labels: horas,
                            datasets: [{
                                label: '📊 Sin eventos Oracle',
                                data: new Array(24).fill(0),
                                backgroundColor: '#6c757d',
                                borderColor: '#6c757d',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                }
                            },
                            scales: {
                                x: {
                                    ticks: {
                                        maxRotation: 45,
                                        minRotation: 30,
                                        font: {
                                            size: 8
                                        }
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            }
                        }
                    });
                }
                
                //Grafica Porcentaje vacía
                if (canvasPorcentaje) {
                    if (chartPorcentajeExito) chartPorcentajeExito.destroy();
                    chartPorcentajeExito = new Chart(canvasPorcentaje, {
                        type: 'doughnut',
                        data: {
                            labels: ['Sin datos', 'Sin datos'],
                            datasets: [{
                                data: [50, 50],
                                backgroundColor: ['#6c757d', '#dee2e6'],
                                borderWidth: 2
                            }]
                        },
                        options: DOUGHNUT_CHART_OPTIONS
                    });
                }
                
                //Grafica Estado MySQL vacia
                if (canvasEstadoMySQL) {
                    if (chartEstadoMySQL) chartEstadoMySQL.destroy();
                    chartEstadoMySQL = new Chart(canvasEstadoMySQL, {
                        type: 'bar',
                        data: {
                            labels: ['Sin datos'],
                            datasets: [{
                                label: '📊 Sin datos',
                                data: [0],
                                backgroundColor: '#6c757d'
                            }]
                        },
                        options: BAR_CHART_OPTIONS
                    });
                }
                
                //Grafica Estado Oracle vacia
                if (canvasEstadoOracle) {
                    if (chartEstadoOracle) chartEstadoOracle.destroy();
                    chartEstadoOracle = new Chart(canvasEstadoOracle, {
                        type: 'bar',
                        data: {
                            labels: ['Sin datos'],
                            datasets: [{
                                label: '📊 Sin datos',
                                data: [0],
                                backgroundColor: '#6c757d'
                            }]
                        },
                        options: BAR_CHART_OPTIONS
                    });
                }
            }
            
            /*
            Carga los eventos de Oracle
            */
            function cargarEventosOracle() {
                $.getJSON('api/eventos_oracle.php')
                    .done(function(data) {
                        let html = '';
                        if (data.length === 0) {
                            html = '<tr><td colspan="5" class="text-center text-muted">📭 Sin eventos registrados en Oracle</td></tr>';
                        } else {
                            data.forEach(evento => {
                                let badgeClass = 'secondary';
                                let estado = evento.estado_replicacion || 'DESCONOCIDO';
                                
                                if (estado === 'REPLICADO') badgeClass = 'success';
                                else if (estado === 'PENDIENTE') badgeClass = 'warning';
                                else if (estado === 'ERROR') badgeClass = 'danger';
                                else if (estado === 'CONFLICTO') badgeClass = 'info';
                                
                                html += `<tr>
                                    <td><code>${evento.id || 'N/A'}</code></td>
                                    <td><code>${evento.tabla_afectada || 'N/A'}</code></td>
                                    <td><span class="badge bg-secondary">${evento.tipo_operacion || 'N/A'}</span></td>
                                    <td><small>${evento.fecha_hora || 'N/A'}</small></td>
                                    <td><span class="badge bg-${badgeClass}">${estado}</span></td>
                                </tr>`;
                            });
                        }
                        $('#tablaEventosOracle').html(html);
                    })
                    .fail(function(jqXHR, textStatus, errorThrown) {
                        console.error('Error cargando eventos Oracle:', textStatus, errorThrown);
                        $('#tablaEventosOracle').html('<tr><td colspan="5" class="text-center text-danger">❌ Error al cargar eventos de Oracle</td></tr>');
                    });
            }
            
            /*
            Carga los eventos de Oracle con filtros
            */
            function cargarEventosOracleFiltrados() {
                const tabla = $('#filtroTablaOracle').val();
                const estado = $('#filtroEstadoOracle').val();
                let url = 'api/eventos_oracle_filtrados.php?';
                if (tabla) url += 'tabla=' + encodeURIComponent(tabla) + '&';
                if (estado) url += 'estado=' + encodeURIComponent(estado);
                
                $.getJSON(url)
                    .done(function(data) {
                        let html = '';
                        if (data.length === 0) {
                            html = '<tr><td colspan="5" class="text-center text-muted">📭 Sin eventos en Oracle con estos filtros</td></tr>';
                        } else {
                            data.forEach(evento => {
                                let badgeClass = 'secondary';
                                let estado = evento.estado_replicacion || 'DESCONOCIDO';
                                
                                if (estado === 'REPLICADO') badgeClass = 'success';
                                else if (estado === 'PENDIENTE') badgeClass = 'warning';
                                else if (estado === 'ERROR') badgeClass = 'danger';
                                else if (estado === 'CONFLICTO') badgeClass = 'info';
                                
                                html += `<tr>
                                    <td><code>${evento.id || 'N/A'}</code></td>
                                    <td><code>${evento.tabla_afectada || 'N/A'}</code></td>
                                    <td><span class="badge bg-secondary">${evento.tipo_operacion || 'N/A'}</span></td>
                                    <td><small>${evento.fecha_hora || 'N/A'}</small></td>
                                    <td><span class="badge bg-${badgeClass}">${estado}</span></td>
                                </tr>`;
                            });
                        }
                        $('#tablaEventosOracle').html(html);
                    })
                    .fail(function() {
                        $('#tablaEventosOracle').html('<tr><td colspan="5" class="text-center text-danger">❌ Error al cargar eventos de Oracle</td></tr>');
                    });
            }

            /*
            Carga los ultimos eventos procesados aplicando filtros
            Endpoint: api/eventos_recientes.php
            */
            function cargarEventosRecientes() {
                const tabla = $('#filtroTabla').val();
                const estado = $('#filtroEstado').val();
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
                        let mysqlSpan = $('#estado_mysql');
                        if (data.mysql) {
                            mysqlSpan.html('🟢 Online');
                            mysqlSpan.attr('class', 'status-online');
                            mysqlSpan.attr('data-tooltip', 'Conexion exitosa a Aiven MySQL');
                        } else {
                            mysqlSpan.html('🔴 Offline');
                            mysqlSpan.attr('class', 'status-offline');
                            mysqlSpan.attr('data-tooltip', data.mysql_error || 'MySQL no disponible');
                        }
                        
                        let oracleSpan = $('#estado_oracle');
                        if (data.oracle) {
                            oracleSpan.html('🟢 Online');
                            oracleSpan.attr('class', 'status-online');
                            oracleSpan.attr('data-tooltip', data.oracle_detalle || 'Conexion exitosa a AWS Oracle');
                        } else {
                            oracleSpan.html('🔴 Offline');
                            oracleSpan.attr('class', 'status-offline');
                            oracleSpan.attr('data-tooltip', data.oracle_error || data.oracle_detalle || 'Oracle no disponible');
                        }
                        
                        $('#ultima_ejecucion').text(data.ultima_ejecucion || 'No registrada');
                    })
                    .fail(function() {
                        console.error('Error al obtener estado de conexion');
                    });
            }

            //Boton de filtros
            //MySQL
            $('#btnAplicarFiltros').click(function() {
                cargarEventosRecientes();
            });
            //Oracle
            $('#btnAplicarFiltrosOracle').click(function() {
                cargarEventosOracleFiltrados();
            });

            /*
            Carga la comparacion de CANTIDAD entre MySQL y Oracle
            Endpoint: api/comparacion_replicacion.php
            */
            function cargarComparacion() {
                $.getJSON('api/comparacion_replicacion.php')
                    .done(function(data) {
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
                            resumenHtml = `<div class="alert alert-warning">⚠️ Uno o ambos motores no estan disponibles</div>`;
                        }
                        $('#comparacionResumen').html(resumenHtml);
                        
                        let html = '';
                        if (!data.mysql_online || !data.oracle_online) {
                            html = `<tr><td colspan="6" class="text-center text-warning">
                                ⚠️ ${!data.mysql_online ? 'MySQL' : ''} 
                                ${!data.mysql_online && !data.oracle_online ? 'y' : ''} 
                                ${!data.oracle_online ? 'Oracle' : ''} no esta disponible
                            </td></tr>`;
                        } else {
                            data.comparison.forEach(function(item) {
                                let statusBadge = '';
                                let statusText = '';
                                let rowClass = '';
                                let diffText = '';
                                
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
                        }
                        $('#cuerpoComparacion').html(html);
                    })
                    .fail(function() {
                        $('#cuerpoComparacion').html(`<tr><td colspan="6" class="text-center text-danger">Error al cargar comparacion</td></tr>`);
                    });
            }

            /*
            Carga la comparacion de CONTENIDO
            Endpoint: api/comparacion_contenido.php
            */
            function cargarComparacionContenido() {
                $.getJSON('api/comparacion_contenido.php')
                    .done(function(data) {
                        //RESUMEN DE CONTENIDO
                        let resumenHtml = '';
                        if (data.mysql_online && data.oracle_online) {
                            const totalTables = data.summary.total_tables || 0;
                            const syncedTables = data.summary.synced_tables || 0;
                            const diffCount = data.summary.differences_found || 0;
                            const totalRecords = data.summary.total_records_compared || 0;
                            const onlyMysql = data.summary.only_in_mysql_total || 0;
                            const onlyOracle = data.summary.only_in_oracle_total || 0;
                            const syncPercent = totalTables > 0 ? Math.round((syncedTables /totalTables) * 100) : 0;
                            
                            resumenHtml = `
                                <div class="row">
                                    <div class="col-md-2 col-sm-6">
                                        <div class="alert alert-info text-center p-2 mb-1">
                                            <strong>📊 Tablas</strong>
                                            <br><span class="h5">${totalTables}</span>
                                            <br><small>${syncedTables} sincronizadas</small>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-sm-6">
                                        <div class="alert ${syncPercent === 100 ? 'alert-success' : 'alert-warning'} text-center p-2 mb-1">
                                            <strong>${syncPercent === 100 ? '✅' : '🔄'} Sincronizacion</strong>
                                            <br><span class="h5">${syncPercent}%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-sm-6">
                                        <div class="alert ${diffCount === 0 ? 'alert-success' : 'alert-danger'} text-center p-2 mb-1">
                                            <strong>${diffCount === 0 ? '✅' : '⚠️'} Diferencias</strong>
                                            <br><span class="h5">${diffCount}</span>
                                            <br><small>registros con diferencias</small>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-sm-6">
                                        <div class="alert ${onlyMysql === 0 ? 'alert-success' : 'alert-warning'} text-center p-2 mb-1">
                                            <strong>📤 Solo MySQL</strong>
                                            <br><span class="h5">${onlyMysql}</span>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-sm-6">
                                        <div class="alert ${onlyOracle === 0 ? 'alert-success' : 'alert-warning'} text-center p-2 mb-1">
                                            <strong>📥 Solo Oracle</strong>
                                            <br><span class="h5">${onlyOracle}</span>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-sm-6">
                                        <div class="alert alert-info text-center p-2 mb-1">
                                            <strong>📈 Comparados</strong>
                                            <br><span class="h5">${totalRecords}</span>
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            resumenHtml = `<div class="alert alert-warning">⚠️ Uno o ambos motores no estan disponibles</div>`;
                        }
                        $('#comparacionContenidoResumen').html(resumenHtml);
                        
                        //TABLA DE COMPARACION DE CONTENIDO
                        let html = '';
                        if (!data.mysql_online || !data.oracle_online) {
                            html = `<tr><td colspan="10" class="text-center text-warning">
                                ⚠️ ${!data.mysql_online ? 'MySQL' : ''} 
                                ${!data.mysql_online && !data.oracle_online ? 'y' : ''} 
                                ${!data.oracle_online ? 'Oracle' : ''} no esta disponible
                            </td></tr>`;
                        } else {
                            data.comparison.forEach(function(item) {
                                let statusBadge = '';
                                let statusText = '';
                                let rowClass = '';
                                let hasDifferences = item.only_in_mysql > 0 || item.only_in_oracle > 0 || item.different_content > 0;
                                
                                if (item.synced) {
                                    statusBadge = 'badge bg-success';
                                    statusText = '✅ Sincronizado';
                                    rowClass = 'table-success';
                                } else if (hasDifferences) {
                                    statusBadge = 'badge bg-danger';
                                    statusText = '❌ Con diferencias';
                                    rowClass = 'table-danger';
                                } else {
                                    statusBadge = 'badge bg-warning';
                                    statusText = '⚠️ Revisar';
                                    rowClass = 'table-warning';
                                }
                                
                                let actionBtn = '';
                                if (item.different_content > 0) {
                                    const diffsJson = JSON.stringify(item.differences).replace(/"/g, '&quot;');
                                    actionBtn = `<button class="btn btn-sm btn-outline-info" onclick="verDiferencias('${item.mysql_table}', ${diffsJson})">
                                        🔍 Ver ${item.different_content} dif.
                                    </button>`;
                                } else if (item.only_in_mysql > 0 || item.only_in_oracle > 0) {
                                    actionBtn = `<span class="badge bg-warning">⚠️ Faltan ${item.only_in_mysql + item.only_in_oracle}</span>`;
                                } else {
                                    actionBtn = `<span class="badge bg-success">✅ OK</span>`;
                                }
                                
                                //Color para el porcentaje de sincronizacion
                                let syncColor = 'text-success';
                                if (item.sync_percentage < 50) syncColor = 'text-danger';
                                else if (item.sync_percentage < 90) syncColor = 'text-warning';
                                
                                html += `<tr class="${rowClass}">
                                    <td><code>${item.mysql_table}</code></td>
                                    <td><code>${item.oracle_table}</code></td>
                                    <td class="text-center"><strong>${item.mysql_count}</strong></td>
                                    <td class="text-center"><strong>${item.oracle_count}</strong></td>
                                    <td class="text-center ${item.only_in_mysql > 0 ? 'text-danger' : 'text-success'}">${item.only_in_mysql || 0}</td>
                                    <td class="text-center ${item.only_in_oracle > 0 ? 'text-danger' : 'text-success'}">${item.only_in_oracle || 0}</td>
                                    <td class="text-center ${item.different_content > 0 ? 'text-danger' : 'text-success'}">${item.different_content || 0}</td>
                                    <td class="text-center"><span class="${syncColor}"><strong>${item.sync_percentage}%</strong></span></td>
                                    <td><span class="${statusBadge}">${statusText}</span></td>
                                    <td>${actionBtn}</td>
                                </tr>`;
                            });
                        }
                        $('#cuerpoComparacionContenido').html(html);
                    })
                    .fail(function() {
                        $('#cuerpoComparacionContenido').html(`<tr><td colspan="10" class="text-center text-danger">Error al cargar comparacion de contenido</td></tr>`);
                    });
            }

            /*
            Muestra el detalle de las diferencias encontradas en una tabla
            */
            function verDiferencias(tabla, diferencias) {
                let html = `<h6>🔍 Diferencias en tabla: <code>${tabla}</code></h6>`;
                
                if (diferencias.length === 0) {
                    html += `<div class="alert alert-success">✅ No se encontraron diferencias en esta tabla</div>`;
                } else {
                    html += `<p><strong>Total de diferencias:</strong> ${diferencias.length}</p>`;
                    html += `<div class="table-responsive"><table class="table table-sm table-bordered">`;
                    html += `<thead><tr><th>ID</th><th>Campo</th><th>Valor MySQL</th><th>Valor Oracle</th></tr></thead><tbody>`;
                    
                    diferencias.forEach(function(diff) {
                        const mysqlData = diff.mysql_data || {};
                        const oracleData = diff.oracle_data || {};
                        const allKeys = new Set([...Object.keys(mysqlData), ...Object.keys(oracleData)]);
                        
                        let firstRow = true;
                        allKeys.forEach(function(key) {
                            const mysqlVal = mysqlData[key] !== undefined ? String(mysqlData[key]) : 'NULL';
                            const oracleVal = oracleData[key] !== undefined ? String(oracleData[key]) : 'NULL';
                            
                            if (mysqlVal !== oracleVal) {
                                html += `<tr class="table-warning">
                                    <td>${firstRow ? '<strong>' + diff.id + '</strong>' : ''}</td>
                                    <td><code>${key}</code></td>
                                    <td><span class="text-success">${mysqlVal}</span></td>
                                    <td><span class="text-danger">${oracleVal}</span></td>
                                </tr>`;
                                firstRow = false;
                            }
                        });
                    });
                    
                    html += `</tbody></table></div>`;
                }
                
                html += `<button class="btn btn-sm btn-secondary mt-2" onclick="$('#detalleDiferencias').hide()">Cerrar</button>`;
                
                $('#contenidoDiferencias').html(html);
                $('#detalleDiferencias').show();
                $('#detalleDiferencias')[0].scrollIntoView({ behavior: 'smooth' });
            }
            
            //ACTUALIZACIoN AUTOMaTICA CADA 30 SEGUNDOS
            setInterval(function() {
                if (document.hidden) return;
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