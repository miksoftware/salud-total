@extends('layouts.app')
@section('title', 'Configuración API - Salud Total')

@section('content')

{{-- ── Selector de Método ─────────────────────────────────────────────────── --}}
<div class="card glass-card" style="max-width: 720px; margin-bottom: 1.5rem;">
    <h2 style="margin-bottom: 0.25rem;">Método de inicio de sesión</h2>
    <p style="color: #9999bb; font-size: 0.85rem; margin-bottom: 1.5rem;">
        Seleccione cómo se autenticará el scraper ante el portal de Salud Total.
    </p>

    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">

        {{-- Opción 1: URL de sesión --}}
        <label id="cardUrl" onclick="selectMethod('url')"
               style="flex: 1; min-width: 240px; cursor: pointer; border-radius: 12px; padding: 1rem 1.2rem;
                      border: 2px solid {{ $loginMethod === 'url' ? 'rgba(232,121,249,0.7)' : 'rgba(255,255,255,0.08)' }};
                      background: {{ $loginMethod === 'url' ? 'rgba(232,121,249,0.07)' : 'rgba(255,255,255,0.02)' }};
                      transition: border .25s, background .25s;">
            <div style="display:flex; align-items:center; gap:.6rem; margin-bottom:.5rem;">
                <input type="radio" name="loginMethod" value="url" id="radioUrl"
                       {{ $loginMethod === 'url' ? 'checked' : '' }}
                       style="accent-color:#e879f9;">
                <span style="font-weight:600; color:#e879f9;">URL de sesión <span style="font-size:.7rem; opacity:.6;">(legado)</span></span>
            </div>
            <div style="color:#9999bb; font-size:0.8rem; line-height:1.4;">
                URL mágica con token cifrado obtenida del portal Transaccional clásico.<br>
                Expira cada cierto tiempo y debe actualizarse manualmente.
            </div>
        </label>

        {{-- Opción 2: Usuario + Contraseña --}}
        <label id="cardCommerce" onclick="selectMethod('commerce')"
               style="flex: 1; min-width: 240px; cursor: pointer; border-radius: 12px; padding: 1rem 1.2rem;
                      border: 2px solid {{ $loginMethod === 'commerce' ? 'rgba(105,240,174,0.7)' : 'rgba(255,255,255,0.08)' }};
                      background: {{ $loginMethod === 'commerce' ? 'rgba(105,240,174,0.06)' : 'rgba(255,255,255,0.02)' }};
                      transition: border .25s, background .25s;">
            <div style="display:flex; align-items:center; gap:.6rem; margin-bottom:.5rem;">
                <input type="radio" name="loginMethod" value="commerce" id="radioCommerce"
                       {{ $loginMethod === 'commerce' ? 'checked' : '' }}
                       style="accent-color:#69f0ae;">
                <span style="font-weight:600; color:#69f0ae;">Usuario + Contraseña <span style="font-size:.7rem; opacity:.6;">(nuevo)</span></span>
            </div>
            <div style="color:#9999bb; font-size:0.8rem; line-height:1.4;">
                Inicio de sesión con credenciales propias en el nuevo portal<br>
                <code style="color:#aaa; font-size:.76rem;">transaccional.saludtotal.com.co/SaludTotal.Comerce</code>
            </div>
        </label>
    </div>

    <div id="methodMsg" style="margin-top:.75rem; display:none;"></div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════════
     PANEL: URL de sesión (legado)
═══════════════════════════════════════════════════════════════════════════ --}}
<div id="panelUrl" class="card glass-card" style="max-width: 720px; {{ $loginMethod !== 'url' ? 'display:none;' : '' }}">
    <h3 style="margin-bottom: 0.2rem; font-size: 1rem; color: #e879f9;">⚡ URL de sesión (método legado)</h3>
    <p style="color: #9999bb; font-size: 0.82rem; margin-bottom: 1.25rem;">
        El portal requiere una <strong style="color: #e879f9;">URL especial con token cifrado</strong>
        para inicializar la sesión. Cuando la URL cambie o expire, actualícela aquí.
    </p>

    {{-- URL activa --}}
    <div style="background: rgba(232,121,249,0.08); border: 1px solid rgba(232,121,249,0.2);
                border-radius: 10px; padding: 0.9rem 1rem; margin-bottom: 1.25rem; font-size: 0.82rem;">
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

    <div id="messageUrl" style="margin-top: 1rem; display: none;"></div>
