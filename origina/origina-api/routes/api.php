<?php

use App\Http\Controllers\Api\ActivityWorkflowController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'message' => 'API Origina OK',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    $user = User::where('email', $credentials['email'])->first();

    if (! $user || ! Hash::check($credentials['password'], $user->password)) {
        return response()->json([
            'message' => 'Identifiants invalides.',
        ], 422);
    }

    return response()->json([
        'message' => 'Connexion reussie.',
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'department' => $user->department,
        ],
    ]);
});

Route::post('/logout', function () {
    return response()->json([
        'message' => 'Deconnexion reussie.',
    ]);
});

Route::controller(ActivityWorkflowController::class)->group(function (): void {
    Route::get('/me/overview', 'overview');

    Route::post('/themes/propose', 'proposeTheme');
    Route::get('/themes/pending', 'pendingThemes');
    Route::patch('/themes/{theme}/moderate', 'moderateTheme');
    Route::patch('/themes/{theme}/validate-cd', 'validateThemeCd');
    Route::patch('/themes/{theme}/validate-da', 'validateThemeDa');

    Route::post('/documents/upload', 'uploadDocument');
    Route::post('/documents/{document}/auto-test', 'autoTestDocument');
    Route::post('/documents/{document}/analyze', 'analyzeDocument');

    Route::get('/reports', 'reportsIndex');
    Route::get('/reports/{report}', 'showReport');
    Route::post('/reports/{report}/deliberate', 'deliberateReport');
});
