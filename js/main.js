document.addEventListener('DOMContentLoaded'), function () {
    const uploadForm = document.getElementById('uploadForm');
    const resultsSection = document.getElementById('results');
    const previewSection = document.getElementById('preview');
    const downloadBtn = document.getElementById('downloadBtn');
    const configFile = document.getElementById('configFile');
}

// Botones de configuración
const importCentrosBtn = document.getElementById('importCentrosBtn');
const importElementosBtn = document.getElementById('importElementosBtn');

let tipoConfigActual = '';

// Manejadores para botones de configuración
importCentrosBtn.addEventListener('click', function () {
    tipoConfigActual = 'centros_costos';
    configFile.click();
});

importElementosBtn.addEventListener('click', function () {
    tipoConfigActual = 'elementos';
    configFile.click();
});

// Manejador para archivo de configuración
configFile.addEventListener('change', function () {
    if (this.files.length > 0 && tipoConfigActual) {
        procesarArchivoConfiguracion(this.files[0], tipoConfigActual);
    }
});

// Función para procesar archivos de configuración
document.addEventListener('DOMContentLoaded', function () {
    const uploadForm = document.getElementById('uploadForm');
    const resultsSection = document.getElementById('results');
    const previewSection = document.getElementById('preview');
    const downloadBtn = document.getElementById('downloadBtn');
    const configFile = document.getElementById('configFile');

    // Botones de configuración
    const importCentrosBtn = document.getElementById('importCentrosBtn');
    const importElementosBtn = document.getElementById('importElementosBtn');

    let tipoConfigActual = '';

    // Manejadores para botones de configuración
    importCentrosBtn.addEventListener('click', function () {
        tipoConfigActual = 'centros_costos';
        configFile.click();
    });

    importElementosBtn.addEventListener('click', function () {
        tipoConfigActual = 'elementos';
        configFile.click();
    });

    // Manejador para archivo de configuración
    configFile.addEventListener('change', function () {
        if (this.files.length > 0 && tipoConfigActual) {
            procesarArchivoConfiguracion(this.files[0], tipoConfigActual);
        }
    });

    // Función para procesar archivos de configuración
    function procesarArchivoConfiguracion(file, tipo) {
        const formData = new FormData();
        formData.append('configFile', file);
        formData.append('tipo', tipo);

        // Mostrar mensaje de procesamiento
        const mensaje = tipo === 'centros_costos' ? 'Importando centros de costos...' : 'Importando elementos...';
        mostrarMensajeConfig(mensaje, 'info');

        fetch('includes/config_handler.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tipoTexto = tipo === 'centros_costos' ? 'centros de costos' : 'elementos';
                    mostrarMensajeConfig(
                        `✅ ${data.records} ${tipoTexto} importados correctamente`,
                        'success'
                    );
                } else {
                    mostrarMensajeConfig(`❌ Error: ${data.message}`, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarMensajeConfig('❌ Error al procesar el archivo de configuración', 'error');
            })
            .finally(() => {
                // Limpiar input file
                configFile.value = '';
                tipoConfigActual = '';
            });
    }

    // Función para mostrar mensajes de configuración
    function mostrarMensajeConfig(mensaje, tipo) {
        // Buscar o crear contenedor de mensajes
        let messageContainer = document.getElementById('configMessages');
        if (!messageContainer) {
            messageContainer = document.createElement('div');
            messageContainer.id = 'configMessages';
            messageContainer.style.marginTop = '15px';
            document.querySelector('.config-buttons').parentNode.appendChild(messageContainer);
        }

        messageContainer.innerHTML = `<p class="${tipo}">${mensaje}</p>`;

        // Auto-limpiar mensaje después de 5 segundos si es success
        if (tipo === 'success') {
            setTimeout(() => {
                messageContainer.innerHTML = '';
            }, 5000);
        }
    }

    // Manejador para formulario principal de inventario
    uploadForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData();
        const fileInput = document.getElementById('csvFile');

        if (fileInput.files.length === 0) {
            alert('Por favor selecciona un archivo CSV');
            return;
        }

        // Validar que el archivo sea CSV
        const fileName = fileInput.files[0].name;
        const fileExtension = fileName.split('.').pop().toLowerCase();

        if (fileExtension !== 'csv') {
            alert('Por favor selecciona un archivo CSV válido');
            return;
        }

        formData.append('csvFile', fileInput.files[0]);

        // Mostrar loading
        document.getElementById('processInfo').innerHTML = '<p>Procesando archivo de inventario...</p>';
        resultsSection.style.display = 'block';
        downloadBtn.style.display = 'none';

        fetch('includes/upload_handler.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('processInfo').innerHTML = `
                    <p class="success">✅ Archivo procesado exitosamente</p>
                    <p>Registros procesados: ${data.records}</p>
                    <p>El archivo está listo para descargar en formato ContaPyme</p>
                `;

                    downloadBtn.style.display = 'inline-block';
                    mostrarVistaPrevia();
                    mostrarEstadisticas();
                } else {
                    document.getElementById('processInfo').innerHTML = `
                    <p class="error">❌ Error: ${data.message}</p>
                    <p>Verifique que el archivo tenga el formato correcto:</p>
                    <ul>
                        <li>Headers: codigo_elemento, referencia, cantidad, fecha_movimiento, ILABOR, observaciones</li>
                        <li>Formato de fecha: YYYY-MM-DD o DD/MM/YYYY</li>
                        <li>Separador: comas (,)</li>
                    </ul>
                `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('processInfo').innerHTML = `
                <p class="error">❌ Error al procesar el archivo</p>
                <p>Verifique la conexión y el formato del archivo</p>
            `;
            });
    });

    // Manejador para descarga
    downloadBtn.addEventListener('click', function () {
        // Mostrar mensaje de preparación
        const originalText = this.textContent;
        this.textContent = 'Preparando descarga...';
        this.disabled = true;

        // Realizar descarga
        window.location.href = 'includes/download_csv.php';

        // Restaurar botón después de un momento
        setTimeout(() => {
            this.textContent = originalText;
            this.disabled = false;
        }, 2000);
    });

    // Función para mostrar vista previa
    function mostrarVistaPrevia() {
        fetch('includes/get_preview.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    const tableHead = document.getElementById('tableHead');
                    const tableBody = document.getElementById('tableBody');

                    // Limpiar tabla
                    tableHead.innerHTML = '';
                    tableBody.innerHTML = '';

                    // Headers
                    const headerRow = document.createElement('tr');
                    const headers = ['Código', 'Elemento', 'Cantidad', 'Valor Unit.', 'Valor Total', 'Fecha', 'Centro Costo'];
                    headers.forEach(header => {
                        const th = document.createElement('th');
                        th.textContent = header;
                        headerRow.appendChild(th);
                    });
                    tableHead.appendChild(headerRow);

                    // Datos (máximo 10 registros para vista previa)
                    data.data.slice(0, 10).forEach(row => {
                        const tr = document.createElement('tr');
                        [
                            row.codigo_elemento,
                            row.nombre_elemento || 'N/A',
                            parseFloat(row.cantidad).toLocaleString('es-CO'),
                            parseFloat(row.valor_unitario || 0).toLocaleString('es-CO', {
                                style: 'currency',
                                currency: 'COP'
                            }),
                            parseFloat(row.valor_total || 0).toLocaleString('es-CO', {
                                style: 'currency',
                                currency: 'COP'
                            }),
                            new Date(row.fecha_movimiento).toLocaleDateString('es-CO'),
                            row.centro_costo_asignado
                        ].forEach(cellValue => {
                            const td = document.createElement('td');
                            td.textContent = cellValue;
                            tr.appendChild(td);
                        });
                        tableBody.appendChild(tr);
                    });

                    previewSection.style.display = 'block';
                } else {
                    console.log('No hay datos para mostrar en la vista previa');
                }
            })
            .catch(error => {
                console.error('Error al cargar vista previa:', error);
            });
    }

    // Función para mostrar estadísticas
    function mostrarEstadisticas() {
        fetch('includes/get_statistics.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const statsHtml = `
                        <div class="statistics">
                            <h3>Estadísticas del procesamiento:</h3>
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <strong>Total registros:</strong> ${data.stats.total_registros}
                                </div>
                                <div class="stat-item">
                                    <strong>Elementos únicos:</strong> ${data.stats.elementos_unicos}
                                </div>
                                <div class="stat-item">
                                    <strong>Centros de costo:</strong> ${data.stats.centros_costo_unicos}
                                </div>
                                <div class="stat-item">
                                    <strong>Cantidad total:</strong> ${parseFloat(data.stats.cantidad_total).toLocaleString('es-CO')}
                                </div>
                                <div class="stat-item">
                                    <strong>Rango de fechas:</strong> ${data.stats.fecha_min} - ${data.stats.fecha_max}
                                </div>
                            </div>
                        </div>
                    `;
                    document.getElementById('processInfo').innerHTML += statsHtml;
                }
            })
            .catch(error => {
                console.error('Error al cargar estadísticas:', error);
            });
    }

    // Validación de archivos en tiempo real
    document.getElementById('csvFile').addEventListener('change', function () {
        const file = this.files[0];
        if (file) {
            const fileSize = file.size / 1024 / 1024; // MB
            const fileName = file.name;
            const fileExtension = fileName.split('.').pop().toLowerCase();

            let warnings = [];

            if (fileExtension !== 'csv') {
                warnings.push('⚠️ Se esperaba un archivo CSV');
            }

            if (fileSize > 10) {
                warnings.push('⚠️ Archivo muy grande (>10MB), el procesamiento puede tomar tiempo');
            }

            // Mostrar advertencias si las hay
            let warningContainer = document.getElementById('fileWarnings');
            if (!warningContainer) {
                warningContainer = document.createElement('div');
                warningContainer.id = 'fileWarnings';
                this.parentNode.appendChild(warningContainer);
            }

            if (warnings.length > 0) {
                warningContainer.innerHTML = warnings.map(w => `<p style="color: orange; font-size: 12px;">${w}</p>`).join('');
            } else {
                warningContainer.innerHTML = '<p style="color: green; font-size: 12px;">✅ Archivo válido</p>';
            }
        }
    });
});