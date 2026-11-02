<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\InquiryController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:120,1')->group(function () {
    Route::get('/catalog/meta', [CatalogController::class, 'meta']);
    Route::get('/catalog/featured', [CatalogController::class, 'featured']);
    Route::get('/products', [CatalogController::class, 'index']);
    Route::get('/products/{product}', [CatalogController::class, 'show'])->whereNumber('product');
});
Route::post('/inquiries', [InquiryController::class, 'store'])->middleware('throttle:5,1');
