<?php

namespace App\Http\Controllers\Api\Internal\Admin;

use App\Http\Controllers\Api\Internal\Admin\Concerns\ResolvesShop;
use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\Admin\VerificationCheckRequest;
use App\Http\Responses\ApiResponse;
use App\Models\BusinessDetail;
use App\Models\TenantVerification;
use App\Services\Central\Admin\AdminAudit;
use App\Support\ActingAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Private admin API: a shop's verification checks. Files are kept on the private
 * `verifications` disk and only streamed to the admin app (which shows them behind its own
 * short-lived signed links); every file view is audited.
 */
class VerificationController extends Controller
{
    use ResolvesShop;

    public function __construct(private AdminAudit $audit) {}

    /**
     * @OA\Get(path="/api/internal/admin/shops/{shop}/verification", summary="A shop's verification checks (current ones) and whether it can be approved",
     *     operationId="internalAdminVerificationShow", tags={"Internal - Admin"}, security={{"internalAdmin": {}}},
     *
     *     @OA\Parameter(name="shop", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="is_verified, checks[], missing[] (required checks not yet submitted)"), @OA\Response(response=404, description="Unknown shop")
     * )
     */
    public function show(string $shop): JsonResponse
    {
        $tenant = $this->shop($shop);
        $checks = self::current((string) $tenant->id);

        return ApiResponse::success('Verification', [
            'tenant_id' => $tenant->id,
            'is_verified' => (bool) BusinessDetail::query()->where('tenant_id', $tenant->id)->value('is_verified'),
            'checks' => $checks->map->toAdminArray()->values(),
            'missing' => self::missing($checks),
        ]);
    }

    /**
     * @OA\Post(path="/api/internal/admin/shops/{shop}/verification/checks", summary="Upload one verification check (replaces the previous one of that kind)",
     *     operationId="internalAdminVerificationUpload", tags={"Internal - Admin"}, security={{"internalAdmin": {}}},
     *
     *     @OA\Parameter(name="shop", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(required={"check"},
     *
     *         @OA\Property(property="check", type="string", enum={"id","kra_pin","permit","location","photo"}),
     *         @OA\Property(property="file", type="string", format="binary", description="JPEG/PNG/WebP/PDF ≤ 8 MB"),
     *         @OA\Property(property="value", type="string", description="KRA PIN or lat,lng")))),
     *
     *     @OA\Response(response=201, description="The new check"), @OA\Response(response=422, description="Invalid")
     * )
     */
    public function store(VerificationCheckRequest $request, string $shop): JsonResponse
    {
        $tenant = $this->shop($shop);
        $admin = ActingAdmin::from($request);
        $check = (string) $request->validated('check');
        $file = $request->file('file');
        $path = null;

        if ($file !== null) {
            $ext = match ($file->getMimeType()) {
                'application/pdf' => 'pdf', 'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg',
            };
            $path = Storage::disk('verifications')->putFileAs((string) $tenant->id, $file, $check.'-'.Str::uuid().'.'.$ext);
        }

        $row = DB::connection('central')->transaction(function () use ($tenant, $check, $path, $file, $request, $admin) {
            TenantVerification::query()->where('tenant_id', $tenant->id)->where('check', $check)
                ->whereIn('status', TenantVerification::CURRENT)->update(['status' => 'replaced']);

            return TenantVerification::query()->create([
                'tenant_id' => $tenant->id,
                'check' => $check,
                'file_path' => $path,
                'file_mime' => $file?->getMimeType(),
                'file_size' => $file?->getSize(),
                'value' => $request->normalisedValue(),
                'status' => 'pending',
                'uploaded_by' => $admin->tag(),
            ]);
        });

        $this->audit->record($request, 'shop.verification.upload', (string) $tenant->id, 'tenant_verification', $row->id, null,
            ['check' => $check, 'has_file' => $path !== null, 'value' => $row->value]);

        return ApiResponse::success('Check saved', $row->toAdminArray(), 201);
    }

    /**
     * @OA\Get(path="/api/internal/admin/shops/{shop}/verification/checks/{id}/file", summary="Stream a verification file (audited)",
     *     operationId="internalAdminVerificationFile", tags={"Internal - Admin"}, security={{"internalAdmin": {}}},
     *
     *     @OA\Parameter(name="shop", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="The file"), @OA\Response(response=404, description="No such file for this shop")
     * )
     */
    public function file(Request $request, string $shop, int $id): StreamedResponse|JsonResponse
    {
        $tenant = $this->shop($shop);
        $row = TenantVerification::query()->where('tenant_id', $tenant->id)->find($id);

        if ($row?->file_path === null || ! Storage::disk('verifications')->exists($row->file_path)) {
            return ApiResponse::notFound('File not found.');
        }

        $this->audit->record($request, 'shop.verification.file_view', (string) $tenant->id, 'tenant_verification', $row->id);

        return Storage::disk('verifications')->response($row->file_path, "{$row->check}-{$row->id}", [
            'Content-Type' => $row->file_mime ?? 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return Collection<int, TenantVerification> */
    public static function current(string $tenantId)
    {
        return TenantVerification::query()->where('tenant_id', $tenantId)
            ->whereIn('status', TenantVerification::CURRENT)->orderBy('id')->get()->keyBy('check');
    }

    /**
     * Required checks not submitted, or rejected and not yet replaced.
     *
     * @param  Collection<string, TenantVerification>  $checks
     * @return array<int, string>
     */
    public static function missing($checks): array
    {
        return array_values(array_filter(TenantVerification::REQUIRED, fn (string $c) => ! isset($checks[$c]) || $checks[$c]->status === 'rejected'));
    }
}
