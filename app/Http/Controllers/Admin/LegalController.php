<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Support\PlatformLegalDetails;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/Legal/Edit', [
            'legal' => PlatformLegalDetails::forFrontend(),
            'pageTitle' => 'Реквизиты ИП',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'legal_name' => 'required|string|max:255',
            'pin' => 'required|string|max:64',
            'activity' => 'required|string|max:500',
            'address' => 'required|string|max:2000',
            'about' => 'required|string|max:10000',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'required|string|max:64',
            'site_url' => 'required|url|max:255',
        ]);

        PlatformSetting::setValue(PlatformLegalDetails::SETTING_KEY, [
            ...$validated,
            'updated_at' => now()->toDateString(),
        ]);

        return redirect()
            ->route('admin.legal.edit')
            ->with('success', __('Реквизиты ИП сохранены.'));
    }
}
