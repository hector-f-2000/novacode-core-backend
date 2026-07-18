<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            // 1. Identificadores Base
            $table->id();
            $table->string('rut')->unique(); // RUT de la empresa (Clave para Chile/DTE)
            $table->string('razon_social'); // Nombre legal de la empresa contratante
            $table->string('giro');
            $table->string('address');
            $table->string('slug')->unique(); // Identificador amigable para URLs o subdominios (ej: empresa-abc)
            
            // 2. Licenciamiento, Seguridad y Estado del SaaS
            $table->string('api_key_client')->unique(); // Clave pública que enviará la App satélite en las peticiones
            $table->string('api_secret_token'); // Token secreto (hash) para firmar el handshake inter-app
            $table->foreignId('plan_id')->nullable(); // Vinculación al plan contratado en el Core
            $table->string('status')->default('trial'); // Estado actual (trial, active, suspended, cancelled)
            $table->timestamp('expires_at')->nullable(); // Fecha exacta en que vence el acceso del cliente
            
            // 3. Auditoría Transversal (Estándar NovaCode Labs)
            $table->timestamps(); // Nativos de Laravel: created_at y updated_at
            
            $table->softDeletes(); // deleted_at para desactivación lógica sin pérdida de historial comercial
            $table->foreignId('created_by')->nullable(); // ID del usuario del Core que registró la empresa
            $table->foreignId('updated_by')->nullable(); // ID del último usuario del Core que modificó la empresa
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