</div>

{{-- Resultado test URL --}}
<div class="card glass-card" id="testCardUrl" style="max-width: 720px; display: none; {{ $loginMethod !== 'url' ? 'margin-top:0;' : '' }}">
    <h3 style="margin-bottom: 0.75rem; font-size: 1rem;">Resultado — URL</h3>
    <p id="testResultUrl" style="font-size: 0.88rem; font-family: Consolas, monospace; word-break: break-all;"></p>
</div>

{{-- ══════════════════════════════════════════════════════════════════════════
     PANEL: Usuario + Contraseña (Commerce)
═══════════════════════════════════════════════════════════════════════════ --}}
<div id="panelCommerce" class="card glass-card" style="max-width: 720px; {{ $loginMethod !== 'commerce' ? 'display:none;' : '' }}">
    <h3 style="margin-bottom: 0.2rem; font-size: 1rem; color: #69f0ae;">🔐 Usuario + Contraseña (Commerce)</h3>
    <p style="color: #9999bb; font-size: 0.82rem; margin-bottom: 1.25rem;">
        Ingrese las credenciales del portal
        <code style="color: #aaa; font-size:.76rem;">SaludTotal.Comerce/login.aspx</code>.
        Se guardan en sesión y no se almacenan en base de datos.
    </p>

    {{-- Credenciales activas --}}
    <div style="background: rgba(105,240,174,0.06); border: 1px solid rgba(105,240,174,0.2);
                border-radius: 10px; padding: 0.9rem 1rem; margin-bottom: 1.25rem; font-size: 0.82rem;">
        <div style="color: #9999bb; margin-bottom: 4px;">Usuario activo:</div>
        <div style="color: #69f0ae; font-family: Consolas, monospace; font-size: 0.85rem;">
            {{ $commerceUsername ?: '— no configurado —' }}
        </div>
        <div style="margin-top: 6px;">
            @if(session()->has('salud_total_commerce_username'))
                <span style="background: rgba(105,240,174,0.15); color: #69f0ae; padding: 2px 8px; border-radius: 20px; font-size: 0.75rem;">sesión</span>
            @elseif($commerceUsername)
                <span style="background: rgba(255,255,255,0.08); color: #9999bb; padding: 2px 8px; border-radius: 20px; font-size: 0.75rem;">config/.env</span>
            @else
                <span style="background: rgba(255,100,100,0.15); color: #ff6b7a; padding: 2px 8px; border-radius: 20px; font-size: 0.75rem;">sin configurar</span>
            @endif
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Usuario</label>
            <input type="text" id="commerceUsername" class="form-control"
                   placeholder="Ej: 79954735"
                   value="{{ $commerceUsername }}"
                   autocomplete="username">
        </div>
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Contraseña</label>
            <div style="position: relative;">
                <input type="password" id="commercePassword" class="form-control"
                       placeholder="••••••••"
                       value="{{ $commercePassword ? str_repeat('•', 8) : '' }}"
                       autocomplete="current-password"
                       style="padding-right: 2.8rem;">
                <button type="button" onclick="togglePass()" title="Mostrar/ocultar"
                        style="position:absolute; right:.6rem; top:50%; transform:translateY(-50%);
                               background:none; border:none; cursor:pointer; color:#9999bb; font-size:1rem; padding:0;">👁</button>
            </div>
        </div>
    </div>

    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.25rem;">
        <button class="btn btn-primary" id="btnSaveCommerce" onclick="saveCommerce()">💾 Guardar credenciales</button>
        <button class="btn btn-success" id="btnTestCommerce" onclick="testCommerce()">🔌 Probar conexión</button>
        @if(session()->has('salud_total_commerce_username'))
        <button class="btn btn-outline" onclick="resetCommerce()">Limpiar credenciales</button>
        @endif
    </div>

    <div id="messageCommerce" style="margin-top: 1rem; display: none;"></div>
