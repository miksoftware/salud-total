<?php

namespace App\Exports;

use App\Models\ConsultaResult;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ResultsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected int $consultaId;

    public function __construct(int $consultaId)
    {
        $this->consultaId = $consultaId;
    }

    public function query()
    {
        return ConsultaResult::where('consulta_id', $this->consultaId)
            ->orderBy('id');
    }

    public function headings(): array
    {
        return [
            'Cédula Consultada',
            'Tipo Documento',
            'Identificación',
            'Consecutivo',
            'Nombres',
            'Parentesco',
            'Estado',
            'Documentos Faltantes',
            'Fecha Nacimiento',
            'Edad',
            'Sexo',
            'Antigüedad Salud Total',
            'Fecha Afiliación',
            'EPS Anterior',
            'Antigüedad Otra EPS',
            'Dirección',
            'Teléfono',
            'Ciudad',
            'IPS Médica Asignada',
            'IPS Odontológica Asignada',
            'Estado Consulta',
            'Error',
        ];
    }

    public function map($result): array
    {
        return [
            $result->cedula,
            $result->tipo_documento,
            $result->identificacion,
            $result->consecutivo,
            $result->nombres,
            $result->parentesco,
            $result->estado_detallado,
            $result->documentos_faltantes,
            $result->fecha_nacimiento,
            $result->edad,
            $result->sexo,
            $result->antiguedad_salud_total,
            $result->fecha_afiliacion,
            $result->eps_anterior,
            $result->antiguedad_otra_eps,
            $result->direccion,
            $result->telefono,
            $result->ciudad,
            $result->ips_medica_asignada,
            $result->ips_odontologica_asignada,
            $result->status,
            $result->error_message,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '00838F'],
                ],
            ],
        ];
    }
}
