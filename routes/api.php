<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WasteCategoryController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CartController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
    });
});

Route::get('/waste-categories', [WasteCategoryController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::get('/profile', [UserController::class, 'profile']);
    Route::patch('/profile/update', [UserController::class, 'updateProfile']);
    
    // Cart routes
    Route::get('/cart', [CartController::class, 'getCart']);
    Route::post('/cart/add', [CartController::class, 'addToCart']);
    Route::post('/cart/remove', [CartController::class, 'removeFromCart']);
    Route::post('/cart/update-item', [CartController::class, 'updateCartItem']);
    Route::post('/cart/submit', [CartController::class, 'submit']);
    
    // Transaction routes
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
    
    Route::middleware('role:collector,admin')->group(function () {
        Route::post('/transactions/verify/{id}', [TransactionController::class, 'verify']);
        Route::post('/transactions/verify/{id}/submit', [TransactionController::class, 'submitVerification']);
    });

    // Admin routes
    Route::middleware('admin')->group(function () {
        Route::post('/transactions/{id}/admin-action', [TransactionController::class, 'adminAction']);
        Route::post('/waste-categories', [WasteCategoryController::class, 'store']);
        Route::post('/waste-categories/{id}', [WasteCategoryController::class, 'update']);
        Route::delete('/waste-categories/{id}', [WasteCategoryController::class, 'destroy']);
    });
});
