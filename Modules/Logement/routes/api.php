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

Route::apiResource('logements', LogementController::class);

// Route::middleware(['auth:sanctum'])->prefix('v1')->name('api.')->group(function () {
//     Route::get('logement', fn (Request $request) => $request->user())->name('logement');
// });
