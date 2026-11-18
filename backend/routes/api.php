<?php

use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Listings\Http\Controllers\ListingController;
use App\Modules\Media\Http\Controllers\PhotoController;
use App\Modules\Search\Http\SearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function (): void {
    // Identité & comptes
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    });
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');

    // Catalogue
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category:slug}', [CategoryController::class, 'show']);

    // Recherche (RECH-01 à 07)
    Route::get('search', [SearchController::class, 'index']);
    Route::get('search/suggest', [SearchController::class, 'suggest']);

    // Annonces
    Route::get('listings/{listing}', [ListingController::class, 'show'])->whereUuid('listing');
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('listings', [ListingController::class, 'store'])->middleware('throttle:listing-creation');
        Route::patch('listings/{listing}', [ListingController::class, 'update'])->whereUuid('listing');
        Route::post('listings/{listing}/submit', [ListingController::class, 'submit'])->whereUuid('listing');

        // Photos (DEP-04)
        Route::scopeBindings()->whereUuid(['listing', 'photo'])->prefix('listings/{listing}/photos')->group(function (): void {
            Route::post('/', [PhotoController::class, 'store'])->middleware('throttle:photo-uploads');
            Route::put('order', [PhotoController::class, 'reorder']);
            Route::get('{photo}', [PhotoController::class, 'show']);
            Route::post('{photo}/complete', [PhotoController::class, 'complete']);
            Route::delete('{photo}', [PhotoController::class, 'destroy']);
        });
    });
});
