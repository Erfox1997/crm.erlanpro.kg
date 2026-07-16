<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\CrmPageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        if ($user?->is_platform_admin && $user->company_id === null) {
            return redirect()->intended(route('admin.dashboard', absolute: false));
        }

        return redirect()->intended($this->homeRouteFor($user));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function homeRouteFor(?\App\Models\User $user): string
    {
        if ($user === null) {
            return route('dashboard', absolute: false);
        }

        $user->loadMissing('position');

        if (CrmPageCatalog::userCanAccess($user, 'dashboard')) {
            return route('dashboard', absolute: false);
        }

        $routeByPage = [
            'messenger' => 'messenger.index',
            'comments' => 'comments.index',
            'quick-replies' => 'messenger.quick-replies.index',
            'client-fields' => 'client-fields.index',
            'funnels' => 'funnels.index',
            'broadcasts' => 'broadcasts.index',
            'integrations' => 'integrations.index',
            'tariffs' => 'tariffs.index',
            'positions' => 'positions.index',
            'employees' => 'employees.index',
            'chat-distribution' => 'chat-distribution.index',
        ];

        foreach (CrmPageCatalog::allowedPagesFor($user) as $pageKey) {
            if (isset($routeByPage[$pageKey])) {
                return route($routeByPage[$pageKey], absolute: false);
            }
        }

        return route('dashboard', absolute: false);
    }
}
