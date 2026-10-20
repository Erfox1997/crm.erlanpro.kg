<?php

namespace App\Observers;

use App\Models\Client;
use App\Services\Google\GoogleContactsService;

class ClientObserver
{
    public function __construct(
        private GoogleContactsService $googleContacts,
    ) {}

    public function saved(Client $client): void
    {
        // Sync when created, when name/phone change, or when never pushed to Google yet
        // (e.g. Google was connected after the client was first saved).
        $needsSync = $client->wasRecentlyCreated
            || $client->wasChanged(['name', 'phone'])
            || blank($client->google_resource_name);

        if (! $needsSync) {
            return;
        }

        $this->googleContacts->syncClientQuietly($client);
    }
}
