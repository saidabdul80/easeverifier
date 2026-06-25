<?php

use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Api\ResultVerificationController;
use App\Http\Controllers\Api\ResultPinController;
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
    // Result verification endpoints
    Route::get('results/waec/form', [ResultVerificationController::class, 'form'])->defaults('board', 'waec');
    Route::post('results/waec/fetch', [ResultVerificationController::class, 'fetch'])->defaults('board', 'waec');
    Route::get('results/neco/form', [ResultVerificationController::class, 'form'])->defaults('board', 'neco');
    Route::post('results/neco/fetch', [ResultVerificationController::class, 'fetch'])->defaults('board', 'neco');
    Route::get('results/nbais/form', [ResultVerificationController::class, 'form'])->defaults('board', 'nbais');
    Route::get('results/nbais/schools', [ResultVerificationController::class, 'nbaisSchools']);
    Route::post('results/nbais/fetch', [ResultVerificationController::class, 'fetch'])->defaults('board', 'nbais');
    Route::get('results/nabteb/form', [ResultVerificationController::class, 'form'])->defaults('board', 'nabteb');
    Route::post('results/nabteb/fetch', [ResultVerificationController::class, 'fetch'])->defaults('board', 'nabteb');

    // Result PIN endpoints
    Route::get('result-pins/products', [ResultPinController::class, 'products']);
    Route::post('result-pins/purchase', [ResultPinController::class, 'purchase']);

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
