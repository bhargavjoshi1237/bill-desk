<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Switch the interface to the signed-in user's chosen language.
 */
class SetUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($locale = $request->user()?->preferredLocale()) {
            app()->setLocale($locale);
            Carbon::setLocale($locale);
        }

        return $next($request);
    }
}
