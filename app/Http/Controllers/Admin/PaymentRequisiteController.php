<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Support\PlatformPaymentDetails;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PaymentRequisiteController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/PaymentRequisites/Edit', [
            'requisites' => PlatformPaymentDetails::forFrontend(),
            'pageTitle' => 'Реквизиты для клиентов',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'text' => 'nullable|string|max:10000',
            'whatsapp' => 'nullable|string|max:32',
            'qr' => 'nullable|image|max:4096',
        ]);

        $existing = PlatformSetting::getValue('payment_details', []);
        $qrPath = $existing['qr_path'] ?? null;

        if ($request->hasFile('qr')) {
            if ($qrPath !== null) {
                Storage::disk('public')->delete($qrPath);
            }

            $qrPath = $request->file('qr')->store('platform', 'public');
        }

        PlatformSetting::setValue('payment_details', [
            'text' => $validated['text'] ?? '',
            'whatsapp' => $validated['whatsapp'] ?? '',
            'qr_path' => $qrPath,
        ]);

        return redirect()
            ->route('admin.payment-requisites.edit')
            ->with('success', __('Реквизиты сохранены.'));
    }
}
