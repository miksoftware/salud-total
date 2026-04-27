<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsultaResult;
use Illuminate\Http\JsonResponse;

class ConsultaCedulaController extends Controller
{
    /**
     * Retorna el historial completo de consultas de un afiliado por cédula,
     * ordenado del más reciente al más antiguo.
     *
     * GET /api/consulta/cedula/{cedula}
     */
    public function show(string $cedula): JsonResponse
    {
        $resultados = ConsultaResult::where('cedula', $cedula)
            ->where('status', 'success')
            ->latest('updated_at')
            ->get();

        if ($resultados->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron resultados para la cédula proporcionada.',
                'data'    => null,
            ], 404);
        }

        $data = $resultados->map(fn (ConsultaResult $r) => [
            'cedula'                    => $r->cedula,
            'tipo_documento'            => $r->tipo_documento,
            'identificacion'            => $r->identificacion,
            'nombres'                   => $r->nombres,
            'parentesco'                => $r->parentesco,
            'estado_detallado'          => $r->estado_detallado,
            'fecha_nacimiento'          => $r->fecha_nacimiento,
            'edad'                      => $r->edad,
            'sexo'                      => $r->sexo,
            'antiguedad_salud_total'    => $r->antiguedad_salud_total,
            'fecha_afiliacion'          => $r->fecha_afiliacion,
            'eps_anterior'              => $r->eps_anterior,
            'direccion'                 => $r->direccion,
            'telefono'                  => $r->telefono,
            'ciudad'                    => $r->ciudad,
            'ips_medica_asignada'       => $r->ips_medica_asignada,
            'ips_odontologica_asignada' => $r->ips_odontologica_asignada,
            'contrato_empresa_nombre'   => $r->contrato_empresa_nombre,
            'consultado_en'             => $r->updated_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Consulta exitosa.',
            'total'   => $data->count(),
            'data'    => $data,
        ]);
    }
}
