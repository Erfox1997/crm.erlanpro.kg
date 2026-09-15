<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MobileAppController extends Controller
{
    /**
     * Entry for the Android Capacitor shell: mark compact messenger mode and open chat.
     */
    public function entry(Request $request): RedirectResponse
    {
        $request->session()->put('telegram_mini_app', true);

        $params = ['mini' => 1, 'app' => 'android'];
        if ($request->filled('conversation')) {
            $params['conversation'] = (int) $request->integer('conversation');
        }

        $cookie = cookie('crm_mobile', '1', 60 * 24 * 180, '/', null, false, false, false, 'lax');
        $messengerUrl = route('messenger.index', $params);

        if (! $request->user()) {
            return redirect()
                ->guest($messengerUrl)
                ->cookie($cookie);
        }

        return redirect()
            ->to($messengerUrl)
            ->cookie($cookie);
    }
}