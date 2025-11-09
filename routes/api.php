<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AssistantController;

use App\Http\Controllers\Api\UnifiedLoginController;

Route::post('/unified/login', [UnifiedLoginController::class, 'login']);
Route::get('/me', [UnifiedLoginController::class, 'me']); // optionnel pour récupérer le profil courant

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

Route::middleware(['auth:sanctum', 'throttle:30,1'])
  ->post('/assistant/query', [AssistantController::class, 'query']);
Route::post('/assistant/query', [AssistantController::class, 'query']);
Route::middleware(['auth:sanctum','throttle:assistant'])
  ->post('/assistant/query', [AssistantController::class, 'query']);