/*
Define los colores y etiquetas utilizados en las diferentes visualizaciones de datos
*/
const CHART_COLORS = {
    mysql: {
        primary: '#007bff',
        background: 'rgba(0,123,255,0.1)',
        label: 'Eventos MySQL',
        borderColor: '#007bff'
    },
    oracle: {
        primary: '#6c5ce7',
        background: 'rgba(108,92,231,0.1)',
        label: 'Eventos Oracle',
        borderColor: '#6c5ce7'
    },
    replicados: {
        color: '#28a745',
        label: '✅ Replicados (MySQL)'
    },
    pendientes: {
        color: '#ffc107',
        label: '⏳ Pendientes (MySQL)'
    },
    errores: {
        color: '#dc3545',
        label: '❌ Errores (MySQL)'
    },
    oracle_registros: {
        color: '#6c5ce7',
        label: '📊 Registros Oracle'
    },
    exito: {
        color: '#28a745',
        label: '✅ Éxito (MySQL)'
    },
    fallo: {
        color: '#dc3545',
        label: '❌ Fallo (MySQL)'
    },
    sin_datos: {
        color: '#6c757d',
        label: 'No hay eventos registrados'
    }
};

//NOMBRES PARA LAS TABLAS. Mapea los nombres tecnicos de las tablas (tanto MySQL como Oracle) a nombres mas descriptivos y legibles para los usuarios.
const FRIENDLY_TABLE_NAMES = {
    //Nombres MySQL
    'tbl_clientes_logisticos': 'Clientes',
    'centros_logisticos': 'Centros Logisticos',
    'unidades_transporte': 'Unidades Transporte',
    'contenedores': 'Contenedores',
    'stock_carga': 'Inventario',
    'ordenes_envio': 'Embarques',
    'tbl_facturas_logisticas': 'Facturas',
    'servicios_logisticos': 'Servicios',
    'factura_servicios': 'Detalle Factura',
    'movimientos_carga': 'Transferencias',
    //Nombres Oracle
    'CLIENTE_NAVIERA': 'Clientes',
    'TERMINAL_PORTUARIA': 'Centros',
    'BUQUE_OPERACION': 'Unidades',
    'CONTENEDOR_NAVIERO': 'Contenedores',
    'INVENTARIO_CARGA': 'Inventario',
    'EMBARQUE_MARITIMO': 'Embarques',
    'FACTURACION_EMBARQUE': 'Facturas',
    'SERVICIO_PORTUARIO': 'Servicios',
    'DETALLE_FACTURA_SERVICIO': 'Detalle',
    'TRANSFERENCIA_CARGA': 'Transferencias'
};

//FUNCIONES DE UTILIDAD

//Obtener nombre de una tabla
function getFriendlyTableName(tableName) {
    return FRIENDLY_TABLE_NAMES[tableName] || tableName;
}

//Obtener nombres para un array de tablas
function getFriendlyTableNames(tableNames) {
    return tableNames.map(function(table) {
        return getFriendlyTableName(table);
    });
}

//CONFIGURACIONES

//Configuracion base para graficas
const CHART_OPTIONS = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
        }
    }
};

//Configuracion para grafica de barras
const BAR_CHART_OPTIONS = {
    ...CHART_OPTIONS,
    scales: {
        x: {
            ticks: {
                maxRotation: 45,
                minRotation: 30,
                font: {
                    size: 10
                }
            }
        },
        y: {
            beginAtZero: true
        }
    }
};

//Configuracion para grafica de lineas
const LINE_CHART_OPTIONS = {
    ...CHART_OPTIONS,
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                stepSize: 1
            }
        }
    }
};

//Configuracion para grafica de dona
const DOUGHNUT_CHART_OPTIONS = {
    ...CHART_OPTIONS,
    plugins: {
        ...CHART_OPTIONS.plugins,
        legend: {
            position: 'bottom',
        }
    }
};

//Configuracion para grafica sin datos
const NO_DATA_CHART_OPTIONS = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
        }
    },
    scales: {
        y: {
            beginAtZero: true
        }
    }
};