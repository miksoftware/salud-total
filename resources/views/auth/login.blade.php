@extends('layouts.app')

@section('title', 'Iniciar Sesión')

@section('content')
<div style="display: flex; align-items: center; justify-content: center; min-height: 80vh;">
    <div class="card" style="width: 100%; max-width: 420px;">
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <div style="font-size: 3rem; margin-bottom: 0.5rem;">🏥</div>
            <h2 style="font-size: 1.3rem; font-weight: 700; background: linear-gradient(135deg, var(--accent), var(--primary-light)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                Salud Total
            </h2>
            <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">Consultor Automático</p>
        </div>

        @if($errors->any())
            <div class="alert alert-error">
                <span>❌</span> {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="form-group">
                <label for="email">Usuario / Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                       placeholder="correo@ejemplo.com">
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required
                       placeholder="••••••••">
            </div>
            <div style="margin: 1rem 0;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; text-transform: none;">
                    <input type="checkbox" name="remember" style="width: auto;">
                    Recordarme
                </label>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                🔐 Iniciar Sesión
            </button>
        </form>
    </div>
</div>
@endsection
