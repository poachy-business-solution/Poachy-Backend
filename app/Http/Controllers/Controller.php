<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Poachy API Documentation",
 *     description="Multi-tenant POS and eCommerce Marketplace Platform API",
 *     @OA\Contact(
 *         email="support@poachy.com",
 *         name="Poachy Support"
 *     ),
 *     @OA\License(
 *         name="Proprietary",
 *         url="https://poachy.com/license"
 *     )
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Laravel Sanctum token authentication"
 * )
 *
 * @OA\Tag(
 *     name="Central Admin Authentication",
 *     description="Admin authentication endpoints with 2FA"
 * )
 *
 * @OA\Tag(
 *     name="Admin Management",
 *     description="Admin user management endpoints"
 * )
 */
abstract class Controller
{
    //
}
