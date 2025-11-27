<?php

use App\Http\Controllers\Api\VerificationController;
use Illuminate\Support\Facades\Route;

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

// Public API documentation route
Route::get('/', function () {
    return response()->json([
        'name' => 'EaseVerifier API',
        'version' => '1.0.0',
        'documentation' => url('/customer/api/documentation'),
    ]);
});

// Protected API Routes (API Key authentication)
Route::middleware('api.auth')->prefix('v1')->group(function () {
    // Verification endpoints
    Route::post('verify/nin', [VerificationController::class, 'verifyNin']);
    Route::post('verify/bvn', [VerificationController::class, 'verifyBvn']);
    Route::post('verify/{service}', [VerificationController::class, 'verify']);
    
    // Wallet
    Route::get('wallet/balance', [VerificationController::class, 'walletBalance']);
    
    // History
    Route::get('verifications', [VerificationController::class, 'history']);
    Route::get('verifications/{reference}', [VerificationController::class, 'showByReference']);
    
    // Services
    Route::get('services', [VerificationController::class, 'services']);
});

