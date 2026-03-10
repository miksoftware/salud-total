<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Salud Total - Consulta Automatizada')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #00838F;
            --primary-dark: #006064;
            --primary-light: #00ACC1;
            --accent: #00E5FF;
            --bg-dark: #0a0e17;
            --bg-card: #111827;
            --bg-card-hover: #1a2332;
            --bg-input: #1e293b;
            --border: #1e3a5f;
            --border-active: #00ACC1;
            --text-primary: #e2e8f0;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --success: #10b981;
            --success-bg: rgba(16, 185, 129, 0.1);
            --error: #ef4444;
            --error-bg: rgba(239, 68, 68, 0.1);
            --warning: #f59e0b;
            --warning-bg: rgba(245, 158, 11, 0.1);
            --glass: rgba(17, 24, 39, 0.7);
            --glass-border: rgba(0, 172, 193, 0.15);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            --shadow-glow: 0 0 30px rgba(0, 131, 143, 0.15);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
            min-height: 100vh;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(0, 131, 143, 0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(0, 229, 255, 0.05) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 80%, rgba(0, 96, 100, 0.06) 0%, transparent 50%);
            z-index: 0; pointer-events: none;
        }

        .app-container {
            position: relative; z-index: 1;
            max-width: 1400px; margin: 0 auto; padding: 2rem;
        }

        /* Navbar */
        .navbar {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 2rem; padding: 1rem 2rem;
            background: var(--glass); backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border); border-radius: 16px;
            box-shadow: var(--shadow);
        }
        .navbar-brand {
            display: flex; align-items: center; gap: 0.75rem;
            text-decoration: none;
        }
        .navbar-brand .logo-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 10px; display: flex; align-items: center;
            justify-content: center; font-size: 1.2rem;
            box-shadow: 0 0 15px rgba(0, 131, 143, 0.3);
        }
        .navbar-brand h1 {
            font-size: 1.1rem; font-weight: 700;
            background: linear-gradient(135deg, var(--accent), var(--primary-light));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .navbar-links {
            display: flex; align-items: center; gap: 0.5rem;
        }
        .navbar-links a {
            padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.8rem;
            font-weight: 500; color: var(--text-secondary); text-decoration: none;
            transition: all 0.2s ease;
        }
        .navbar-links a:hover, .navbar-links a.active {
            background: rgba(0, 172, 193, 0.1); color: var(--accent);
        }
        .navbar-user {
            display: flex; align-items: center; gap: 1rem; font-size: 0.8rem;
            color: var(--text-muted);
        }
        .navbar-user .user-name { color: var(--text-secondary); font-weight: 500; }
        .navbar-user .badge-role {
            padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.65rem;
            font-weight: 600; text-transform: uppercase;
        }
        .badge-role.admin { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .badge-role.consulta { background: rgba(0, 172, 193, 0.15); color: var(--accent); }

        /* Cards */
        .card {
            background: var(--glass); backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border); border-radius: 16px;
            padding: 1.5rem 2rem; box-shadow: var(--shadow);
            margin-bottom: 1.5rem; transition: all 0.3s ease;
        }
        .card:hover { box-shadow: var(--shadow-glow); }
        .card-title {
            font-size: 1rem; font-weight: 600; color: var(--primary-light);
            margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;
        }
        .card-title .icon { font-size: 1.2rem; }

        /* Form elements */
        label {
            display: block; font-size: 0.8rem; font-weight: 500;
            color: var(--text-secondary); margin-bottom: 0.5rem;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        textarea, input[type="text"], input[type="email"], input[type="password"], input[type="file"], select {
            width: 100%; padding: 0.75rem 1rem;
            background: var(--bg-input); border: 1px solid var(--border);
            border-radius: 10px; color: var(--text-primary);
            font-family: 'Inter', sans-serif; font-size: 0.9rem;
            transition: all 0.3s ease; outline: none;
        }
        textarea:focus, input:focus, select:focus {
            border-color: var(--border-active);
            box-shadow: 0 0 0 3px rgba(0, 172, 193, 0.1);
        }
        select { cursor: pointer; }
        select option { background: var(--bg-card); color: var(--text-primary); }

        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.7rem 1.5rem; border: none; border-radius: 10px;
            font-family: 'Inter', sans-serif; font-size: 0.85rem;
            font-weight: 600; cursor: pointer; transition: all 0.3s ease;
            text-decoration: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white; box-shadow: 0 4px 15px rgba(0, 131, 143, 0.3);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0, 131, 143, 0.4); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .btn-success {
            background: linear-gradient(135deg, #059669, var(--success));
            color: white; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        .btn-success:hover { transform: translateY(-2px); }
        .btn-danger {
            background: linear-gradient(135deg, #dc2626, var(--error));
            color: white;
        }
        .btn-outline {
            background: transparent; border: 1px solid var(--border);
            color: var(--text-secondary);
        }
        .btn-outline:hover {
            border-color: var(--primary-light); color: var(--primary-light);
            background: rgba(0, 172, 193, 0.05);
        }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.8rem; }
        .btn-xs { padding: 0.3rem 0.6rem; font-size: 0.7rem; }
        .btn-group { display: flex; gap: 0.75rem; margin-top: 1rem; }

        /* File upload area */
        .file-upload-area {
            border: 2px dashed var(--border); border-radius: 12px;
            padding: 2rem; text-align: center; transition: all 0.3s ease;
            cursor: pointer; position: relative;
        }
        .file-upload-area:hover, .file-upload-area.dragover {
            border-color: var(--accent); background: rgba(0, 229, 255, 0.03);
        }
        .file-upload-area .upload-icon { font-size: 2.5rem; margin-bottom: 0.75rem; }
        .file-upload-area p { color: var(--text-secondary); font-size: 0.85rem; }
        .file-upload-area .file-name { color: var(--accent); font-weight: 600; margin-top: 0.5rem; }
        .file-upload-area input[type="file"] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer;
        }

        /* Progress */
        .progress-container { display: none; }
        .progress-container.active { display: block; }
        .progress-bar-wrapper {
            width: 100%; height: 8px; background: var(--bg-input);
            border-radius: 4px; overflow: hidden; margin: 1rem 0;
        }
        .progress-bar {
            height: 100%; background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 4px; transition: width 0.5s ease; width: 0%; position: relative;
        }
        .progress-bar::after {
            content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shimmer 2s infinite;
        }
        @keyframes shimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }

        .progress-stats {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem; margin-top: 1rem;
        }
        .stat-card {
            background: var(--bg-input); border-radius: 10px;
            padding: 0.75rem 1rem; text-align: center;
        }
        .stat-card .stat-value { font-size: 1.5rem; font-weight: 700; }
        .stat-card .stat-label {
            font-size: 0.7rem; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.25rem;
        }
        .stat-card.success .stat-value { color: var(--success); }
        .stat-card.error .stat-value { color: var(--error); }
        .stat-card.pending .stat-value { color: var(--warning); }
        .stat-card.total .stat-value { color: var(--accent); }

        /* Tables */
        .results-table-wrapper {
            overflow-x: auto; border-radius: 10px; border: 1px solid var(--border);
        }
        .results-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        .results-table th {
            background: var(--primary-dark); color: white;
            padding: 0.75rem 1rem; text-align: left; font-weight: 600;
            font-size: 0.75rem; text-transform: uppercase;
            letter-spacing: 0.03em; white-space: nowrap; position: sticky; top: 0;
        }
        .results-table td {
            padding: 0.6rem 1rem; border-bottom: 1px solid var(--border); white-space: nowrap;
        }
        .results-table tr:hover td { background: var(--bg-card-hover); }

        /* Badges */
        .badge {
            display: inline-flex; align-items: center; gap: 0.3rem;
            padding: 0.25rem 0.6rem; border-radius: 6px;
            font-size: 0.7rem; font-weight: 600; text-transform: uppercase;
        }
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-error { background: var(--error-bg); color: var(--error); }
        .badge-pending { background: var(--warning-bg); color: var(--warning); }

        /* Live log */
        .live-log {
            max-height: 200px; overflow-y: auto; background: var(--bg-input);
            border-radius: 10px; padding: 1rem;
            font-family: 'Consolas', 'Monaco', monospace; font-size: 0.75rem;
            margin-top: 1rem;
        }
        .live-log .log-entry {
            padding: 0.25rem 0; border-bottom: 1px solid rgba(255,255,255,0.03);
            display: flex; gap: 0.75rem;
        }
        .live-log .log-time { color: var(--text-muted); flex-shrink: 0; }
        .live-log .log-message { color: var(--text-secondary); }
        .live-log .log-message.success { color: var(--success); }
        .live-log .log-message.error { color: var(--error); }

        /* Alerts */
        .alert {
            padding: 1rem 1.25rem; border-radius: 10px; font-size: 0.85rem;
            margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert-success { background: var(--success-bg); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--success); }
        .alert-error { background: var(--error-bg); border: 1px solid rgba(239, 68, 68, 0.2); color: var(--error); }
        .alert-info { background: rgba(0, 172, 193, 0.1); border: 1px solid rgba(0, 172, 193, 0.2); color: var(--primary-light); }

        /* History items */
        .history-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 0.75rem 1rem; background: var(--bg-input);
            border-radius: 10px; margin-bottom: 0.5rem; transition: all 0.2s ease;
        }
        .history-item:hover { background: var(--bg-card-hover); }
        .history-item .info { display: flex; flex-direction: column; }
        .history-item .info .name { font-weight: 500; font-size: 0.85rem; }
        .history-item .info .meta { font-size: 0.75rem; color: var(--text-muted); }

        /* Connection */
        .connection-status { display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; }
        .connection-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--text-muted); }
        .connection-dot.connected { background: var(--success); box-shadow: 0 0 8px rgba(16, 185, 129, 0.5); }
        .connection-dot.error { background: var(--error); }

        /* Spinner */
        .spinner {
            display: inline-block; width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,0.2); border-top-color: var(--accent);
            border-radius: 50%; animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Modal */
        .modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px); z-index: 100;
            align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: var(--bg-card); border: 1px solid var(--glass-border);
            border-radius: 16px; padding: 2rem; width: 90%; max-width: 480px;
            box-shadow: var(--shadow);
        }
        .modal h3 {
            font-size: 1.1rem; font-weight: 600; color: var(--primary-light);
            margin-bottom: 1.5rem;
        }
        .form-group { margin-bottom: 1rem; }

        /* Responsive */
        @media (max-width: 768px) {
            .app-container { padding: 1rem; }
            .navbar { flex-direction: column; gap: 0.75rem; }
            .navbar-links { flex-wrap: wrap; justify-content: center; }
        }

        .hidden { display: none !important; }
    </style>
