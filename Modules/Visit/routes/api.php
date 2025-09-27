<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Visit\App\Http\Controllers\VisitController;

Route::apiResource('visits', VisitController::class);
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
//     Route::get('visit', fn (Request $request) => $request->user())->name('visit');
// });
