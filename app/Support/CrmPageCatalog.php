<?php

namespace App\Support;

use App\Models\User;

class CrmPageCatalog
{
    /**
     * @return array<string, string>
     */
    public static function pages(): array
    {
        return [
            'dashboard' => 'Дашборд',
            'messenger' => 'Месенджер',
            'tasks' => 'Задачи',
            'comments' => 'Комментарии',
            'quick-replies' => 'Быстрые ответы',
            'client-fields' => 'Данные клиента',
            'funnels' => 'Воронки',
            'broadcasts' => 'Рассылка',
            'shop-sales' => 'Продажи магазина',
            'integrations' => 'Интеграции',
            'tariffs' => 'Тарифы',
            'positions' => 'Должности',
            'employees' => 'Сотрудники',
            'chat-distribution' => 'Распределение чата',
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::pages());
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::pages())
            ->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    public static function pageKeyForRoute(?string $routeName): ?string
    {
        if ($routeName === null || $routeName === '') {
            return null;
        }

        if ($routeName === 'dashboard') {
            return 'dashboard';
        }

        if (str_starts_with($routeName, 'messenger.quick-replies')) {
            return 'quick-replies';
        }

        if (str_starts_with($routeName, 'messenger.conversations.') && str_contains($routeName, 'tasks')) {
            return 'messenger';
        }

        if (str_starts_with($routeName, 'messenger.')) {
            return 'messenger';
        }

        if (str_starts_with($routeName, 'tasks.')) {
            return 'tasks';
        }

        if (str_starts_with($routeName, 'comments.')) {
            return 'comments';
        }

        if (str_starts_with($routeName, 'client-fields.')) {
            return 'client-fields';
        }

        if (str_starts_with($routeName, 'funnels.')
            || str_starts_with($routeName, 'deals.')
            || str_starts_with($routeName, 'pipelines.')
            || str_starts_with($routeName, 'stages.')
            || str_starts_with($routeName, 'stage-tunnels.')
            || str_starts_with($routeName, 'pipeline-tunnels.')) {
            return 'funnels';
        }

        if (str_starts_with($routeName, 'broadcasts.')) {
            return 'broadcasts';
        }

        if (str_starts_with($routeName, 'shop-sales.')) {
            return 'shop-sales';
        }

        if (str_starts_with($routeName, 'integrations.')) {
            return 'integrations';
        }

        if (str_starts_with($routeName, 'tariffs.')) {
            return 'tariffs';
        }

        if (str_starts_with($routeName, 'positions.')) {
            return 'positions';
        }

        if (str_starts_with($routeName, 'employees.')) {
            return 'employees';
        }

        if (str_starts_with($routeName, 'chat-distribution.')) {
            return 'chat-distribution';
        }

        if (str_starts_with($routeName, 'clients.')) {
            return 'client-fields';
        }

        return null;
    }

    public static function userCanAccess(User $user, string $pageKey): bool
    {
        if ($user->is_platform_admin) {
            return true;
        }

        if ($user->company_role === 'owner') {
            return true;
        }

        $position = $user->relationLoaded('position')
            ? $user->position
            : $user->position()->first();

        if ($position === null) {
            return false;
        }

        return $position->allows($pageKey);
    }

    /**
     * @return list<string>
     */
    public static function allowedPagesFor(User $user): array
    {
        if ($user->is_platform_admin || $user->company_role === 'owner') {
            return self::keys();
        }

        $position = $user->relationLoaded('position')
            ? $user->position
            : $user->position()->first();

        if ($position === null) {
            return [];
        }

        $allowed = $position->permissionKeys();

        return array_values(array_intersect(self::keys(), $allowed));
    }
}
