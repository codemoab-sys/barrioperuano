<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->user()?->password_change_required
            && !$request->routeIs('password.force-change', 'password.force-change.update')
        ) {
            return redirect()->route('password.force-change');
        }

        return $next($request);
    }
}
