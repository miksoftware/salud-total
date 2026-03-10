@extends('layouts.app')

@section('title', 'Consultas')

@section('content')
<div class="card">
    <div class="card-title">
        <span class="icon">🔍</span>
        Consultar por Cédula
    </div>
    <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 1rem;">
        Busque los resultados de una cédula previamente consultada en el sistema.
    </p>
    <div style="display: flex; gap: 0.75rem; align-items: flex-end;">
        <div style="flex: 1;">
            <label for="searchCedula">Número de Cédula</label>
            <input type="text" id="searchCedula" placeholder="Ej: 1121148057"
                   onkeydown="if(event.key==='Enter') searchCedula()">
        </div>
        <button class="btn btn-primary" onclick="searchCedula()" id="searchBtn" style="height: 44px;">
            🔍 Buscar
        </button>
    </div>
</div>

<div class="card hidden" id="resultsCard">
    <div class="card-title">
        <span class="icon">📋</span>
        Resultados
        <span id="resultsCount" style="margin-left: 0.5rem; font-size: 0.8rem; color: var(--text-muted);"></span>
    </div>
    <div id="resultsContainer"></div>
</div>

<div class="card hidden" id="noResultsCard">
    <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
        <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🔍</div>
        <p>No se encontraron resultados para esta cédula.</p>
        <p style="font-size: 0.8rem; margin-top: 0.5rem;">Verifique el número o solicite que se procese el archivo correspondiente.</p>
    </div>
</div>
@endsection

@section('scripts')
<script>
    async function searchCedula() {
        const cedula = document.getElementById('searchCedula').value.trim();
        if (!cedula || cedula.length < 5) {
            showAlert('error', 'Ingrese un número de cédula válido (mínimo 5 dígitos).');
            return;
        }

        const btn = document.getElementById('searchBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Buscando...';

        document.getElementById('resultsCard').classList.add('hidden');
        document.getElementById('noResultsCard').classList.add('hidden');

        try {
            const response = await fetchApi(`{{ route('consultas.searchByCedula') }}?cedula=${cedula}`);
            const data = await response.json();

            if (!data.success) {
                showAlert('error', data.message);
                return;
            }

            if (data.total === 0) {
                document.getElementById('noResultsCard').classList.remove('hidden');
                return;
            }

            document.getElementById('resultsCount').textContent = `(${data.total} resultado${data.total > 1 ? 's' : ''})`;
            renderResults(data.results);
            document.getElementById('resultsCard').classList.remove('hidden');

        } catch (error) {
            showAlert('error', 'Error al buscar: ' + error.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '🔍 Buscar';
        }
    }

    function renderResults(results) {
        const container = document.getElementById('resultsContainer');
        let html = '<div class="results-table-wrapper" style="max-height: 500px; overflow-y: auto;"><table class="results-table"><thead><tr>';
        html += '<th>Cédula</th><th>Nombres</th><th>Tipo Doc.</th><th>Parentesco</th>';
        html += '<th>Estado</th><th>Fecha Nac.</th><th>Edad</th><th>Sexo</th>';
        html += '<th>Ciudad</th><th>Dirección</th><th>Teléfono</th>';
        html += '<th>IPS Médica</th><th>IPS Odontológica</th><th>EPS Anterior</th>';
        html += '<th>Antigüedad ST</th><th>Fecha Afiliación</th><th>Consultado</th>';
        html += '</tr></thead><tbody>';

        results.forEach(r => {
            html += `<tr>
                <td>${r.cedula || ''}</td>
                <td>${r.nombres || ''}</td>
                <td>${r.tipo_documento || ''}</td>
                <td>${r.parentesco || ''}</td>
                <td>${r.estado_detallado || ''}</td>
                <td>${r.fecha_nacimiento || ''}</td>
                <td>${r.edad || ''}</td>
                <td>${r.sexo || ''}</td>
                <td>${r.ciudad || ''}</td>
                <td>${r.direccion || ''}</td>
                <td>${r.telefono || ''}</td>
                <td>${r.ips_medica_asignada || ''}</td>
                <td>${r.ips_odontologica_asignada || ''}</td>
                <td>${r.eps_anterior || ''}</td>
                <td>${r.antiguedad_salud_total || ''}</td>
                <td>${r.fecha_afiliacion || ''}</td>
                <td>${r.created_at ? new Date(r.created_at).toLocaleDateString('es-CO') : ''}</td>
            </tr>`;
        });

        html += '</tbody></table></div>';
        container.innerHTML = html;
    }
</script>
@endsection
