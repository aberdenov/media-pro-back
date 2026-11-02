<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['application' => 'MEDIAPRO API', 'catalog' => '/api/products', 'health' => '/up']);
});
