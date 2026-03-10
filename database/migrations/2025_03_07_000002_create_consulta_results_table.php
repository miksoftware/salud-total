<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consulta_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consulta_id')->constrained()->onDelete('cascade');
            $table->string('cedula');
            $table->string('tipo_documento')->nullable();
            $table->string('identificacion')->nullable();
            $table->string('consecutivo')->nullable();
            $table->string('nombres')->nullable();
            $table->string('parentesco')->nullable();
            $table->string('estado_detallado')->nullable();
            $table->string('documentos_faltantes')->nullable();
            // Detail fields
            $table->string('fecha_nacimiento')->nullable();
            $table->string('edad')->nullable();
            $table->string('sexo')->nullable();
            $table->string('antiguedad_salud_total')->nullable();
            $table->string('fecha_afiliacion')->nullable();
            $table->string('eps_anterior')->nullable();
            $table->string('antiguedad_otra_eps')->nullable();
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('ips_medica_asignada')->nullable();
            $table->string('ips_odontologica_asignada')->nullable();
            $table->enum('status', ['pending', 'success', 'error'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consulta_results');
    }
};
