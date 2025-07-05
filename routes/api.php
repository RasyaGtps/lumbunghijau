<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WasteCategoryController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\WithdrawalController;
use App\Http\Controllers\Api\OTPController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\LoginController;

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
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    
    Route::middleware('auth:sanctum')->group(function () {
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
    
    // User transaction routes
    Route::get('/transactions/user', [TransactionController::class, 'getUserTransactions']);
    Route::get('/transactions/user/{id}', [TransactionController::class, 'getUserTransaction']);
    
    // Collector/Admin transaction routes
    Route::middleware('role:collector,admin')->group(function () {
        Route::get('/transactions/pending', [TransactionController::class, 'getPendingTransactions']);
        Route::get('/transactions/verified', [TransactionController::class, 'getVerifiedTransactions']);
        Route::get('/transactions/search', [TransactionController::class, 'searchTransactions']);
        Route::post('/transactions/verify/{id}', [TransactionController::class, 'verify']);
        Route::post('/transactions/verify/{id}/submit', [TransactionController::class, 'submitVerification']);
    });
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
    // Withdrawal routes
    Route::post('/withdrawals', [WithdrawalController::class, 'store']);
    Route::get('/withdrawals', [WithdrawalController::class, 'index']);
    Route::get('/withdrawals/success', [WithdrawalController::class, 'getSuccessfulWithdrawals']);
    Route::get('/withdrawals/check-expired', [WithdrawalController::class, 'checkExpiredWithdrawals'])->middleware('admin');
    Route::get('/withdrawals/{withdrawal}', [WithdrawalController::class, 'show']);
    Route::post('/withdrawals/{id}/status', [WithdrawalController::class, 'updateStatus'])->middleware('admin');

    // Admin routes
    Route::middleware('admin')->group(function () {
        Route::post('/transactions/{id}/admin-action', [TransactionController::class, 'adminAction']);
        Route::post('/waste-categories', [WasteCategoryController::class, 'store']);
        Route::post('/waste-categories/{id}', [WasteCategoryController::class, 'update']);
        Route::delete('/waste-categories/{id}', [WasteCategoryController::class, 'destroy']);
    });

    // OTP Routes
    Route::post('/otp/send', [OTPController::class, 'sendOTP']);
    Route::post('/otp/verify', [OTPController::class, 'verifyOTP']);
    Route::post('/otp/resend', [OTPController::class, 'resendOTP']);
});

// Article routes
Route::get('articles', [ArticleController::class, 'index']);
Route::get('articles/{article}', [ArticleController::class, 'show']);

Route::post('/login', [LoginController::class, 'login']);
Route::get('/admin/{id}', [LoginController::class, 'getAdminData']);


use Illuminate\Support\Facades\Cache;

Route::get('/test-redis-cache', function () {
    $value = Cache::remember('my_data', 60, function () {
        return 'Data dari Redis!';
    });
    return $value;
});
