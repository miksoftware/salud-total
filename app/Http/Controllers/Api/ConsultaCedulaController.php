<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsultaResult;
use Illuminate\Http\JsonResponse;

class ConsultaCedulaController extends Controller
{
    /**
     * Retorna la información más reciente de un afiliado por cédula.
     *
     * GET /api/consulta/cedula/{cedula}
     */
    public function show(string $cedula): JsonResponse
    {
        $resultado = ConsultaResult::where('cedula', $cedula)
            ->where('status', 'success')
            ->latest('updated_at')
            ->first();

        if (! $resultado) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron resultados para la cédula proporcionada.',
                'data'    => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Consulta exitosa.',
            'data'    => [
                'cedula'                    => $resultado->cedula,
                'tipo_documento'            => $resultado->tipo_documento,
                'identificacion'            => $resultado->identificacion,
                'nombres'                   => $resultado->nombres,
                'parentesco'                => $resultado->parentesco,
                'estado_detallado'          => $resultado->estado_detallado,
                'fecha_nacimiento'          => $resultado->fecha_nacimiento,
                'edad'                      => $resultado->edad,
                'sexo'                      => $resultado->sexo,
                'antiguedad_salud_total'    => $resultado->antiguedad_salud_total,
                'fecha_afiliacion'          => $resultado->fecha_afiliacion,
                'eps_anterior'              => $resultado->eps_anterior,
                'direccion'                 => $resultado->direccion,
                'telefono'                  => $resultado->telefono,
                'ciudad'                    => $resultado->ciudad,
                'ips_medica_asignada'       => $resultado->ips_medica_asignada,
                'ips_odontologica_asignada' => $resultado->ips_odontologica_asignada,
                'contrato_empresa_nombre'   => $resultado->contrato_empresa_nombre,
                'consultado_en'             => $resultado->updated_at?->toIso8601String(),
            ],
        ]);
    }
}
