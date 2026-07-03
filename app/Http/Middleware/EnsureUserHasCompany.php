<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->is_platform_admin) {
            return $next($request);
        }

        if ($user->company_id === null) {
            abort(Response::HTTP_FORBIDDEN, __('Нет привязки к компании.'));
        }

        return $next($request);
    }
}
