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
 *     name="Central - Admin - Auth",
 *     description="Admin authentication endpoints with 2FA"
 * )
 *
 * @OA\Tag(
 *     name="Central - Admin - Management",
 *     description="Admin user management endpoints"
 * )
 * 
 * @OA\Tag(
 *     name="Central - Subscription Plans",
 *     description="Poachy subscription plans management endpoints"
 * )
 * 
 * @OA\Tag(
 *     name="Central - Admin - Business Review",
 *     description="Business/Tenant details management."
 * )
 * 
 * @OA\Tag(
 *     name="Central - Admin - Tenant Management",
 *     description="Tenant and domain management endpoints"
 * )
 * 
 * @OA\Tag(
 *     name="Central - Customer - Auth",
 *     description="Customer authentication and authorization endpoints."
 * )
 *
 * @OA\Tag(
 *     name="Central - Customer - Profile",
 *     description="Customer profile management endpoints for retrieving and updating customer information."
 * )
 *
 * @OA\Tag(
 *     name="Central - Customer - Delivery Addresses",
 *     description="Delivery address management endpoints for creating, retrieving, updating, and deleting customer delivery addresses."
 * )
 * 
 * @OA\Tag(
 *     name="Central - Marketplace - Products",
 *     description="Marketplace products management endpoints."
 * )
 *
 *
 * 
 */
abstract class Controller
{
    //
}
