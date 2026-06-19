<?php
require_once 'config/db.php';

// Verificar conexión para mostrar estado inicial
$mysql_online = isMySQLConnected();
$oracle_online = isOracleConnected();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Replicación Naviera <?php echo !$mysql_online ? '- MySQL Offline' : ''; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <style>
        /* Estilos adicionales solo para estados offline*/
        .offline-card { opacity: 0.7; }
        .data-unavailable { background-color: #f8f9fa; text-align: center; padding: 40px; color: #6c757d; border-radius: 8px; }
        .offline-badge { background-color: #dc3545; color: white; font-size: 12px; padding: 2px 8px; border-radius: 20px; margin-left: 10px; }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <h1 class="mb-4">
            📊 Sistema de Replicación Bidireccional
            <?php if (!$mysql_online): ?>
                <span class="offline-badge">MySQL OFFLINE</span>
            <?php endif; ?>
            <?php if (!$oracle_online): ?>
                <span class="offline-badge">Oracle OFFLINE</span>
            <?php endif; ?>
        </h1>
        <h6 class="mb-4 text-muted">MySQL ↔ Oracle | GlobalShipping Corp.</h6>
        
        <div class="row mb-4" id="indicadores">
            <div class="col-md-3">
                <div class="card text-center card-stats <?php echo !$mysql_online ? 'offline-card' : ''; ?>">
                    <div class="card-body">
                        <h5 class="card-title">⏳ Pendientes</h5>
                        <h2 class="text-warning" id="pendientes"><?php echo !$mysql_online ? '?' : '-'; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center card-stats <?php echo !$mysql_online ? 'offline-card' : ''; ?>">
                    <div class="card-body">
                        <h5 class="card-title">✅ Replicados</h5>
                        <h2 class="text-success" id="replicados"><?php echo !$mysql_online ? '?' : '-'; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center card-stats <?php echo !$mysql_online ? 'offline-card' : ''; ?>">
                    <div class="card-body">
                        <h5 class="card-title">❌ Errores</h5>
                        <h2 class="text-danger" id="errores"><?php echo !$mysql_online ? '?' : '-'; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center card-stats <?php echo !$mysql_online ? 'offline-card' : ''; ?>">
                    <div class="card-body">
                        <h5 class="card-title">⚔️ Conflictos</h5>
                        <h2 class="text-info" id="conflictos"><?php echo !$mysql_online ? '?' : '-'; ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>📈 Estado de Replicación por Tabla</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!$mysql_online): ?>
                            <div class="data-unavailable">
                                <p>📡 Datos no disponibles</p>
                            </div>
                        <?php else: ?>
                            <canvas id="chartEstadoTablas" height="250"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>🥧 Porcentaje de Éxito (Últimas 24h)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!$mysql_online): ?>
                            <div class="data-unavailable">
                                <p>📡 Datos no disponibles</p>
                            </div>
                        <?php else: ?>
                            <canvas id="chartPorcentajeExito" height="250"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>📊 Eventos por Día</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!$mysql_online): ?>
                            <div class="data-unavailable">
                                <p>📡 Datos no disponibles</p>
                            </div>
                        <?php else: ?>
                            <canvas id="chartEventosDiarios" height="250"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>🔌 Estado de Conexión</h5>
                    </div>
                    <div class="card-body">
                        <div id="estadoConexion">
                            <?php
                            $mysql_status_text = $mysql_online ? '● Online' : '○ Offline';
                            $mysql_status_class = $mysql_online ? 'status-online' : 'status-offline';
                            $mysql_tooltip = $mysql_online ? 'Conexión exitosa' : ($mysql_error ?? 'Conexión fallida');
                            
                            // Estado Oracle
                            $oracle_status_text = $oracle_online ? '● Online' : '○ Offline';
                            $oracle_status_class = $oracle_online ? 'status-online' : 'status-offline';
                            $oracle_tooltip = $oracle_online ? 'Conexión exitosa' : ($oracle_error ?? 'OCI8 no instalada');
                            ?>
                            <p>MySQL: <span id="estado_mysql" class="<?php echo $mysql_status_class; ?>" data-tooltip="<?php echo htmlspecialchars($mysql_tooltip); ?>"><?php echo $mysql_status_text; ?></span></p>
                            <p>Oracle: <span id="estado_oracle" class="<?php echo $oracle_status_class; ?>" data-tooltip="<?php echo htmlspecialchars($oracle_tooltip); ?>"><?php echo $oracle_status_text; ?></span></p>
                        </div>
                        <hr>
                        <div id="ultimaSincronizacion">
                            <small>Última sincronización: <span id="ultima_ejecucion"><?php echo !$mysql_online ? 'Esperando conexión...' : 'Cargando...'; ?></span></small>
                        </div>
                        <?php if (!$mysql_online): ?>
                            <div class="alert alert-warning mt-3 mb-0">
                                <small>⚠️ No hay conexión con MySQL. Verifica que Aiven esté encendido y tu IP esté permitida.</small>
                            </div>
                        <?php endif; ?>
                        <?php if (!$oracle_online): ?>
                            <div class="alert alert-warning mt-3 mb-0">
                                <small>⚠️ No hay conexión con Oracle. Verifica que AWS esté encendido y tu IP esté permitida.</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>📋 Últimos 10 Eventos Procesados</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <?php if (!$mysql_online): ?>
                        <div class="data-unavailable">
                            <p>📡 Datos no disponibles</p>
                        </div>
                    <?php else: ?>
                        <table class="table table-sm table-events">
                            <thead>
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
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>⚠️ Registros con Error</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <?php if (!$mysql_online): ?>
                        <div class="data-unavailable">
                            <p>📡 Datos no disponibles</p>
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

        <div class="card mb-4">
            <div class="card-header">
                <h5>🗄️ Tablas en Oracle</h5>
                <small class="text-muted">Datos disponibles en AWS RDS</small>
            </div>
            <div class="card-body">
                <div id="oracleTablesContainer">
                    <div class="text-center" id="oracleLoading">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p>Cargando datos de Oracle...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5>🔄 Comparación de Datos: MySQL vs Oracle</h5>
                <small class="text-muted">Verifica la sincronización entre bases de datos</small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm" id="tablaComparacion">
                        <thead>
                            <tr>
                                <th>Tabla MySQL</th>
                                <th>Tabla Oracle</th>
                                <th>MySQL</th>
                                <th>Oracle</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpoComparacion">
                            <tr><td colspan="5" class="text-center">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php if ($mysql_online): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5>🔍 Filtros de Búsqueda</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label>Filtrar por tabla:</label>
                        <select id="filtroTabla" class="form-select">
                            <option value="">Todas</option>
                            <option value="tbl_clientes_logisticos">Clientes</option>
                            <option value="centros_logisticos">Centros</option>
                            <option value="contenedores">Contenedores</option>
                            <option value="ordenes_envio">Embarques</option>
                        </select>
                    </div>
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
                    <div class="col-md-4">
                        <label>&nbsp;</label>
                        <button id="btnAplicarFiltros" class="btn btn-primary w-100">Aplicar Filtros</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <button class="btn btn-success refresh-btn" onclick="cargarDatos()">
        🔄 Refrescar
    </button>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($mysql_online): ?>
    <script>
        let chartEstadoTablas, chartPorcentajeExito, chartEventosDiarios;
        
        $(document).ready(function() {
            cargarDatos();
            setInterval(cargarDatos, 30000);
        });
        
        function cargarDatos() {
            cargarIndicadores();
            cargarEstadisticasGraficas();
            cargarEventosRecientes();
            cargarErrores();
            cargarEstadoConexion();
            cargarOracleData();      // <-- NUEVO
            cargarComparacion();     // <-- NUEVO
        }
        
        function cargarIndicadores() {
            $.getJSON('api/indicadores.php', function(data) {
                $('#pendientes').text(data.pendientes || 0);
                $('#replicados').text(data.replicados || 0);
                $('#errores').text(data.errores || 0);
                $('#conflictos').text(data.conflictos || 0);
                
                // Actualizar indicadores de Oracle en el estado de conexión
                if (data.oracle_online) {
                    $('#estado_oracle').html('● Online');
                    $('#estado_oracle').attr('class', 'status-online');
                    $('#estado_oracle').attr('data-tooltip', `Oracle: ${data.oracle_tables} tablas, ${data.oracle_rows} registros`);
                }
            }).fail(function() {
                $('#pendientes').text('?');
                $('#replicados').text('?');
                $('#errores').text('?');
                $('#conflictos').text('?');
            });
        }
        
        function cargarEstadisticasGraficas() {
            $.getJSON('api/estadisticas_graficas.php', function(data) {
                if(chartEstadoTablas) chartEstadoTablas.destroy();
                const ctx1 = document.getElementById('chartEstadoTablas').getContext('2d');
                chartEstadoTablas = new Chart(ctx1, {
                    type: 'bar',
                    data: {
                        labels: data.tablas,
                        datasets: [{
                            label: 'Pendientes',
                            data: data.pendientes_por_tabla,
                            backgroundColor: '#ffc107'
                        }, {
                            label: 'Errores',
                            data: data.errores_por_tabla,
                            backgroundColor: '#dc3545'
                        }]
                    }
                });
                
                if(chartPorcentajeExito) chartPorcentajeExito.destroy();
                const ctx2 = document.getElementById('chartPorcentajeExito').getContext('2d');
                chartPorcentajeExito = new Chart(ctx2, {
                    type: 'doughnut',
                    data: {
                        labels: ['Éxito', 'Fallo'],
                        datasets: [{
                            data: [data.porcentaje_exito, 100 - data.porcentaje_exito],
                            backgroundColor: ['#28a745', '#dc3545']
                        }]
                    }
                });
                
                if(chartEventosDiarios) chartEventosDiarios.destroy();
                if(data.eventos_diarios) {
                    const ctx3 = document.getElementById('chartEventosDiarios').getContext('2d');
                    chartEventosDiarios = new Chart(ctx3, {
                        type: 'line',
                        data: {
                            labels: data.eventos_diarios.fechas,
                            datasets: [{
                                label: 'Eventos',
                                data: data.eventos_diarios.cantidades,
                                borderColor: '#007bff',
                                fill: false
                            }]
                        }
                    });
                }
            });
        }
        
        function cargarEventosRecientes() {
            const tabla = $('#filtroTabla').val();
            const estado = $('#filtroEstado').val();
            
            $.getJSON('api/eventos_recientes.php', {tabla: tabla, estado: estado}, function(data) {
                let html = '';
                data.forEach(evento => {
                    let badgeClass = 'secondary';
                    if (evento.estado_replicacion === 'REPLICADO') badgeClass = 'success';
                    else if (evento.estado_replicacion === 'PENDIENTE') badgeClass = 'warning';
                    else if (evento.estado_replicacion === 'ERROR') badgeClass = 'danger';
                    else if (evento.estado_replicacion === 'CONFLICTO') badgeClass = 'info';
                    
                    html += `<tr>
                        <td>${evento.id}</td>
                        <td>${evento.tabla_afectada}</td>
                        <td>${evento.tipo_operacion}</td>
                        <td>${evento.fecha_hora}</td>
                        <td><span class="badge bg-${badgeClass}">${evento.estado_replicacion}</span></td>
                    </tr>`;
                });
                $('#tablaEventos').html(html || '<tr><td colspan="5" class="text-center">Sin eventos</td></tr>');
            });
        }
        
        function cargarErrores() {
            $.getJSON('api/errores.php', function(data) {
                let html = '';
                data.forEach(error => {
                    html += `<tr>
                        <td>${error.id}</td>
                        <td>${error.tabla_afectada}</td>
                        <td>${error.intentos_replicacion}/3</td>
                        <td><small>${error.mensaje_error || 'N/A'}</small></td>
                        <td>${error.created_at}</td>
                    </tr>`;
                });
                $('#tablaErrores').html(html || '<tr><td colspan="5" class="text-center">Sin errores</td></tr>');
            });
        }
        
        function cargarEstadoConexion() {
            $.getJSON('api/estado_conexion.php')
                .done(function(data) {
                    // Actualizar MySQL
                    let mysqlSpan = $('#estado_mysql');
                    if (data.mysql) {
                        mysqlSpan.html('● Online');
                        mysqlSpan.attr('class', 'status-online');
                        mysqlSpan.attr('data-tooltip', 'Conexión exitosa a MySQL');
                    } else {
                        mysqlSpan.html('○ Offline');
                        mysqlSpan.attr('class', 'status-offline');
                        mysqlSpan.attr('data-tooltip', data.mysql_error || 'MySQL no disponible');
                    }
                    
                    // Actualizar Oracle
                    let oracleSpan = $('#estado_oracle');
                    if (data.oracle) {
                        oracleSpan.html('● Online');
                        oracleSpan.attr('class', 'status-online');
                        oracleSpan.attr('data-tooltip', data.oracle_detalle || 'Conexión exitosa a Oracle');
                    } else {
                        oracleSpan.html('○ Offline');
                        oracleSpan.attr('class', 'status-offline');
                        oracleSpan.attr('data-tooltip', data.oracle_error || data.oracle_detalle || 'Oracle no disponible');
                    }
                    
                    // Actualizar última ejecución
                    $('#ultima_ejecucion').text(data.ultima_ejecucion || 'No registrada');
                    
                    // Mostrar detalles si hay error
                    if (!data.oracle && data.oracle_detalle) {
                        console.log('Oracle error details:', data.oracle_detalle);
                    }
                })
                .fail(function(jqXHR, textStatus, errorThrown) {
                    console.error('Error al obtener estado de conexión:', textStatus, errorThrown);
                });
        }

        // CARGAR DATOS DE ORACLE

        function cargarOracleData() {
            // Cargar tablas de Oracle
            $.getJSON('api/oracle_data.php?action=summary')
                .done(function(data) {
                    if (data.success && data.oracle_online) {
                        let html = `
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="alert alert-success">
                                        <strong>✅ Oracle Online</strong>
                                        <br>Total tablas: ${data.total_tables}
                                        <br>Total registros: ${data.total_rows}
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6>Tablas disponibles:</h6>
                                            <div style="max-height: 200px; overflow-y: auto;">
                                                <table class="table table-sm table-striped">
                                                    <thead>
                                                        <tr><th>Tabla</th><th>Registros</th></tr>
                                                    </thead>
                                                    <tbody>
                                        `;
                                        
                        data.tables.forEach(function(table) {
                            html += `<tr><td>${table.name}</td><td>${table.rows}</td></tr>`;
                        });
                        
                        html += `
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        $('#oracleTablesContainer').html(html);
                    } else {
                        $('#oracleTablesContainer').html(`
                            <div class="alert alert-warning">
                                ⚠️ No se pudieron cargar datos de Oracle
                                <br><small>${data.error || 'Oracle no disponible'}</small>
                            </div>
                        `);
                    }
                })
                .fail(function() {
                    $('#oracleTablesContainer').html(`
                        <div class="alert alert-danger">
                            ❌ Error al conectar con Oracle
                        </div>
                    `);
                });
        }

        // CARGAR COMPARACIÓN MYSQL ↔ ORACLE

        function cargarComparacion() {
            $.getJSON('api/comparacion_replicacion.php')
                .done(function(data) {
                    let html = '';
                    
                    if (!data.mysql_online || !data.oracle_online) {
                        html = `<tr><td colspan="5" class="text-center text-warning">
                            ⚠️ ${!data.mysql_online ? 'MySQL' : ''} 
                            ${!data.mysql_online && !data.oracle_online ? 'y' : ''} 
                            ${!data.oracle_online ? 'Oracle' : ''} no está disponible
                        </td></tr>`;
                        $('#cuerpoComparacion').html(html);
                        return;
                    }
                    
                    data.comparison.forEach(function(item) {
                        let statusBadge = '';
                        let statusText = '';
                        
                        if (item.mysql_count < 0) {
                            statusBadge = 'badge bg-warning';
                            statusText = '⚠️ Error MySQL';
                        } else if (item.match) {
                            statusBadge = 'badge bg-success';
                            statusText = '✅ Sincronizado';
                        } else {
                            statusBadge = 'badge bg-danger';
                            statusText = `❌ Diferencia: ${item.difference}`;
                        }
                        
                        html += `<tr>
                            <td><code>${item.mysql_table}</code></td>
                            <td><code>${item.oracle_table}</code></td>
                            <td class="text-center">${item.mysql_count >= 0 ? item.mysql_count : 'N/A'}</td>
                            <td class="text-center">${item.oracle_count >= 0 ? item.oracle_count : 'N/A'}</td>
                            <td><span class="${statusBadge}">${statusText}</span></td>
                        </tr>`;
                    });
                    
                    $('#cuerpoComparacion').html(html);
                })
                .fail(function() {
                    $('#cuerpoComparacion').html(`
                        <tr><td colspan="5" class="text-center text-danger">Error al cargar comparación</td></tr>
                    `);
                });
        }

        $('#btnAplicarFiltros').click(function() {
            cargarEventosRecientes();
        });
    </script>
    <?php else: ?>
    <script>
        function checkReconnection() {
            $.getJSON('api/estado_conexion.php', function(data) {
                if (data.mysql) {
                    location.reload();
                }
                if (data.oracle) {
                    $('#estado_oracle').html('● Online');
                    $('#estado_oracle').attr('class', 'status-online');
                } else {
                    $('#estado_oracle').html('○ Offline');
                    $('#estado_oracle').attr('class', 'status-offline');
                }
            });
        }
        
        $(document).ready(function() {
            setInterval(checkReconnection, 10000);
        });
    </script>
    <?php endif; ?>
</body>
</html>