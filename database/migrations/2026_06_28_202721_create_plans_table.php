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
        Schema::create('plans', function (Blueprint $table) {
            // 1. Identificadores y Relación con el Producto
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->onDelete('cascade'); // Si se borra la App, se borran sus planes
            
            // 2. Datos Comerciales del Plan
            $table->string('name'); // Ejemplo: 'Básico', 'Premium', 'Enterprise'
            $table->text('description')->nullable(); // Detalle de características comerciales
            $table->decimal('price', 10, 2); // Costo del plan
            $table->string('billing_period')->default('monthly'); // Frecuencia: 'monthly', 'yearly'
            
            // 3. Límites de Control para el SaaS (Validación Core)
            $table->integer('max_users')->default(1); // Límite de usuarios que el cliente puede crear en su App

            // 4. Auditoría Transversal (Estándar NovaCode Labs)
            $table->timestamps();
            $table->softDeletes();
            
            // Relaciones estrictas de auditoría hacia usuarios del Core
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
