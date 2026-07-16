<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\Telegram\ManagerTelegramBotService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private ManagerTelegramBotService $managerBot,
    ) {}

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'managerBotUsername' => $this->managerBot->botUsername(),
            'telegramLinked' => $user->telegram_id !== null,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $telegramUsername = $this->managerBot->normalizeUsername($validated['telegram_username'] ?? null);
        if (($validated['telegram_username'] ?? '') !== '' && $telegramUsername === null) {
            return back()->withErrors([
                'telegram_username' => __('Укажите корректный Telegram username (например, ivan_manager).'),
            ]);
        }

        if ($telegramUsername) {
            $taken = User::query()
                ->whereKeyNot($user->id)
                ->whereRaw('LOWER(telegram_username) = ?', [$telegramUsername])
                ->exists();

            if ($taken) {
                return back()->withErrors([
                    'telegram_username' => __('Этот Telegram уже привязан к другому сотруднику.'),
                ]);
            }
        }

        $previousUsername = $user->telegram_username;
        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'telegram_username' => $telegramUsername,
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        // Changing username requires /start again so another person cannot keep the old link.
        if ($previousUsername !== $telegramUsername) {
            $user->telegram_id = null;
        }

        $user->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
