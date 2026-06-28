<?php

namespace Database\Seeders;

use App\Models\User\User;
use App\Models\User\UserProfile;
use App\Models\Security\SecurityAuditLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class SecurityAuditLogsSeeder extends Seeder
{
    private array $locations = [
        ['city' => 'Santiago', 'country_code' => 'CL', 'country' => 'Chile'],
        ['city' => 'Valparaíso', 'country_code' => 'CL', 'country' => 'Chile'],
        ['city' => 'Concepción', 'country_code' => 'CL', 'country' => 'Chile'],
        ['city' => 'Viña del Mar', 'country_code' => 'CL', 'country' => 'Chile'],
        ['city' => 'Buenos Aires', 'country_code' => 'AR', 'country' => 'Argentina'],
    ];

    private array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Firefox/126.0 Gecko/20100101',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148',
        'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/125.0.0.0 Mobile Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Edge/125.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/125.0.0.0 Safari/537.36',
        'Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148',
    ];

    private array $deviceNames = [
        'Windows 10 - Chrome 125',
        'macOS 10.15 - Firefox 126',
        'iOS 17.5 - Safari',
        'Android 14 - Chrome Mobile 125',
        'Windows 10 - Edge 125',
        'Linux - Chrome 125',
        'iPadOS 17.5 - Safari Mobile',
    ];

    public function run(): void
    {
        DB::statement('TRUNCATE TABLE personal_access_tokens RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE security_audit_logs RESTART IDENTITY CASCADE');

        DB::transaction(function () {
            $users = $this->createOrGetUsers();
            $this->createTokens($users);
            $this->createAuditLogs($users);
        });
    }

    private function createOrGetUsers(): array
    {
        $admin = User::where('email', 'admin@novacode.cl')->first();

        if (!$admin) {
            $admin = User::create([
                'username' => 'admin',
                'email' => 'admin@novacode.cl',
                'password' => Hash::make('admin123'),
                'role_id' => 1,
                'status' => true,
            ]);

            $admin->profile()->create([
                'firstname' => 'User',
                'lastname' => 'Admin',
                'phone' => '+56912345678',
                'address' => 'Santiago, Chile',
                'avatar' => null,
                'settings' => ['theme' => 'dark', 'language' => 'es'],
            ]);
        }

        $editor = User::firstOrCreate(
            ['email' => 'editor@novacode.cl'],
            [
                'username' => 'editor',
                'password' => Hash::make('editor123'),
                'role_id' => 3,
                'status' => true,
            ]
        );

        if (!$editor->profile) {
            $editor->profile()->create([
                'firstname' => 'María',
                'lastname' => 'González',
                'phone' => '+56987654321',
                'address' => 'Valparaíso, Chile',
                'avatar' => null,
                'settings' => ['theme' => 'dark', 'language' => 'es'],
            ]);
        }

        $viewer = User::firstOrCreate(
            ['email' => 'viewer@novacode.cl'],
            [
                'username' => 'viewer',
                'password' => Hash::make('viewer123'),
                'role_id' => 4,
                'status' => true,
            ]
        );

        if (!$viewer->profile) {
            $viewer->profile()->create([
                'firstname' => 'Carlos',
                'lastname' => 'Muñoz',
                'phone' => '+56956781234',
                'address' => 'Concepción, Chile',
                'avatar' => null,
                'settings' => ['theme' => 'light', 'language' => 'es'],
            ]);
        }

        return ['admin' => $admin, 'editor' => $editor, 'viewer' => $viewer];
    }

    private function createTokens(array $users): void
    {
        $now = Carbon::now();

        $tokenableType = User::class;

        $tokensData = [
            // Admin tokens
            [
                'tokenable_id' => $users['admin']->id,
                'tokenable_type' => $tokenableType,
                'name' => $this->deviceNames[0],
                'token' => hash('sha256', 'admin-token-1'),
                'abilities' => '["*"]',
                'ip_address' => '190.45.32.100',
                'user_agent' => $this->userAgents[0],
                'device_name' => $this->deviceNames[0],
                'location' => 'Santiago, CL',
                'is_revoked' => false,
                'last_used_at' => $now->copy()->subMinutes(5),
                'created_at' => $now->copy()->subDays(15),
                'updated_at' => $now->copy()->subMinutes(5),
            ],
            [
                'tokenable_id' => $users['admin']->id,
                'tokenable_type' => $tokenableType,
                'name' => $this->deviceNames[1],
                'token' => hash('sha256', 'admin-token-2'),
                'abilities' => '["*"]',
                'ip_address' => '191.115.45.200',
                'user_agent' => $this->userAgents[1],
                'device_name' => $this->deviceNames[1],
                'location' => 'Valparaíso, CL',
                'is_revoked' => false,
                'last_used_at' => $now->copy()->subDays(1),
                'created_at' => $now->copy()->subDays(30),
                'updated_at' => $now->copy()->subDays(1),
            ],
            [
                'tokenable_id' => $users['admin']->id,
                'tokenable_type' => $tokenableType,
                'name' => $this->deviceNames[3],
                'token' => hash('sha256', 'admin-token-3'),
                'abilities' => '["*"]',
                'ip_address' => '186.79.12.50',
                'user_agent' => $this->userAgents[3],
                'device_name' => $this->deviceNames[3],
                'location' => 'Concepción, CL',
                'is_revoked' => true,
                'last_used_at' => $now->copy()->subDays(10),
                'created_at' => $now->copy()->subDays(20),
                'updated_at' => $now->copy()->subDays(8),
            ],
            // Editor tokens
            [
                'tokenable_id' => $users['editor']->id,
                'tokenable_type' => $tokenableType,
                'name' => $this->deviceNames[2],
                'token' => hash('sha256', 'editor-token-1'),
                'abilities' => '["*"]',
                'ip_address' => '190.45.100.50',
                'user_agent' => $this->userAgents[2],
                'device_name' => $this->deviceNames[2],
                'location' => 'Viña del Mar, CL',
                'is_revoked' => false,
                'last_used_at' => $now->copy()->subHours(2),
                'created_at' => $now->copy()->subDays(7),
                'updated_at' => $now->copy()->subHours(2),
            ],
            [
                'tokenable_id' => $users['editor']->id,
                'tokenable_type' => $tokenableType,
                'name' => $this->deviceNames[4],
                'token' => hash('sha256', 'editor-token-2'),
                'abilities' => '["*"]',
                'ip_address' => '181.45.67.89',
                'user_agent' => $this->userAgents[4],
                'device_name' => $this->deviceNames[4],
                'location' => 'Santiago, CL',
                'is_revoked' => false,
                'last_used_at' => $now->copy()->subDays(2),
                'created_at' => $now->copy()->subDays(14),
                'updated_at' => $now->copy()->subDays(2),
            ],
            // Viewer token
            [
                'tokenable_id' => $users['viewer']->id,
                'tokenable_type' => $tokenableType,
                'name' => $this->deviceNames[5],
                'token' => hash('sha256', 'viewer-token-1'),
                'abilities' => '["*"]',
                'ip_address' => '200.112.33.77',
                'user_agent' => $this->userAgents[5],
                'device_name' => $this->deviceNames[5],
                'location' => 'Concepción, CL',
                'is_revoked' => false,
                'last_used_at' => $now->copy()->subDays(3),
                'created_at' => $now->copy()->subDays(60),
                'updated_at' => $now->copy()->subDays(3),
            ],
        ];

        DB::table('personal_access_tokens')->insert($tokensData);
    }

    private function createAuditLogs(array $users): void
    {
        $now = Carbon::now();

        $auditLogs = [
            // --- Admin audit logs ---
            [
                'user_id' => $users['admin']->id,
                'event_type' => 'login_success',
                'ip_address' => '190.45.32.100',
                'user_agent' => $this->userAgents[0],
                'device_name' => $this->deviceNames[0],
                'location' => 'Santiago, CL',
                'attempt_count' => 0,
                'description' => 'Login exitoso desde Windows 10 - Chrome 125',
                'created_at' => $now->copy()->subDays(15)->subMinutes(30),
                'updated_at' => $now->copy()->subDays(15)->subMinutes(30),
            ],
            [
                'user_id' => $users['admin']->id,
                'event_type' => 'login_success',
                'ip_address' => '191.115.45.200',
                'user_agent' => $this->userAgents[1],
                'device_name' => $this->deviceNames[1],
                'location' => 'Valparaíso, CL',
                'attempt_count' => 0,
                'description' => 'Login exitoso desde macOS 10.15 - Firefox 126',
                'created_at' => $now->copy()->subDays(1)->subHours(3),
                'updated_at' => $now->copy()->subDays(1)->subHours(3),
            ],
            [
                'user_id' => $users['admin']->id,
                'event_type' => 'login_failed',
                'ip_address' => '190.45.32.100',
                'user_agent' => $this->userAgents[0],
                'device_name' => $this->deviceNames[0],
                'location' => 'Santiago, CL',
                'attempt_count' => 2,
                'description' => 'Intento fallido de login para admin@novacode.cl (intento #2)',
                'created_at' => $now->copy()->subDays(15)->subMinutes(35),
                'updated_at' => $now->copy()->subDays(15)->subMinutes(35),
            ],
            [
                'user_id' => $users['admin']->id,
                'event_type' => 'session_revoked',
                'ip_address' => '186.79.12.50',
                'user_agent' => $this->userAgents[3],
                'device_name' => $this->deviceNames[3],
                'location' => 'Concepción, CL',
                'attempt_count' => 0,
                'description' => 'Sesión revocada: Android 14 - Chrome Mobile 125',
                'created_at' => $now->copy()->subDays(8),
                'updated_at' => $now->copy()->subDays(8),
            ],
            [
                'user_id' => $users['admin']->id,
                'event_type' => 'sessions_revoked_all',
                'ip_address' => '190.45.32.100',
                'user_agent' => $this->userAgents[0],
                'device_name' => $this->deviceNames[0],
                'location' => 'Santiago, CL',
                'attempt_count' => 0,
                'description' => 'Se revocaron 3 sesiones',
                'created_at' => $now->copy()->subDays(20),
                'updated_at' => $now->copy()->subDays(20),
            ],
            [
                'user_id' => $users['admin']->id,
                'event_type' => 'login_success',
                'ip_address' => '186.79.12.50',
                'user_agent' => $this->userAgents[3],
                'device_name' => $this->deviceNames[3],
                'location' => 'Concepción, CL',
                'attempt_count' => 0,
                'description' => 'Login exitoso desde Android 14 - Chrome Mobile 125',
                'created_at' => $now->copy()->subDays(20)->subHours(1),
                'updated_at' => $now->copy()->subDays(20)->subHours(1),
            ],
            // --- Editor audit logs ---
            [
                'user_id' => $users['editor']->id,
                'event_type' => 'login_success',
                'ip_address' => '190.45.100.50',
                'user_agent' => $this->userAgents[2],
                'device_name' => $this->deviceNames[2],
                'location' => 'Viña del Mar, CL',
                'attempt_count' => 0,
                'description' => 'Login exitoso desde iOS 17.5 - Safari',
                'created_at' => $now->copy()->subDays(7)->subHours(1),
                'updated_at' => $now->copy()->subDays(7)->subHours(1),
            ],
            [
                'user_id' => $users['editor']->id,
                'event_type' => 'login_success',
                'ip_address' => '181.45.67.89',
                'user_agent' => $this->userAgents[4],
                'device_name' => $this->deviceNames[4],
                'location' => 'Santiago, CL',
                'attempt_count' => 0,
                'description' => 'Login exitoso desde Windows 10 - Edge 125',
                'created_at' => $now->copy()->subDays(2)->subHours(5),
                'updated_at' => $now->copy()->subDays(2)->subHours(5),
            ],
            [
                'user_id' => $users['editor']->id,
                'event_type' => 'login_failed',
                'ip_address' => '200.112.33.77',
                'user_agent' => $this->userAgents[5],
                'device_name' => $this->deviceNames[5],
                'location' => 'Buenos Aires, AR',
                'attempt_count' => 3,
                'description' => 'Intento fallido de login para editor@novacode.cl (intento #3)',
                'created_at' => $now->copy()->subDays(1)->subHours(6),
                'updated_at' => $now->copy()->subDays(1)->subHours(6),
            ],
            [
                'user_id' => $users['editor']->id,
                'event_type' => 'session_revoked',
                'ip_address' => '181.45.67.89',
                'user_agent' => $this->userAgents[4],
                'device_name' => $this->deviceNames[4],
                'location' => 'Santiago, CL',
                'attempt_count' => 0,
                'description' => 'Sesión revocada: Windows 10 - Edge 125',
                'created_at' => $now->copy()->subDays(1)->subHours(4),
                'updated_at' => $now->copy()->subDays(1)->subHours(4),
            ],
            // --- Viewer audit logs ---
            [
                'user_id' => $users['viewer']->id,
                'event_type' => 'login_success',
                'ip_address' => '200.112.33.77',
                'user_agent' => $this->userAgents[5],
                'device_name' => $this->deviceNames[5],
                'location' => 'Concepción, CL',
                'attempt_count' => 0,
                'description' => 'Login exitoso desde Linux - Chrome 125',
                'created_at' => $now->copy()->subDays(3)->subHours(2),
                'updated_at' => $now->copy()->subDays(3)->subHours(2),
            ],
            [
                'user_id' => $users['viewer']->id,
                'event_type' => 'login_success',
                'ip_address' => '200.112.33.77',
                'user_agent' => $this->userAgents[6],
                'device_name' => $this->deviceNames[6],
                'location' => 'Concepción, CL',
                'attempt_count' => 0,
                'description' => 'Login exitoso desde iPadOS 17.5 - Safari Mobile',
                'created_at' => $now->copy()->subDays(1)->subHours(12),
                'updated_at' => $now->copy()->subDays(1)->subHours(12),
            ],
            // --- Login failed without valid user (assigned to admin for audit) ---
            [
                'user_id' => $users['admin']->id,
                'event_type' => 'login_failed',
                'ip_address' => '45.33.32.156',
                'user_agent' => $this->userAgents[0],
                'device_name' => $this->deviceNames[0],
                'location' => 'Unknown, UNKNOWN',
                'attempt_count' => 5,
                'description' => 'Intento fallido de login para unknown@malicious.com (intento #5)',
                'created_at' => $now->copy()->subHours(1),
                'updated_at' => $now->copy()->subHours(1),
            ],
        ];

        SecurityAuditLog::insert($auditLogs);
    }
}
