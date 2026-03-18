<?php

use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'message' => 'API Origina OK',
        'timestamp' => now()->toIso8601String(),
    ]);
});
