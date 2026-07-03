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
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

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
        ]);

        $user = DB::transaction(function () use ($validated) {
            $tariff = Tariff::free();

            $company = Company::query()->create([
                'name' => $validated['company_name'],
                'tariff_id' => $tariff->id,
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

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('verification.notice', absolute: false));
    }
}
