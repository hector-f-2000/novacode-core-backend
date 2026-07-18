<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_apps', function (Blueprint $table) {
            $table->string('instance_type', 20)
                ->default('shared')
                ->after('is_active');

            $table->string('instance_endpoint')
                ->nullable()
                ->after('instance_type');
        });

        DB::statement(<<<SQL
            ALTER TABLE tenant_apps
            ADD CONSTRAINT tenant_apps_instance_type_check
            CHECK (instance_type IN ('shared', 'dedicated'))
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tenant_apps DROP CONSTRAINT IF EXISTS tenant_apps_instance_type_check');

        Schema::table('tenant_apps', function (Blueprint $table) {
            $table->dropColumn(['instance_type', 'instance_endpoint']);
        });
    }
};
