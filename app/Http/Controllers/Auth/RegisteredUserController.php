<?php

namespace App\Http\Controllers\Auth;

use App\Actions\CreateDefaultPipelineForCompany;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms_accepted' => 'accepted',
        ]);

        $user = DB::transaction(function () use ($validated) {
            $tariff = Tariff::free();

            $company = Company::query()->create([
                'name' => $validated['company_name'],
                'tariff_id' => $tariff->id,
                'subscription_ends_at' => now()->addDays($tariff->duration_days),
                'is_active' => true,
                'settings' => [],
            ]);

            CreateDefaultPipelineForCompany::run($company);

            return User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'company_id' => $company->id,
                'company_role' => 'owner',
                'is_platform_admin' => false,
            ]);
        });

        try {
            event(new Registered($user));
        } catch (Throwable $e) {
            // Account is already created — do not fail registration on mail misconfiguration.
            Log::error('Failed to send registration verification email.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'message' => $e->getMessage(),
            ]);
        }

        Auth::login($user);

        return redirect(route('verification.notice', absolute: false));
    }
}
