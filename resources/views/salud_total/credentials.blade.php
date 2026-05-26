@extends('layouts.app')
@section('title', 'Configuración API - Salud Total')

@section('content')
<div class="card glass-card" style="max-width: 680px;">
    <h2 style="margin-bottom: 0.25rem;">URL de sesión — Salud Total</h2>
    <p style="color: #9999bb; font-size: 0.85rem; margin-bottom: 1.5rem;">
        El portal de Salud Total requiere una <strong style="color: #e879f9;">URL especial con token cifrado</strong>
        para inicializar la sesión. Cuando la URL cambie o expire, actualícela aquí.
    </p>

    {{-- URL activa --}}
    <div style="background: rgba(232,121,249,0.08); border: 1px solid rgba(232,121,249,0.2); border-radius: 10px; padding: 0.9rem 1rem; margin-bottom: 1.5rem; font-size: 0.82rem;">
        <div style="color: #9999bb; margin-bottom: 4px;">URL de sesión activa:</div>
        <div id="displayUrl" style="color: #e879f9; word-break: break-all; font-family: Consolas, monospace; font-size: 0.78rem;">{{ $sessionUrl }}</div>
        <div style="margin-top: 6px;">
            @if(session()->has('salud_total_session_url'))
                <span style="background: rgba(105,240,174,0.15); color: #69f0ae; padding: 2px 8px; border-radius: 20px; font-size: 0.75rem;">sesión</span>
            @else
                <span style="background: rgba(255,255,255,0.08); color: #9999bb; padding: 2px 8px; border-radius: 20px; font-size: 0.75rem;">config/.env</span>
            @endif
        </div>
    </div>

    {{-- Form --}}
    <div class="form-group">
        <label class="form-label">Nueva URL de sesión</label>
        <textarea id="sessionUrl" class="form-control"
                  rows="3"
                  placeholder="https://transaccional.saludtotal.com.co/Transaccional/inicio.aspx?q=..."
                  style="resize: vertical; font-family: Consolas, monospace; font-size: 0.8rem;">{{ $sessionUrl }}</textarea>
        <small style="color: #666688; font-size: 0.78rem; margin-top: 4px; display: block;">
            Pegue la URL completa incluyendo el parámetro <code style="color: #9999bb;">?q=...</code> con el token cifrado.
        </small>
    </div>

    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.25rem;">
        <button class="btn btn-primary" id="btnSave" onclick="saveUrl()">Guardar URL</button>
        <button class="btn btn-success" id="btnTest" onclick="testUrl()">Probar sesión</button>
        @if(session()->has('salud_total_session_url'))
        <button class="btn btn-outline" onclick="resetUrl()">Restaurar default</button>
        @endif
    </div>

    <div id="message" style="margin-top: 1rem; display: none;"></div>
</div>

{{-- Resultado del test --}}
<div class="card glass-card" id="testCard" style="max-width: 680px; display: none;">
    <h3 style="margin-bottom: 0.75rem; font-size: 1rem;">Resultado</h3>
    <p id="testResult" style="font-size: 0.88rem; font-family: Consolas, monospace; word-break: break-all;"></p>
</div>
@endsection

@section('scripts')
<script>
    function showMsg(text, type) {
        const colors = { success: '#69f0ae', error: '#ff6b7a', info: '#e879f9' };
        const el = document.getElementById('message');
        el.style.display = 'block';
        el.innerHTML = `<div style="padding:0.75rem 1rem;border-radius:8px;background:rgba(255,255,255,0.04);border:1px solid ${colors[type]||colors.info}44;color:${colors[type]||colors.info};font-size:0.85rem;">${text}</div>`;
    }

    async function saveUrl() {
        const url = document.getElementById('sessionUrl').value.trim();
        if (!url) { showMsg('Ingrese la URL de sesión.', 'error'); return; }

        const btn = document.getElementById('btnSave');
        btn.disabled = true; btn.textContent = 'Guardando...';

        try {
            const res = await fetch('{{ route("salud_total.credentials.save") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ session_url: url }),
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('displayUrl').textContent = url;
                showMsg('✓ URL guardada correctamente.', 'success');
                setTimeout(() => location.reload(), 700);
            } else {
                showMsg(data.message || 'Error al guardar.', 'error');
            }
        } catch (e) {
            showMsg('Error: ' + e.message, 'error');
        } finally {
            btn.disabled = false; btn.textContent = 'Guardar URL';
        }
    }

    async function testUrl() {
        const btn = document.getElementById('btnTest');
        btn.disabled = true; btn.textContent = '⏳ Probando sesión...';

        const card = document.getElementById('testCard');
        const result = document.getElementById('testResult');
        card.style.display = 'block';
        result.style.color = '#7777aa';
        result.textContent = 'Inicializando sesión con Salud Total...\n(puede tardar varios segundos)';

        try {
            const res = await fetch('{{ route("salud_total.credentials.test") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });
            const data = await res.json();
            result.style.color = data.success ? '#69f0ae' : '#ff6b7a';
            result.textContent = data.message;
            showMsg((data.success ? '✓ ' : '✗ ') + data.message, data.success ? 'success' : 'error');
        } catch (e) {
            result.style.color = '#ff6b7a';
            result.textContent = 'Error: ' + e.message;
            showMsg('Error: ' + e.message, 'error');
        } finally {
            btn.disabled = false; btn.textContent = 'Probar sesión';
        }
    }

    async function resetUrl() {
        if (!confirm('¿Restaurar la URL del config/.env?')) return;
        await fetch('{{ route("salud_total.credentials.reset") }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        });
        location.reload();
    }
</script>
@endsection
