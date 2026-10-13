<?php

namespace App\Http\Controllers\Api\Internal\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Admin\EmailTemplatePreview;
use Illuminate\Http\JsonResponse;

/** The emails Poachy sends, listed and rendered with sample data for the admin app. */
class EmailTemplateController extends Controller
{
    public function __construct(private readonly EmailTemplatePreview $previews) {}

    /**
     * @OA\Get(path="/api/internal/admin/emails/templates", summary="Transactional email templates", operationId="internalAdminEmailTemplates",
     *     tags={"Internal - Admin"}, security={{"internalAdmin": {}}},
     *
     *     @OA\Response(response=200, description="key, name, description per template"))
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success('Email templates', $this->previews->list());
    }

    /**
     * @OA\Get(path="/api/internal/admin/emails/templates/{key}/preview", summary="One template rendered with sample data", operationId="internalAdminEmailTemplatePreview",
     *     tags={"Internal - Admin"}, security={{"internalAdmin": {}}},
     *
     *     @OA\Parameter(name="key", in="path", required=true, @OA\Schema(type="string", example="low_balance")),
     *
     *     @OA\Response(response=200, description="subject and html; nothing is sent or logged"),
     *     @OA\Response(response=404, description="Unknown template"))
     */
    public function preview(string $key): JsonResponse
    {
        $rendered = $this->previews->render($key);

        return $rendered === null
            ? ApiResponse::notFound('No such email template.')
            : ApiResponse::success('Email preview', $rendered);
    }
}
