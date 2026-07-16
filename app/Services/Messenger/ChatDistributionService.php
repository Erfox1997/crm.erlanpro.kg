<?php

namespace App\Services\Messenger;

use App\Models\Company;
use App\Models\MessengerConversation;
use App\Models\User;
use App\Support\CrmPageCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChatDistributionService
{
    public const MODE_EVEN = 'even';

    public const MODE_FIRST_RESPONDER = 'first_responder';

    public function modeForCompany(Company|int $company): string
    {
        if (! $company instanceof Company) {
            $company = Company::query()->find($company);
        }

        if ($company === null) {
            return self::MODE_FIRST_RESPONDER;
        }

        $mode = $company->settings['chat_distribution_mode'] ?? self::MODE_FIRST_RESPONDER;

        return in_array($mode, [self::MODE_EVEN, self::MODE_FIRST_RESPONDER], true)
            ? $mode
            : self::MODE_FIRST_RESPONDER;
    }

    public function updateMode(Company $company, string $mode): void
    {
        abort_unless(in_array($mode, [self::MODE_EVEN, self::MODE_FIRST_RESPONDER], true), 422);

        $settings = $company->settings ?? [];
        $settings['chat_distribution_mode'] = $mode;
        $company->update(['settings' => $settings]);
    }

    public function assignIfNew(MessengerConversation $conversation): void
    {
        if (! $conversation->wasRecentlyCreated) {
            return;
        }

        if ($conversation->assigned_user_id !== null) {
            return;
        }

        if ($this->modeForCompany((int) $conversation->company_id) !== self::MODE_EVEN) {
            return;
        }

        $assigneeId = $this->nextEvenAssigneeId((int) $conversation->company_id);

        if ($assigneeId === null) {
            return;
        }

        $conversation->update(['assigned_user_id' => $assigneeId]);
    }

    public function claimIfNeeded(MessengerConversation $conversation, User $user): void
    {
        if ($conversation->assigned_user_id !== null) {
            return;
        }

        if ($this->modeForCompany((int) $conversation->company_id) !== self::MODE_FIRST_RESPONDER) {
            return;
        }

        if (! $this->userCanHandleMessenger($user)) {
            return;
        }

        if ((int) $user->company_id !== (int) $conversation->company_id) {
            return;
        }

        $conversation->update(['assigned_user_id' => $user->id]);
    }

    public function userCanViewConversation(User $user, MessengerConversation $conversation): bool
    {
        if ((int) $user->company_id !== (int) $conversation->company_id) {
            return false;
        }

        if ($user->is_platform_admin || $user->company_role === 'owner') {
            return true;
        }

        if (! $this->userCanHandleMessenger($user)) {
            return false;
        }

        if ($conversation->assigned_user_id === null) {
            return $this->modeForCompany((int) $conversation->company_id) === self::MODE_FIRST_RESPONDER;
        }

        return (int) $conversation->assigned_user_id === (int) $user->id;
    }

    /**
     * @param  Builder<MessengerConversation>  $query
     * @return Builder<MessengerConversation>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_platform_admin || $user->company_role === 'owner') {
            return $query;
        }

        $mode = $this->modeForCompany((int) $user->company_id);

        return $query->where(function (Builder $inner) use ($user, $mode) {
            $inner->where('assigned_user_id', $user->id);

            if ($mode === self::MODE_FIRST_RESPONDER) {
                $inner->orWhereNull('assigned_user_id');
            }
        });
    }

    public function userCanHandleMessenger(User $user): bool
    {
        $user->loadMissing('position');

        return CrmPageCatalog::userCanAccess($user, 'messenger');
    }

    /**
     * Employees (not owners) with messenger page access.
     *
     * @return Collection<int, User>
     */
    public function eligibleAgents(int $companyId): Collection
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where(function ($query) {
                $query->whereNull('company_role')
                    ->orWhere('company_role', '!=', 'owner');
            })
            ->with('position')
            ->orderBy('id')
            ->get()
            ->filter(fn (User $user) => $this->userCanHandleMessenger($user))
            ->values();
    }

    private function nextEvenAssigneeId(int $companyId): ?int
    {
        $agents = $this->eligibleAgents($companyId);

        if ($agents->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($companyId, $agents) {
            $company = Company::query()->whereKey($companyId)->lockForUpdate()->first();

            if ($company === null) {
                return null;
            }

            $settings = $company->settings ?? [];
            $cursor = (int) ($settings['chat_distribution_rr_cursor'] ?? 0);
            $index = $cursor % $agents->count();
            $assignee = $agents[$index];

            $settings['chat_distribution_rr_cursor'] = $cursor + 1;
            $company->update(['settings' => $settings]);

            return (int) $assignee->id;
        });
    }
}
