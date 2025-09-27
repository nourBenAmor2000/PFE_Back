<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Agency\App\Http\Controllers\AgencyController;

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

// Route::middleware(['auth:sanctum'])->prefix('v1')->name('api.')->group(function () {
//     Route::get('agency', fn (Request $request) => $request->user())->name('agency');
// });
Route::middleware(['auth:agent', 'agent.role:admin_agence,rh'])->prefix('v1')->group(function () {
    Route::apiResource('agencies', AgencyController::class);
});
