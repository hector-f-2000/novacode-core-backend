<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();       // Identificador técnico (ej: company_rut)
            $table->text('value')->nullable();     // Valor (texto, URL o path del logo)
            $table->string('label');               // Nombre amigable para la UI (ej: "Razón Social")
            $table->string('type')->default('text'); // Control para el front: text, email, url, file
            $table->string('group')->default('identidad'); // Clasificador de pestañas
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_parameters');
    }
};