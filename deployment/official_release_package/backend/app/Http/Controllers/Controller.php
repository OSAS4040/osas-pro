<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="SaaS Automotive Platform API",
 *     description="Production-grade SaaS for automotive service centers and fleet management.",
 *     @OA\Contact(email="api@autocenter.sa"),
 *     @OA\License(name="Proprietary")
 * )
 *
 * @OA\Server(url="/", description="Current server")
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 *
 * @OA\Schema(
 *     schema="ApiResponse",
 *     @OA\Property(property="data", type="object"),
 *     @OA\Property(property="trace_id", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
 * )
 *
 * @OA\Schema(
 *     schema="PaginatedResponse",
 *     @OA\Property(property="data", type="object",
 *         @OA\Property(property="data", type="array", @OA\Items(type="object")),
 *         @OA\Property(property="current_page", type="integer"),
 *         @OA\Property(property="last_page", type="integer"),
 *         @OA\Property(property="per_page", type="integer"),
 *         @OA\Property(property="total", type="integer")
 *     ),
 *     @OA\Property(property="trace_id", type="string", format="uuid")
 * )
 *
 * @OA\Schema(
 *     schema="Company",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="uuid", type="string", format="uuid"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="status", type="string", enum={"active","inactive","suspended"}),
 *     @OA\Property(property="timezone", type="string", example="Asia/Riyadh"),
 *     @OA\Property(property="currency", type="string", example="SAR"),
 *     @OA\Property(property="email", type="string", format="email"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Branch",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="uuid", type="string", format="uuid"),
 *     @OA\Property(property="company_id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="code", type="string"),
 *     @OA\Property(property="status", type="string", enum={"active","inactive"}),
 *     @OA\Property(property="is_main", type="boolean"),
 *     @OA\Property(property="cross_branch_access", type="boolean"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="User",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="uuid", type="string", format="uuid"),
 *     @OA\Property(property="company_id", type="integer"),
 *     @OA\Property(property="branch_id", type="integer", nullable=true),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="email", type="string", format="email"),
 *     @OA\Property(property="role", type="string", enum={"owner","manager","cashier","accountant","technician","viewer"}),
 *     @OA\Property(property="status", type="string", enum={"active","inactive","suspended"}),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Role",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="company_id", type="integer", nullable=true, description="null = system role"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="is_system", type="boolean"),
 *     @OA\Property(property="permissions", type="array", @OA\Items(type="string"))
 * )
 *
 * @OA\Schema(
 *     schema="Subscription",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="company_id", type="integer"),
 *     @OA\Property(property="plan", type="string", example="professional"),
 *     @OA\Property(property="status", type="string", enum={"active","grace_period","suspended"}),
 *     @OA\Property(property="starts_at", type="string", format="date-time"),
 *     @OA\Property(property="ends_at", type="string", format="date-time"),
 *     @OA\Property(property="grace_ends_at", type="string", format="date-time", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="Plan",
 *     @OA\Property(property="slug", type="string", example="professional"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="price_monthly", type="number", format="float"),
 *     @OA\Property(property="price_yearly", type="number", format="float"),
 *     @OA\Property(property="max_branches", type="integer"),
 *     @OA\Property(property="max_users", type="integer"),
 *     @OA\Property(property="features", type="object")
 * )
 *
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(property="trace_id", type="string", format="uuid"),
 *     @OA\Property(property="errors", type="object", description="Field validation errors")
 * )
 *
 * @OA\Tag(name="Auth", description="Authentication — login, register, logout, me")
 * @OA\Tag(name="Companies", description="Company management")
 * @OA\Tag(name="Branches", description="Branch management")
 * @OA\Tag(name="Users", description="User management")
 * @OA\Tag(name="Roles", description="Role management (RBAC)")
 * @OA\Tag(name="Permissions", description="Permission catalogue")
 * @OA\Tag(name="Subscriptions", description="Subscription and billing")
 * @OA\Tag(name="Plans", description="Subscription plans")
 * @OA\Tag(name="Invoices", description="Invoice management")
 * @OA\Tag(name="Wallet", description="Wallet and transactions")
 * @OA\Tag(name="Products", description="Product and service catalog")
 * @OA\Tag(name="Inventory", description="Inventory management")
 * @OA\Tag(name="Customers", description="Customer management")
 * @OA\Tag(name="Vehicles", description="Vehicle management")
 * @OA\Tag(name="Work Orders", description="Work order management")
 * @OA\Tag(name="Suppliers", description="Supplier management")
 * @OA\Tag(name="Purchases", description="Purchase order management")
 * @OA\Tag(name="Reports", description="Reporting and analytics")
 */
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
}
