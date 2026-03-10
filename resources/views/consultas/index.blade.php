@extends('layouts.app')

@section('title', 'Salud Total - Subir Archivo')

@section('content')
    <!-- Step 1: Connection Test -->
    <div class="card" id="connectionCard">
        <div class="card-title">
            <span class="icon">🔌</span>
            Paso 1: Verificar Conexión al Portal
            <div class="connection-status" style="margin-left: auto;">
                <span class="connection-dot" id="connectionDot"></span>
                <span id="connectionText">Listo</span>
            </div>
        </div>
        <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 1rem;">
            Verifique que la conexión al portal de Salud Total funcione correctamente.
        </p>
        <div class="btn-group">
            <button class="btn btn-primary btn-sm" id="testBtn" onclick="testConnection()">
                <span id="testBtnContent">🔌 Probar Conexión</span>
            </button>
        </div>
    </div>

    <!-- Step 2: File Upload -->
    <div class="card" id="uploadCard">
        <div class="card-title">
            <span class="icon">📁</span>
            Paso 2: Cargar Archivo de Cédulas
        </div>
        <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 1rem;">
            Suba un archivo CSV o Excel con una columna llamada <strong>"cedula"</strong>.
        </p>
        <div class="file-upload-area" id="dropZone">
            <div class="upload-icon">📤</div>
            <p>Arrastre su archivo aquí o haga clic para seleccionar</p>
            <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">CSV, XLSX, XLS (máx. 5MB)</p>
            <div class="file-name" id="fileName"></div>
            <input type="file" id="fileInput" accept=".csv,.xlsx,.xls,.txt">
        </div>
        <div class="btn-group">
            <button class="btn btn-primary" id="uploadBtn" onclick="uploadFile()" disabled>
                🚀 Cargar y Procesar
            </button>
        </div>
    </div>

    <!-- Step 3: Progress -->
    <div class="card progress-container" id="progressCard">
        <div class="card-title">
            <span class="icon">⚙️</span>
            Procesando Consultas
            <span class="spinner" id="processingSpinner" style="margin-left: auto;"></span>
        </div>
        <div class="progress-bar-wrapper">
            <div class="progress-bar" id="progressBar" style="width: 0%"></div>
        </div>
        <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--text-secondary);">
            <span id="progressText">0 de 0 procesados</span>
            <span id="progressPercent">0%</span>
        </div>
        <div class="progress-stats">
            <div class="stat-card total"><div class="stat-value" id="statTotal">0</div><div class="stat-label">Total</div></div>
            <div class="stat-card success"><div class="stat-value" id="statSuccess">0</div><div class="stat-label">Exitosos</div></div>
            <div class="stat-card error"><div class="stat-value" id="statFailed">0</div><div class="stat-label">Fallidos</div></div>
            <div class="stat-card pending"><div class="stat-value" id="statPending">0</div><div class="stat-label">Pendientes</div></div>
        </div>
        <div class="live-log" id="liveLog"></div>
        <div class="btn-group">
            <button class="btn btn-danger btn-sm hidden" id="stopBtn" onclick="stopProcessing()">⏹ Detener</button>
            <button class="btn btn-success btn-sm hidden" id="exportBtn" onclick="exportResults()">📊 Exportar a Excel</button>
        </div>
    </div>

    <!-- Step 4: Results Table -->
    <div class="card hidden" id="resultsCard">
        <div class="card-title">
            <span class="icon">📋</span>
            Resultados
            <button class="btn btn-success btn-sm" style="margin-left: auto;" onclick="exportResults()">📊 Exportar Excel</button>
        </div>
        <div class="results-table-wrapper" style="max-height: 500px; overflow-y: auto;">
            <table class="results-table" id="resultsTable">
                <thead>
                    <tr>
                        <th>Estado</th><th>Cédula</th><th>Nombres</th><th>Tipo Doc.</th>
                        <th>Parentesco</th><th>Estado Afiliación</th><th>Fecha Nacimiento</th>
                        <th>Edad</th><th>Sexo</th><th>Ciudad</th><th>Dirección</th>
                        <th>Teléfono</th><th>IPS Médica</th><th>IPS Odontológica</th>
                        <th>EPS Anterior</th><th>Antigüedad ST</th><th>Fecha Afiliación</th>
                    </tr>
                </thead>
                <tbody id="resultsBody"></tbody>
            </table>
        </div>
    </div>

    <!-- History -->
    @if(isset($consultas) && $consultas->count() > 0)
    <div class="card">
        <div class="card-title">
            <span class="icon">📜</span>
            Historial de Consultas
        </div>
        @foreach($consultas as $consulta)
        <div class="history-item" id="history-{{ $consulta->id }}">
            <div class="info">
                <span class="name">{{ $consulta->filename ?? 'Consulta #' . $consulta->id }}</span>
                <span class="meta">
                    {{ $consulta->created_at->format('d/m/Y H:i') }} •
                    {{ $consulta->total_cedulas }} cédulas •
                    {{ $consulta->successful }} exitosas •
                    {{ $consulta->failed }} fallidas
                </span>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <span class="badge badge-{{ $consulta->status === 'completed' ? 'success' : ($consulta->status === 'error' ? 'error' : 'pending') }}">
                    {{ $consulta->status }}
                </span>
                @if($consulta->status === 'completed')
                    <a href="{{ route('consultas.export', $consulta->id) }}" class="btn btn-outline btn-sm">📊 Excel</a>
                @elseif($consulta->status === 'processing' || $consulta->status === 'pending')
                    <button class="btn btn-primary btn-sm" onclick="resumeConsulta({{ $consulta->id }})">▶️ Reanudar</button>
                @endif
                <button class="btn btn-danger btn-sm" onclick="deleteConsulta({{ $consulta->id }}, this)" title="Eliminar">🗑️</button>
            </div>
        </div>
        @endforeach
    </div>
    @endif
