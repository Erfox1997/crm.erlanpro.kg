<?php

namespace App\Console\Commands;

use App\Services\Messenger\MessengerSyncService;
use Illuminate\Console\Command;

class SyncMessengerCommand extends Command
{
    protected $signature = 'messenger:sync
                            {--company= : ID компании для синхронизации}
                            {--all : Синхронизировать все компании с Instagram/Facebook}
                            {--days=1 : Загружать сообщения за последние N дней}';

    protected $description = 'Синхронизировать диалоги мессенджера из Meta (Instagram/Facebook)';

    public function handle(MessengerSyncService $sync): int
    {
        set_time_limit(0);

        $days = max(1, (int) $this->option('days'));

        if ($this->option('all')) {
            $companyIds = $sync->companyIdsWithMessengerIntegrations();

            if ($companyIds === []) {
                $this->warn('Нет компаний с подключённым Instagram или Facebook.');

                return self::SUCCESS;
            }

            $this->info('Синхронизация компаний: '.implode(', ', $companyIds)." (за {$days} дн.)");

            $failed = false;

            foreach ($companyIds as $companyId) {
                if (! $this->syncCompany($sync, $companyId, $days)) {
                    $failed = true;
                }
            }

            return $failed ? self::FAILURE : self::SUCCESS;
        }

        $companyOption = $this->option('company');

        if ($companyOption === null || $companyOption === '') {
            $this->error('Укажите --company=ID или --all');
            $this->newLine();
            $this->line('Доступные компании:');

            foreach ($sync->companiesWithMessengerIntegrations() as $company) {
                $label = $company['name'] ?: 'Без названия';
                $this->line("  {$company['id']} — {$label}");
            }

            $this->newLine();
            $this->line('Примеры:');
            $this->line('  php artisan messenger:sync --company=1');
            $this->line('  php artisan messenger:sync --company=1 --days=1');
            $this->line('  php artisan messenger:sync --all --days=3');

            return self::FAILURE;
        }

        return $this->syncCompany($sync, (int) $companyOption, $days) ? self::SUCCESS : self::FAILURE;
    }

    protected function syncCompany(MessengerSyncService $sync, int $companyId, int $days = 1): bool
    {
        $label = "Компания #{$companyId}";
        $this->info("{$label}: синхронизация за {$days} дн.…");

        $startedAt = microtime(true);

        try {
            $result = $sync->syncForCompany($companyId, $days);
        } catch (\Throwable $e) {
            $this->error("{$label}: {$e->getMessage()}");

            return false;
        }

        $duration = round(microtime(true) - $startedAt, 1);
        $name = $result['company_name'] ? " ({$result['company_name']})" : '';

        if ($result['errors'] !== []) {
            foreach ($result['errors'] as $error) {
                $this->warn("{$label}{$name}: {$error}");
            }
        }

        $this->info("{$label}{$name}: обновлено диалогов — {$result['synced']}, {$duration} сек.");

        return $result['errors'] === [];
    }
}
