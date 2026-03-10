<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consulta_results', function (Blueprint $table) {
            $table->string('contrato_empresa_id')->nullable()->after('ips_odontologica_asignada');
            $table->string('contrato_empresa_nombre')->nullable()->after('contrato_empresa_id');
            $table->string('contrato_arp')->nullable()->after('contrato_empresa_nombre');
            $table->string('contrato_afp')->nullable()->after('contrato_arp');
            $table->string('contrato_cargo')->nullable()->after('contrato_afp');
            $table->string('contrato_ultimo_pago')->nullable()->after('contrato_cargo');
            $table->string('contrato_ingreso_base')->nullable()->after('contrato_ultimo_pago');
            $table->string('contrato_cotizacion_pagada')->nullable()->after('contrato_ingreso_base');
            $table->string('contrato_periodos_mora')->nullable()->after('contrato_cotizacion_pagada');
            $table->string('contrato_fecha_primer_pago')->nullable()->after('contrato_periodos_mora');
        });
    }

    public function down(): void
    {
        Schema::table('consulta_results', function (Blueprint $table) {
            $table->dropColumn([
                'contrato_empresa_id', 'contrato_empresa_nombre',
                'contrato_arp', 'contrato_afp', 'contrato_cargo',
                'contrato_ultimo_pago', 'contrato_ingreso_base',
                'contrato_cotizacion_pagada', 'contrato_periodos_mora',
                'contrato_fecha_primer_pago',
            ]);
        });
    }
};
