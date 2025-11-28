<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Logement\App\Http\Controllers\LogementController;


/*
    |--------------------------------------------------------------------------
    | API Routes
    |--------------------------------------------------------------------------
    |
    | Here is where you can register API routes for your application. These
    | routes are loaded by the RouteServiceProvider within a group which
    | is assigned the "api" middleware group. Enjoy building your API!
    |
*/

// Protected routes - require authentication and appropriate role
Route::middleware(['auth:admin,agent,client'])->group(function () {
    Route::apiResource('logements', LogementController::class);
    Route::post('logements/map-search', [LogementController::class, 'mapSearch']);
});

// Public routes (for public property browsing)
Route::post('logements/map-search', [LogementController::class, 'mapSearch']);
Route::get('logements', [LogementController::class, 'index']);
Route::get('logements/all-with-coordinates', [LogementController::class, 'getAllWithCoordinates']);
Route::get('logements/{id}', [LogementController::class, 'show']); // Public access to property details

// Route::middleware(['auth:sanctum'])->prefix('v1')->name('api.')->group(function () {
//     Route::get('logement', fn (Request $request) => $request->user())->name('logement');
// });
