<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->away(env('FRONTEND_URL', 'http://127.0.0.1:5173'));
});
