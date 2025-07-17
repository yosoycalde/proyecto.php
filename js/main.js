document.addEventListener('DOMContentLoaded', function () {
    const uploadForm = document.getElementById('uploadForm');
    const resultsSection = document.getElementById('results');
    const previewSection = document.getElementById('preview');
    const downloadBtn = document.getElementById('downloadBtn');
    const configFile = document.getElementById('configFile');

    // Botones de configuración
    const importCentrosBtn = document.getElementById('importCentrosBtn');
    const importElementosBtn = document.getElementById('importElementosBtn');

    // Manejar importación de centros de costos
    importCentrosBtn.addEventListener('click', function () {
        configFile.onchange = function () {
            if (this.files.length > 0) {
                importarArchivo('import_centros', this.files[0], 'Centros de Costos');
            }
        };
        configFile.click();
    });

    // Manejar importación de elementos
    importElementosBtn.addEventListener('click', function () {
        configFile.onchange = function () {
            if (this.files.length > 0) {
                importarArchivo('import_elementos', this.files[0], 'Elementos');
            }
        };
        configFile.click();
    });

    function importarArchivo(action, file, tipo) {
        const formData = new FormData();
        formData.append('configFile', file);
        formData.append('action', action);

        // Mostrar mensaje de carga
        showMessage(`Importando ${tipo}...`, 'info');

        fetch('includes/upload_handler.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(`✅ ${tipo} importados exitosamente (${data.records} registros)`, 'success');
                } else {
                    showMessage(`❌ Error al importar ${tipo}: ${data.message}`, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage(`❌ Error al importar ${tipo}`, 'error');
            });
    }

    // Procesar inventario de Ineditto
    uploadForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData();
        const fileInput = document.getElementById('csvFile');

        if (fileInput.files.length === 0) {
            showMessage('Por favor selecciona un archivo CSV de inventario', 'error');
            return;
        }

        formData.append('csvFile', fileInput.files[0]);

        // Mostrar loading
        const processInfo = document.getElementById('processInfo');
        processInfo.innerHTML = '<p class="info">🔄 Procesando archivo de inventario...</p>';
        resultsSection.style.display = 'block';

        fetch('includes/upload_handler.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const stats = data.statistics;
                    processInfo.innerHTML = `
                    <div class="success">
                        <h3>✅ Archivo procesado exitosamente</h3>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <strong>Registros procesados:</strong> ${data.records}
                            </div>
                            <div class="stat-item">
                                <strong>Total en BD:</strong> ${stats.total_registros}
                            </div>
                            <div class="stat-item">
                                <strong>Registros sin ILABOR:</strong> ${stats.ilabor_vacios}
                            </div>
                            <div class="stat-item">
                                <strong>Centros de costo utilizados:</strong> ${stats.centros_costo_diferentes}
                            </div>
                            <div class="stat-item">
                                <strong>Suma total cantidades:</strong> ${parseFloat(stats.suma_cantidades || 0).toFixed(2)}
                            </div>
                        </div>
                    </div>`;

                    downloadBtn.style.display = 'inline-block';
                    mostrarVistPrevia();
                } else {
                    processInfo.innerHTML = `<p class="error">❌ Error: ${data.message}</p>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                processInfo.innerHTML = '<p class="error">❌ Error al procesar el archivo</p>';
            });
    });

    downloadBtn.addEventListener('click', function () {
        window.location.href = 'includes/download_csv.php';
    });

    function mostrarVistPrevia() {
        fetch('includes/get_preview.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tableHead = document.getElementById('tableHead');
                    const tableBody = document.getElementById('tableBody');

                    // Limpiar tabla
                    tableHead.innerHTML = '';
                    tableBody.innerHTML = '';

                    // Headers
                    const headerRow = document.createElement('tr');
                    ['Código Elemento', 'Categoría/Descripción', 'Cantidad', 'Fecha', 'Centro Costo', 'Labor Original', 'Observaciones'].forEach(header => {
                        const th = document.createElement('th');
                        th.textContent = header;
                        headerRow.appendChild(th);
                    });
                    tableHead.appendChild(headerRow);

                    // Datos
                    data.data.forEach(row => {
                        const tr = document.createElement('tr');
                        Object.values(row).forEach(cell => {
                            const td = document.createElement('td');
                            td.textContent = cell || '';
                            tr.appendChild(td);
                        });
                        tableBody.appendChild(tr);
                    });

                    // Mostrar distribución de centros de costo
                    if (data.distribucion_centros_costo) {
                        const distribucionDiv = document.getElementById('distribucion');
                        if (distribucionDiv) {
                            let distribucionHTML = '<h4>Distribución por Centro de Costo:</h4><ul>';
                            data.distribucion_centros_costo.forEach(item => {
                                distribucionHTML += `<li><strong>${item.centro_costo_asignado}:</strong> ${item.cantidad_registros} registros</li>`;
                            });
                            distribucionHTML += '</ul>';
                            distribucionDiv.innerHTML = distribucionHTML;
                        }
                    }

                    previewSection.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error al obtener vista previa:', error);
            });
    }

    function showMessage(message, type) {
        // Crear o actualizar elemento de mensaje
        let messageDiv = document.getElementById('globalMessage');
        if (!messageDiv) {
            messageDiv = document.createElement('div');
            messageDiv.id = 'globalMessage';
            messageDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 5px;
                color: white;
                font-weight: bold;
                z-index: 1000;
                max-width: 400px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            `;
            document.body.appendChild(messageDiv);
        }

        // Establecer color según el tipo
        switch (type) {
            case 'success':
                messageDiv.style.backgroundColor = '#28a745';
                break;
            case 'error':
                messageDiv.style.backgroundColor = '#dc3545';
                break;
            case 'info':
                messageDiv.style.backgroundColor = '#17a2b8';
                break;
            default:
                messageDiv.style.backgroundColor = '#6c757d';
        }

        messageDiv.textContent = message;
        messageDiv.style.display = 'block';

        // Auto-ocultar después de 5 segundos
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 5000);
    }
});