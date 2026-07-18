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
        Schema::create('tenant_apps', function (Blueprint $table) {
            $table->id();
            
            // 1. Relaciones Cruzadas (Llaves Foráneas Críticas)
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade'); // Si se elimina el cliente, se limpian sus accesos
            $table->foreignId('app_id')->constrained('apps')->onDelete('restrict'); // Impide borrar una App si hay clientes usándola
            
            // 2. Parámetros de Control Operativo por Producto
            $table->boolean('is_active')->default(true); // Permite apagar un producto específico sin suspender toda la cuenta de la empresa
            $table->timestamp('activated_at')->useCurrent(); // Fecha exacta en que empezó a usar esta aplicación particular

            // 3. Auditoría Estándar
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_apps');
    }
};
