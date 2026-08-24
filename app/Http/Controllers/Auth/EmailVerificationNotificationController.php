<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            Log::error('Failed to resend email verification notification.', [
                'user_id' => $request->user()->id,
                'email' => $request->user()->email,
                'message' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'email' => __('Не удалось отправить письмо. Проверьте настройки почты на сервере или попробуйте позже.'),
            ]);
        }

        return back()->with('status', 'verification-link-sent');
    }
}
