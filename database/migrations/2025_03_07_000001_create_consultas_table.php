<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultas', function (Blueprint $table) {
            $table->id();
            $table->string('filename')->nullable();
            $table->integer('total_cedulas')->default(0);
            $table->integer('processed')->default(0);
            $table->integer('successful')->default(0);
            $table->integer('failed')->default(0);
            $table->enum('status', ['pending', 'processing', 'completed', 'error'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultas');
    }
};
