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
 *  * @OA\Tag(
 *     name="Central - Customer - Marketplace - Cart",
 *     description="Shopping cart management endpoints for the marketplace."
 * )
 *
 * @OA\Tag(
 *     name="Central - Customer - Marketplace - Checkout",
 *     description="Checkout processing endpoints for submitting carts into orders."
 * )
 *
 * @OA\Tag(
 *     name="Central - Customer - Marketplace - Orders",
 *     description="Order management endpoints for authenticated customers."
 * )
 *
 * @OA\Tag(
 *     name="Central - Customer - Marketplace - Payment",
 *     description="Payment processing and status endpoints for marketplace orders."
 * )
 * 
 * @OA\Tag(
 *     name="Central - Customer - Marketplace - Wishlist",
 *     description="Customer wishlist management endpoints."
 * )
 * 
 * @OA\Tag(
 *     name="Central - Reviews - Products",
 *     description="Product review management endpoints."
 * )
 *
 * @OA\Tag(
 *     name="Central - Reviews - Merchants",
 *     description="Merchant/tenant review management endpoints. Customers can submit reviews for merchants based on completed orders."
 * )
 *
 * @OA\Tag(
 *     name="Central - Admin - Moderate Reviews",
 *     description="Administrative review moderation endpoints. Admins can list pending/flagged reviews and moderate both product and merchant reviews."
 * )
 * 
 * @OA\Tag(
 *     name="Central - Reviews - Votes",
 *     description="Review voting endpoints for helpful/not-helpful votes."
 * )
 *
 * @OA\Tag(
 *     name="Central - Reviews - Flag",
 *     description="Review flagging endpoints for reporting inappropriate content."
 * )
 * 
 * @OA\Tag(
 *     name="Central - Tenant Profiles",
 *     description="Tenant profile aggregation endpoints providing comprehensive merchant analytics."
 * )
 * 
 * @OA\Tag(
 *     name="Central - Analytics - Marketplace",
 *     description="Analytics tracking endpoints for monitoring user behavior and engagement."
 * )
 *
 * @OA\Tag(
 *     name="Tenant - Product Reviews",
 *     description="Tenant-side product review management endpoints. Merchants can view reviews synced from the central marketplace."
 * )
 *
 * 
 */
abstract class Controller
{
    //
}