</div>

{{-- Resultado test Commerce --}}
<div class="card glass-card" id="testCardCommerce" style="max-width: 720px; display: none;">
    <h3 style="margin-bottom: 0.75rem; font-size: 1rem;">Resultado — Commerce</h3>
    <p id="testResultCommerce" style="font-size: 0.88rem; font-family: Consolas, monospace; word-break: break-all;"></p>
</div>

@endsection

@section('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

// ── Helpers UI ───────────────────────────────────────────────────────────────
function showMsg(elId, text, type) {
    const colors = { success: '#69f0ae', error: '#ff6b7a', info: '#e879f9' };
    const el = document.getElementById(elId);
    el.style.display = 'block';
    el.innerHTML = `<div style="padding:.75rem 1rem;border-radius:8px;background:rgba(255,255,255,0.04);
        border:1px solid ${(colors[type]||colors.info)}44;color:${(colors[type]||colors.info)};
        font-size:.85rem;">${text}</div>`;
}

function setBusy(btnId, busy, originalText) {
    const btn = document.getElementById(btnId);
    btn.disabled = busy;
    if (busy) btn.textContent = '⏳ Espere...';
    else btn.textContent = originalText;
}

function togglePass() {
    const el = document.getElementById('commercePassword');
    el.type = el.type === 'password' ? 'text' : 'password';
}

// ── Selector de método ───────────────────────────────────────────────────────
async function selectMethod(method) {
    document.getElementById('radioUrl').checked = (method === 'url');
    document.getElementById('radioCommerce').checked = (method === 'commerce');

    // Visual feedback on cards
    const urlColor    = method === 'url'      ? 'rgba(232,121,249,0.7)' : 'rgba(255,255,255,0.08)';
    const comColor    = method === 'commerce' ? 'rgba(105,240,174,0.7)' : 'rgba(255,255,255,0.08)';
    const urlBg       = method === 'url'      ? 'rgba(232,121,249,0.07)' : 'rgba(255,255,255,0.02)';
    const comBg       = method === 'commerce' ? 'rgba(105,240,174,0.06)' : 'rgba(255,255,255,0.02)';
    document.getElementById('cardUrl').style.borderColor = urlColor;
    document.getElementById('cardUrl').style.background  = urlBg;
    document.getElementById('cardCommerce').style.borderColor = comColor;
    document.getElementById('cardCommerce').style.background  = comBg;

    // Show/hide panels
    document.getElementById('panelUrl').style.display      = method === 'url'      ? '' : 'none';
    document.getElementById('panelCommerce').style.display = method === 'commerce' ? '' : 'none';
    document.getElementById('testCardUrl').style.display      = 'none';
    document.getElementById('testCardCommerce').style.display = 'none';

    // Persist to server
    try {
        await fetch('{{ route("salud_total.credentials.set_method") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ method }),
        });
        showMsg('methodMsg', `✓ Método cambiado a: <strong>${method === 'url' ? 'URL de sesión' : 'Usuario + Contraseña'}</strong>`, 'success');
        document.getElementById('methodMsg').style.display = 'block';
    } catch(e) {
        console.error(e);
    }
}

// ── Método URL (legado) ──────────────────────────────────────────────────────
async function saveUrl() {
    const url = document.getElementById('sessionUrl').value.trim();
    if (!url) { showMsg('messageUrl', 'Ingrese la URL de sesión.', 'error'); return; }

    setBusy('btnSave', true, 'Guardar URL');
    try {
        const res  = await fetch('{{ route("salud_total.credentials.save") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ session_url: url }),
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('displayUrl').textContent = url;
            showMsg('messageUrl', '✓ URL guardada correctamente.', 'success');
            setTimeout(() => location.reload(), 700);
        } else {
            showMsg('messageUrl', data.message || 'Error al guardar.', 'error');
        }
    } catch(e) {
        showMsg('messageUrl', 'Error: ' + e.message, 'error');
    } finally {
        setBusy('btnSave', false, 'Guardar URL');
    }
}

