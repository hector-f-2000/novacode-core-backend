<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('apps', function (Blueprint $table) {
            // 1. Identificadores y Datos del Producto
            $table->id();
            $table->string('name'); // Nombre comercial (ej: 'asist-GO')
            $table->string('slug')->unique(); // Identificador único de sistema (ej: 'asist-go')
            $table->text('description')->nullable(); // Detalle del alcance del software
            $table->string('status')->default('active'); // Estado comercial ('active', 'inactive')

            // 2. Auditoría Transversal (Estándar NovaCode Labs)
            $table->timestamps();
            $table->softDeletes(); // Borrado lógico para no romper históricos de contratos si se descontinúa una App
            
            // 3. Relaciones estrictas de auditoría
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apps');
    }
};
