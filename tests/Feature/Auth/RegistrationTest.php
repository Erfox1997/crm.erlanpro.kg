<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TariffSeeder::class);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'company_name' => 'Acme LLC',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));

        $this->assertDatabaseHas('companies', ['name' => 'Acme LLC']);

        $user = User::query()->where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame(
            Company::query()->where('name', 'Acme LLC')->value('id'),
            $user->company_id
        );
        $this->assertSame('owner', $user->company_role);
    }
}
