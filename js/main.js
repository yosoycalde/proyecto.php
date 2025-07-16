document.addEventListener('DOMContentLoaded', function () {
    const uploadForm = document.getElementById('uploadForm');
    const resultsSection = document.getElementById('results');
    const previewSection = document.getElementById('preview');
    const downloadBtn = document.getElementById('downloadBtn');

    uploadForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData();
        const fileInput = document.getElementById('csvFile');

        if (fileInput.files.length === 0) {
            alert('Por favor selecciona un archivo CSV');
            return;
        }

        formData.append('csvFile', fileInput.files[0]);

        // Mostrar loading
        document.getElementById('processInfo').innerHTML = '<p>Procesando archivo...</p>';
        resultsSection.style.display = 'block';

        fetch('includes/upload_handler.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('processInfo').innerHTML =
                        `<p class="success">✅ Archivo procesado exitosamente</p>
                     <p>Registros procesados: ${data.records}</p>`;

                    downloadBtn.style.display = 'inline-block';
                    mostrarVistPrevia();
                } else {
                    document.getElementById('processInfo').innerHTML =
                        `<p class="error">❌ Error: ${data.message}</p>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('processInfo').innerHTML =
                    '<p class="error">❌ Error al procesar el archivo</p>';
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
                    ['Código', 'Descripción', 'Cantidad', 'Valor Unit.', 'Valor Total', 'Fecha', 'Centro Costo'].forEach(header => {
                        const th = document.createElement('th');
                        th.textContent = header;
                        headerRow.appendChild(th);
                    });
                    tableHead.appendChild(headerRow);

                    // Datos (máximo 10 registros para vista previa)
                    data.data.slice(0, 10).forEach(row => {
                        const tr = document.createElement('tr');
                        Object.values(row).forEach(cell => {
                            const td = document.createElement('td');
                            td.textContent = cell;
                            tr.appendChild(td);
                        });
                        tableBody.appendChild(tr);
                    });

                    previewSection.style.display = 'block';
                }
            });
    }
});