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
        Schema::table('users', function (Blueprint $table) {
            // 1. Relación con Roles (Integridad Referencial)
            // Se usa nullable por si ya tienes datos, pero se recomienda asignar uno por defecto luego
            $table->foreignId('role_id')
                ->after('id')
                ->nullable()
                ->constrained('roles')
                ->onDelete('set null');
            
            // 3. Auditoría de Acceso
            $table->timestamp('last_login_at')->nullable()->after('updated_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');

            // 4. Campos para Escalabilidad Móvil (App)
            // FCM Token para Notificaciones Push (Firebase Cloud Messaging)
            $table->text('fcm_token')->nullable()->after('last_login_ip');
            
            // Device ID para vincular cuenta a un equipo específico si es necesario
            $table->string('device_id')->nullable()->after('fcm_token');
            
            // Plataforma del dispositivo (ios, android, web)
            $table->string('device_type')->nullable()->after('device_id');

            // 5. Borrado Lógico (Recomendado para no perder trazabilidad)
            $table->softDeletes()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn([
                'role_id', 
                'status', 
                'last_login_at', 
                'last_login_ip', 
                'fcm_token', 
                'device_id', 
                'device_type'
            ]);
            $table->dropSoftDeletes();
        });
    }
};