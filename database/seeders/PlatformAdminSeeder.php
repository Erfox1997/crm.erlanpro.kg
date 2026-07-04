<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@erlanpro.kg'],
            [
                'name' => 'Программист',
                'password' => Hash::make('password'),
                'is_platform_admin' => true,
                'company_id' => null,
                'company_role' => null,
                'email_verified_at' => now(),
            ],
        );
    }
}
