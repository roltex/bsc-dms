<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsLawyer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isLawyer() && ! $request->user()?->isAdmin()) {
            abort(403, 'Only lawyers can perform this action.');
        }

        return $next($request);
    }
}
