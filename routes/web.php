<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

// Product Tagger Routes (temporarily without auth for testing)
Route::prefix('product-tagger')->name('product-tagger.')->group(function () {
    Route::get('/', [App\Http\Controllers\ProductTaggerController::class, 'index'])->name('index');
    Route::post('/preview', [App\Http\Controllers\ProductTaggerController::class, 'preview'])->name('preview');
    Route::post('/apply-tag', [App\Http\Controllers\ProductTaggerController::class, 'applyTag'])->name('apply-tag');
    Route::get('/audit-logs', [App\Http\Controllers\ProductTaggerController::class, 'auditLogs'])->name('audit-logs');
    Route::get('/products', [App\Http\Controllers\ProductTaggerController::class, 'viewProducts'])->name('products');
});

// Product Generator Routes (for testing)
Route::prefix('product-generator')->name('product-generator.')->group(function () {
    Route::get('/', [App\Http\Controllers\ProductGeneratorController::class, 'index'])->name('index');
    Route::post('/generate', [App\Http\Controllers\ProductGeneratorController::class, 'generate'])->name('generate');
    Route::post('/delete', [App\Http\Controllers\ProductGeneratorController::class, 'deleteTestProducts'])->name('delete');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
