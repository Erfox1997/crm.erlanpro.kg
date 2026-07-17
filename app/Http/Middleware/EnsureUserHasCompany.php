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

        if ($user->dismissed_at !== null) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => __('Этот аккаунт отключён. Обратитесь к владельцу компании.'),
                ]);
        }

        if ($user->company_id === null) {
            abort(Response::HTTP_FORBIDDEN, __('Нет привязки к компании.'));
        }

        return $next($request);
    }
}
