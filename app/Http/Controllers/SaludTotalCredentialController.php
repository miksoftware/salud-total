<?php

namespace App\Http\Controllers;

use App\Services\SaludTotalService;
use App\Services\SaludTotalCommerceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SaludTotalCredentialController extends Controller
{
    // ── Vista principal ───────────────────────────────────────────────────────

    public function index()
    {
        return view('salud_total.credentials', [
            // Método activo (url | commerce)
            'loginMethod' => session('salud_total_login_method', config('salud_total.login_method', 'url')),

            // Datos para el método URL (legado)
            'sessionUrl'  => session('salud_total_session_url', config('salud_total.session_init_url')),

            // Datos para el método Commerce (usuario/contraseña)
            'commerceUsername' => session('salud_total_commerce_username', config('salud_total.commerce_username', '')),
            'commercePassword' => session('salud_total_commerce_password', config('salud_total.commerce_password', '')),
        ]);
    }

    // ── Guardar configuración del método URL (legado) ─────────────────────────

    public function save(Request $request)
    {
        $request->validate([
            'session_url' => ['required', 'url', 'max:1000'],
        ]);

        session(['salud_total_session_url' => $request->session_url]);

        return response()->json(['success' => true]);
    }

    // ── Guardar credenciales Commerce ─────────────────────────────────────────

    public function saveCommerce(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        session([
            'salud_total_commerce_username' => $request->username,
            'salud_total_commerce_password' => $request->password,
        ]);

        return response()->json(['success' => true]);
    }

    // ── Guardar método de login seleccionado ──────────────────────────────────

    public function setMethod(Request $request)
    {
        $request->validate([
            'method' => ['required', 'in:url,commerce'],
        ]);

        session(['salud_total_login_method' => $request->method]);

        return response()->json(['success' => true, 'method' => $request->method]);
    }

    // ── Probar sesión: método URL (legado) ────────────────────────────────────

    public function test(Request $request)
    {
        $url = session('salud_total_session_url', config('salud_total.session_init_url'));

        try {
            $service = new SaludTotalService();
            $ok      = $service->initSession();

            return response()->json([
                'success' => $ok,
                'message' => $ok
                    ? '✅ Sesión inicializada correctamente con la URL proporcionada.'
                    : '❌ La URL no pudo inicializar sesión. Verifique que sea la URL correcta y vigente.',
                'url' => $url,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '❌ No se pudo conectar: ' . $e->getMessage(),
                'url'     => $url,
            ]);
        }
    }

    // ── Probar sesión: método Commerce ────────────────────────────────────────

    public function testCommerce(Request $request)
    {
        // Override credentials from the request if provided (so user can test
        // before saving).
        $username = $request->input('username') ?: session('salud_total_commerce_username', config('salud_total.commerce_username'));
        $password = $request->input('password') ?: session('salud_total_commerce_password', config('salud_total.commerce_password'));

        if (empty($username) || empty($password)) {
            return response()->json([
                'success' => false,
                'message' => '❌ Debe ingresar usuario y contraseña antes de probar.',
            ]);
        }

        // Temporarily push to session so the service constructor picks them up
        $prevUser = session('salud_total_commerce_username');
        $prevPass = session('salud_total_commerce_password');

        session([
            'salud_total_commerce_username' => $username,
            'salud_total_commerce_password' => $password,
        ]);

        // Clear cached cookies so we force a fresh login
        Cache::forget('st_commerce_cookies');
        Cache::forget('st_commerce_session_active');

        try {
            $service = new SaludTotalCommerceService();
            $ok      = $service->login();

            return response()->json([
                'success' => $ok,
                'message' => $ok
                    ? '✅ Inicio de sesión exitoso. Credenciales correctas y sesión activa.'
                    : '❌ No se pudo iniciar sesión. Verifique el usuario y la contraseña.',
                'username' => $username,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '❌ Error al conectar: ' . $e->getMessage(),
            ]);
        } finally {
            // Restore previous session values if test didn't persist them
            if ($prevUser === null) {
                session()->forget('salud_total_commerce_username');
            }
            if ($prevPass === null) {
                session()->forget('salud_total_commerce_password');
            }
        }
    }

    // ── Reset URL legado ──────────────────────────────────────────────────────

    public function reset(Request $request)
    {
        session()->forget('salud_total_session_url');
        return response()->json(['success' => true]);
    }

    // ── Reset credenciales Commerce ───────────────────────────────────────────

    public function resetCommerce(Request $request)
    {
        session()->forget(['salud_total_commerce_username', 'salud_total_commerce_password']);
        Cache::forget('st_commerce_cookies');
        Cache::forget('st_commerce_session_active');
        return response()->json(['success' => true]);
    }
}
