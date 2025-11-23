<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AssistantController;

use App\Http\Controllers\Api\UnifiedLoginController;

// Unified Authentication Routes
Route::post('/unified/login', [UnifiedLoginController::class, 'login']);
Route::get('/unified/me', [UnifiedLoginController::class, 'me'])->middleware('auth:admin,agent,client');
Route::post('/unified/logout', [UnifiedLoginController::class, 'logout'])->middleware('auth:admin,agent,client');
Route::post('/unified/refresh', [UnifiedLoginController::class, 'refresh'])->middleware('auth:admin,agent,client');

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// AI Assistant routes - supports both authenticated and unauthenticated requests
Route::middleware(['throttle:30,1'])->group(function () {
    Route::post('/assistant/query', [AssistantController::class, 'query']);
    Route::get('/assistant/health', [AssistantController::class, 'health']);
});
  // routes/api.php
  
Route::post('/client/password/email', [ClientAuthController::class, 'sendResetLinkEmail']);
Route::post('/agent/password/email',  [AgentAuthController::class, 'sendResetLinkEmail']);
Route::post('/admin/password/email',  [AdminAuthController::class, 'sendResetLinkEmail']);

// Geocoding routes (public)
Route::prefix('geocoding')->group(function () {
    Route::post('/geocode', [\App\Http\Controllers\GeocodingController::class, 'geocode']);
    Route::post('/reverse', [\App\Http\Controllers\GeocodingController::class, 'reverseGeocode']);
    Route::get('/search', [\App\Http\Controllers\GeocodingController::class, 'searchPlaces']);
});