@endsection

@section('scripts')
<script>
    let currentConsultaId = null;
    let isProcessing = false;
    let shouldStop = false;
    let consecutiveErrors = 0;
    const MAX_CONSECUTIVE_ERRORS = 5;

    const fileInput = document.getElementById('fileInput');
    const fileName = document.getElementById('fileName');
    const uploadBtn = document.getElementById('uploadBtn');
    const dropZone = document.getElementById('dropZone');

    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            fileName.textContent = '📄 ' + this.files[0].name;
            uploadBtn.disabled = false;
        }
    });

    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', () => { dropZone.classList.remove('dragover'); });
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault(); dropZone.classList.remove('dragover');
        if (e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files;
            fileName.textContent = '📄 ' + e.dataTransfer.files[0].name;
            uploadBtn.disabled = false;
        }
    });

    function addLog(message, type = '') {
        const log = document.getElementById('liveLog');
        const time = new Date().toLocaleTimeString();
        const entry = document.createElement('div');
        entry.className = 'log-entry';
        entry.innerHTML = `<span class="log-time">[${time}]</span><span class="log-message ${type}">${message}</span>`;
        log.prepend(entry);
        while (log.children.length > 100) log.removeChild(log.lastChild);
    }

    async function testConnection() {
        const btnContent = document.getElementById('testBtnContent');
        btnContent.innerHTML = '<span class="spinner"></span> Conectando...';
        document.getElementById('testBtn').disabled = true;
        try {
            const response = await fetchApi('{{ route("consultas.test") }}', { method: 'POST' });
            const data = await response.json();
            if (data.success) {
                showAlert('success', data.message);
                document.getElementById('connectionDot').className = 'connection-dot connected';
                document.getElementById('connectionText').textContent = 'Conectado';
            } else {
                showAlert('error', data.message);
                document.getElementById('connectionDot').className = 'connection-dot error';
                document.getElementById('connectionText').textContent = 'Error';
            }
        } catch (error) {
            showAlert('error', 'Error al probar la conexión: ' + error.message);
        } finally {
            btnContent.innerHTML = '🔌 Probar Conexión';
            document.getElementById('testBtn').disabled = false;
        }
    }

    async function uploadFile() {
        const file = fileInput.files[0];
        if (!file) { showAlert('error', 'Seleccione un archivo.'); return; }
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<span class="spinner"></span> Cargando...';
        try {
            const formData = new FormData();
            formData.append('file', file);
            const response = await fetch('{{ route("consultas.upload") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: formData,
            });
            const data = await response.json();
            if (!data.success) { showAlert('error', data.message); uploadBtn.disabled = false; uploadBtn.innerHTML = '🚀 Cargar y Procesar'; return; }
            showAlert('success', data.message);
            currentConsultaId = data.consulta_id;
            document.getElementById('progressCard').classList.add('active');
            document.getElementById('stopBtn').classList.remove('hidden');
            document.getElementById('statTotal').textContent = data.total;
            document.getElementById('statPending').textContent = data.total;
            document.getElementById('resultsCard').classList.remove('hidden');
            addLog(`Archivo cargado: ${file.name} (${data.total} cédulas)`);
            startProcessing();
        } catch (error) {
            showAlert('error', 'Error al cargar: ' + error.message);
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '🚀 Cargar y Procesar';
        }
    }

    async function resumeConsulta(consultaId) {
        try {
            const response = await fetchApi(`/resume/${consultaId}`, { method: 'POST' });
            const data = await response.json();
            if (!data.success) { showAlert('error', data.message); return; }
            showAlert('info', data.message);
            currentConsultaId = data.consulta_id;
            document.getElementById('progressCard').classList.add('active');
            document.getElementById('stopBtn').classList.remove('hidden');
            document.getElementById('resultsCard').classList.remove('hidden');
            updateStats(data.stats);
            addLog(`Reanudando consulta #${consultaId}...`);
            startProcessing();
        } catch (error) { showAlert('error', 'Error al reanudar: ' + error.message); }
    }

    async function startProcessing() {
        isProcessing = true; shouldStop = false; consecutiveErrors = 0;
        document.getElementById('connectionDot').className = 'connection-dot connected';
        document.getElementById('connectionText').textContent = 'Procesando...';
        addLog('🚀 Iniciando procesamiento...', 'success');

        while (isProcessing && !shouldStop) {
            try {
                const response = await fetchApi(`/process/${currentConsultaId}`, { method: 'POST' });
                const data = await response.json();
                if (!data.success) {
                    consecutiveErrors++;
                    addLog(`⚠️ Error: ${data.message} (${consecutiveErrors}/${MAX_CONSECUTIVE_ERRORS})`, 'error');
                    if (consecutiveErrors >= MAX_CONSECUTIVE_ERRORS) { addLog('❌ Demasiados errores. Detenido.', 'error'); break; }
                    await new Promise(r => setTimeout(r, 5000)); continue;
                }
                consecutiveErrors = 0;
                updateStats(data.stats);
                if (data.completed) {
                    addLog('✅ ¡Procesamiento completado!', 'success');
                    showAlert('success', '¡Todas las cédulas han sido procesadas!');
                    finishProcessing(); break;
                }
                if (data.result) {
                    addResultRow(data.result);
                    const logType = data.result.status === 'success' ? 'success' : 'error';
                    const errorMsg = data.result.error ? ` - ${data.result.error}` : '';
                    addLog(`${data.result.cedula} → ${data.result.nombres || 'N/A'} [${data.result.status}]${errorMsg}`, logType);
                }
                await new Promise(r => setTimeout(r, 300));
            } catch (error) {
                consecutiveErrors++;
                addLog(`🌐 Error de red: ${error.message} (${consecutiveErrors}/${MAX_CONSECUTIVE_ERRORS})`, 'error');
                if (consecutiveErrors >= MAX_CONSECUTIVE_ERRORS) { addLog('❌ Error de red persistente.', 'error'); break; }
                await new Promise(r => setTimeout(r, 5000));
            }
        }
        if (shouldStop) finishProcessing();
    }

    function stopProcessing() { shouldStop = true; isProcessing = false; addLog('⏹ Detenido por el usuario.', 'error'); }

    function finishProcessing() {
        isProcessing = false;
        document.getElementById('processingSpinner').style.display = 'none';
        document.getElementById('stopBtn').classList.add('hidden');
        document.getElementById('exportBtn').classList.remove('hidden');
        document.getElementById('connectionDot').className = 'connection-dot';
        document.getElementById('connectionText').textContent = 'Finalizado';
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = '🚀 Cargar y Procesar';
    }

    function updateStats(stats) {
        document.getElementById('statTotal').textContent = stats.total;
        document.getElementById('statSuccess').textContent = stats.successful;
        document.getElementById('statFailed').textContent = stats.failed;
        document.getElementById('statPending').textContent = stats.pending;
        document.getElementById('progressBar').style.width = stats.progress + '%';
        document.getElementById('progressText').textContent = `${stats.processed} de ${stats.total} procesados`;
        document.getElementById('progressPercent').textContent = stats.progress + '%';
    }

    function addResultRow(result) {
        const tbody = document.getElementById('resultsBody');
        const data = result.data || {};
        const statusBadge = result.status === 'success'
            ? '<span class="badge badge-success">✓ OK</span>'
            : '<span class="badge badge-error">✗ Error</span>';
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${statusBadge}</td><td>${result.cedula || ''}</td>
            <td>${data.nombres || result.nombres || ''}</td><td>${data.tipo_documento || ''}</td>
            <td>${data.parentesco || ''}</td><td>${data.estado_detallado || ''}</td>
            <td>${data.fecha_nacimiento || ''}</td><td>${data.edad || ''}</td>
            <td>${data.sexo || ''}</td><td>${data.ciudad || ''}</td>
            <td>${data.direccion || ''}</td><td>${data.telefono || ''}</td>
            <td>${data.ips_medica_asignada || ''}</td><td>${data.ips_odontologica_asignada || ''}</td>
            <td>${data.eps_anterior || ''}</td><td>${data.antiguedad_salud_total || ''}</td>
            <td>${data.fecha_afiliacion || ''}</td>`;
        row.style.animation = 'slideIn 0.3s ease';
        tbody.prepend(row);
    }

    function exportResults() {
        if (currentConsultaId) window.open(`/export/${currentConsultaId}`, '_blank');
    }

    async function deleteConsulta(id, btn) {
        if (!confirm('¿Está seguro de eliminar esta consulta y todos sus resultados?')) return;
        try {
            const response = await fetchApi(`/consulta/${id}`, { method: 'DELETE' });
            const data = await response.json();
            if (data.success) {
                document.getElementById('history-' + id)?.remove();
                showAlert('success', data.message);
            } else { showAlert('error', data.message); }
        } catch (error) { showAlert('error', 'Error: ' + error.message); }
    }
</script>
@endsection
