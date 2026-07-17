<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\Comments\CommentsUnreadService;
use App\Services\Messenger\MessengerUnreadService;
use App\Support\CrmPageCatalog;
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
            'pagePermissions' => fn () => $this->sharedPagePermissions($request),
            'branding' => [
                'appName' => config('app.name'),
                'name' => 'ErlanPro',
                'domain' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'crm.erlanpro.kg',
                'logoUrl' => '/images/logo.jpeg',
            ],
            'publicTelegram' => [
                'supportUsername' => ltrim((string) config('services.telegram.support_bot_username', 'ErlanProtask_bot'), '@') ?: 'ErlanProtask_bot',
                'newsGroupUrl' => (string) config('services.telegram.news_group_url', 'https://t.me/+XAExfDN7j8Q1NWRi'),
            ],
            'company' => fn () => $this->sharedCompany($request),
            'subscription' => fn () => $this->sharedSubscription($request),
            'messengerUnread' => fn () => $this->sharedMessengerUnread($request),
            'commentsUnread' => fn () => $this->sharedCommentsUnread($request),
            'flash' => [
                'success' => $request->session()->get('success'),
            ],
            'telegramMiniApp' => (bool) (
                $request->session()->get('telegram_mini_app')
                || $request->boolean('mini')
            ),
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

        return app(MessengerUnreadService::class)->totalUnreadForCompany((int) $user->company_id, $user);
    }

    private function sharedCommentsUnread(Request $request): int
    {
        $user = $request->user();
        if (! $user?->company_id) {
            return 0;
        }

        return app(CommentsUnreadService::class)->totalUnreadForCompany((int) $user->company_id);
    }

    /**
     * @return list<string>
     */
    private function sharedPagePermissions(Request $request): array
    {
        $user = $request->user();

        if ($user === null || ! $user->company_id) {
            return [];
        }

        $user->loadMissing('position');

        return CrmPageCatalog::allowedPagesFor($user);
    }
}
