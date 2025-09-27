<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Review\App\Http\Controllers\ReviewController;

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
Route::apiResource('reviews', ReviewController::class);

// Route::middleware(['auth:sanctum'])->prefix('v1')->name('api.')->group(function () {
//     Route::get('review', fn (Request $request) => $request->user())->name('review');
// });
