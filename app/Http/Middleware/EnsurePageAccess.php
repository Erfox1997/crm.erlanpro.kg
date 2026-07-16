<?php

namespace App\Http\Middleware;

use App\Support\CrmPageCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePageAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $pageKey = CrmPageCatalog::pageKeyForRoute($request->route()?->getName());

        if ($pageKey === null) {
            return $next($request);
        }

        if (! CrmPageCatalog::userCanAccess($user, $pageKey)) {
            abort(403, __('Нет доступа к этому разделу.'));
        }

        return $next($request);
    }
}