</head>
<body>
    <div class="app-container">
        @auth
        <nav class="navbar">
            <a href="{{ route('dashboard') }}" class="navbar-brand">
                <div class="logo-icon">🏥</div>
                <h1>Salud Total</h1>
            </a>
            <div class="navbar-links">
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.consultas') }}" class="{{ request()->routeIs('admin.consultas') ? 'active' : '' }}">📁 Subir Archivo</a>
                    <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.index') ? 'active' : '' }}">👥 Usuarios</a>
                @endif
                <a href="{{ route('consultas.search') }}" class="{{ request()->routeIs('consultas.search') ? 'active' : '' }}">🔍 Consultas</a>
                <a href="{{ route('consultas.files') }}" class="{{ request()->routeIs('consultas.files') ? 'active' : '' }}">📊 Archivos</a>
            </div>
            <div class="navbar-user">
                <span class="user-name">{{ auth()->user()->name }}</span>
                <span class="badge-role {{ auth()->user()->role }}">{{ auth()->user()->role }}</span>
                <form action="{{ route('logout') }}" method="POST" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-xs">Salir</button>
                </form>
            </div>
        </nav>
        @endauth

        <div id="alertContainer"></div>

        @yield('content')
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        function fetchApi(url, options = {}) {
            const defaults = {
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
            };
            if (!(options.body instanceof FormData)) {
                defaults.headers['Content-Type'] = 'application/json';
            }
            return fetch(url, { ...defaults, ...options });
        }

        function showAlert(type, message) {
            const container = document.getElementById('alertContainer');
            if (!container) return;
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `<span>${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'}</span> ${message}`;
            container.prepend(alert);
            setTimeout(() => alert.remove(), 8000);
        }
    </script>

    @yield('scripts')
</body>
</html>