async function testUrl() {
    setBusy('btnTest', true, 'Probar sesión');
    const card   = document.getElementById('testCardUrl');
    const result = document.getElementById('testResultUrl');
    card.style.display = 'block';
    result.style.color  = '#7777aa';
    result.textContent  = 'Inicializando sesión con Salud Total...\n(puede tardar varios segundos)';

    try {
        const res  = await fetch('{{ route("salud_total.credentials.test") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        });
        const data = await res.json();
        result.style.color = data.success ? '#69f0ae' : '#ff6b7a';
        result.textContent  = data.message;
        showMsg('messageUrl', (data.success ? '✓ ' : '✗ ') + data.message, data.success ? 'success' : 'error');
    } catch(e) {
        result.style.color = '#ff6b7a';
        result.textContent  = 'Error: ' + e.message;
        showMsg('messageUrl', 'Error: ' + e.message, 'error');
    } finally {
        setBusy('btnTest', false, 'Probar sesión');
    }
}

async function resetUrl() {
    if (!confirm('¿Restaurar la URL del config/.env?')) return;
    await fetch('{{ route("salud_total.credentials.reset") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF },
    });
    location.reload();
}

// ── Método Commerce (usuario + contraseña) ───────────────────────────────────
async function saveCommerce() {
    const username = document.getElementById('commerceUsername').value.trim();
    const password = document.getElementById('commercePassword').value.trim();

    if (!username || !password) {
        showMsg('messageCommerce', 'Ingrese usuario y contraseña.', 'error');
        return;
    }

    setBusy('btnSaveCommerce', true, '💾 Guardar credenciales');
    try {
        const res  = await fetch('{{ route("salud_total.credentials.save_commerce") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ username, password }),
        });
        const data = await res.json();
        if (data.success) {
            showMsg('messageCommerce', '✓ Credenciales guardadas en sesión.', 'success');
            setTimeout(() => location.reload(), 700);
        } else {
            showMsg('messageCommerce', data.message || 'Error al guardar.', 'error');
        }
    } catch(e) {
        showMsg('messageCommerce', 'Error: ' + e.message, 'error');
    } finally {
        setBusy('btnSaveCommerce', false, '💾 Guardar credenciales');
    }
}

async function testCommerce() {
    const username = document.getElementById('commerceUsername').value.trim();
    const password = document.getElementById('commercePassword').value.trim();

    if (!username || !password) {
        showMsg('messageCommerce', 'Ingrese usuario y contraseña antes de probar.', 'error');
        return;
    }

    setBusy('btnTestCommerce', true, '🔌 Probar conexión');
    const card   = document.getElementById('testCardCommerce');
    const result = document.getElementById('testResultCommerce');
    card.style.display  = 'block';
    result.style.color  = '#7777aa';
    result.textContent  = 'Intentando iniciar sesión en el portal Commerce...\n(puede tardar hasta 15 segundos)';

    try {
        const res  = await fetch('{{ route("salud_total.credentials.test_commerce") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ username, password }),
        });
        const data = await res.json();
        result.style.color = data.success ? '#69f0ae' : '#ff6b7a';
        result.textContent  = data.message;
        showMsg('messageCommerce', (data.success ? '✓ ' : '✗ ') + data.message, data.success ? 'success' : 'error');
    } catch(e) {
        result.style.color = '#ff6b7a';
        result.textContent  = 'Error: ' + e.message;
        showMsg('messageCommerce', 'Error: ' + e.message, 'error');
    } finally {
        setBusy('btnTestCommerce', false, '🔌 Probar conexión');
    }
}

async function resetCommerce() {
    if (!confirm('¿Limpiar las credenciales Commerce de la sesión?')) return;
    await fetch('{{ route("salud_total.credentials.reset_commerce") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF },
    });
    location.reload();
}
</script>
@endsection
