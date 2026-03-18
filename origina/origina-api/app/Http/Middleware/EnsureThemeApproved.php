<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureThemeApproved
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $themeId = $request->route('theme')
            ?? $request->route('theme_id')
            ?? $request->input('theme_id');

        if (! $themeId) {
            abort(400, 'Le theme est requis.');
        }

        $isApproved = DB::table('themes')
            ->where('id', $themeId)
            ->where('status', 'approved')
            ->exists();

        if (! $isApproved) {
            abort(403, 'Le theme doit etre approuve avant soumission.');
        }

        return $next($request);
    }
}
