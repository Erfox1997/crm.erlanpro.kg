<?php

namespace App\Http\Controllers;

use App\Models\DevicePushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DevicePushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'max:32'],
            'app' => ['nullable', 'string', 'max:32'],
        ]);

        $token = DevicePushToken::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'token' => $validated['token'],
            ],
            [
                'platform' => $validated['platform'] ?? 'android',
                'app' => $validated['app'] ?? 'messenger',
                'last_used_at' => now(),
            ],
        );

        return response()->json([
            'ok' => true,
            'id' => $token->id,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        DevicePushToken::query()
            ->where('user_id', $request->user()->id)
            ->where('token', $validated['token'])
            ->delete();

        return response()->json(['ok' => true]);
    }
}
