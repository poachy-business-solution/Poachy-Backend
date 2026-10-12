<?php

namespace App\Http\Controllers\Api\Public;

use App\Helpers\PhoneNumber;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Signup\CompleteSignupRequest;
use App\Http\Requests\Public\Signup\StartSignupRequest;
use App\Http\Requests\Public\Signup\VerifySignupRequest;
use App\Http\Responses\ApiResponse;
use App\Models\BusinessCategory;
use App\Models\SignupRequest;
use App\Models\Tenant;
use App\Services\Central\Signup\SignupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Self sign-up for shops (poachy.com/signup), called by the web app's server.
 * The sign-up id (a UUID) is the only handle on a sign-up; responses never echo full phone numbers.
 */
class SignupController extends Controller
{
    public function __construct(private SignupService $signups) {}

    /**
     * @OA\Post(
     *     path="/api/v1/public/signup/start",
     *     summary="Start a self sign-up: details + Turnstile; sends SMS and email codes",
     *     operationId="publicSignupStart",
     *     tags={"Public - Sign-up"},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"shop_name","owner_name","phone","email","town","turnstile_token"},
     *
     *         @OA\Property(property="shop_name", type="string", example="Mama Njeri Shop"),
     *         @OA\Property(property="owner_name", type="string", example="Njeri Kamau"),
     *         @OA\Property(property="phone", type="string", example="0712345678"),
     *         @OA\Property(property="email", type="string", example="njeri@example.com"),
     *         @OA\Property(property="town", type="string", example="Thika"),
     *         @OA\Property(property="branches", type="integer", minimum=1, maximum=50, example=1),
     *         @OA\Property(property="business_category_id", type="integer", nullable=true),
     *         @OA\Property(property="turnstile_token", type="string")
     *     )),
     *
     *     @OA\Response(response=201, description="signup_id, expires_at, suggested_workspace, phone (masked)"),
     *     @OA\Response(response=422, description="Invalid details, failed bot check, or this phone/email already has a shop"),
     *     @OA\Response(response=429, description="Too many sign-ups from this network or phone")
     * )
     */
    public function start(StartSignupRequest $request): JsonResponse
    {
        $signup = $this->signups->start($request->validated(), $request->ip());

        return ApiResponse::success('We sent codes to your phone and email', $this->summary($signup), 201);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/public/signup/resend",
     *     summary="Send new phone + email codes (max 3 sends)",
     *     operationId="publicSignupResend",
     *     tags={"Public - Sign-up"},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"signup_id"}, @OA\Property(property="signup_id", type="string", format="uuid"))),
     *
     *     @OA\Response(response=200, description="Codes sent"),
     *     @OA\Response(response=404, description="Unknown sign-up"),
     *     @OA\Response(response=422, description="Too many sends, expired or already verified")
     * )
     */
    public function resend(Request $request): JsonResponse
    {
        $signup = $this->find((string) $request->input('signup_id'));
        if ($signup === null) {
            return ApiResponse::notFound('Sign-up not found.');
        }
        if ($signup->isVerified() || $signup->isExpired()) {
            return ApiResponse::error('This sign-up does not need new codes.', ['signup' => ['Not waiting for codes.']], 422);
        }

        $this->signups->sendCodes($signup);

        return ApiResponse::success('New codes sent', $this->summary($signup->fresh()));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/public/signup/verify",
     *     summary="Check the SMS and email codes",
     *     operationId="publicSignupVerify",
     *     tags={"Public - Sign-up"},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"signup_id","phone_code","email_code"},
     *
     *         @OA\Property(property="signup_id", type="string", format="uuid"),
     *         @OA\Property(property="phone_code", type="string", example="123456"),
     *         @OA\Property(property="email_code", type="string", example="654321")
     *     )),
     *
     *     @OA\Response(response=200, description="Verified; suggested_workspace"),
     *     @OA\Response(response=404, description="Unknown sign-up"),
     *     @OA\Response(response=422, description="Wrong or expired codes (5 attempts)")
     * )
     */
    public function verify(VerifySignupRequest $request): JsonResponse
    {
        $signup = $this->find($request->string('signup_id')->toString());
        if ($signup === null) {
            return ApiResponse::notFound('Sign-up not found.');
        }

        $signup = $this->signups->verify($signup, $request->string('phone_code')->toString(), $request->string('email_code')->toString());

        return ApiResponse::success('Phone and email confirmed', $this->summary($signup));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/public/signup/workspace-available",
     *     summary="Is a workspace name free?",
     *     operationId="publicSignupWorkspaceAvailable",
     *     tags={"Public - Sign-up"},
     *
     *     @OA\Parameter(name="name", in="query", required=true, @OA\Schema(type="string", example="mama-njeri-shop")),
     *
     *     @OA\Response(response=200, description="name, available, reason, suggestion")
     * )
     */
    public function workspaceAvailable(Request $request): JsonResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:60']]);

        return ApiResponse::success('Workspace name', $this->signups->workspaceAvailability((string) $request->query('name')));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/public/signup/complete",
     *     summary="Choose the workspace and password, accept terms; queues the shop's creation",
     *     operationId="publicSignupComplete",
     *     tags={"Public - Sign-up"},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"signup_id","workspace","password","password_confirmation","accept_terms"},
     *
     *         @OA\Property(property="signup_id", type="string", format="uuid"),
     *         @OA\Property(property="workspace", type="string", example="mama-njeri-shop"),
     *         @OA\Property(property="password", type="string", format="password"),
     *         @OA\Property(property="password_confirmation", type="string", format="password"),
     *         @OA\Property(property="accept_terms", type="boolean", example=true)
     *     )),
     *
     *     @OA\Response(response=202, description="Provisioning; poll GET /signup/status/{signup_id}"),
     *     @OA\Response(response=404, description="Unknown sign-up"),
     *     @OA\Response(response=422, description="Not verified, name taken/invalid, weak password, terms not accepted")
     * )
     */
    public function complete(CompleteSignupRequest $request): JsonResponse
    {
        $signup = $this->find($request->string('signup_id')->toString());
        if ($signup === null) {
            return ApiResponse::notFound('Sign-up not found.');
        }

        $signup = $this->signups->complete($signup, $request->string('workspace')->toString(), $request->string('password')->toString());

        return ApiResponse::success('Setting up your shop', $this->summary($signup), 202);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/public/signup/status/{id}",
     *     summary="Provisioning progress for the welcome page",
     *     operationId="publicSignupStatus",
     *     tags={"Public - Sign-up"},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="status pending|verified|provisioning|ready|failed; when ready: workspace, account_number"),
     *     @OA\Response(response=404, description="Unknown sign-up")
     * )
     */
    public function status(string $id): JsonResponse
    {
        $signup = $this->find($id);

        return $signup === null ? ApiResponse::notFound('Sign-up not found.') : ApiResponse::success('Sign-up status', $this->summary($signup));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/public/signup/business-types",
     *     summary="Shop types for the sign-up form",
     *     operationId="publicSignupBusinessTypes",
     *     tags={"Public - Sign-up"},
     *
     *     @OA\Response(response=200, description="[{id, name, type}]")
     * )
     */
    public function businessTypes(): JsonResponse
    {
        $categories = BusinessCategory::query()->with('businessType:id,name')->orderBy('business_type_id')->orderBy('name')->get()
            ->map(fn (BusinessCategory $c) => ['id' => $c->id, 'name' => $c->name, 'type' => $c->businessType?->name]);

        return ApiResponse::success('Shop types', $categories);
    }

    private function find(string $id): ?SignupRequest
    {
        return preg_match('/^[0-9a-f-]{36}$/i', $id) ? SignupRequest::query()->find($id) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(SignupRequest $signup): array
    {
        $ready = $signup->status === SignupRequest::READY;

        return [
            'signup_id' => $signup->id,
            'status' => $signup->status,
            'phone' => PhoneNumber::mask($signup->phone),
            'email' => $signup->email,
            'shop_name' => $signup->shop_name,
            'verified' => $signup->isVerified(),
            'suggested_workspace' => $signup->workspace ?? $this->signups->suggest($signup->shop_name),
            'workspace' => $signup->workspace,
            'codes_expire_at' => $signup->codes_expire_at?->toIso8601String(),
            'account_number' => $ready ? Tenant::query()->whereKey($signup->tenant_id)->value('mpesa_paybill_account') : null,
            'failure_reason' => $signup->status === SignupRequest::FAILED ? 'We could not finish setting up your shop. We have been told and will contact you.' : null,
        ];
    }
}
