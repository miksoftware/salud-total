<?php

namespace App\Http\Controllers;

use App\Models\Consulta;
use App\Models\ConsultaResult;
use App\Services\SaludTotalService;
use App\Imports\CedulasImport;
use App\Exports\ResultsExport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;

class ConsultaController extends Controller
{
    /**
     * Main view.
     */
    public function index()
    {
        $consultas = Consulta::latest()->take(10)->get();
        return view('consultas.index', compact('consultas'));
    }

    /**
     * Upload CSV/Excel with cedulas.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ]);

        try {
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension() ?: 'xlsx');

            // Move the uploaded file to a temp location with correct extension
            // Note: getRealPath() can return empty on Windows/Laragon, so we use move()
            $tempDir = storage_path('app');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            $tempName = 'cedulas_' . uniqid() . '.' . $extension;
            $file->move($tempDir, $tempName);
            $tempPath = $tempDir . DIRECTORY_SEPARATOR . $tempName;

            $import = new CedulasImport();

            // For xlsx/xls files, check if ZipArchive is available
            // If not, try to read as CSV or show a helpful error
            if (in_array($extension, ['xlsx', 'xls'])) {
                if (!class_exists(\ZipArchive::class)) {
                    @unlink($tempPath);
                    return response()->json([
                        'success' => false,
                        'message' => 'La extensión PHP "zip" no está habilitada. '
                            . 'Para archivos Excel (.xlsx/.xls), habilite la extensión php_zip en su php.ini '
                            . '(descomente ";extension=zip"). '
                            . 'Alternativamente, suba el archivo en formato CSV.',
                    ], 422);
                }
            }

            // Determine the reader type explicitly
            $readerType = match ($extension) {
                'csv', 'txt' => \Maatwebsite\Excel\Excel::CSV,
                'xls' => \Maatwebsite\Excel\Excel::XLS,
                default => \Maatwebsite\Excel\Excel::XLSX,
            };

            Excel::import($import, $tempPath, null, $readerType);

            // Cleanup temp file
            @unlink($tempPath);

            $cedulas = $import->getCedulas();

            if (empty($cedulas)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron cédulas válidas en el archivo. Asegúrese de que la primera columna se llame "cedula".',
                ], 422);
            }

            // Create consulta record
            $consulta = Consulta::create([
                'filename' => $file->getClientOriginalName(),
                'total_cedulas' => count($cedulas),
                'status' => 'pending',
            ]);

            // Create pending result records
            foreach ($cedulas as $cedula) {
                ConsultaResult::create([
                    'consulta_id' => $consulta->id,
                    'cedula' => $cedula,
                    'status' => 'pending',
                ]);
            }

            return response()->json([
                'success' => true,
                'consulta_id' => $consulta->id,
                'total' => count($cedulas),
                'cedulas' => $cedulas,
                'message' => 'Archivo cargado correctamente. ' . count($cedulas) . ' cédulas encontradas.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process the next pending cedula for a given consulta.
     * The service handles session init and auto-retry internally.
     */
    public function processNext(int $id): JsonResponse
    {
        $consulta = Consulta::findOrFail($id);

        // Get the next pending result
        $pendingResult = ConsultaResult::where('consulta_id', $id)
            ->where('status', 'pending')
            ->first();

        if (!$pendingResult) {
            $consulta->update(['status' => 'completed']);
            return response()->json([
                'success' => true,
                'completed' => true,
                'message' => 'Todas las cédulas han sido procesadas.',
                'stats' => $this->getStats($consulta),
            ]);
        }

        // Mark as processing
        $consulta->update(['status' => 'processing']);

        // Create service - it will auto-initialize the session
        $service = new SaludTotalService();

        // Process the cedula (service handles session init + retries internally)
        $result = $service->processCedula($pendingResult->cedula);

        // Update the result record
        $updateData = [
            'status' => $result['status'],
            'error_message' => $result['error'],
        ];

        if (!empty($result['data'])) {
            $data = $result['data'];
            $updateData = array_merge($updateData, [
                'tipo_documento' => $data['tipo_documento'] ?? null,
                'identificacion' => $data['identificacion'] ?? null,
                'consecutivo' => $data['consecutivo'] ?? null,
                'nombres' => $data['nombres'] ?? null,
                'parentesco' => $data['parentesco'] ?? null,
                'estado_detallado' => $data['estado_detallado'] ?? null,
                'documentos_faltantes' => $data['documentos_faltantes'] ?? null,
                'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
                'edad' => $data['edad'] ?? null,
                'sexo' => $data['sexo'] ?? null,
                'antiguedad_salud_total' => $data['antiguedad_salud_total'] ?? null,
                'fecha_afiliacion' => $data['fecha_afiliacion'] ?? null,
                'eps_anterior' => $data['eps_anterior'] ?? null,
                'antiguedad_otra_eps' => $data['antiguedad_otra_eps'] ?? null,
                'direccion' => $data['direccion'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'ciudad' => $data['ciudad'] ?? null,
                'ips_medica_asignada' => $data['ips_medica_asignada'] ?? null,
                'ips_odontologica_asignada' => $data['ips_odontologica_asignada'] ?? null,
                'contrato_empresa_id' => $data['contrato_empresa_id'] ?? null,
                'contrato_empresa_nombre' => $data['contrato_empresa_nombre'] ?? null,
                'contrato_arp' => $data['contrato_arp'] ?? null,
                'contrato_afp' => $data['contrato_afp'] ?? null,
                'contrato_cargo' => $data['contrato_cargo'] ?? null,
                'contrato_ultimo_pago' => $data['contrato_ultimo_pago'] ?? null,
                'contrato_ingreso_base' => $data['contrato_ingreso_base'] ?? null,
                'contrato_cotizacion_pagada' => $data['contrato_cotizacion_pagada'] ?? null,
                'contrato_periodos_mora' => $data['contrato_periodos_mora'] ?? null,
                'contrato_fecha_primer_pago' => $data['contrato_fecha_primer_pago'] ?? null,
            ]);
        }

        $pendingResult->update($updateData);

        // Update consulta counters
        $consulta->increment('processed');
        if ($result['status'] === 'success') {
            $consulta->increment('successful');
        } else {
            $consulta->increment('failed');
        }

        return response()->json([
            'success' => true,
            'completed' => false,
            'result' => [
                'cedula' => $pendingResult->cedula,
                'status' => $result['status'],
                'nombres' => $result['data']['nombres'] ?? 'N/A',
                'error' => $result['error'],
                'data' => $result['data'],
            ],
            'stats' => $this->getStats($consulta->fresh()),
        ]);
    }

    /**
     * Get current status of a consulta.
     */
    public function status(int $id): JsonResponse
    {
        $consulta = Consulta::findOrFail($id);
        $results = ConsultaResult::where('consulta_id', $id)
            ->where('status', '!=', 'pending')
            ->get();

        return response()->json([
            'success' => true,
            'consulta' => $consulta,
            'stats' => $this->getStats($consulta),
            'results' => $results,
        ]);
    }

    /**
     * Export results to Excel.
     */
    public function export(int $id)
    {
        $consulta = Consulta::findOrFail($id);

        // If ZipArchive is not available, export as CSV instead of XLSX
        if (!class_exists(\ZipArchive::class)) {
            $filename = 'resultados_salud_total_' . $consulta->id . '_' . now()->format('Y-m-d_His') . '.csv';
            return Excel::download(new ResultsExport($id), $filename, \Maatwebsite\Excel\Excel::CSV);
        }

        $filename = 'resultados_salud_total_' . $consulta->id . '_' . now()->format('Y-m-d_His') . '.xlsx';
        return Excel::download(new ResultsExport($id), $filename);
    }

    /**
     * Test connection to the portal.
     */
    public function testConnection(): JsonResponse
    {
        try {
            $service = new SaludTotalService();
            $success = $service->initSession();

            if ($success) {
                $html = $service->navigateToFamilyGroupPage();
                if ($html && !str_contains($html, 'no existen datos validos')) {
                    return response()->json([
                        'success' => true,
                        'message' => '✅ Conexión exitosa. El portal responde correctamente.',
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => '❌ No se pudo conectar al portal. El enlace de sesión puede haber expirado.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '❌ Error de conexión: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Resume a stopped/partial consulta.
     */
    public function resume(int $id): JsonResponse
    {
        $consulta = Consulta::findOrFail($id);

        $pending = ConsultaResult::where('consulta_id', $id)
            ->where('status', 'pending')
            ->count();

        if ($pending === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No hay cédulas pendientes para esta consulta.',
            ]);
        }

        $consulta->update(['status' => 'processing']);

        return response()->json([
            'success' => true,
            'consulta_id' => $consulta->id,
            'stats' => $this->getStats($consulta),
            'message' => "Reanudando consulta. $pending cédulas pendientes.",
        ]);
    }

    /**
     * Get stats for a consulta.
     */
    protected function getStats(Consulta $consulta): array
    {
        return [
            'total' => $consulta->total_cedulas,
            'processed' => $consulta->processed,
            'successful' => $consulta->successful,
            'failed' => $consulta->failed,
            'pending' => $consulta->total_cedulas - $consulta->processed,
            'progress' => $consulta->progress_percentage,
            'status' => $consulta->status,
        ];
    }

    /**
     * Delete a consulta and all its results.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $consulta = Consulta::findOrFail($id);
            ConsultaResult::where('consulta_id', $id)->delete();
            $consulta->delete();

            return response()->json([
                'success' => true,
                'message' => 'Consulta eliminada correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la consulta: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search panel - search results by cedula.
     */
    public function search()
    {
        return view('consultas.search');
    }

    /**
     * API: search results by cedula number.
     */
    public function searchByCedula(Request $request): JsonResponse
    {
        $cedula = preg_replace('/[^0-9]/', '', $request->input('cedula', ''));

        if (strlen($cedula) < 5) {
            return response()->json([
                'success' => false,
                'message' => 'Ingrese un número de cédula válido (mínimo 5 dígitos).',
            ], 422);
        }

        $results = ConsultaResult::where('cedula', $cedula)
            ->orWhere('identificacion', $cedula)
            ->where('status', 'success')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'total' => $results->count(),
            'results' => $results,
        ]);
    }

    /**
     * Files panel - list all consulta files with export option.
     */
    public function files()
    {
        $consultas = Consulta::where('status', 'completed')
            ->orderByDesc('created_at')
            ->get();

        return view('consultas.files', compact('consultas'));
    }
}
