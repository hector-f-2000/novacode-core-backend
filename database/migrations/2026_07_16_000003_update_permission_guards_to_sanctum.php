<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $updatedPerms = DB::table('permissions')
            ->where('guard_name', 'api')
            ->update(['guard_name' => 'sanctum']);

        $updatedRoles = DB::table('roles')
            ->where('guard_name', 'api')
            ->update(['guard_name' => 'sanctum']);

        echo "Migrated {$updatedPerms} permissions and {$updatedRoles} roles from guard 'api' to 'sanctum'.\n";
    }

    public function down(): void
    {
        $revertedPerms = DB::table('permissions')
            ->where('guard_name', 'sanctum')
            ->update(['guard_name' => 'api']);

        $revertedRoles = DB::table('roles')
            ->where('guard_name', 'sanctum')
            ->update(['guard_name' => 'api']);

        echo "Reverted {$revertedPerms} permissions and {$revertedRoles} roles from guard 'sanctum' to 'api'.\n";
    }
};
