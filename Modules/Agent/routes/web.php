<?php

use Illuminate\Support\Facades\Route;
use Modules\Agent\App\Http\Controllers\AgentController;
use Modules\Agent\App\Http\Controllers\Auth\VerificationController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group([], function () {
    Route::resource('agent', AgentController::class)->names('agent');
});
Route::get('/agent/login', [AgentController::class, 'showLoginForm'])->name('agent.login');
Route::post('/agent/login', [AgentController::class, 'login']);

Route::prefix('agent')->group(function() {
    
    
    // Email verification routes
    // routes/api.php
Route::get('/agent/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
->middleware(['signed']) // Critical middleware
->name('agent.verification.verify'); // Must match exactly
    
Route::post('/email/resend', [VerificationController::class, 'resend'])
    ->middleware('auth:agent')
    ->name('agent.verification.resend');

  
});