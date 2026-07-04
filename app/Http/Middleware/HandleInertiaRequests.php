<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'branding' => [
                'appName' => config('app.name'),
                'name' => 'ErlanPro',
                'domain' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'crm.erlanpro.kg',
                'logoUrl' => '/images/erlanpro-logo.svg',
            ],
            'company' => fn () => $this->sharedCompany($request),
            'flash' => [
                'success' => $request->session()->get('success'),
            ],
        ];
    }

    /**
     * @return \App\Models\Company|null
     */
    private function sharedCompany(Request $request): ?Company
    {
        $user = $request->user();
        if (! $user?->company_id) {
            return null;
        }

        return Company::query()->with('tariff')->find($user->company_id);
    }
}
