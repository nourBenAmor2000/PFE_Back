<?php

use Illuminate\Support\Facades\Route;
use Modules\Admin\App\Http\Controllers\AdminController;

Route::prefix('admin')->group(function () {
    // Public routes
    Route::post('/login', [AdminController::class, 'login'])->name('api.admin.login');
    Route::post('/register', [AdminController::class, 'register'])->name('api.admin.register');

    // Protected routes
    Route::middleware('auth:admin')->group(function () {
        Route::get('me', [AdminController::class, 'me']);
        Route::post('logout', [AdminController::class, 'logout']);
        Route::post('refresh', [AdminController::class, 'refresh']);
    
        // =========================
        // CRUD Agency (réservé GLOBAL, checké dans le controller)
        // =========================
        Route::get(   'agencies',          [AdminController::class, 'agenciesIndex']);
        Route::post(  'agencies',          [AdminController::class, 'agenciesStore']);
        Route::get(   'agencies/{id}',     [AdminController::class, 'agenciesShow']);
        Route::put(   'agencies/{id}',     [AdminController::class, 'agenciesUpdate']);
        Route::delete('agencies/{id}',     [AdminController::class, 'agenciesDestroy']);

        // --- CRUD Agent (nouveau) ---
        Route::get(   'agents',        [AdminController::class,'agentsIndex']);
        Route::post(  'agents',        [AdminController::class,'agentsStore']);
        Route::get(   'agents/{id}',   [AdminController::class,'agentsShow']);
        Route::put(   'agents/{id}',   [AdminController::class,'agentsUpdate']);
        Route::delete('agents/{id}',   [AdminController::class,'agentsDestroy']);
        

        // Attributes CRUD (admin)
        Route::get(   'attributes',        [AdminController::class,'attributesIndex']);
        Route::post(  'attributes',        [AdminController::class,'attributesStore']);
        Route::get(   'attributes/{id}',   [AdminController::class,'attributesShow']);
        Route::put(   'attributes/{id}',   [AdminController::class,'attributesUpdate']);
        Route::delete('attributes/{id}',   [AdminController::class,'attributesDestroy']);

         // Categories CRUD (admin)
        Route::get(   'categories',        [AdminController::class,'categoriesIndex']);
        Route::post(  'categories',        [AdminController::class,'categoriesStore']);
        Route::get(   'categories/{id}',   [AdminController::class,'categoriesShow']);
        Route::put(   'categories/{id}',   [AdminController::class,'categoriesUpdate']);
        Route::delete('categories/{id}',   [AdminController::class,'categoriesDestroy']);


        // Clients CRUD (admin)
        Route::get(   'clients',        [AdminController::class,'clientsIndex']);
        Route::post(  'clients',        [AdminController::class,'clientsStore']);
        Route::get(   'clients/{id}',   [AdminController::class,'clientsShow']);
        Route::put(   'clients/{id}',   [AdminController::class,'clientsUpdate']);
        Route::delete('clients/{id}',   [AdminController::class,'clientsDestroy']);

            // Contracts CRUD (admin)
        Route::get(   'contracts',        [AdminController::class,'contractsIndex']);
        Route::post(  'contracts',        [AdminController::class,'contractsStore']);
        Route::get(   'contracts/{id}',   [AdminController::class,'contractsShow']);
        Route::put(   'contracts/{id}',   [AdminController::class,'contractsUpdate']);
        Route::delete('contracts/{id}',   [AdminController::class,'contractsDestroy']);


        // Logements CRUD (admin)
        Route::get(   'logements',        [AdminController::class,'logementsIndex']);
        Route::post(  'logements',        [AdminController::class,'logementsStore']);
        Route::get(   'logements/{id}',   [AdminController::class,'logementsShow']);
        Route::put(   'logements/{id}',   [AdminController::class,'logementsUpdate']);
        Route::delete('logements/{id}',   [AdminController::class,'logementsDestroy']);

         // PaymentContracts CRUD (admin)
        Route::get(   'payment-contracts',       [AdminController::class,'paymentContractsIndex']);
        Route::post(  'payment-contracts',       [AdminController::class,'paymentContractsStore']);
        Route::get(   'payment-contracts/{id}',  [AdminController::class,'paymentContractsShow']);
        Route::put(   'payment-contracts/{id}',  [AdminController::class,'paymentContractsUpdate']);
        Route::delete('payment-contracts/{id}',  [AdminController::class,'paymentContractsDestroy']);

        // Reviews CRUD (admin)
        Route::get(   'reviews',        [AdminController::class,'reviewsIndex']);
        Route::post(  'reviews',        [AdminController::class,'reviewsStore']);
        Route::get(   'reviews/{id}',   [AdminController::class,'reviewsShow']);
        Route::put(   'reviews/{id}',   [AdminController::class,'reviewsUpdate']);
        Route::delete('reviews/{id}',   [AdminController::class,'reviewsDestroy']);
         // SubCategories
    Route::get(   'subcategories',       [AdminController::class,'subcategoriesIndex']);
    Route::post(  'subcategories',       [AdminController::class,'subcategoriesStore']);
    Route::get(   'subcategories/{id}',  [AdminController::class,'subcategoriesShow']);
    Route::put(   'subcategories/{id}',  [AdminController::class,'subcategoriesUpdate']);
    Route::delete('subcategories/{id}',  [AdminController::class,'subcategoriesDestroy']);

    // Visits (admin)
    Route::get(   'visits',       [AdminController::class,'visitsIndex']);
    Route::post(  'visits',       [AdminController::class,'visitsStore']);
    Route::get(   'visits/{id}',  [AdminController::class,'visitsShow']);
    Route::put(   'visits/{id}',  [AdminController::class,'visitsUpdate']);
    Route::delete('visits/{id}',  [AdminController::class,'visitsDestroy']);
    });
    });
    // Routes de vérification communes
Route::prefix('email')->group(function() {
    // Vérification
    Route::get('/verify/{id}/{hash}', 'Auth\VerificationController@verify')
        ->middleware(['signed'])
        ->name('verification.verify');
    
    // Renvoi du lien
    Route::post('/verify/resend', 'Auth\VerificationController@resend')
        ->middleware(['auth:api'])
        ->name('verification.resend');
});
