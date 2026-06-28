<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Eliminamos los campos que ahora viven en user_profiles
            $table->dropColumn(['firstname', 'lastname', 'name']); 
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Por si revertimos, los volvemos a crear
            $table->string('name')->nullable();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
        });
    }
};
