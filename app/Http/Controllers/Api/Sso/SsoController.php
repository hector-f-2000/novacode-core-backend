<?php

namespace App\Http\Controllers\Api\Sso;

use App\Core\Tenants\Domain\Contracts\TenantRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Auth\SsoDelegateToken;
use App\Models\Tenant\TenantUser;
use Firebase\JWT\JWT;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class SsoController extends Controller
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {}

    public function delegateToken(Request $request): JsonResponse
    {
        $request->validate([
            'app_slug' => ['required', 'string', 'max:100'],
        ]);

        $tenantUser = $request->user();
        if (!$tenantUser || !$tenantUser instanceof TenantUser) {
            return response()->json([
                'status'  => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $tenantId = $tenantUser->tenant_id;

        $tenantStatus = DB::table('tenants')
            ->where('id', $tenantId)
            ->value('status');

        if (!$tenantStatus || !in_array($tenantStatus, ['trial', 'active'], true)) {
            return response()->json([
                'status'  => false,
                'message' => 'La cuenta de la empresa no está activa.',
            ], 403);
        }

        $appSlug = $request->app_slug;

        $tenantApp = DB::table('tenant_apps')
            ->join('apps', 'apps.id', '=', 'tenant_apps.app_id')
            ->where('tenant_apps.tenant_id', $tenantId)
            ->where('apps.slug', $appSlug)
            ->where('tenant_apps.is_active', true)
            ->select(
                'apps.slug as app_slug',
                'apps.name as app_name',
                'tenant_apps.instance_type',
                'tenant_apps.instance_endpoint'
            )
            ->first();

        if (!$tenantApp) {
            return response()->json([
                'status'  => false,
                'message' => 'Esta aplicación no está disponible para tu cuenta.',
            ], 403);
        }

        $now = now();
        $ttl = config('sso.ttl', 900);
        $jti = Uuid::uuid4()->toString();
        $expiresAt = (clone $now)->addSeconds($ttl);

        $tenant = DB::table('tenants')->where('id', $tenantId)->select('slug', 'razon_social')->first();

        $payload = [
            'iss' => 'novacode-core',
            'jti' => $jti,
            'iat' => $now->timestamp,
            'exp' => $expiresAt->timestamp,
            'sub' => 'tenant_owner',
            'tenant_user' => [
                'id'    => $tenantUser->id,
                'email' => $tenantUser->email,
                'name'  => $tenantUser->name,
                'role'  => $tenantUser->role,
            ],
            'tenant' => [
                'id'           => $tenantId,
                'slug'         => $tenant->slug,
                'razon_social' => $tenant->razon_social,
            ],
            'app' => [
                'slug'             => $tenantApp->app_slug,
                'instance_type'    => $tenantApp->instance_type,
                'instance_endpoint' => $tenantApp->instance_endpoint,
            ],
        ];

        $privateKey = config('sso.private_key');

        $token = JWT::encode($payload, $privateKey, 'EdDSA');

        SsoDelegateToken::create([
            'jti'           => $jti,
            'tenant_user_id' => $tenantUser->id,
            'tenant_id'     => $tenantId,
            'app_slug'      => $appSlug,
            'issued_at'     => $now,
            'expires_at'    => $expiresAt,
        ]);

        return response()->json([
            'status'     => true,
            'message'    => 'Token delegado emitido.',
            'data'       => [
                'token'      => $token,
                'expires_in' => $ttl,
            ],
        ]);
    }

    public function checkRevocation(Request $request, string $appSlug): JsonResponse
    {
        $jti = $request->query('jti');
        if (!$jti) {
            return $this->allFalse(422);
        }

        $tenantSlug  = $request->header('X-Tenant-Slug');
        $secretToken = $request->header('X-Tenant-Token');

        if (!$tenantSlug || !$secretToken) {
            return $this->allFalse(401);
        }

        $tenantData = $this->tenantRepository->verifyCredentials(
            $tenantSlug, $secretToken, $appSlug
        );

        if (!$tenantData) {
            return $this->allFalse(401);
        }

        $delegate = SsoDelegateToken::where('jti', $jti)->first();
        if (!$delegate || $delegate->app_slug !== $appSlug) {
            return $this->allFalse(404);
        }

        $valid = is_null($delegate->revoked_at) && $delegate->expires_at->isFuture();

        $tenantActive = in_array($tenantData['tenant_status'], ['trial', 'active'], true);

        $appActive = DB::table('tenant_apps')
            ->join('apps', 'apps.id', '=', 'tenant_apps.app_id')
            ->where('tenant_apps.tenant_id', $delegate->tenant_id)
            ->where('apps.slug', $delegate->app_slug)
            ->where('tenant_apps.is_active', true)
            ->exists();

        return response()->json([
            'valid'         => $valid,
            'tenant_active' => $tenantActive,
            'app_active'    => $appActive,
        ]);
    }

    private function allFalse(int $status): JsonResponse
    {
        return response()->json([
            'valid'         => false,
            'tenant_active' => false,
            'app_active'    => false,
        ], $status);
    }
}
