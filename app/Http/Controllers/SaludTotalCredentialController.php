<?php

namespace App\Http\Controllers;

use App\Services\SaludTotalService;
use Illuminate\Http\Request;

class SaludTotalCredentialController extends Controller
{
    public function index()
    {
        return view('salud_total.credentials', [
            'sessionUrl' => session('salud_total_session_url', config('salud_total.session_init_url')),
        ]);
    }

    public function save(Request $request)
    {
        $request->validate([
            'session_url' => ['required', 'url', 'max:1000'],
        ]);

        session(['salud_total_session_url' => $request->session_url]);

        return response()->json(['success' => true]);
    }

    public function test(Request $request)
    {
        $url = session('salud_total_session_url', config('salud_total.session_init_url'));

        try {
            $service = new SaludTotalService();
            $ok = $service->initSession();

            return response()->json([
                'success' => $ok,
                'message' => $ok
                    ? 'Sesión inicializada correctamente con la URL proporcionada.'
                    : 'La URL no pudo inicializar sesión. Verifique que sea la URL correcta y vigente.',
                'url'     => $url,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo conectar: ' . $e->getMessage(),
                'url'     => $url,
            ]);
        }
    }

    public function reset(Request $request)
    {
        session()->forget('salud_total_session_url');

        return response()->json(['success' => true]);
    }
}
