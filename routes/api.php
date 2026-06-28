<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\TwoFactorController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CashRegisterController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeliveryZoneController;
use App\Http\Controllers\Api\FbrSubmissionController;
use App\Http\Controllers\Api\ModifierGroupController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\StockAdjustmentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RiderController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\TimeEntryController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WaiterController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - POS System
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [PasswordResetController::class, 'forgot']);
Route::post('/auth/reset-password',  [PasswordResetController::class, 'reset']);

// Protected routes (require sanctum auth + tenant context)
Route::middleware(['auth:sanctum', 'tenant'])->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Dashboard - any authenticated user
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Categories
    Route::middleware('permission:categories.view')->group(function () {
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/categories/{category}', [CategoryController::class, 'show']);
    });
    Route::middleware('permission:categories.create')->post('/categories', [CategoryController::class, 'store']);
    Route::middleware('permission:categories.update')->put('/categories/{category}', [CategoryController::class, 'update']);
    Route::middleware('permission:categories.delete')->delete('/categories/{category}', [CategoryController::class, 'destroy']);

    // Products
    Route::middleware('permission:products.view')->group(function () {
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/low-stock', [ProductController::class, 'lowStock']);
        Route::get('/products/{product}', [ProductController::class, 'show']);
        Route::get('/products/{product}/cost-history', [ProductController::class, 'costHistory']);
    });
    Route::middleware('permission:products.create')->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::post('/products/import', [ProductController::class, 'import']);
    });
    Route::middleware('permission:products.update')->put('/products/{product}', [ProductController::class, 'update']);
    Route::middleware('permission:products.delete')->delete('/products/{product}', [ProductController::class, 'destroy']);

    // Stock adjustments
    Route::middleware('permission:stock_adjustments.view')->get('/stock-adjustments', [StockAdjustmentController::class, 'index']);
    Route::middleware('permission:stock_adjustments.create')->post('/stock-adjustments', [StockAdjustmentController::class, 'store']);

    // Customers
    Route::middleware('permission:customers.view')->group(function () {
        Route::get('/customers/lookup', [CustomerController::class, 'lookup']);
        Route::get('/customers', [CustomerController::class, 'index']);
        Route::get('/customers/{customer}', [CustomerController::class, 'show']);
    });
    Route::middleware('permission:customers.create')->post('/customers', [CustomerController::class, 'store']);
    Route::middleware('permission:customers.update')->put('/customers/{customer}', [CustomerController::class, 'update']);
    Route::middleware('permission:customers.delete')->delete('/customers/{customer}', [CustomerController::class, 'destroy']);

    // Orders
    Route::middleware('permission:orders.view')->group(function () {
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::get('/orders/{order}/receipt', [OrderController::class, 'receipt']);
    });
    Route::middleware('permission:orders.create')->post('/orders', [OrderController::class, 'store']);
    Route::middleware('permission:orders.update')->group(function () {
        Route::put('/orders/{order}', [OrderController::class, 'update']);
        Route::post('/orders/{order}/resume', [OrderController::class, 'resume']);
        Route::post('/orders/{order}/void', [OrderController::class, 'void']);
        Route::post('/orders/{order}/refund', [OrderController::class, 'refund']);
    });
    Route::middleware('permission:orders.delete')->delete('/orders/{order}', [OrderController::class, 'destroy']);

    // Admin: users
    Route::middleware('permission:users.view')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show']);
    });
    Route::middleware('permission:users.create')->post('/users', [UserController::class, 'store']);
    Route::middleware('permission:users.update')->put('/users/{user}', [UserController::class, 'update']);
    Route::middleware('permission:users.delete')->delete('/users/{user}', [UserController::class, 'destroy']);

    // Admin: roles
    Route::middleware('permission:roles.view')->group(function () {
        Route::get('/roles', [RoleController::class, 'index']);
        Route::get('/roles/{role}', [RoleController::class, 'show']);
        Route::get('/permissions', [PermissionController::class, 'index']);
    });
    Route::middleware('permission:roles.create')->post('/roles', [RoleController::class, 'store']);
    Route::middleware('permission:roles.update')->put('/roles/{role}', [RoleController::class, 'update']);
    Route::middleware('permission:roles.delete')->delete('/roles/{role}', [RoleController::class, 'destroy']);

    // Waiters
    Route::middleware('permission:waiters.view')->group(function () {
        Route::get('/waiters', [WaiterController::class, 'index']);
        Route::get('/waiters/{waiter}', [WaiterController::class, 'show']);
    });
    Route::middleware('permission:waiters.create')->post('/waiters', [WaiterController::class, 'store']);
    Route::middleware('permission:waiters.update')->put('/waiters/{waiter}', [WaiterController::class, 'update']);
    Route::middleware('permission:waiters.delete')->delete('/waiters/{waiter}', [WaiterController::class, 'destroy']);

    // Tables
    Route::middleware('permission:tables.view')->group(function () {
        Route::get('/tables', [TableController::class, 'index']);
        Route::get('/tables/{table}', [TableController::class, 'show']);
    });
    Route::middleware('permission:tables.create')->post('/tables', [TableController::class, 'store']);
    Route::middleware('permission:tables.update')->put('/tables/{table}', [TableController::class, 'update']);
    Route::middleware('permission:tables.delete')->delete('/tables/{table}', [TableController::class, 'destroy']);

    // Riders
    Route::middleware('permission:riders.view')->group(function () {
        Route::get('/riders', [RiderController::class, 'index']);
        Route::get('/riders/{rider}', [RiderController::class, 'show']);
    });
    Route::middleware('permission:riders.create')->post('/riders', [RiderController::class, 'store']);
    Route::middleware('permission:riders.update')->put('/riders/{rider}', [RiderController::class, 'update']);
    Route::middleware('permission:riders.delete')->delete('/riders/{rider}', [RiderController::class, 'destroy']);

    // Subscription Plans (super-admin only — platform-level pricing config).
    Route::middleware('permission:plans.view')->group(function () {
        Route::get('/plans', [PlanController::class, 'index']);
        Route::get('/plans/{plan}', [PlanController::class, 'show']);
        Route::post('/plans/preview', [PlanController::class, 'preview']);
    });
    Route::middleware('permission:plans.create')->post('/plans', [PlanController::class, 'store']);
    Route::middleware('permission:plans.update')->put('/plans/{plan}', [PlanController::class, 'update']);
    Route::middleware('permission:plans.delete')->delete('/plans/{plan}', [PlanController::class, 'destroy']);
    // Tenant billing preview — any authenticated user can see their own tenant's bill
    Route::get('/tenants/{tenant}/billing', [PlanController::class, 'forTenant']);

    // Tenants (super-admin only — tenants.* permissions are granted only to super-admin role)
    Route::middleware('permission:tenants.view')->group(function () {
        Route::get('/tenants', [TenantController::class, 'index']);
        Route::get('/tenants/{tenant}', [TenantController::class, 'show']);
    });
    Route::middleware('permission:tenants.create')->post('/tenants', [TenantController::class, 'store']);
    Route::middleware('permission:tenants.update')->group(function () {
        Route::put('/tenants/{tenant}', [TenantController::class, 'update']);
        Route::post('/tenants/{tenant}/toggle-status', [TenantController::class, 'toggleStatus']);
        Route::post('/tenants/{tenant}/reset-demo', [TenantController::class, 'resetDemo']);
    });
    Route::middleware('permission:tenants.delete')->delete('/tenants/{tenant}', [TenantController::class, 'destroy']);

    // Cash drawer (shifts)
    Route::middleware('permission:cash.view')->group(function () {
        Route::get('/cash-registers', [CashRegisterController::class, 'index']);
        Route::get('/cash-registers/current', [CashRegisterController::class, 'current']);
        Route::get('/cash-registers/{cashRegister}/z-report', [CashRegisterController::class, 'zReport']);
    });
    Route::middleware('permission:cash.open')->post('/cash-registers', [CashRegisterController::class, 'open']);
    Route::middleware('permission:cash.close')->post('/cash-registers/{cashRegister}/close', [CashRegisterController::class, 'close']);

    // Reports
    Route::middleware('permission:reports.view')->get('/reports/sales', [ReportController::class, 'sales']);
    Route::middleware('permission:reports.export')->get('/reports/sales/export', [ReportController::class, 'exportSales']);

    // Audit log
    Route::middleware('permission:audit.view')->get('/audit-logs', [AuditLogController::class, 'index']);

    // Branches
    Route::middleware('permission:branches.view')->group(function () {
        Route::get('/branches', [BranchController::class, 'index']);
        Route::get('/branches/{branch}', [BranchController::class, 'show']);
    });
    Route::middleware('permission:branches.create')->post('/branches', [BranchController::class, 'store']);
    Route::middleware('permission:branches.update')->put('/branches/{branch}', [BranchController::class, 'update']);
    Route::middleware('permission:branches.delete')->delete('/branches/{branch}', [BranchController::class, 'destroy']);

    // Coupons
    Route::middleware('permission:coupons.view')->group(function () {
        Route::get('/coupons', [CouponController::class, 'index']);
        Route::get('/coupons/{coupon}', [CouponController::class, 'show']);
        Route::post('/coupons/validate', [CouponController::class, 'validateCode']);
    });
    Route::middleware('permission:coupons.create')->post('/coupons', [CouponController::class, 'store']);
    Route::middleware('permission:coupons.update')->put('/coupons/{coupon}', [CouponController::class, 'update']);
    Route::middleware('permission:coupons.delete')->delete('/coupons/{coupon}', [CouponController::class, 'destroy']);

    // Modifier Groups (and their modifiers)
    Route::middleware('permission:modifiers.view')->group(function () {
        Route::get('/modifier-groups', [ModifierGroupController::class, 'index']);
        Route::get('/modifier-groups/{modifierGroup}', [ModifierGroupController::class, 'show']);
    });
    Route::middleware('permission:modifiers.create')->post('/modifier-groups', [ModifierGroupController::class, 'store']);
    Route::middleware('permission:modifiers.update')->put('/modifier-groups/{modifierGroup}', [ModifierGroupController::class, 'update']);
    Route::middleware('permission:modifiers.delete')->delete('/modifier-groups/{modifierGroup}', [ModifierGroupController::class, 'destroy']);

    // Delivery Zones
    Route::middleware('permission:delivery_zones.view')->get('/delivery-zones', [DeliveryZoneController::class, 'index']);
    Route::middleware('permission:delivery_zones.create')->post('/delivery-zones', [DeliveryZoneController::class, 'store']);
    Route::middleware('permission:delivery_zones.update')->put('/delivery-zones/{deliveryZone}', [DeliveryZoneController::class, 'update']);
    Route::middleware('permission:delivery_zones.delete')->delete('/delivery-zones/{deliveryZone}', [DeliveryZoneController::class, 'destroy']);

    // Suppliers
    Route::middleware('permission:suppliers.view')->get('/suppliers', [SupplierController::class, 'index']);
    Route::middleware('permission:suppliers.create')->post('/suppliers', [SupplierController::class, 'store']);
    Route::middleware('permission:suppliers.update')->put('/suppliers/{supplier}', [SupplierController::class, 'update']);
    Route::middleware('permission:suppliers.delete')->delete('/suppliers/{supplier}', [SupplierController::class, 'destroy']);

    // Purchase Orders
    Route::middleware('permission:purchase_orders.view')->group(function () {
        Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
        Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
    });
    Route::middleware('permission:purchase_orders.create')->post('/purchase-orders', [PurchaseOrderController::class, 'store']);
    Route::middleware('permission:purchase_orders.update')->post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);
    Route::middleware('permission:purchase_orders.receive')->post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive']);
    Route::middleware('permission:purchase_orders.delete')->delete('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy']);

    // Time Entries (clock-in/clock-out for staff)
    Route::middleware('permission:time_entries.view')->group(function () {
        Route::get('/time-entries', [TimeEntryController::class, 'index']);
        Route::get('/time-entries/current', [TimeEntryController::class, 'current']);
    });
    Route::middleware('permission:time_entries.create')->group(function () {
        Route::post('/time-entries/clock-in',  [TimeEntryController::class, 'clockIn']);
        Route::post('/time-entries/clock-out', [TimeEntryController::class, 'clockOut']);
    });
    Route::middleware('permission:time_entries.delete')->delete('/time-entries/{timeEntry}', [TimeEntryController::class, 'destroy']);

    // 2FA (any authenticated user manages their own)
    Route::get('/auth/2fa/status', [TwoFactorController::class, 'status']);
    Route::post('/auth/2fa/enable', [TwoFactorController::class, 'enable']);
    Route::post('/auth/2fa/confirm', [TwoFactorController::class, 'confirm']);
    Route::post('/auth/2fa/disable', [TwoFactorController::class, 'disable']);

    // FBR (Pakistan tax) submissions
    Route::middleware('permission:fbr.view')->group(function () {
        Route::get('/fbr-submissions', [FbrSubmissionController::class, 'index']);
        Route::get('/fbr-submissions/{fbrSubmission}', [FbrSubmissionController::class, 'show']);
    });
    Route::middleware('permission:fbr.retry')->post('/fbr-submissions/{fbrSubmission}/retry', [FbrSubmissionController::class, 'retry']);

    // Payroll
    Route::middleware('permission:payroll.view')->group(function () {
        Route::get('/payslips', [PayrollController::class, 'index']);
        Route::get('/payslips/{payslip}', [PayrollController::class, 'show']);
    });
    Route::middleware('permission:payroll.create')->post('/payslips/generate', [PayrollController::class, 'generate']);
    Route::middleware('permission:payroll.update')->group(function () {
        Route::put('/payslips/{payslip}/deductions', [PayrollController::class, 'applyDeductions']);
        Route::post('/payslips/{payslip}/finalize', [PayrollController::class, 'finalize']);
    });
    Route::middleware('permission:payroll.pay')->post('/payslips/{payslip}/mark-paid', [PayrollController::class, 'markPaid']);
    Route::middleware('permission:payroll.delete')->delete('/payslips/{payslip}', [PayrollController::class, 'destroy']);
});
