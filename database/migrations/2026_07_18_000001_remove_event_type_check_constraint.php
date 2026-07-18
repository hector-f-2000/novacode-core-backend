<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE security_audit_logs DROP CONSTRAINT IF EXISTS security_audit_logs_event_type_check');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE security_audit_logs DROP CONSTRAINT IF EXISTS security_audit_logs_event_type_check');
        DB::statement("ALTER TABLE security_audit_logs ADD CONSTRAINT security_audit_logs_event_type_check CHECK (event_type::text = ANY (ARRAY['login_success', 'login_failed', 'session_revoked', 'sessions_revoked_all', 'tenant_login_failed']::text[]))");
    }
};
