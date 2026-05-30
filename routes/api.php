<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RiderController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TableController;
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

// Protected routes (require sanctum auth)
Route::middleware('auth:sanctum')->group(function () {

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
        Route::get('/products/{product}', [ProductController::class, 'show']);
    });
    Route::middleware('permission:products.create')->post('/products', [ProductController::class, 'store']);
    Route::middleware('permission:products.update')->put('/products/{product}', [ProductController::class, 'update']);
    Route::middleware('permission:products.delete')->delete('/products/{product}', [ProductController::class, 'destroy']);

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
    });
    Route::middleware('permission:orders.create')->post('/orders', [OrderController::class, 'store']);
    Route::middleware('permission:orders.update')->put('/orders/{order}', [OrderController::class, 'update']);
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

    // Branches
    Route::middleware('permission:branches.view')->group(function () {
        Route::get('/branches', [BranchController::class, 'index']);
        Route::get('/branches/{branch}', [BranchController::class, 'show']);
    });
    Route::middleware('permission:branches.create')->post('/branches', [BranchController::class, 'store']);
    Route::middleware('permission:branches.update')->put('/branches/{branch}', [BranchController::class, 'update']);
    Route::middleware('permission:branches.delete')->delete('/branches/{branch}', [BranchController::class, 'destroy']);
});
