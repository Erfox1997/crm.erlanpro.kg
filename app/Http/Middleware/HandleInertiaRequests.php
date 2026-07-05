<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\Messenger\MessengerUnreadService;
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
                'logoUrl' => '/images/logo.jpeg',
            ],
            'company' => fn () => $this->sharedCompany($request),
            'subscription' => fn () => $this->sharedSubscription($request),
            'messengerUnread' => fn () => $this->sharedMessengerUnread($request),
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

    /**
     * @return array<string, mixed>|null
     */
    private function sharedSubscription(Request $request): ?array
    {
        $company = $this->sharedCompany($request);

        if ($company === null) {
            return null;
        }

        $endsAt = $company->effectiveSubscriptionEndsAt();
        $isActive = $company->subscriptionIsActive();
        $expiresSoon = $endsAt !== null
            && $endsAt->isFuture()
            && $endsAt->lte(now()->addDays(7));

        return [
            'tariff_name' => $company->tariff?->name,
            'ends_at' => $endsAt?->format('d.m.Y'),
            'is_active' => $isActive,
            'expires_soon' => $expiresSoon,
            'is_expired' => ! $isActive && $endsAt !== null,
        ];
    }

    private function sharedMessengerUnread(Request $request): int
    {
        $user = $request->user();
        if (! $user?->company_id) {
            return 0;
        }

        return app(MessengerUnreadService::class)->totalUnreadForCompany((int) $user->company_id);
    }
}
