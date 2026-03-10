<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultaResult extends Model
{
    protected $fillable = [
        'consulta_id',
        'cedula',
        'tipo_documento',
        'identificacion',
        'consecutivo',
        'nombres',
        'parentesco',
        'estado_detallado',
        'documentos_faltantes',
        'fecha_nacimiento',
        'edad',
        'sexo',
        'antiguedad_salud_total',
        'fecha_afiliacion',
        'eps_anterior',
        'antiguedad_otra_eps',
        'direccion',
        'telefono',
        'ciudad',
        'ips_medica_asignada',
        'ips_odontologica_asignada',
        'contrato_empresa_id',
        'contrato_empresa_nombre',
        'contrato_arp',
        'contrato_afp',
        'contrato_cargo',
        'contrato_ultimo_pago',
        'contrato_ingreso_base',
        'contrato_cotizacion_pagada',
        'contrato_periodos_mora',
        'contrato_fecha_primer_pago',
        'status',
        'error_message',
    ];

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class);
    }
}
